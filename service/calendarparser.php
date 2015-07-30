<?php
/**
 * ownCloud - Utility class for VObject properties
 *
 * @author Thomas Tanghus
 * @copyright 2013-2014 Thomas Tanghus (thomas@tanghus.net)
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

namespace OCA\Tasks\Service;

use Sabre\VObject;

Class CalendarParser {

	public function parseCalendar($calendar_entry){
		$calendar = array( 'id' => $calendar_entry->getId() );
		$calendar['userid'] = $calendar_entry->getUserid();
		$calendar['displayname'] = $calendar_entry->getDisplayname();
		$calendar['uri'] = $calendar_entry->getUri();
		$calendar['active'] = $calendar_entry->getActive();
		$calendar['ctag'] = $calendar_entry->getCtag();
		$calendar['calendarorder'] = $calendar_entry->getCalendarorder();
		$calendar['calendarcolor'] = $calendar_entry->getCalendarcolor();
		$calendar['timezone'] = $calendar_entry->getTimezone();
		$calendar['components'] = $calendar_entry->getComponents();
		return $calendar;
	}
}
