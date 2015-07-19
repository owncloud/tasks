<?php
namespace OCA\Tasks\Service;

use \OCA\Tasks\Service\Helper;
use \OCA\Tasks\Service\TaskParser;
use \OCA\Tasks\Db\TasksMapper;

class TasksService {

	private $userId;
	private $tasksMapper;
	private $helper;
	private $taskParser;

	public function __construct($userId, TasksMapper $tasksMapper, Helper $helper, TaskParser $taskParser){
		$this->userId = $userId;
		$this->tasksMapper = $tasksMapper;
		$this->helper = $helper;
		$this->taskParser = $taskParser;
	}

	/**
	 * get a list of Tasks filtered by listID and type
	 * 
	 * @param  string $listID
	 * @param  string $type
	 * @return array
	 * @throws \Exception
	 */
	public function getAll($listID = 'all', $type = 'all'){
		
		if ($listID == 'all'){
			$calendars = \OC_Calendar_Calendar::allCalendars($this->userId, true);
		} else {
			$calendar = \OC_Calendar_App::getCalendar($listID, true, false);
			$calendars = array($calendar);
		}

		$tasks = array();
		$lists = array();
		foreach( $calendars as $calendar ) {
			$calendar_entries = $this->tasksMapper->findAllVTODOs($calendar['id']);

			list($lists[], $tasks_calendar) = $this->helper->getTasks($calendar_entries, $type, $calendar['id']);

			$tasks = array_merge($tasks, $tasks_calendar);
		}
		return array(
			'tasks' => $tasks,
			'lists' => $lists
		);
	}

	/**
	 * get task by id
	 * 
	 * @param  string $taskID
	 * @return array
	 * @throws \Exception
	 */
	public function get($taskID){
		$calendar_entry = $this->tasksMapper->findVTODOById($taskID);
		$task = array();
		$vtodo = $this->helper->checkTask($calendar_entry);
		if($vtodo){
			$task_data = $this->taskParser->parseTask($calendar_entry->getId(), $vtodo, $calendar_entry->getCalendarid());
			$task[] = $task_data;
		}
		return array(
			'tasks' => $task
		);
	}

	/**
	 * Search for query in tasks
	 *
	 * @param string $query
	 * @return array
	 */
	public function search($query) {
		$calendars = \OC_Calendar_Calendar::allCalendars($this->userId, true);
		$results = array();
		foreach ($calendars as $calendar) {
			$calendar_entries = $this->tasksMapper->findAllVTODOs($calendar['id']);
		 	// search all calendar objects, one by one
			foreach ($calendar_entries as $calendar_entry) {
				$vtodo = $this->helper->checkTask($calendar_entry);
				if(!$vtodo){
					continue;
				}
				if($this->checkTaskByQuery($vtodo, $query)) {
					$results[] = $this->taskParser->parseTask($calendar_entry->getId(), $vtodo, $calendar_entry->getCalendarid());
				}
			}
		}
		usort($results, array($this, 'sort_completed'));
		return $results;
	}

	/**
	 * check if task contains query
	 *
	 * @param mixed  $vtodo
	 * @param string $query
	 * @return array
	 */
	private function checkTaskByQuery($vtodo, $query) {
		// check these properties
		$properties = array('SUMMARY', 'DESCRIPTION', 'LOCATION', 'CATEGORIES', 'COMMENT');
		foreach ($properties as $property) {
			$strings = $vtodo->{$property};
			if ($strings) {
				foreach ($strings as $string) {
					$needle = $string->getValue();
					if (stripos($needle, $query) !== false) {
						return true;
					}
				}
			}
		}
		return false;
	}


	/**
	 * sort tasks by completed
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	private function sort_completed($a, $b){
		$t1 = $a['completed'];
		$t2 = $b['completed'];
		if ($t1 == $t2) {
			return 0;
		}
		return $t1 > $t2 ? 1 : -1;
	}

	/**
	 * create new task
	 * 
	 * @param  string $taskName
	 * @param  int    $calendarId
	 * @param  bool   $starred
	 * @param  mixed  $due
	 * @param  mixed  $start
	 * @param  int    $tmpID
	 * @return array
	 */
	public function add($taskName, $calendarId, $starred, $due, $start, $tmpID){
		$request = array(
				'summary'			=> $taskName,
				'starred'			=> $starred,
				'due'				=> $due,
				'start'				=> $start,
			);
		$vcalendar = $this->helper->createVCalendar($request);
		$taskID = \OC_Calendar_Object::add($calendarId, $vcalendar->serialize());

		$task = $this->taskParser->parseTask($taskID, $vcalendar->VTODO, $calendarId);

		$task['tmpID'] = $tmpID;
		return $task;
	}

	/**
	 * delete task by id
	 * 
	 * @param  int   $taskID
	 * @return bool
	 */
	public function delete($taskID) {
		return \OC_Calendar_Object::delete($taskID);
	}

	/**
	 * set name of task by id
	 * @param  int    $taskID
	 * @param  string $name
	 * @return bool
	 * @throws \Exception
	 */
	public function setName($taskID, $name) {
		return $this->helper->setProperty($taskID,'SUMMARY',$name);
	}

	/**
	 * set calendar id of task by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $calendarID
	 * @return bool
	 * @throws \Exception
	 */
	public function setCalendarId($taskID, $calendarID) {
		$data = \OC_Calendar_App::getEventObject($taskID);
		if ($data['calendarid'] != $calendarID) {
			return \OC_Calendar_Object::moveToCalendar($taskID, $calendarID);
		} else {
			return true;
		}
	}

	/**
	 * set completeness of task in percent by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $percent_complete
	 * @return bool
	 * @throws \Exception
	 */
	public function setPercentComplete($taskID, $percent_complete) {
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		if (!empty($percent_complete)) {
			$vtodo->{'PERCENT-COMPLETE'} = $percent_complete;
		}else{
			unset($vtodo->{'PERCENT-COMPLETE'});
		}
		if ($percent_complete == 100) {
			$vtodo->STATUS = 'COMPLETED';
			$vtodo->COMPLETED = new \DateTime('now', new \DateTimeZone('UTC'));
		} elseif ($percent_complete != 0) {
			$vtodo->STATUS = 'IN-PROCESS';
			unset($vtodo->COMPLETED);
		} else{
			$vtodo->STATUS = 'NEEDS-ACTION';
			unset($vtodo->COMPLETED);
		}
		return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
	}

	/**
	 * set priority of task by id
	 * 
	 * @param  int    $taskID
	 * @param  int    $priority
	 * @return bool
	 * @throws \Exception
	 */
	public function priority($taskID, $priority){
		$priority = (10 - $priority) % 10;
		return $this->helper->setProperty($taskID,'PRIORITY',$priority);
	}

	/**
	 * set due date of task by id
	 * 
	 * @param  int    $taskID
	 * @param  mixed  $dueDate
	 * @return bool
	 * @throws \Exception
	 */
	public function setDueDate($taskID, $dueDate) {
		return $this->helper->setProperty($taskID, 'DUE', $this->helper->formatDate($dueDate));
	}

	/**
	 * set start date of task by id
	 * 
	 * @param  int    $taskID
	 * @param  mixed  $startDate
	 * @return bool
	 * @throws \Exception
	 */
	public function setStartDate($taskID, $startDate) {
		return $this->helper->setProperty($taskID, 'DTSTART', $this->helper->formatDate($startDate));
	}

	/**
	 * set reminder date of task by id
	 * @param  int    $taskID
	 * @param  string $type
	 * @param  mixed  $action
	 * @param  mixed  $date
	 * @param  bool   $invert
	 * @param  string $related
	 * @param  mixed  $week
	 * @param  mixed  $day
	 * @param  mixed  $hour
	 * @param  mixed  $minute
	 * @param  mixed  $second
	 * @return bool
	 * @throws \Exception
	 */
	public function setReminderDate($taskID, $type, $action, $date, $invert, $related = null, $week, $day, $hour, $minute, $second){
		$types = array('DATE-TIME','DURATION');

		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$valarm = $vtodo->VALARM;

		if ($type == false){
			unset($vtodo->VALARM);
			$vtodo->{'LAST-MODIFIED'}->setValue(new \DateTime('now', new \DateTimeZone('UTC')));
			$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
		elseif (in_array($type,$types)) {
			if($valarm == null) {
				$valarm = $vcalendar->createComponent('VALARM');
				$valarm->ACTION = $action;
				$valarm->DESCRIPTION = 'Default Event Notification';
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
				$valarm->add('TRIGGER', $tv, array('VALUE' => $type, 'RELATED' => $related));
			} else {
				$valarm->add('TRIGGER', $tv, array('VALUE' => $type));
			}
			$vtodo->{'LAST-MODIFIED'}->setValue(new \DateTime('now', new \DateTimeZone('UTC')));
			$vtodo->DTSTAMP = new \DateTime('now', new \DateTimeZone('UTC'));
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		}
	}

	/**
	 * add category to task by id
	 * @param  int    $taskID
	 * @param  string $category
	 * @return bool
	 * @throws \Exception
	 */
	public function addCategory($taskID, $category){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		// fetch categories from TODO
		$categories = $vtodo->CATEGORIES;
		$taskcategories = array();
		if ($categories){
			$taskcategories = $categories->getParts();
		}
		// add category
		if (!in_array($category, $taskcategories)){
			$taskcategories[] = $category;
			$vtodo->CATEGORIES = $taskcategories;
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} else {
			return true;
		}
	}

	/**
	 * remove category from task by id
	 * @param  int    $taskID
	 * @param  string $category
	 * @return bool
	 * @throws \Exception
	 */
	public function removeCategory($taskID, $category){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		// fetch categories from TODO
		$categories = $vtodo->CATEGORIES;
		if ($categories){
			$taskcategories = $categories->getParts();
			// remove category
			$key = array_search($category, $taskcategories);
			if ($key !== null && $key !== false){
				unset($taskcategories[$key]);
				if(count($taskcategories)){
					$vtodo->CATEGORIES = $taskcategories;
				} else{
					unset($vtodo->{'CATEGORIES'});
				}
				return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
			}
		}
	}

	/**
	 * set location of task by id
	 * @param  int    $taskID
	 * @param  string $location
	 * @return bool
	 * @throws \Exception
	 */
	public function setLocation($taskID, $location){
		return $this->helper->setProperty($taskID,'LOCATION',$location);
	}

	/**
	 * set description of task by id
	 * 
	 * @param  int    $taskID
	 * @param  string $description
	 * @return bool
	 * @throws \Exception
	 */
	public function setDescription($taskID, $description){
		return $this->helper->setProperty($taskID,'DESCRIPTION',$description);
	}

	/**
	 * add comment to task by id
	 * @param  int    $taskID
	 * @param  string $comment
	 * @param  int    $tmpID
	 * @return array
	 * @throws \Exception
	 */
	public function addComment($taskID, $comment, $tmpID){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;

		if($vtodo->COMMENT == "") {
			// if this is the first comment set the id to 0
			$commentId = 0;
		} else {
			// Determine new commentId by looping through all comments
			$commentIds = array();
			foreach($vtodo->COMMENT as $com) {
				$commentIds[] = (int)$com['X-OC-ID']->getValue();
			}
			$commentId = 1+max($commentIds);
		}

		$now = 	new \DateTime();
		$vtodo->add('COMMENT',$comment,
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
			'name' => \OC::$server->getUserManager()->get($this->userId)->getDisplayName(),
			'userID' => $this->userId,
			'comment' => $comment,
			'time' => $now->format('Ymd\THis')
			);
		return $comment;
	}

	/**
	 * delete comment of task by id
	 * @param  int   $taskID
	 * @param  int   $commentID
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteComment($taskID, $commentID){
		$vcalendar = \OC_Calendar_App::getVCalendar($taskID);
		$vtodo = $vcalendar->VTODO;
		$commentIndex = $this->helper->getCommentById($vtodo,$commentID);
		$comment = $vtodo->children[$commentIndex];
		if($comment['X-OC-USERID']->getValue() == $this->userId){
			unset($vtodo->children[$commentIndex]);
			return \OC_Calendar_Object::edit($taskID, $vcalendar->serialize());
		} else {
			throw new \Exception('Not allowed.');
		}
	}

}
