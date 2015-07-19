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
// use OCA\Tasks\App;

Class Helper {

	private $taskParser;

	public function __construct(TaskParser $taskParser){
		$this->taskParser = $taskParser;
	}

	/**
	 * check if task is valid
	 *
	 * @param \OCA\Tasks\Db\Tasks $task
	 * @return mixed
	*/
	public function checkTask($task) {
		$object = $this->readTask($task);
		if($object) {
			if(\OC_Calendar_Object::getowner($task->getId()) !== \OC::$server->getUserSession()->getUser()->getUID()){
				$sharedAccessClassPermissions = \OC_Calendar_Object::getAccessClassPermissions($object);
				if (!($sharedAccessClassPermissions & \OCP\Constants::PERMISSION_READ)) {
					return false;
				}
			}
			$object = \OC_Calendar_Object::cleanByAccessClass($task->getId(), $object);
			return $object->VTODO;
		}
		return false;
	}

	/**
	 * read object from calendar data
	 *
	 * @param \OCA\Tasks\Db\Tasks $task
	 * @return mixed
	*/
	private function readTask($task){
		$object = \Sabre\VObject\Reader::read($task->getCalendardata());
		if (!is_null($task->getSummary()) && $object) {
			return $object;
		} else{
			return false;
		}
	}

	/**
	 * get tasks
	 *
	 * @param array  $calendar_entries
	 * @param string $type
	 * @param string $calendarID
	 * @return array
	*/
	public function getTasks($calendar_entries, $type, $calendarID) {
		$list = array(
			'id' 		=> $calendarID,
			'notLoaded' => 0
			);
		$tasks_completed = array();
		$tasks_uncompleted = array();
		foreach ($calendar_entries as $calendar_entry) {
			$vtodo = $this->checkTask($calendar_entry);
			if(!$vtodo){
				continue;
			}
			$task = $this->taskParser->parseTask($calendar_entry->getId(), $vtodo, $calendarID);
			if($task['completed']) {
				$tasks_completed[] = $task;
			} else {
				$tasks_uncompleted[] = $task;
			}
		}
		list($list['notLoaded'], $tasks) = $this->selectTasks($tasks_completed, $tasks_uncompleted, $type);
		return array($list, $tasks);
	}

	/**
	 * select tasks
	 *
	 * @param array $tasks_completed
	 * @param array $tasks_uncompleted
	 * @param string $type
	 * @return array
	*/

	private function selectTasks($tasks_completed, $tasks_uncompleted, $type) {
		$notLoaded = 0;
		usort($tasks_completed, array($this, 'sort_completed'));
		switch($type){
			case 'init':
				$tasks_completed_recent = array_slice($tasks_completed,0,5);
				$tasks = array_merge($tasks_uncompleted, $tasks_completed_recent);
				$notLoaded = count($tasks_completed) - count($tasks_completed_recent);
				break;
			case 'completed':
				$tasks = $tasks_completed;
				break;
			case 'uncompleted':
				$tasks = $tasks_uncompleted;
				break;
			default:
				$tasks = array_merge($tasks_uncompleted, $tasks_completed);
				break;
		}
		return array($notLoaded, $tasks);
	}

	/**
	 * set property of a task
	 * 
	 * @param  int    $taskID
	 * @param  string $property
	 * @param  mixed  $value
	 * @return bool
	 * @throws \Exception
	 */
	public function setProperty($taskID,$property,$value){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if($value){
			$vtodo->{$property} = $value;
		}else{
			unset($vtodo->{$property});
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * format date
	 * 
	 * @param  mixed  $date
	 * @return mixed  $date
	 */
	public function formatDate($date) {
		$timezone = \OC_Calendar_App::getTimezone();
		$timezone = new \DateTimeZone($timezone);
		$date = new \DateTime('@'.$date);
		$date->setTimezone($timezone);
		return $date;
	}

	/**
	 * sort tasks
	 *
	 * @param array $a
	 * @param array $b
	 * @return array
	*/
	public function sort_completed($a, $b) {
		$t1 = \DateTime::createFromFormat('Ymd\THis', $a['completed_date']);
		$t2 = \DateTime::createFromFormat('Ymd\THis', $b['completed_date']);
		if ($t1 == $t2) {
			return 0;
		}
		return $t1 < $t2 ? 1 : -1;
	}

	/**
	 * sort get comment by ID
	 *
	 * @param array $vtodo
	 * @param string $commentID
	 * @return array
	*/
	public function getCommentById($vtodo,$commentID) {
		$idx = 0;
		foreach ($vtodo->children as $i => &$property) {
			if ( $property->name == 'COMMENT' && $property['X-OC-ID']->getValue() == $commentID ) {
				return $idx;
			}
			$idx += 1;
		}
		throw new \Exception('Commment not found.');
	}

	/**
	 * create calendar entry from request
	 *
	 * @param array $request
	 * @return mixed
	*/
	public function createVCalendar($request){
		$vcalendar = new \Sabre\VObject\Component\VCalendar();
		$vcalendar->PRODID = 'ownCloud Calendar';
		$vcalendar->VERSION = '2.0';

		$vtodo = $vcalendar->createComponent('VTODO');
		$vcalendar->add($vtodo);

		$vtodo->CREATED = new \DateTime('now', new \DateTimeZone('UTC'));

		$vtodo->UID = \Sabre\VObject\UUIDUtil::getUUID();
		return $this->addVTODO($vcalendar, $request);
	}

	/**
	 * update task from request
	 *
	 * @param array $request
	 * @param mixed $vcalendar
	 * @return mixed
	*/
	public function addVTODO($vcalendar, $request){
		$vtodo = $vcalendar->VTODO;
		$timezone = \OC_Calendar_App::getTimezone();
		$timezone = new \DateTimeZone($timezone);

		$vtodo->{'LAST-MODIFIED'} = new \DateTime('now', new \DateTimeZone('UTC'));
		$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
		$vtodo->SUMMARY = $request['summary'];

		if($request['starred']) {
			$vtodo->PRIORITY = 1; // prio: high
		}
		$due = $request['due'];
		if ($due) {
			$vtodo->DUE = new \DateTime($due, $timezone);
		}
		$start = $request['start'];
		if ($start) {
			$vtodo->DTSTART = new \DateTime($start, $timezone);
		}

		return $vcalendar;
	}
}
