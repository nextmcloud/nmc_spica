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
namespace OCA\NmcSpica;

use OCA\NmcSpica\AppInfo\Application;
use OCA\NmcSpica\Service\SpicaContactsService;
use OCP\IAddressBook;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;

class SpicaAddressBook implements IAddressBook {

	/** @var IConfig */
	private $config;

	/** @var ICache */
	private $cache;

	/** @var SpicaContactsService */
	private $spicaContactsService;

	/** @var string|null */
	private $userId;

	/**
	 * ContactController constructor.
	 *
	 * @param SpicaContactsService $spicaContactsService
	 */
	public function __construct(IConfig $config, ICacheFactory $cacheFactory, SpicaContactsService $spicaContactsService, $userId) {
		$this->spicaContactsService = $spicaContactsService;
		$this->config = $config;
		$this->cache = $cacheFactory->createDistributed(Application::APP_ID . '_contacts');
		$this->userId = $userId;
	}

	public function getKey() {
		return 'spicaAddressBook'; // I don't think we can provide this
	}

	public function getUri(): string {
		return $this->spicaContactsService->getSpicaBaseUrl(''); // expose this or no?
	}

	public function getDisplayName() {
		return 'SPICA Address Book'; // translate this?
	}

	/**
	 *
	 * return array an array of contacts which are arrays of key-value-pairs
	 *  example result:
	 *  [
	 *		['id' => 0, 'FN' => 'Thomas Müller', 'EMAIL' => 'a@b.c', 'GEO' => '37.386013;-122.082932'],
	 *		['id' => 5, 'FN' => 'Thomas Tanghus', 'EMAIL' => ['d@e.f', 'g@h.i']]
	 *	]
	 * @since 5.0.0
	 */
	public function search($pattern, $searchProperties, $options) {
		// use all arguments combined with the user id as a cache key
		$cacheKey = md5(json_encode([
			$this->userId, $pattern, $searchProperties, $options
		], JSON_THROW_ON_ERROR));
		$hit = $this->cache->get($cacheKey);

		if ($hit !== null) {
			return $hit;
		}
		try {
			$result = $this->spicaContactsService->search($pattern, $options);
		} catch (ServiceException $e) {
			return [];
		}

		$contacts = $result['contacts'] ?? null;
		if (empty($contacts)) {
			return [];
		}
		// form the result set
		$result = array_merge(...array_map(static function ($contact) {
			$emails = $contact['emails'] ?? [];
			$template = ['FN' => ($contact['first'] ?? '') . ' ' . ($contact['last'] ?? '') ];
			if (empty($emails)) {
				return [array_merge($template, ['EMAIL' => ''])];
			}
			return array_map(static function ($email) use ($template) {
				return array_merge($template, ['EMAIL' => $email['email']]);
			}, $emails);
		}, $contacts));

		$this->cache->set($cacheKey, $result,
			$this->config->getAppValue(Application::APP_ID, Application::APP_CONFIG_CACHE_TTL_CONTACTS, Application::APP_CONFIG_CACHE_TTL_CONTACTS_DEFAULT)
		);

		return $result;
	}

	/**
	 * @throws ServiceException
	 */
	public function createOrUpdate($properties) {
		throw new ServiceException("Operation not available", 403);
	}

	/**
	 * @throws ServiceException
	 */
	public function getPermissions() {
		throw new ServiceException("Operation not available", 403);
	}

	/**
	 * @throws ServiceException
	 */
	public function delete($id) {
		throw new ServiceException("Operation not available", 403);
	}

	public function isShared(): bool {
		return false;
	}

	public function isSystemAddressBook(): bool {
		return true;
	}
}
