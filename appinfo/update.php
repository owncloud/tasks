<?php

$installedVersionTasks=OCP\Config::getAppValue('tasks', 'installed_version');
$installedVersionTasksEnhanced=OCP\Config::getAppValue('tasks_enhanced', 'installed_version');

\OC::$CLASSPATH['OC_Calendar_Calendar'] = 'calendar/lib/calendar.php';
\OC::$CLASSPATH['OC_Calendar_Object'] = 'calendar/lib/object.php';

if ( version_compare($installedVersionTasksEnhanced, '0.4.1', '<=') && version_compare($installedVersionTasks, '0.5.0', '<=') ) {
	try {

		$stmt = \OCP\DB::prepare( 'SELECT * FROM `*PREFIX*clndr_calendars`');
		$result = $stmt->execute();

		$calendars = array();
		while( $row = $result->fetchRow()) {
			$calendars[] = $row;
		}

		foreach( $calendars as $calendar ) {
			$calendar_entries = \OC_Calendar_Object::all($calendar['id']);

			foreach( $calendar_entries as $task ) {
				if($task['objecttype']!='VTODO') {
					continue;
				}
				$vcalendar = \Sabre\VObject\Reader::read($task['calendardata']);
				$vtodo = $vcalendar->VTODO;
				$children = $vtodo->children;
				$taskId = $task['id'];

				$comments = $vtodo->COMMENT;
				if($comments){
					foreach($comments as $com) {

						$idx = 0;
						foreach ($children as $i => &$property) {
							if ( $property['ID'] && $com['ID']) {
								if ( $property->name == 'COMMENT' && $property['ID']->getValue() == (int)$com['ID']->getValue() ) {
									unset($vtodo->children[$idx]);
								}
							}
							$idx += 1;
						}
						if ( $com['ID'] && $com['USERID'] && $com['DATE-TIME'] ) {
							$vtodo->add('COMMENT',$com->getValue(),
								array(
									'X-OC-ID' => (int)$com['ID']->getValue(),
									'X-OC-USERID' => $com['USERID']->getValue(),	
									'X-OC-DATE-TIME' => $com['DATE-TIME']->getValue()
									)
								);
						}
						OCP\Util::emitHook('OC_Calendar', 'editEvent', $taskId);
					}
					$data = $vcalendar->serialize();
					$oldobject = \OC_Calendar_Object::find($taskId);
					// $this->tasksMapper->findVTODOById($taskID);

					$object = \Sabre\VObject\Reader::read($data);

					$type = 'VTODO';
					$startdate = null;
					$enddate = null;
					$summary = '';
					$repeating = 0;
					$uid = null;

					foreach($object->children as $property) {
						if($property->name == 'VTODO') {
							foreach($property->children as &$element) {
								if($element->name == 'SUMMARY') {
									$summary = $element->getValue();
								}
								elseif($element->name == 'UID') {
									$uid = $element->getValue();
								}
							};
							break;
						}
					}

					$stmt = OCP\DB::prepare( 'UPDATE `*PREFIX*clndr_objects` SET `objecttype`=?,`startdate`=?,`enddate`=?,`repeating`=?,`summary`=?,`calendardata`=?,`lastmodified`= ? WHERE `id` = ?' );
					$stmt->execute(array($type,$startdate,$enddate,$repeating,$summary,$data,time(),$taskId));

					\OC_Calendar_Calendar::touchCalendar($oldobject['calendarid']);
				}
			}
		}
	} catch (\Exception $e){

	}
}
