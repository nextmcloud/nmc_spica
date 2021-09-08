/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function() {

	var renderHeader = function () {
		var count = OCP.InitialState.loadState('nmc_spica', 'unread-counter')
		var icon = document.createElement('div');
		icon.classList = 'icon-mail';
		var label = document.createElement('div');
		label.textContent = 'Mail' + ' (' + count + ')'


		var mailWrapper = document.createElement('a');
		mailWrapper.href = OCP.InitialState.loadState('nmc_spica', 'mail-url')
		mailWrapper.classList = 'nmc_spica_wrapper'
		mailWrapper.appendChild(icon);
		mailWrapper.appendChild(label);

		return mailWrapper
	}

	document.querySelector('.header-right').insertBefore(renderHeader(), document.getElementById('unified-search'));
})()
