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

namespace OCA\NmcMail\Listener;

use OCA\NmcMail\Service\TokenService;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;

class TokenObtainedEventListener implements IEventListener {

	/** @var IClientService */
	private $clientService;

	/** @var TokenService */
	private $tokenService;

	public function __construct(IClientService $clientService, TokenService $tokenService) {
		$this->clientService = $clientService;
		$this->tokenService = $tokenService;
	}

	public function handle(Event $event): void {
		if (!$event instanceof TokenObtainedEvent) {
			return;
		}

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
					'client_secret' => $provider->getClientSecret(),
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
					'scope' => 'spica',
				],
			]
		);

		$tokenData = json_decode($result->getBody(), true);

		$this->tokenService->storeToken(array_merge($tokenData, ['provider_id' => $provider->getId()]));

		// TODO: initial fetch
	}
}
