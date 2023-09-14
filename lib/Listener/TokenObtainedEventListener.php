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

namespace OCA\NmcSpica\Listener;

use OCA\NmcSpica\Service\SpicaMailService;
use OCA\NmcSpica\Service\TokenService;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use OCP\Security\ICrypto;

/** @template-implements IEventListener<TokenObtainedEvent|Event> */
class TokenObtainedEventListener implements IEventListener {
	private IClientService $clientService;
	private TokenService $tokenService;
	private SpicaMailService $mailService;
	private LoggerInterface $logger;
	private ICrypto $crypt;

	public function __construct(IClientService $clientService, 
            TokenService $tokenService, 
            SpicaMailService $mailService, 
            LoggerInterface $logger,
            ICrypto $crypto) {
		$this->clientService = $clientService;
		$this->tokenService = $tokenService;
		$this->mailService = $mailService;
		$this->logger = $logger;
        $this->crypto = $crypto;
	}

	public function handle(Event $event): void {
		if (!$event instanceof TokenObtainedEvent) {
			return;
		}

		try {
			$token = $event->getToken();
			$provider = $event->getProvider();
			$discovery = $event->getDiscovery();

			$refreshToken = $token['refresh_token'] ?? null;

			if (!$refreshToken) {
				return;
			}

			$client = $this->clientService->newClient();
			$result = $client->post(
				$discovery['token_endpoint'],
				[
					'body' => [
						'client_id' => $provider->getClientId(),
						'client_secret' => $this->crypto->decrypt($provider->getClientSecret()),
						'grant_type' => 'refresh_token',
						'refresh_token' => $refreshToken,
						'scope' => 'spica',
					],
				]
			);

			$tokenData = json_decode((string)$result->getBody(), true);

			$this->tokenService->storeToken(array_merge($tokenData, ['provider_id' => $provider->getId()]));

			$this->mailService->resetCache();
			$this->mailService->fetchUnreadCounter();
		} catch (\Throwable $e) {
            if (str_starts_with(get_class($e), 'PHPUnit')) {
                // to keep unit test assertions enabled
                throw $e;
            }

            // Only log exceptions but do not block login
			$this->logger->error('Failed to handle oidc token for spica initialization', ['exception' => $e]);
		}
	}
}
