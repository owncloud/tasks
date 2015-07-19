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

Class TaskParser {

	public function parseTask($taskID, $vtodo, $calendarID){
		$task = array( 'id' => $taskID );
		$task['calendarid'] = $calendarID;
		$task['type'] 		= 'task';
		$task['name'] 		= (string) $vtodo->SUMMARY;
		$task['created'] 	= (string) $vtodo->CREATED;
		$task['note'] 		= (string) $vtodo->DESCRIPTION;
		$task['location'] 	= (string) $vtodo->LOCATION;
		$task['categories'] 	= $this->parseCategories($vtodo->CATEGORIES);
		$task['start'] 			= $this->parseDate($vtodo->DTSTART);
		$task['due'] 			= $this->parseDate($vtodo->DUE);
		$task['completed_date'] = $this->parseDate($vtodo->COMPLETED);
		$task['completed'] = (bool) $task['completed_date'];
		$task['reminder']  = $this->parseReminder($vtodo->VALARM, $vtodo->DTSTART, $vtodo->DUE);
		$task['priority']  = $this->parsePriority($vtodo->PRIORITY);
		$task['starred']   = $this->parseStarred($task['priority']);
		$task['complete']  = $this->parsePercentCompleted($vtodo->{'PERCENT-COMPLETE'});
		$task['comments']  = $this->parseComments($vtodo->COMMENT);
		return $task;
	}

	private function parseStarred($priority) {
		if ((int) $priority > 5) {
			return true;
		} else {
			return false;
		}
	}

	private function parsePriority($priority) {
		if(isset($priority)){
			return (string) (10 - $priority->getValue()) % 10;
		} else {
			return '0';
		}
	}

	private function parseReminder($reminder, $start, $due) {
		$user_timezone = \OC_Calendar_App::getTimezone();
		if($reminder) {
			try {
				if ($reminder->TRIGGER['VALUE']){
					$reminderType = $reminder->TRIGGER['VALUE']->getValue();	
				} else {
					throw new \Exception('Reminder type not specified.');
				}
				if ($reminder->ACTION) {
					$reminderAction = $reminder->ACTION->getValue();
				} else {
					throw new \Exception('Reminder action not specified.');	
				}
				$reminderDate = null;
				$reminderDuration = null;

				if($reminderType == 'DATE-TIME'){
					$reminderDate = $reminder->TRIGGER->getDateTime();
					$reminderDate->setTimezone(new \DateTimeZone($user_timezone));
					$reminderDate = $reminderDate->format('Ymd\THis');
				} elseif ($reminderType == 'DURATION' && ($start || $due)) {

					$parsed = VObject\DateTimeParser::parseDuration($reminder->TRIGGER,true);
					// Calculate the reminder date from duration and start date
					$related = null;
					if(is_object($reminder->TRIGGER['RELATED'])){
						$related = $reminder->TRIGGER['RELATED']->getValue();
						if($related == 'END' && $due){
							$due = $due->getDateTime();
							$due->setTimezone(new \DateTimeZone($user_timezone));
							$reminderDate = $due->modify($parsed)->format('Ymd\THis');
						} else {
							throw new \Exception('Reminder duration related to not available date.');
						}
					} elseif ($start) {
						$start = $start->getDateTime();
						$start->setTimezone(new \DateTimeZone($user_timezone));
						$reminderDate = $start->modify($parsed)->format('Ymd\THis');
					} else{
						throw new \Exception('Reminder duration related to not available date.');
					}
					preg_match('/^(?P<plusminus>\+|-)?P((?P<week>\d+)W)?((?P<day>\d+)D)?(T((?P<hour>\d+)H)?((?P<minute>\d+)M)?((?P<second>\d+)S)?)?$/', $reminder->TRIGGER, $matches);
		            $invert = false;
		            if ($matches['plusminus']==='-') {
		                $invert = true;
		            }

		            $parts = array(
		                'week',
		                'day',
		                'hour',
		                'minute',
		                'second',
		            );

		            $reminderDuration = array(
		            	'token' => null
		            	);
		            foreach($parts as $part) {
		                $matches[$part] = isset($matches[$part])&&$matches[$part]?(int)$matches[$part]:0;
		                $reminderDuration[$part] = $matches[$part];
		                if($matches[$part] && !$reminderDuration['token']){
		                	$reminderDuration['token'] = $part;
		                }
		            }
		            if($reminderDuration['token'] == null){
		            	$reminderDuration['token'] = $parts[0];
		            }

					$reminderDuration['params'] = array(
							'id'	=> (int)$invert.(int)($related == 'END'),
							'related'=> $related?$related:'START',
							'invert'=>	$invert
							);
				} else {
					$reminderDate = null;
					$reminderDuration = null;
				}

				return array(
					'type' 		=> $reminderType,
					'action'	=> $reminderAction,
					'date'		=> $reminderDate,
					'duration'	=> $reminderDuration
					);

			} catch(\Exception $e) {
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
				return null;
			}
		} else {
			return null;
		}
	}

	private function parseCategories($categories) {
		if ($categories){
			return $categories->getParts();
		} else {
			return array();
		}
	}

	private function parseDate($date) {
		if ($date) {
			try {
				$user_timezone = \OC_Calendar_App::getTimezone();
				$date = new \DateTime($date);
				$date->setTimezone(new \DateTimeZone($user_timezone));
				return $date->format('Ymd\THis');
			} catch(\Exception $e) {
				\OCP\Util::writeLog('tasks', $e->getMessage(), \OCP\Util::ERROR);
				return null;
			}
		} else {
			return null;
		}
	}

	private function parsePercentCompleted($percentComplete) {
		if($percentComplete){
			return $percentComplete->getValue();
		} else {
			return '0';
		}
	}

	private function parseComments($comments){
		$comments_parsed = array();
		if($comments){
			foreach($comments as $com) {
				// parse time
				$time = $this->parseDate($com['X-OC-DATE-TIME']);
				// parse comment ID
				$comID = $com['X-OC-ID'];
				// parse user ID
				$userID = $com['X-OC-USERID'];

				if ($this->isCommentValid($time, $comID, $userID)) {
					$userID = (string) $userID->getValue();
					$user = \OC::$server->getUserManager()->get($userID);
					if ($user) {
						$comments_parsed[] = array(
							'id' 		=> $comID->getValue(),
							'userID' 	=> $userID,
							'name' 		=> $user->getDisplayName(),
							'comment' 	=> $com->getValue(),
							'time' 		=> $time,
						);
					}
				}
			}
		}
		return $comments_parsed;
	}

	private function isCommentValid($time, $comID, $userID) {
		return ($time && $comID && $userID);
	}
}
