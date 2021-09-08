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

namespace OCA\NmcSpica\AppInfo;

use OCA\NmcSpica\Listener\TokenObtainedEventListener;
use OCA\NmcSpica\Service\TokenService;
use OCA\NmcSpica\Service\SpicaMailService;
use OCA\NmcSpica\SpicaAddressBook;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Services\IInitialState;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nmc_spica';

	public const USER_CONFIG_KEY_UNREAD_COUNT = 'unread-count';

	public const APP_CONFIG_WEBMAIL_URL = 'webmail-url';
	public const APP_CONFIG_SPICA_URL = 'spica-baseurl';
	public const APP_CONFIG_SPICA_APPID = 'spica-appid';
	public const APP_CONFIG_SPICA_APPSECRET = 'spica-appsecret';

	public function __construct() {
		parent::__construct(self::APP_ID, []);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(TokenObtainedEvent::class, TokenObtainedEventListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			IInitialState $initialState,
			TokenService $tokenService,
			INavigationManager $navigationManager,
			IManager $contactsManager,
			SpicaAddressBook $spicaAddressBook,
			IL10N $l10n,
			SpicaMailService $unreadService,
			IURLGenerator $urlGenerator,
			IConfig $config,
			$userId
		) {
			if (!$userId) {
				return;
			}

			$token = $tokenService->getToken();
			if ($token === null) {
				return;
			}

			$contactsManager->registerAddressBook($spicaAddressBook);

			// TODO only for apge requests probably in middleware otherwise there would be a onetime failure if a api request hits this
			// and it gets autoredirected to the idp for reauth
			if ($token->isExpired()) {
				$tokenService->reauthenticate();
			}

			$unreadCounter = $unreadService->getUnreadCounter();
			$mailUrl = $config->getAppValue(self::APP_ID, self::APP_CONFIG_WEBMAIL_URL, '');

			// Provide a regular navigation entry
			$navigationManager->add(function () use ($l10n, $urlGenerator, $mailUrl) {
				return [
					'id' => 'nmc_spica',
					'icon' => $urlGenerator->imagePath('core', 'mail.svg'),
					'href' => $mailUrl,
					'appname' => self::APP_ID,
					'order' => 25,
					'name' => $l10n->t('Mail'),
				];
			});
			$navigationManager->setUnreadCounter('nmc_spica', $unreadCounter);

			$initialState->provideLazyInitialState('unread-counter', function () use ($unreadCounter) {
				return $unreadCounter;
			});

			$initialState->provideLazyInitialState('mail-url', function () use ($mailUrl) {
				return $mailUrl;
			});

			Util::addScript('nmc_spica', 'nmc_spica');
			Util::addStyle('nmc_spica', 'nmc_spica');
		});
	}
}
