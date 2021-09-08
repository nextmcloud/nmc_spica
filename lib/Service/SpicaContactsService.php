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
namespace OCA\NmcMail\Service;

use OCA\NmcMail\Exception\ServiceException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SpicaContactsService extends SpicaBaseService {

	/** @var IClientService */
	private $clientService;

	/** @var LoggerInterface */
	private $logger;

	/** @var string|null */
	private $userId;

	public function __construct(IConfig $config, TokenService $tokenService, IClientService $clientService, LoggerInterface $logger, $userId) {
		parent::__construct($config, $tokenService, $userId);
		$this->clientService = $clientService;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	/**
	 * @throws ServiceException
	 */
	public function search(string $searchTerm, array $options) {
		if (!$this->checkSetup()) {
			return [];
		}
		$searchTerm = '*' . str_replace(' ', '*,*', $searchTerm) . '*';
		$searchUrl = $this->getSpicaBaseUrl('/rest/contacts/v1') . '/query?filter=or(is(first,any(' .$searchTerm .')),is(last,any('.$searchTerm.')),is(emails.*.email,any('.$searchTerm.')))&fields=take(first,last,emails)';
		if (isset($options['limit'])) {
			$searchUrl .= '&count=' . $options['limit'];
		}
		try {
			$client = $this->clientService->newClient();
			$response = $client->get($searchUrl, $this->getSpicaOptions());
			$responseBody = $response->getBody();
			return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Exception $e) {
			$this->logger->error('Failed to fetch contacts for user ' . $this->userId, ['exception' => $e]);
			throw new ServiceException('Could not fetch results');
		}
	}
}
