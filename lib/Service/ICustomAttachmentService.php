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


namespace OCA\AnnouncementCenter\Service;

/**
 * Interface to implement in case attachments are handled by a different backend than
 * then oc_deck_attachments table, e.g. for file sharing. When this interface is used
 * for implementing an attachment handler no backlink will be stored in the deck attachments
 * table and it is up to the implementation to track attachment to announcement relation.
 */
interface ICustomAttachmentService
{
	public function listAttachments(int $announcementId): array;

	public function getAttachmentCount(int $announcementId): int;
}
