/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Gets the announcements
 *
 * @param {int} [offset] The last announcement id loaded
 * @returns {Object} The axios response
 */
const getAnnouncements = async function(offset) {
	return axios.get(generateOcsUrl('apps/announcementcenter', 2) + 'api/v1/announcements', {
		offset: offset || 0,
	})
}

/**
 * Delete an announcement
 *
 * @param {int} id The announcement id to delete
 * @returns {Object} The axios response
 */
const deleteAnnouncement = async function(id) {
	return axios.delete(generateOcsUrl('apps/announcementcenter', 2) + 'api/v1/announcements/' + id)
}

/**
 * Remove notifications for an announcement
 *
 * @param {int} id The announcement id to delete
 * @returns {Object} The axios response
 */
const removeNotifications = async function(id) {
	return axios.delete(generateOcsUrl('apps/announcementcenter', 2) + 'api/v1/announcements/' + id + '/notifications')
}

export {
	getAnnouncements,
	deleteAnnouncement,
	removeNotifications,
}
