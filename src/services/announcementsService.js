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

import axios from "@nextcloud/axios";
import { generateOcsUrl } from "@nextcloud/router";

const getAnnouncements = async function (page = 1, pageSize = 10) {
	return axios.get(
		generateOcsUrl("apps/announcementcenter/api/v1/announcements"),
		{
			params: {
				page,
				pageSize,
			},
		}
	);
};
const searchAnnouncements = async function (filterKey, page, pageSize) {
	return axios.get(
		generateOcsUrl("apps/announcementcenter/api/v1/announcements/search"),
		{
			params: {
				filterKey,
				page,
				pageSize,
			},
		}
	);
};
/**
 * Get the groups for posting an announcement
 *
 * @param {string} [search] Search term to autocomplete a group
 * @return {object} The axios response
 */
const searchGroups = async function (search) {
	return axios.get(generateOcsUrl("apps/announcementcenter/api/v1/groups"), {
		params: {
			search: search || "",
		},
	});
};

/**
 * Post an announcement
 *
 * @param {string} subject Short title of the announcement
 * @param {string} message Markdown body of the announcement
 * @param {string} plainMessage Plain body of the announcement
 * @param {string[]} groups List of groups that can read the announcement
 * @param {boolean} activities Should activities be generated
 * @param {boolean} notifications Should notifications be generated
 * @param {boolean} emails Should emails be sent
 * @param {boolean} comments Are comments allowed
 * @return {object} The axios response
 */
const postAnnouncement = async function (
	subject,
	message,
	plainMessage,
	groups,
	activities,
	notifications,
	emails,
	comments
) {
	return axios.post(
		generateOcsUrl("apps/announcementcenter/api/v1/announcements"),
		{
			subject,
			message,
			plainMessage,
			groups,
			activities,
			notifications,
			emails,
			comments,
		}
	);
};
const updateAnnouncement = async (
	id,
	subject = null,
	message = null,
	plainMessage = null
) => {
	return axios.post(
		generateOcsUrl("apps/announcementcenter/api/v1/announcements/update"),
		{
			id,
			subject,
			message,
			plainMessage,
		}
	);
};
/**
 * Delete an announcement
 *
 * @param {number} id The announcement id to delete
 * @return {object} The axios response
 */
const deleteAnnouncement = async function (id) {
	return axios.delete(
		generateOcsUrl("apps/announcementcenter/api/v1/announcements/{id}", {
			id,
		})
	);
};

/**
 * Remove notifications for an announcement
 *
 * @param {number} id The announcement id to delete
 * @return {object} The axios response
 */
const removeNotifications = async function (id) {
	return axios.delete(
		generateOcsUrl(
			"apps/announcementcenter/api/v1/announcements/{id}/notifications",
			{ id }
		)
	);
};

export {
	getAnnouncements,
	searchGroups,
	postAnnouncement,
	deleteAnnouncement,
	removeNotifications,
	updateAnnouncement,
	searchAnnouncements,
};
