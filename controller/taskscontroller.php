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
use \OCA\Tasks\Service\TasksService;

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
		$response = new JSONResponse();
		$result = $this->tasksService->getAll($listID, $type);
		$response->setData($result);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTask($taskID){
		$response = new JSONResponse();
		$result = $this->tasksService->get($taskID);
		$response->setData($result);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function starTask($taskID){
		$response = new JSONResponse();
		try {
			$this->tasksService->setStarred($taskID, true);
			return $response;
		} catch(\Exception $e) {
			return $response;
			// return $this->renderJSON(array(), $e->getMessage());
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function unstarTask($taskID){
		$response = new JSONResponse();
		try {
			$this->tasksService->setStarred($taskID, false);
			return $response;
		} catch(\Exception $e) {
			return $response;
			// return $this->renderJSON(array(), $e->getMessage());
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function percentComplete($taskID, $complete){
		$response = new JSONResponse();
		try{
			$this->tasksService->setPercentComplete($taskID, $complete);
			return $response;
		} catch(\Exception $e) {
			return $response;
			// return $this->renderJSON(array(), $e->getMessage());
		}
	}


	/**
	 * @NoAdminRequired
	 */
	public function completeTask($taskID){
		$response = new JSONResponse();
		try {
			$this->tasksService->setPercentComplete($taskID, 100);
			return $response;
		} catch(\Exception $e) {
			return $response;
			// return $this->renderJSON(array(), $e->getMessage());
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function uncompleteTask($taskID){
		$response = new JSONResponse();
		try {
			$this->tasksService->setPercentComplete($taskID, 0);
			return $response;
		} catch(\Exception $e) {
			return $response;
			// return $this->renderJSON(array(), $e->getMessage());
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function addTask($name, $calendarID, $starred, $due, $start, $tmpID){
		$result = $this->tasksService->create($name, $calendarID, $starred, $due, $start, $tmpID);

		$response = new JSONResponse();
		$response->setData($result);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteTask($taskID){
		$response = new JSONResponse();
		$this->tasksService->delete($taskID);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskName($taskID, $name){
		$response = new JSONResponse();
		$this->tasksService->setName($taskID, $name);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskCalendar($taskID, $calendarID){
		$response = new JSONResponse();
		try {
			$this->tasksService->setCalendarId($taskID, $calendarID);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setTaskNote($taskID, $note){
		$response = new JSONResponse();
		try {
			$this->tasksService->setTaskNote($taskID, $note);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setDueDate($taskID, $due){
		$response = new JSONResponse();
		try{
			$this->tasksService->setDueDate($taskID, $due);
		} catch (\Exception $e) {

		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setStartDate($taskID, $start){
		$response = new JSONResponse();

		try{
			$this->tasksService->setStartDate($taskID, $start);
		} catch (\Exception $e) {

		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setReminderDate($taskID, $type, $action, $date, $invert, $related, $week, $day, $hour, $minute, $second){
		$response = new JSONResponse();

		$this->tasksService->setReminderDate($taskID, $type, $action, $date, $invert, $related, $week, $day, $hour, $minute, $second);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setCategories($taskID, $categories){
		$response = new JSONResponse();
		try {
			$this->tasksService->setCategories($taskID, $categories);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function setLocation($taskID, $location){
		$response = new JSONResponse();
		try {
			$this->tasksService->setLocation($taskID, $location);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function addComment($taskID, $comment, $tmpID){
		$response = new JSONResponse();
		try {
			$result = $this->tasksService->addComment($taskID, $comment, $tmpID);
			$response->setData($result);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteComment($taskID, $commentID){
		$response = new JSONResponse();
		try {
			$this->tasksService->deleteComment($taskID, $commentID);
		} catch(\Exception $e) {
			// throw new BusinessLayerException($e->getMessage());
		}
		return $response;
	}

}