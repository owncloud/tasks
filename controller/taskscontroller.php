<?php

/**
* ownCloud - Tasks
*
* @author Raimund Schlüßler
* @copyright 2013 Raimund Schlüßler raimund.schluessler@googlemail.com
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

namespace OCA\Tasks\Controller;

use \OCP\IRequest;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCA\Tasks\Controller\Helper;

class TasksController extends Controller {

	private $userId;
	private $tasksService;

	public function __construct($appName, IRequest $request, TasksService $tasksService, $userId){
		parent::__construct($appName, $request);
		$this->tasksService = $tasksService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTasks($listID = 'all', $type = 'all'){
<<<<<<< HEAD
		$result = $this->tasksService->getAll($listID, $type);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
=======
		
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
				if(!($vtodo = Helper::parseVTODO($task))){
					continue;
				}
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
		$result = array(
			'data' => array(
				'tasks' => $tasks,
				'lists' => $lists
				)
			);
		$response = new JSONResponse();
		$response->setData($result);
		return $response;
>>>>>>> 39896f72b50ee03e91ca4cd0c771f3590355a7a6
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTask($taskID){
<<<<<<< HEAD
		$result = $this->tasksService->get($taskID);
		$response = array(
=======
		$object = \OC_Calendar_App::getEventObject($taskID);
		$user_timezone = \OC_Calendar_App::getTimezone();
		$task = array();
		if($object['objecttype']=='VTODO' && !is_null($object['summary'])) {
			if($vtodo = Helper::parseVTODO($object)){
				try {
					$task_data = Helper::arrayForJSON($object['id'], $vtodo, $user_timezone, $object['calendarid']);
					$task[] = $task_data;
				} catch(\Exception $e) {
					\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
				}	
			}	
		}
		$result = array(
>>>>>>> 39896f72b50ee03e91ca4cd0c771f3590355a7a6
			'data' => array(
				'task' => $result
			)
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function starTask($taskID){
		$result = $this->tasksService->setStarred($taskID, true);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function unstarTask($taskID){
		$result = $this->tasksService->setStarred($taskID, false);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function percentComplete($taskID, $complete){
		$result = $this->tasksService->setPercentComplete($taskID, $complete);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}


	/**
	 * @NoAdminRequired
	 */
	public function completeTask(){
		$result = $this->tasksService->setPercentComplete($taskID, 100);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function uncompleteTask(){
		$result = $this->tasksService->setPercentComplete($taskID, 0);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addTask($taskName, $calendarId, $starred, $due, $start, $tmpID){
		$result = $this->tasksService->add($taskName, $calendarId, $starred, $due, $start, $tmpID);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteTask($taskID){
		$result = $this->tasksService->delete($taskID);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskName($taskID, $name){
		$result = $this->tasksService->setName($taskID, $name);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskCalendar($taskID, $calendarID){
		$result = $this->tasksService->setCalendarId($taskID, $calendarID);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskNote($taskID, $note){
		$result = $this->tasksService->setDescription($taskID, $note);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setDueDate($taskID, $due){
		$result = $this->tasksService->setDueDate($taskID, $due);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setStartDate($taskID, $start){
		$result = $this->tasksService->setStartDate($taskID, $start);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setReminderDate($taskID, $type, $action, $date, $invert, $related = null, $week, $day, $hour, $minute, $second){
		$result = $this->tasksService->setReminderDate($taskID, $type, $action, $date, $invert, $related = null, $week, $day, $hour, $minute, $second);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addCategory($taskID, $category){
		$result = $this->tasksService->addCategory($taskID, $category);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function removeCategory($taskID, $category){
		$result = $this->tasksService->removeCategory($taskID, $category);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setLocation($taskID, $location){
		$result = $this->tasksService->setLocation($taskID, $location);
		$response = array(
			'data' => $result
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function addComment($taskID, $comment, $tmpID){
		$result = $this->tasksService->addComment($taskID, $comment);
		$response = array(
			'data' => array(
				'comment' => $result
			)
		);
		return (new JSONResponse())->setData($response);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteComment($taskID, $commentID){
		$result = $this->tasksService->deleteComment($taskID, $commentID);
		$response = array(
			'data' => array(
				'comment' => $result
			)
		);
		return (new JSONResponse())->setData($response);
	}
}
