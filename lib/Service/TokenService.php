<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\NmcSpica\Service;

use OCA\NmcSpica\AppInfo\Application;
use OCA\NmcSpica\Model\Token;
use OCA\UserOIDC\Db\Provider;
use OCA\UserOIDC\Db\ProviderMapper;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TokenService {

	private const INVALIDATE_DISCOVERY_CACHE_AFTER_SECONDS = 3600;

	private const SESSION_TOKEN_KEY = 'nmcuser-token';

	/** @var ISession */
	private $session;
	/** @var IClient */
	private $client;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IUserSession */
	private $userSession;
	/** @var IRequest */
	private $request;
	/** @var LoggerInterface */
	private $logger;
	/** @var ICache */
	private $cache;
	/** @var IConfig */
	private $config;

	public function __construct(ISession $session, IClientService $client, IURLGenerator $urlGenerator, IUserSession $userSession, IRequest $request, LoggerInterface $logger, ICacheFactory $cacheFactory, IConfig $config) {
		$this->session = $session;
		$this->client = $client->newClient();
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->request = $request;
		$this->logger = $logger;
		$this->cache = $cacheFactory->createDistributed('nmc_spica');
		$this->config = $config;
	}

	public function storeToken(array $tokenData): Token {
		$token = new Token($tokenData);
		$this->session->set(self::SESSION_TOKEN_KEY, json_encode($token, JSON_THROW_ON_ERROR));
		return $token;
	}

	public function getUserDebugToken(): string {
		return $this->config->getAppValue(Application::APP_ID, 'spica-usertoken', '');
	}

	public function getToken(bool $refresh = true): ?Token {
		$sessionData = $this->session->get(self::SESSION_TOKEN_KEY);
		if (!$sessionData) {
			return null;
		}

		$token = new Token(json_decode($sessionData, true, 512, JSON_THROW_ON_ERROR));
		if ($token->isExpired()) {
			return $token;
		}

		if ($refresh && $token->isExpiring()) {
			$token = $this->refresh($token);
		}
		return $token;
	}

	public function refresh(Token $token) {
		/** @var ProviderMapper $providerMapper */
		$providerMapper = \OC::$server->get(ProviderMapper::class);
		$oidcProvider = $providerMapper->getProvider($token->getProviderId());
		$discovery = $this->obtainDiscovery($oidcProvider);

		try {
			$result = $this->client->post(
				$discovery['token_endpoint'],
				[
					'body' => [
						'client_id' => $oidcProvider->getClientId(),
						'client_secret' => $oidcProvider->getClientSecret(),
						'grant_type' => 'refresh_token',
						'refresh_token' => $token->getRefreshToken(),
						'scope' => 'spica',
					],
				]
			);
			return $this->storeToken(
				array_merge(
					json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR),
					['provider_id' => $token->getProviderId()],
				)
			);
		} catch (\Exception $e) {
			$this->logger->error('Failed to refresh spica scope token', ['exception' => $e]);
			// Failed to refresh, return old token which will be retried or otherwise timeout if expired
			return $token;
		}
	}

	public function obtainDiscovery(Provider $provider): array {
		$cacheKey = 'discovery-' . $provider->getId();
		$cachedDiscovery = $this->cache->get($cacheKey);
		if ($cachedDiscovery === null) {
			$url = $provider->getDiscoveryEndpoint();
			$this->logger->debug('Obtaining discovery endpoint: ' . $url);

			$response = $this->client->get($url);
			$cachedDiscovery = $response->getBody();
			$this->cache->set($cacheKey, $cachedDiscovery, self::INVALIDATE_DISCOVERY_CACHE_AFTER_SECONDS);
		}

		return json_decode($cachedDiscovery, true, 512, JSON_THROW_ON_ERROR);
	}

	public function reauthenticate() {
		$token = $this->getToken(false);
		if ($token === null) {
			return;
		}

		// Logout the user and redirect to the oidc login flow to gather a fresh token
		$this->userSession->logout();
		$redirectUrl = $this->urlGenerator->getAbsoluteURL('/index.php/apps/user_oidc/login/'  . $token->getProviderId()) .
			'?redirectUrl=' . urlencode($this->request->getRequestUri());
		header('Location: ' . $redirectUrl);
		exit();
	}
}
