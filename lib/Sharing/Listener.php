<?php

/**
 * @copyright Copyright (c) 2023 insiinc <insiinc@outlook.com>
 *
 * @author insiinc <insiinc@outlook.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


declare(strict_types=1);


namespace OCA\AnnouncementCenter\Sharing;

use OC\Files\Filesystem;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCA\AnnouncementCenter\Model\Attachment;
use OCA\AnnouncementCenter\Model\AttachmentMapper;

class Listener
{

	private AttachmentMapper $attachmentMapper;
	private LoggerInterface $logger;
	public function __construct(LoggerInterface $logger, AttachmentMapper $attachmentMapper,)
	{
		$this->logger = $logger;
		$this->attachmentMapper = $attachmentMapper;
	}

	public function register(IEventDispatcher $dispatcher): void
	{
		/**
		 * @psalm-suppress UndefinedClass
		 */
		$dispatcher->addListener('OCP\Share::preShare', [self::class, 'listenPreShare'], 1000);
		$dispatcher->addListener(VerifyMountPointEvent::class, [self::class, 'listenVerifyMountPointEvent'], 1000);
	}

	public static function listenPreShare(GenericEvent $event): void
	{

		/** @var self $listener */
		$listener = Server::get(self::class);
		$listener->overwriteShareTarget($event);
	}

	public static function listenVerifyMountPointEvent(VerifyMountPointEvent $event): void
	{
		/** @var self $listener */
		$listener = Server::get(self::class);
		$listener->overwriteMountPoint($event);
	}

	public function overwriteShareTarget(GenericEvent $event): void
	{

		/** @var IShare $share */
		$share = $event->getSubject();
		// $this->logger->warning('listener:' . $share->getNote());
		// $fileId = $share->getNodeId();
		// $announcementId = (int)$share->getNote();
		// $attachment = new Attachment();
		// $attachment->setAnnouncementId($announcementId);
		// $attachment->setId($fileId);
		// $attachment->setType("share_file");
		// $attachment->setData($share->getNode()->getName());
		// $attachment->setCreatedBy($share->getSharedBy());
		// $attachment->setLastModified(time());
		// $attachment->setCreatedAt(time());
		// $this->logger->warning("insert share attach" . json_encode($attachment));
		// $this->attachmentMapper->insert($attachment);
		// if (
		// 	$share->getShareType() !== IShare::TYPE_DECK
		// 	&& $share->getShareType() !== AnnouncementcenterShareProvider::SHARE_TYPE_DECK_USER
		// ) {
		// 	return;
		// }

		// $target = AnnouncementcenterShareProvider::ANNOUNCEMENTCENTER_FOLDER_PLACEHOLDER . '/' . $share->getNode()->getName();
		// $target = Filesystem::normalizePath($target);
		// $share->setTarget($target);
	}

	public function overwriteMountPoint(VerifyMountPointEvent $event): void
	{
		$share = $event->getShare();
		$view = $event->getView();

		if (
			$share->getShareType() !== IShare::TYPE_DECK
			&& $share->getShareType() !== AnnouncementcenterShareProvider::SHARE_TYPE_DECK_USER
		) {
			return;
		}

		if ($event->getParent() === AnnouncementcenterShareProvider::ANNOUNCEMENTCENTER_FOLDER_PLACEHOLDER) {
			try {
				$userId = $view->getOwner('/');
			} catch (\Exception $e) {
				// If we fail to get the owner of the view from the cache,
				// e.g. because the user never logged in but a cron job runs
				// We fallback to calculating the owner from the root of the view:
				if (substr_count($view->getRoot(), '/') >= 2) {
					// /37c09aa0-1b92-4cf6-8c66-86d8cac8c1d0/files
					[, $userId,] = explode('/', $view->getRoot(), 3);
				} else {
					// Something weird is going on, we can't fallback more
					// so for now we don't overwrite the share path ¯\_(ツ)_/¯
					return;
				}
			}

			$parent = $this->configService->getAttachmentFolder($userId);
			$event->setParent($parent);
			if (!$event->getView()->is_dir($parent)) {
				$event->getView()->mkdir($parent);
			}
		}
	}
}
