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

use Exception;
use OCA\NmcSpica\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SpicaMailService extends SpicaBaseService {
	private const UNREAD_CACHE_TTL = 60;

	/** @var IConfig */
	private $config;
	/** @var IClientService */
	private $clientService;
	/** @var LoggerInterface */
	private $logger;
	/** @var ICache */
	private $cache;
	/** @var string|null */
	private $userId;

	public function __construct(IConfig $config, IClientService $clientService, LoggerInterface $logger, TokenService $tokenService, ICacheFactory $cacheFactory, $userId) {
		parent::__construct($config, $tokenService, $userId);
		$this->config = $config;
		$this->clientService = $clientService;
		$this->logger = $logger;
		$this->cache = $cacheFactory->createDistributed(Application::APP_ID . '_unread');
		$this->userId = $userId;
	}

	public function fetchUnreadCounter(): void {
		if (!$this->checkSetup()) {
			return;
		}

		$cachedUnreadCount = $this->cache->get($this->userId);
		if ($cachedUnreadCount) {
			return;
		}


		$unreadUrl = $this->getSpicaBaseUrl('/rest/messaging/v1/emails/inbox/unread/count');
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($unreadUrl, $this->getSpicaOptions());
			$responseBody = $response->getBody();
			$result = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
			if (!isset($result['count'])) {
				throw new Exception('Response did not contain the unread email count: ' . $responseBody);
			}
			$this->setUnreadCounter((int)$result['count']);
		} catch (Exception $e) {
			$this->logger->error('Failed to fetch unread email counter for user ' . $this->userId, ['exception' => $e]);
			$this->setUnreadCounter(0);
		}
	}

	public function getUnreadCounter(): int {
		if (!$this->checkSetup()) {
			return 0;
		}
		$this->fetchUnreadCounter();
		return (int)$this->config->getUserValue($this->userId, Application::APP_ID, Application::USER_CONFIG_KEY_UNREAD_COUNT, '0');
	}

	public function setUnreadCounter(int $counter): void {
		$this->cache->set($this->userId, $counter, self::UNREAD_CACHE_TTL);
		$this->config->setUserValue($this->userId, Application::APP_ID, Application::USER_CONFIG_KEY_UNREAD_COUNT, (string)$counter);
	}
}
