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
import { showError } from "@nextcloud/dialogs";
import { formatFileSize } from "@nextcloud/files";
// eslint-disable-next-line import/no-unresolved
import PQueue from "p-queue";
import { AttachmentApi } from "./../services/AttachmentApi.js";
const apiClient = new AttachmentApi();
const queue = new PQueue({ concurrency: 2 });

export default {
	data() {
		return {
			uploadQueue: {},
			newUploadAttachments: [],
		};
	},
	methods: {
		async onLocalAttachmentSelected(file, type) {
			if (this.maxUploadSize > 0 && file.size > this.maxUploadSize) {
				showError(
					t("announcementcenter", "Failed to upload {name}", {
						name: file.name,
					}) +
						" - " +
						t(
							"announcementcenter",
							"Maximum file size of {size} exceeded",
							{
								size: formatFileSize(this.maxUploadSize),
							}
						)
				);
				event.target.value = "";
				return;
			}

			this.$set(this.uploadQueue, file.name, {
				name: file.name,
				progress: 0,
			});
			let bodyFormData = new FormData();

			bodyFormData.append("announcementId", this.announcementId);
			bodyFormData.append("type", type);
			bodyFormData.append("file", file);

			await queue.add(async () => {
				try {
					await this.$store.dispatch("createAttachment", {
						announcementId: this.announcementId,
						formData: bodyFormData,
						onUploadProgress: (e) => {
							const percentCompleted = Math.round(
								(e.loaded * 100) / e.total
							);
							this.$set(
								this.uploadQueue[file.name],
								"progress",
								percentCompleted
							);
						},
					});
				} catch (err) {
					if (err.response.data.status === 409) {
						this.overwriteAttachment = err.response.data.data;
						this.modalShow = true;
					} else {
						showError(
							err.response.data
								? err.response.data.message
								: "Failed to upload file"
						);
					}
				}
				this.$delete(this.uploadQueue, file.name);
			});
		},
		async onNewLocalAttachmentSelected(file) {
			if (this.maxUploadSize > 0 && file.size > this.maxUploadSize) {
				showError(
					t("announcementcenter", "Failed to upload {name}", {
						name: file.name,
					}) +
						" - " +
						t(
							"announcementcenter",
							"Maximum file size of {size} exceeded",
							{
								size: formatFileSize(this.maxUploadSize),
							}
						)
				);
				event.target.value = "";
				return;
			}

			this.$set(this.uploadQueue, file.name, {
				name: file.name,
				progress: 0,
			});
			let bodyFormData = new FormData();

			// bodyFormData.append("announcementId", this.announcementId);
			bodyFormData.append("file", file);
			await queue.add(async () => {
				try {
					const result = await apiClient.uploadAttachment({
						formData: bodyFormData,
						onUploadProgress: (e) => {
							const percentCompleted = Math.round(
								(e.loaded * 100) / e.total
							);
							this.$set(
								this.uploadQueue[file.name],
								"progress",
								percentCompleted
							);
						},
					});
					console.log(result.ocs.data);
					this.newUploadAttachments.push(result.ocs.data);
				} catch (err) {
					if (err.response.data.status === 409) {
						this.overwriteAttachment = err.response.data.data;
						this.modalShow = true;
					} else {
						showError(
							err.response.data
								? err.response.data.message
								: "Failed to upload file"
						);
					}
				}
				this.$delete(this.uploadQueue, file.name);
			});
		},
		async onShareAttachmentSelected(type, data) {
			let bodyFormData = new FormData();

			bodyFormData.append("announcementId", this.announcementId);
			bodyFormData.append("type", type);
			bodyFormData.append("data", data);

			try {
				await this.$store.dispatch("createAttachment", {
					announcementId: this.announcementId,
					formData: bodyFormData,
					onUploadProgress: (e) => {},
				});
			} catch (err) {
				if (err.response.data.status === 409) {
					this.overwriteAttachment = err.response.data.data;
					this.modalShow = true;
				} else {
					showError(
						err.response.data
							? err.response.data.message
							: "Failed to upload file"
					);
				}
			}
		},
		overrideAttachment() {
			const bodyFormData = new FormData();
			bodyFormData.append("announcementId", this.announcementId);
			bodyFormData.append("type", "announcementcenter_file");
			bodyFormData.append("file", this.file);
			this.$store.dispatch("updateAttachment", {
				announcementId: this.announcementId,
				attachment: this.overwriteAttachment,
				formData: bodyFormData,
			});

			this.modalShow = false;
		},
	},
};
