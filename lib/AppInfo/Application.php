<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\AnnouncementCenter\AppInfo;

use OCA\AnnouncementCenter\Dashboard\Widget;
use OCA\AnnouncementCenter\Listener\BeforeTemplateRenderedListener;
use OCA\AnnouncementCenter\Listener\CommentsEntityListener;
use OCA\AnnouncementCenter\Notification\Notifier;
use OCA\AnnouncementCenter\Sharing\AnnouncementcenterShareProvider;
use OCA\AnnouncementCenter\Sharing\Listener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Comments\CommentsEntityEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\AnnouncementCenter\Service\UserService;
use OCP\Util;


class Application extends App implements IBootstrap
{
	public const APP_ID = 'announcementcenter';
	public function __construct()
	{
		parent::__construct(self::APP_ID);
	}
	public function register(IRegistrationContext $context): void
	{
		$context->registerDashboardWidget(Widget::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		// FIXME when Nextcloud 28+ is required
		if (Util::getVersion()[0] >= 28) {
			$context->registerEventListener(CommentsEntityEvent::class, CommentsEntityListener::class);
		} else {
			$context->registerEventListener(CommentsEntityEvent::EVENT_ENTITY, CommentsEntityListener::class);
		}
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void
	{
		$userService = $context->getAppContainer()->get(UserService::class);
		$context->injectFn(function (\OCP\Share\IManager $shareManager) {
			$shareManager->registerShareProvider(AnnouncementcenterShareProvider::class);
		});

		$context->injectFn(function (Listener $listener, IEventDispatcher $eventDispatcher) {
			$listener->register($eventDispatcher);
		});
		$userService->createGroupWithAllUsers();
	}
}
