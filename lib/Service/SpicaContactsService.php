<?php

declare(strict_types=1);
/**
 * @author Anna Larch <anna.larch@nextcloud.com>
 *
 * @copyright 2021 Annna Larch <anna.larch@nextcloud.com>
 *
 * Spica Integration
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\IntegrationSpica\Service;

use OCA\IntegrationSpica\AppInfo\Application;
use OCA\IntegrationSpica\Exception\ServiceException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SpicaContactsService {
	/** @var IConfig */
	private $config;

	/** @var IClientService */
	private $clientService;

	/** @var LoggerInterface */
	private $logger;

	/** @var string|null */
	private $userId;

	/** @var string */
	private $spicaBaseUrl;

	/** @var string */
	private $spicaAppId;

	/** @var string */
	private $spicaAppSecret;

	public function __construct(IConfig $config, IClientService $clientService, LoggerInterface $logger, $userId) {
		$this->config = $config;
		$this->clientService = $clientService;
		$this->logger = $logger;
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

	// https://ngc.jenk.toon.sul.t-online.de/spica-contacts-api/current/html5/#_oauth2_bearer_token

	/**
	 * @throws ServiceException
	 */
	public function search(string $searchTerm, array $options) {
		$searchTerm = '*' . str_replace(' ', '*,*', $searchTerm) . '*';
		$searchUrl = Application::APP_CONFIG_SPICA_URL . '/query?filter=or(is(first,any(' .$searchTerm .')),is(last,any('.$searchTerm.')),is(emails.*.email,any('.$searchTerm.')))&fields=take(first,last,emails)';
		if(isset($options['limit'])) {
			$searchUrl .= '&count=' . $options['limit'];
		}
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($searchUrl, [
				'headers' => [
					'X-UserToken' => '',
				],
				'auth' => []
			]);
			$responseBody = $response->getBody();
			return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Exception $e) {
			$this->logger->error('Failed to fetch contacts for user ' . $this->userId, ['exception' => $e]);
			throw new ServiceException('Could not fetch results');
		}
	}

	public function getSpicaBaseUrl() {
		return $this->spicaBaseUrl;
	}
}
