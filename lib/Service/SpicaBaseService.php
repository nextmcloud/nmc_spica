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

namespace OCA\NmcSpica\Service;

use OCA\NmcSpica\AppInfo\Application;
use OCA\NmcSpica\Exception\ServiceException;
use OCP\IConfig;

class SpicaBaseService {

	/** @var IConfig */
	private $config;
	/** @var TokenService */
	private $tokenService;
	/** @var string|null */
	private $userId;

	private $spicaBaseUrl;
	private $spicaAppId;
	private $spicaAppSecret;

	public function __construct(IConfig $config, TokenService $tokenService, $userId) {
		$this->config = $config;
		$this->tokenService = $tokenService;
		$this->userId = $userId;
		if ($userId === null) {
			return;
		}

		$this->spicaBaseUrl = $this->config->getAppValue(Application::APP_ID, Application::APP_CONFIG_SPICA_URL);
		$this->spicaAppId = $this->config->getAppValue(Application::APP_ID, Application::APP_CONFIG_SPICA_APPID);
		$this->spicaAppSecret = $this->config->getAppValue(Application::APP_ID, Application::APP_CONFIG_SPICA_APPSECRET);
	}


	public function checkSetup(): bool {
		return $this->userId !== null && $this->spicaBaseUrl !== '' && $this->spicaAppId !== '' && $this->spicaAppSecret !== '';
	}

	public function getSpicaBaseUrl(string $endpoint): string {
		return ltrim(rtrim($this->spicaBaseUrl, '/') . '/' . trim($endpoint, '/'), '/');
	}

	protected function getSpicaOptions(): array {
		$spicaDebugUserToken = $this->config->getAppValue(Application::APP_ID, 'spica-usertoken');

		$oidcToken = $this->tokenService->getToken();
		if (!$oidcToken && $spicaDebugUserToken === '') {
			$this->logger->debug('Attempt to fetch unread count but could not find SPICA token');
			throw new ServiceException('Could not get spica request options');
		}
		$spicaToken = $spicaDebugUserToken !== '' ? $spicaDebugUserToken : $oidcToken->getAccessToken();

		return [
			'headers' => [
				'X-UserToken' => $spicaToken,
			],
			'auth' => [ $this->spicaAppId, $this->spicaAppSecret ],
		];
	}
}
