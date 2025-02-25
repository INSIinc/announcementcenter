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

namespace OCA\AnnouncementCenter;

use InvalidArgumentException;
use OCA\AnnouncementCenter\Model\Announcement;
use OCA\AnnouncementCenter\Model\AnnouncementDoesNotExistException;
use OCA\AnnouncementCenter\Model\AnnouncementMapper;
use OCA\AnnouncementCenter\Model\AttachmentMapper;
use OCA\AnnouncementCenter\Model\Group;
use OCA\AnnouncementCenter\Model\GroupMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\BackgroundJob\IJobList;
use OCP\Comments\ICommentsManager;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use ReflectionException;

class Manager
{

	/** @var IConfig */
	protected IConfig $config;

	/** @var AnnouncementMapper */
	protected AnnouncementMapper $announcementMapper;
	protected AttachmentMapper $attachmentMapper;

	/** @var GroupMapper */
	protected GroupMapper $groupMapper;

	/** @var IGroupManager */
	protected IGroupManager $groupManager;

	/** @var INotificationManager */
	protected INotificationManager $notificationManager;

	/** @var ICommentsManager */
	protected ICommentsManager $commentsManager;

	/** @var IJobList */
	protected IJobList $jobList;

	/** @var IUserSession */
	protected IUserSession $userSession;
	protected LoggerInterface $logger;
	protected IL10N $l;
	public function __construct(
		IConfig $config,
		AnnouncementMapper $announcementMapper,
		GroupMapper $groupMapper,
		IGroupManager $groupManager,
		INotificationManager $notificationManager,
		ICommentsManager $commentsManager,
		IJobList $jobList,
		IUserSession $userSession,
		AttachmentMapper $attachmentMapper,
		LoggerInterface $logger,
		IL10N $l
	) {
		$this->config = $config;
		$this->announcementMapper = $announcementMapper;
		$this->groupMapper = $groupMapper;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->commentsManager = $commentsManager;
		$this->jobList = $jobList;
		$this->userSession = $userSession;
		$this->logger = $logger;
		$this->attachmentMapper = $attachmentMapper;
		$this->l = $l;
	}

	/**
	 * @param string $subject
	 * @param string $message
	 * @param string $plainMessage
	 * @param string $user
	 * @param int $time
	 * @param string[] $groups
	 * @param bool $comments
	 * @return Announcement
	 * @throws InvalidArgumentException|Exception when the subject is empty or invalid
	 */
	public function announce(string $subject, string $message, string $plainMessage, string $user, int $time, array $groups, bool $comments): Announcement
	{
		$subject = trim($subject);
		$message = trim($message);
		$plainMessage = trim($plainMessage);
		if (isset($subject[512])) {
			throw new InvalidArgumentException('Invalid subject', 1);
		}

		if ($subject === '') {
			throw new InvalidArgumentException('Invalid subject', 2);
		}

		$announcement = new Announcement();
		$announcement->setSubject($subject);
		$announcement->setMessage($message);
		$announcement->setPlainMessage($plainMessage);
		$announcement->setUser($user);
		$announcement->setTime($time);
		$announcement->setAllowComments((int) $comments);
		$this->announcementMapper->insert($announcement);

		$addedGroups = 0;
		foreach ($groups as $group) {
			if ($this->groupManager->groupExists($group)) {
				$this->addGroupLink($announcement, $group);
				$addedGroups++;
			}
		}

		if ($addedGroups === 0) {
			$gid = $this->l->t("everyone");
			$this->addGroupLink($announcement, $gid);
		}

		return $announcement;
	}

	/**
	 * @param Announcement $announcement
	 * @return Announcement
	 * @throws Exception
	 */
	public function updateAnnouncement(Announcement $announcement): Announcement
	{
		return $this->announcementMapper->updateAnnouncement($announcement);
	}

	/**
	 * @throws Exception
	 */
	protected function addGroupLink(Announcement $announcement, string $gid): void
	{
		$group = new Group();
		$group->setId($announcement->getId());
		$group->setGroup($gid);
		$this->groupMapper->insert($group);
	}

	public function delete(int $id): void
	{
		// Delete notifications
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('announcementcenter')
			->setObject('announcement', (string)$id);
		$this->notificationManager->markProcessed($notification);

		// Delete comments
		$this->commentsManager->deleteCommentsAtObject('announcement', (string) $id);

		$announcement = $this->announcementMapper->getById($id);
		$this->attachmentMapper->deleteAttachmentsSharesForAnnouncement($announcement);
		$this->groupMapper->deleteGroupsForAnnouncement($announcement);
		$this->announcementMapper->delete($announcement);
	}

	/**
	 * @param int $id
	 * @param bool $ignorePermissions Permissions are ignored e.g. in background jobs to generate activities etc.
	 * @return Announcement
	 * @throws AnnouncementDoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getAnnouncement(int $id, bool $ignorePermissions = false): Announcement
	{
		try {
			$announcement = $this->announcementMapper->getById($id);
		} catch (DoesNotExistException $e) {
			throw new AnnouncementDoesNotExistException();
		}

		if ($ignorePermissions) {
			return $announcement;
		}

		$userGroups = $this->getUserGroups();
		$memberOfAdminGroups = array_intersect($this->getAdminGroups(), $userGroups);
		if (!empty($memberOfAdminGroups)) {
			return $announcement;
		}

		$groups = $this->groupMapper->getGroupsForAnnouncement($announcement);
		$memberOfGroups = array_intersect($groups, $userGroups);

		if (empty($memberOfGroups)) {
			throw new AnnouncementDoesNotExistException();
		}

		return $announcement;
	}

	protected function getUserGroups(): array
	{
		$user = $this->userSession->getUser();
		if ($user instanceof IUser) {
			$userGroups = $this->groupManager->getUserGroupIds($user);
		}

		return $userGroups;
	}

	/**
	 * @param Announcement $announcement
	 * @return string[]
	 */
	public function getGroups(Announcement $announcement): array
	{
		return $this->groupMapper->getGroupsForAnnouncement($announcement);
	}
	/**
	 * @param int $announcementId
	 * @return string[]
	 */
	public function getGroupsByAnnouncementId(int $announcementId): array
	{
		return $this->groupMapper->getGroupsByAnnouncementId($announcementId);
	}
	public function getUsersByAnnouncementId(int $announcementId): array
	{
		// 获取与公告ID相关的群组
		$groups = $this->groupMapper->getGroupsByAnnouncementId($announcementId);

		// 存储所有用户的数组
		$users = [];

		// 遍历每个群组
		foreach ($groups as $group) {
			// 使用GroupManager获取群组对象
			$groupObject = $this->groupManager->get($group);

			// 获取群组中的所有用户
			$groupUsers = $groupObject->getUsers();

			// 将群组用户添加到总用户数组中
			foreach ($groupUsers as $user) {
				$users[] = $user->getUID();
			}
		}

		return $users;
	}

	/**
	 * @param int $page
	 * @param int $pageSize
	 * @return array
	 * @throws Exception
	 */
	public function getAnnouncements(int $page = 1, int $pageSize = 10): array
	{
		$userGroups = $this->getUserGroups();
		// $memberOfAdminGroups = array_intersect($this->getAdminGroups(), $userGroups);
		// if (!empty($memberOfAdminGroups)) {
		// 	$userGroups = [];
		// }
		return $this->announcementMapper->getAnnouncements($this->userSession->getUser()->getUID(), $userGroups, $page, $pageSize);
	}
	public function getAnnouncementsFromOffsetId(int $since = null, int $limit = 7): array
	{
		$userGroups = $this->getUserGroups();
		// $memberOfAdminGroups = array_intersect($this->getAdminGroups(), $userGroups);
		// if (!empty($memberOfAdminGroups)) {
		// 	$userGroups = [];
		// }
		return $this->announcementMapper->getAnnouncementsFromOffsetId($this->userSession->getUser()->getUID(), $userGroups, $since, $limit);
	}
	/**
	 * @param string $filterKey
	 * @param int $page
	 * @param int $pageSize
	 * @return array
	 * @throws BadRequestException
	 * @throws Exception
	 * @throws InvalidAttachmentType
	 * @throws ReflectionException
	 */
	public function searchAnnouncements(string $filterKey = '', int $page = 1, int $pageSize = 10): array
	{
		$userGroups = $this->getUserGroups();
		// $memberOfAdminGroups = array_intersect($this->getAdminGroups(), $userGroups);
		// if (!empty($memberOfAdminGroups)) {
		// 	$userGroups = [];
		// }
		$result = $this->announcementMapper->searchAnnouncements($this->userSession->getUser()->getUID(), $userGroups, $filterKey, $page, $pageSize);
		// $result['data'] = array_map(function (Announcement $announcement) {
		// 	$attachments = $this->attachmentService->findAll($announcement->getId(), true);
		// 	$announcement->setAttachments($attachments);
		// 	$announcement->setAttachmentCount($this->attachmentService->count($announcement->getId()));
		// 	return $announcement;
		// }, $result['data']);
		return $result;
	}


	public function markNotificationRead(int $id): void
	{
		$user = $this->userSession->getUser();

		if ($user instanceof IUser) {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('announcementcenter')
				->setUser($user->getUID())
				->setObject('announcement', (string)$id);
			$this->notificationManager->markProcessed($notification);
		}
	}

	public function getNumberOfComments(Announcement $announcement): int
	{
		return $this->commentsManager->getNumberOfCommentsForObject('announcement', (string) $announcement->getId());
	}

	public function hasNotifications(Announcement $announcement): bool
	{
		$jobMatrix = [
			['id' => $announcement->getId(), 'activities' => true, 'notifications' => true, 'emails' => true],
			['id' => $announcement->getId(), 'activities' => true, 'notifications' => true, 'emails' => false],
			['id' => $announcement->getId(), 'activities' => false, 'notifications' => true, 'emails' => true],
			['id' => $announcement->getId(), 'activities' => false, 'notifications' => true, 'emails' => false],
		];

		foreach ($jobMatrix as $jobArguments) {
			if ($hasJob = $this->jobList->has(BackgroundJob::class, $jobArguments)) {
				break;
			}
		}

		if ($hasJob) {
			return true;
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('announcementcenter')
			->setObject('announcement', (string)$announcement->getId());
		return $this->notificationManager->getCount($notification) > 0;
	}

	public function removeNotifications(int $id): void
	{
		$jobMatrix = [
			['id' => $id, 'activities' => true, 'notifications' => true, 'emails' => true],
			['id' => $id, 'activities' => true, 'notifications' => true, 'emails' => false],
			['id' => $id, 'activities' => false, 'notifications' => true, 'emails' => true],
		];

		$jobArguments = ['id' => $id, 'activities' => false, 'notifications' => true, 'emails' => false];
		if ($this->jobList->has(BackgroundJob::class, $jobArguments)) {
			// Delete the current background job as it was only for notifications
			$this->jobList->remove(BackgroundJob::class, $jobArguments);
		} else {
			foreach ($jobMatrix as $jobArguments) {
				if ($this->jobList->has(BackgroundJob::class, $jobArguments)) {
					// Delete the current background job and add a new one without notifications
					$this->jobList->remove(BackgroundJob::class, $jobArguments);
					$jobArguments['notifications'] = false;
					$this->jobList->add(BackgroundJob::class, $jobArguments);
					break;
				}
			}
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('announcementcenter')
			->setObject('announcement', (string)$id);
		$this->notificationManager->markProcessed($notification);
	}

	/**
	 * Check if the user is in the admin group
	 */
	public function checkIsAdmin(): bool
	{
		$user = $this->userSession->getUser();

		if ($user instanceof IUser) {
			$group = "admin";
			if ($this->groupManager->isInGroup($user->getUID(), $group)) {
				return true;
			}
		}

		return false;
	}
	public function checkIsAuthor(Announcement $announcement): bool
	{
		$user = $this->userSession->getUser();
		$userId = $user instanceof IUser ? $user->getUID() : '';
		return $userId == $announcement->getUser();
	}
	public function checkCanCreate(): bool
	{
		$user = $this->userSession->getUser();

		if ($user instanceof IUser) {
			$groups = $this->getAdminGroups();
			foreach ($groups as $group) {
				if ($this->groupManager->isInGroup($user->getUID(), $group)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	protected function getAdminGroups(): array
	{
		$adminGroups = $this->config->getAppValue('announcementcenter', 'admin_groups', '["admin"]');
		$adminGroups = json_decode($adminGroups, true);
		return $adminGroups;
	}
}
