<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 T-Systems International
 *
 * @author M. Mura <mauro-efisio.mura@t-systems.com>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\NmcSpica\Listener;

use OCA\NmcSpica\AppInfo\Application;
use OCA\NmcSpica\Service\SpicaMailService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IInitialStateService;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {

	/** @var IConfig */
	private $config;

	/** @var IInitialStateService */
	private $initialState;

	/** @var SpicaMailService */
	private $spicaMailService;

	/**
	 * BeforeTemplateRenderedListener constructor.
	 *
	 * @param IInitialStateService $initialState
	 */
	public function __construct(
		IConfig $config,
		IInitialStateService $initialState,
		SpicaMailService $spicaMailService
	) {
		$this->config = $config;
		$this->initialState = $initialState;
		$this->spicaMailService = $spicaMailService;
	}

	public function handle(Event $event): void {

		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn() || $event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}

		$this->initialState->provideLazyInitialState(Application::APP_ID, 'unread-counter', function () {
			$unreadCounter = $this->spicaMailService->getUnreadCounter();
			return $unreadCounter;
		});

		$this->initialState->provideLazyInitialState(Application::APP_ID, 'mail-url', function () {
			$mailUrl = $this->config->getAppValue(Application::APP_ID, Application::APP_CONFIG_WEBMAIL_URL, '');
			return $mailUrl;
		});

		// show email button only to logged in users
		\OCP\Util::addScript("nmc_spica", "nmc_spica");
		\OCP\Util::addStyle('nmc_spica', 'nmc_spica');
	}
}
