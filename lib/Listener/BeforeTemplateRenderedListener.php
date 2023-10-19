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

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {

	public function handle(Event $event): void {

		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if (!$event->isLoggedIn() || $event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}

		// show email button only to logged in users
		\OCP\Util::addScript("nmc_spica", "nmc_spica");
		\OCP\Util::addStyle('nmc_spica', 'nmc_spica');
	}
}
