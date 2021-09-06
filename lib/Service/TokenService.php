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

namespace OCA\NmcMail\Service;

use OCA\NmcMail\Model\Token;
use OCA\UserOIDC\Db\ProviderMapper;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\ISession;

class TokenService {

	private const SESSION_TOKEN_KEY = 'nmcuser-token';

	/** @var ISession */
	private $session;
	/** @var IClient */
	private $client;

	public function __construct(ISession $session, IClientService $client) {
		$this->session = $session;
		$this->client = $client->newClient();
	}

	public function storeToken(array $tokenData): Token {
		$token = new Token($tokenData);
		$this->session->set(self::SESSION_TOKEN_KEY, json_encode($token, JSON_THROW_ON_ERROR));
		return $token;
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
		$discovery = $this->obtainDiscovery($oidcProvider->getDiscoveryEndpoint());

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
			// Failed to refresh, return old token which will be retried or otherwise timeout if expired
			return $token;
		}
	}

	private function obtainDiscovery(string $url) {
		$response = $this->client->get($url);
		return json_decode($response->getBody(), true);
	}

}
