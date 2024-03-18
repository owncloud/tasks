/**
 * ownCloud - Tasks
 *
 * @author Raghu Nayyar
 * @author Georg Ehrke
 * @author Raimund Schlüßler
 * @copyright 2016 Raghu Nayyar <beingminimal@gmail.com>
 * @copyright 2016 Georg Ehrke <oc.list@georgehrke.com>
 * @copyright 2016 Raimund Schlüßler <raimund.schluessler@googlemail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

angular.module('Tasks').factory('Calendar', ['$rootScope', '$filter', '$window', function($rootScope, $filter, $window) {
	'use strict';

	function Calendar(url, props, uri) {
		var _this = this;

		props.color = props['{http://apple.com/ns/ical/}calendar-color'];
		if (typeof props.color !== 'undefined') {
			if (props.color.length === 9) {
				props.color = props.color.slice(0,7);
			}
		} else {
			props.color = '#1d2d44';
		}

		angular.extend(this, {
			_propertiesBackup: {},
			_properties: {
				url: url,
				uri: uri,
				enabled: props['{http://owncloud.org/ns}calendar-enabled'] === '1',
				displayname: props['{DAV:}displayname'] || t('tasks','Unnamed'),
				color: props.color,
				order: parseInt(props['{http://apple.com/ns/ical/}calendar-order']) || 0,
				components: {
					vevent: false,
					vjournal: false,
					vtodo: false
				},
				writable: props.canWrite,
				shareable: props.canWrite,
				sharedWith: {
					users: [],
					groups: []
				},
				owner: '',
				loadedCompleted: false
			},
			_updatedProperties: []
		});
		this._propertiesBackup = angular.copy(this._properties);

		// angular.extend(this, {
		// 	tmpId: null,
		// 	fcEventSource: {
		// 		events: function (start, end, timezone, callback) {
		// 			// console.log('querying events ...');
		// 			// TimezoneService.get(timezone).then(function(tz) {
		// 			// 	_this.list.loading = true;
		// 			// 	$rootScope.$broadcast('reloadCalendarList');

		// 			// 	VEventService.getAll(_this, start, end).then(function(events) {
		// 			// 		var vevents = [];
		// 			// 		for (var i = 0; i < events.length; i++) {
		// 			// 			vevents = vevents.concat(events[i].getFcEvent(start, end, tz));
		// 			// 		}

		// 			// 		callback(vevents);

		// 			// 		_this.list.loading = false;
		// 			// 		$rootScope.$broadcast('reloadCalendarList');
		// 			// 	});
		// 			// });
		// 		},
		// 		editable: this._properties.writable,
		// 		calendar: this
		// 	},
		// 	list: {
		// 		edit: false,
		// 		loading: this.enabled,
		// 		locked: false,
		// 		editingShares: false
		// 	}
		// });

		var components = props['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'];
		for (var i=0; i < components.length; i++) {
			var name = components[i].attributes.getNamedItem('name').textContent.toLowerCase();
			if (this._properties.components.hasOwnProperty(name)) {
				this._properties.components[name] = true;
			}
		}

		var shares = props['{http://owncloud.org/ns}invite'];
		if (typeof shares !== 'undefined') {
			for (var j=0; j < shares.length; j++) {
				var href = shares[j].getElementsByTagNameNS('DAV:', 'href');
				if (href.length === 0) {
					continue;
				}
				href = href[0].textContent;

				var access = shares[j].getElementsByTagNameNS('http://owncloud.org/ns', 'access');
				if (access.length === 0) {
					continue;
				}
				access = access[0];

				var readWrite = access.getElementsByTagNameNS('http://owncloud.org/ns', 'read-write');
				readWrite = readWrite.length !== 0;

				if (href.startsWith('principal:principals/users/')) {
					this._properties.sharedWith.users.push({
						id: href.slice(27),
						displayname: href.slice(27),
						writable: readWrite
					});
				} else if (href.startsWith('principal:principals/groups/')) {
					this._properties.sharedWith.groups.push({
						id: href.slice(28),
						displayname: href.slice(28),
						writable: readWrite
					});
				}
			}
		}

		var owner = props['{DAV:}owner'];
		if (typeof owner !== 'undefined' && owner.length !== 0) {
			owner = owner[0].textContent.slice(0, -1);
			if (owner.startsWith('/remote.php/dav/principals/users/')) {
				this._properties.owner = owner.slice(33);
			}
		}

		// this.tmpId = RandomStringService.generate();
	}

	Calendar.prototype = {
		get url() {
			return this._properties.url;
		},
		get caldav() {
			return $window.location.origin + this.url;
		},
		get exportUrl() {
			var url = this.url;
			// cut off last slash to have a fancy name for the ics
			if (url.slice(url.length - 1) === '/') {
				url = url.slice(0, url.length - 1);
			}
			url += '?export';
			return url;
		},
		get enabled() {
			return this._properties.enabled;
		},
		get uri() {
			return this._properties.uri;
		},
		get components() {
			return this._properties.components;
		},
		set enabled(enabled) {
			this._properties.enabled = enabled;
			this._setUpdated('enabled');
		},
		get displayname() {
			return this._properties.displayname;
		},
		set displayname(displayname) {
			this._properties.displayname = displayname;
			this._setUpdated('displayname');
		},
		get color() {
			return this._properties.color;
		},
		set color(color) {
			this._properties.color = color;
			this._setUpdated('color');
		},
		get sharedWith() {
			return this._properties.sharedWith;
		},
		set sharedWith(sharedWith) {
			this._properties.sharedWith = sharedWith;
		},
		get textColor() {
			var color = this.color;
			var fallbackColor = '#fff';
			var c;
			switch (color.length) {
				case 4:
					c = color.match(/^#([0-9a-f]{3})$/i)[1];
					if (c) {
						return this._generateTextColor(
							parseInt(c.charAt(0),16)*0x11,
							parseInt(c.charAt(1),16)*0x11,
							parseInt(c.charAt(2),16)*0x11
						);
					}
					return fallbackColor;

				case 7:
				case 9:
					var regex = new RegExp('^#([0-9a-f]{' + (color.length - 1) + '})$', 'i');
					c = color.match(regex)[1];
					if (c) {
						return this._generateTextColor(
							parseInt(c.slice(0,2),16),
							parseInt(c.slice(2,4),16),
							parseInt(c.slice(4,6),16)
						);
					}
					return fallbackColor;

				default:
					return fallbackColor;
			}
		},
		get order() {
			return this._properties.order;
		},
		set order(order) {
			this._properties.order = order;
			this._setUpdated('order');
		},
		get writable() {
			return this._properties.writable;
		},
		get shareable() {
			return this._properties.shareable;
		},
		get owner() {
			return this._properties.owner;
		},
		get loadedCompleted() {
			return this._properties.loadedCompleted;
		},
		set loadedCompleted(loadedCompleted) {
			this._properties.loadedCompleted = loadedCompleted;
		},
		_setUpdated: function(propName) {
			if (this._updatedProperties.indexOf(propName) === -1) {
				this._updatedProperties.push(propName);
			}
		},
		get updatedProperties() {
			return this._updatedProperties;
		},
		resetUpdatedProperties: function() {
			this._updatedProperties = [];
		},
		prepareUpdate: function() {
			this._propertiesBackup = angular.copy(this._properties);
		},
		resetToPreviousState: function() {
			this._properties = angular.copy(this._propertiesBackup);
			this._propertiesBackup = {};
		},
		dropPreviousState: function() {
			this._propertiesBackup = {};
		},
		toggleSharesEditor: function() {
			this.list.editingShares = !this.list.editingShares;
		},
		_generateTextColor: function(r,g,b) {
			var brightness = (((r * 299) + (g * 587) + (b * 114)) / 1000);
			return (brightness > 130) ? '#000000' : '#FAFAFA';
		}
	};

	return Calendar;
}]);
