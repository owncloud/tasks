<?php
namespace OCA\Tasks\Service;

use \OCA\Tasks\Db\CalendarsMapper;

class CalendarService {

	private $userId;
	private $calendarsMapper;
	private $calendarParser;

	public function __construct($userId, CalendarsMapper $calendarsMapper, CalendarParser $calendarParser){
		$this->userId = $userId;
		$this->calendarsMapper = $calendarsMapper;
		$this->calendarParser = $calendarParser;
	}

	/**
	 * get a list of Calendars
	 * 
	 * @param  string $userID
	 * @param  bool   $active
	 * @param  bool   $create
	 * @return array
	 * @throws \Exception
	 */
	public function getAllCalendars($active=false, $create=true){
		$calendars = $this->getOwnCalendars($this->userId, $active);

		// TODO: create default calendar if necessary

		$calendars_shared = $this->getSharedCalendars($this->userId, true);

		return array_merge($calendars, $calendars_shared);
	}

	private function getOwnCalendars($userID, $active) {
		if ($active) {
			$calendar_entries = $this->calendarsMapper->findActiveCalendarsByUserId($userID);
		} else {
			$calendar_entries = $this->calendarsMapper->findCalendarsByUserId($userID);
		}
		$calendars = array();
		foreach ($calendar_entries as $calendar_entry) {
			$calendar = $this->calendarParser->parseCalendar($calendar_entry);
			$calendar['permissions'] = $this->getCalendarPermissions();
			$calendars[] = $calendar;
		}
		return $calendars;
	}

	// TODO
	private function getSharedCalendars($userID, $onlyForeign=false) {
		return array();
	}

	// TODO
	private function getCalendarPermissions() {
		return 31;
		// return OCP\PERMISSION_ALL;
	}
}
