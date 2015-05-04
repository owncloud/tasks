<?php

namespace OCA\Tasks\Service;

use \OCA\Tasks\Controller\Helper;

/**
 * Class TasksService
 *
 * @package OCA\Notes\Service
 */
class TasksService {

	private $userId;

	public function __construct ($userId) {
		$this->userId = $userId;
	}


	/**
	 * get a list of Tasks filtered by listID and type
	 *
	 * @param string $listID
	 * @param string $type
	 * @return array with all notes in the current directory
	 */
	public function getAll($listID = 'all', $type = 'all') {

		$user_timezone = \OC_Calendar_App::getTimezone();
		if ($listID == 'all'){
			$calendars = \OC_Calendar_Calendar::allCalendars($this->userId, true);
		} else {
			$calendar = \OC_Calendar_App::getCalendar($listID, true, false);
			$calendars = array($calendar);
		}

		$tasks = array();
		$lists = array();
		foreach( $calendars as $calendar ) {
			$calendar_entries = \OC_Calendar_Object::all($calendar['id']);
			$tasks_selected = array();
			foreach( $calendar_entries as $task ) {
				if($task['objecttype']!='VTODO') {
					continue;
				}
				if(is_null($task['summary'])) {
					continue;
				}
				$vtodo = Helper::parseVTODO($task['calendardata']);
				try {
					$task_data = Helper::arrayForJSON($task['id'], $vtodo, $user_timezone, $calendar['id']);
					switch($type){
						case 'all':
							$tasks[] = $task_data;
							break;
						case 'init':
							if (!$task_data['completed']){
								$tasks[] = $task_data;
							} else {
								$tasks_selected[] = $task_data;
							}
							break;
						case 'completed':
							if ($task_data['completed']){
								$tasks[] = $task_data;
							}
							break;
						case 'uncompleted':
							if (!$task_data['completed']){
								$tasks[] = $task_data;
							}
							break;
					}
				} catch(\Exception $e) {
					\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
				}
			}
			$nrCompleted = 0;
			$notLoaded = 0;
			usort($tasks_selected, array($this, 'sort_completed'));
			foreach( $tasks_selected as $task_selected){
				$nrCompleted++;
				if ($nrCompleted > 5){
					$notLoaded++;
					continue;
				}
				$tasks[] = $task_selected;
			}
			$lists[] = array(
				'id' 		=> $calendar['id'],
				'notLoaded' => $notLoaded
			);
		}
		return array(
			'data' => array(
				'tasks' => $tasks,
				'lists' => $lists
			)
		);
	}

	/**
	 * get Task by id
	 *
	 * @param string $taskID
	 * @return array
	 */
	public function get($taskID) {
		$object = \OC_Calendar_App::getEventObject($taskID);
		$user_timezone = \OC_Calendar_App::getTimezone();
		$task = array();
		if($object['objecttype']=='VTODO' && !is_null($object['summary'])) {
			$vtodo = Helper::parseVTODO($object['calendardata']);
			try {
				$task_data = Helper::arrayForJSON($object['id'], $vtodo, $user_timezone, $object['calendarid']);
				$task[] = $task_data;
			} catch(\Exception $e) {
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
			}
		}
		return array(
			'data' => array(
				'tasks' => $task
			)
		);
	}

	/**
	 * create new Task
	 *
	 * @param string $taskName
	 * @param $calendarId
	 * @param bool $starred
	 * @param $due
	 * @param $start
	 * @param $tmpID
	 * @return array
	 * @throws \Exception
	 */
	public function create($taskName, $calendarId, $starred, $due, $start, $tmpID) {
		$user_timezone = \OC_Calendar_App::getTimezone();
		$request = array(
			'summary'			=> $taskName,
			'categories'		=> null,
			'priority'			=> (int)$starred,
			'location' 			=> null,
			'due'				=> $due,
			'start'				=> $start,
			'description'		=> null
		);
		$vcalendar = Helper::createVCalendarFromRequest($request);
		$taskID = \OC_Calendar_Object::add($calendarId, $vcalendar->serialize());

		$task = Helper::arrayForJSON($taskID, $vcalendar->VTODO, $user_timezone, $calendarId);

		$task['tmpID'] = $tmpID;
		return array(
			'data' => array(
				'task' => $task
			)
		);
	}

	/**
	 * delete Task by taskID
	 *
	 * @param string $taskID
	 * @throws \Exception
	 */
	public function delete($taskID) {
		\OC_Calendar_Object::delete($taskID);
	}


	/**
	 * sets the name of a task by taskID
	 *
	 * @param string $taskID
	 * @param string $name
	 */
	public function setName($taskID, $name) {
		try {
			$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
			$vtodo = $vcalendar->VTODO;
			$vtodo->setString('SUMMARY', $name);
			\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
	}

	/**
	 * @param string $taskID
	 * @param integer $calendarID
	 * @throws \Exception
	 */
	public function setCalendarId($taskID, $calendarID) {
		$data = \OC_Calendar_App::getEventObject($taskID);
		if ($data['calendarid'] != $calendarID) {
			\OC_Calendar_Object::moveToCalendar($taskID, $calendarID);
		}
	}

	/**
	 * @param string $taskID
	 * @param bool $isStarred
	 */
	public function setStarred($taskID, $isStarred) {
		try {
			$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
			$vtodo = $vcalendar->VTODO;
			if($isStarred) {
				$vtodo->setString('PRIORITY',1);
			} else {
				$vtodo->__unset('PRIORITY');
			}
			\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
	}

	/**
	 * @param string $taskID
	 * @param integer $percent_complete
	 */
	public function setPercentComplete($taskID, $percent_complete){
		try {
			$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
			$vtodo = $vcalendar->VTODO;
			if (!empty($percent_complete)) {
				$vtodo->setString('PERCENT-COMPLETE', $percent_complete);
			}else{
				$vtodo->__unset('PERCENT-COMPLETE');
			}
			if ($percent_complete == 100) {
				$vtodo->setString('STATUS', 'COMPLETED');
				$vtodo->setDateTime('COMPLETED', 'now', \Sabre\VObject\Property\DateTime::UTC);
			} elseif ($percent_complete != 0) {
				$vtodo->setString('STATUS', 'IN-PROCESS');
				unset($vtodo->COMPLETED);
			} else{
				$vtodo->setString('STATUS', 'NEEDS-ACTION');
				unset($vtodo->COMPLETED);
			}
			\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
	}

	/**
	 * sets the due date of the task
	 *
	 * @param $taskID
	 * @param $dueDate
	 * @throws \Exception
	 */
	public function setDueDate($taskID, $dueDate) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if ($dueDate != false) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);

			$dueDate = new \DateTime('@'.$dueDate);
			$dueDate->setTimezone($timezone);
			$type = \Sabre\VObject\Property\DateTime::LOCALTZ;
			$vtodo->setDateTime('DUE', $dueDate, $type);
		} else {
			unset($vtodo->DUE);
		}
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function setStartDate($taskID, $start) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if ($start != false) {
			$timezone = \OC_Calendar_App::getTimezone();
			$timezone = new \DateTimeZone($timezone);

			$start = new \DateTime('@'.$start);
			$start->setTimezone($timezone);
			$type = \Sabre\VObject\Property\DateTime::LOCALTZ;
			$vtodo->setDateTime('DTSTART', $start, $type);
		} else {
			unset($vtodo->DTSTART);
		}
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function setReminderDate($taskID, $type, $action, $date, $invert, $related = null, $week, $day, $hour, $minute, $second) {
		$types = array('DATE-TIME','DURATION');

		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$valarm = $vtodo->VALARM;

		if ($type == false){
			unset($vtodo->VALARM);
			$vtodo->setDateTime('LAST-MODIFIED', 'now', \Sabre\VObject\Property\DateTime::UTC);
			$vtodo->setDateTime('DTSTAMP', 'now', \Sabre\VObject\Property\DateTime::UTC);
			\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
		elseif (in_array($type,$types)) {
			try{
				if($valarm == null) {
					$valarm = new \OC_VObject('VALARM');
					$valarm->setString('ACTION', $action);
					$valarm->setString('DESCRIPTION', 'Default Event Notification');
					$valarm->setString('');
					$vtodo->add($valarm);
				} else {
					unset($valarm->TRIGGER);
				}
				$tv = '';
				if ($type == 'DATE-TIME') {
					$date = new \DateTime('@'.$date);
					$tv = $date->format('Ymd\THis\Z');
				} elseif ($type == 'DURATION') {

					// Create duration string
					if($week || $day || $hour || $minute || $second) {
						if ($invert){
							$tv.='-';
						}
						$tv.='P';
						if ($week){
							$tv.=$week.'W';
						}
						if ($day){
							$tv.=$day.'D';
						}
						$tv.='T';
						if ($hour){
							$tv.=$hour.'H';
						}
						if ($minute){
							$tv.=$minute.'M';
						}
						if ($second){
							$tv.=$second.'S';
						}
					}else{
						$tv = 'PT0S';
					}
				}
				if($related == 'END'){
					$valarm->addProperty('TRIGGER', $tv, array('VALUE' => $type, 'RELATED' => $related));
				} else {
					$valarm->addProperty('TRIGGER', $tv, array('VALUE' => $type));
				}
				$vtodo->setDateTime('LAST-MODIFIED', 'now', \Sabre\VObject\Property\DateTime::UTC);
				$vtodo->setDateTime('DTSTAMP', 'now', \Sabre\VObject\Property\DateTime::UTC);
				\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
			} catch (\Exception $e) {

			}
		}
	}

	public function setCategories($taskID, $categories) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->setString('CATEGORIES', $categories);
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function setLocation($taskID, $location) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->setString('LOCATION', $location);
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function setTaskNote($taskID, $note) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$vtodo->setString('DESCRIPTION', $note);
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	public function addComment($taskID, $comment, $tmpID) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;

		// Determine new commentId by looping through all comments
		$commentIds = array();
		foreach($vtodo->COMMENT as $com) {
			$commentIds[] = (int)$com['X-OC-ID']->value;
		}
		$commentId = 1+max($commentIds);

		$now = 	new \DateTime();
		$vtodo->addProperty('COMMENT',$comment,
			array(
				'X-OC-ID' => $commentId,
				'X-OC-USERID' => $this->userId,
				'X-OC-DATE-TIME' => $now->format('Ymd\THis\Z')
			)
		);
		\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		$user_timezone = \OC_Calendar_App::getTimezone();
		$now->setTimezone(new \DateTimeZone($user_timezone));
		$comment = array(
			'taskID' => $taskID,
			'id' => $commentId,
			'tmpID' => $tmpID,
			'name' => \OCP\User::getDisplayName(),
			'userID' => $this->userId,
			'comment' => $comment,
			'time' => $now->format('Ymd\THis')
		);
		return array(
			'data' => array(
				'comment' => $comment
			)
		);
	}

	public function deleteComment($taskID, $commentID) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$commentIndex = $this->getCommentById($vtodo,$commentID);
		$comment = $vtodo->children[$commentIndex];
		if($comment['X-OC-USERID'] == $this->userId){
			unset($vtodo->children[$commentIndex]);
			\OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}else{
			throw new \Exception('Not allowed.');
		}
	}

	/**
	 *
	 * Private helper functions
	 *
	 */

	private static function sort_completed($a, $b){
		$t1 = \DateTime::createFromFormat('Ymd\THis', $a['completed_date']);
		$t2 = \DateTime::createFromFormat('Ymd\THis', $b['completed_date']);
		if ($t1 == $t2) {
			return 0;
		}
		return $t1 < $t2 ? 1 : -1;
	}

	private function getCommentById($vtodo,$commentId) {
		$idx = 0;
		foreach ($vtodo->children as $i => &$property) {
			if ( $property->name == 'COMMENT' && $property['X-OC-ID']->value == $commentId ) {
				return $idx;
			}
			$idx += 1;
		}
		throw new \Exception('Commment not found.');
	}


}