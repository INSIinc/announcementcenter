/**
 * @copyright Copyright (c) 2020 Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
 * @license AGPL-3.0-or-later
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

import Vue from "vue";
import { generateFilePath } from "@nextcloud/router";
import { getRequestToken } from "@nextcloud/auth";
import { translate, translatePlural } from "@nextcloud/l10n";
import store from "./store/index.js";
import App from "./App.vue";
import Vuex from "vuex";
import VTooltip from "@nextcloud/vue/dist/Directives/Tooltip.js";
// Styles
import "@nextcloud/dialogs/style.css";
import "windi.css";
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken());

// eslint-disable-next-line
__webpack_public_path__ = generateFilePath("announcementcenter", "", "js/");

// Register global directives
Vue.directive("Tooltip", VTooltip);
Vue.use(Vuex);

Vue.mixin({
	methods: {
		t: translate,
		n: translatePlural,
	},
});

export default new Vue({
	el: "#content",
	store,
	render: (h) => h(App),
});
