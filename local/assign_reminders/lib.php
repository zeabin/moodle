<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library function for reminders cron function.
 * @package   local_assign_reminders
 * @copyright 2020 Zheng Zhibin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

require_once($CFG->dirroot . '/local/assign_reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/assign_reminders/locallib.php');

define('ASSIGN_REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS', 1);

define('ASSIGN_REMINDERS_7DAYSBEFORE_INSECONDS', 7 * 24 * 3600);
define('ASSIGN_REMINDERS_3DAYSBEFORE_INSECONDS', 3 * 24 * 3600);
define('ASSIGN_REMINDERS_1DAYBEFORE_INSECONDS', 24 * 3600);

define('ASSIGN_REMINDERS_SEND_ALL_EVENTS', 50);
define('ASSIGN_REMINDERS_SEND_ONLY_VISIBLE', 51);

/**
 * Function to be run periodically according to the moodle cron
 * Finds all events due for a reminder and send them out to the users.
 *
 */
function local_assign_reminders_cron() {
    global $CFG, $DB;

    if (!isset($CFG->local_assign_reminders_enable) || !$CFG->local_assign_reminders_enable) {
        mtrace("   [Local Reminder] This cron cycle will be skipped, because plugin is not enabled!");
        return;
    }

    $currtime = time();

    $aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);

    $timewindowstart = $currtime;

    // We need only last record only, so we limit the returning number of rows at most by one.
    $logrows = $DB->get_records("local_assign_reminders", array(), 'time DESC', '*', 0, 1);

    $timewindowstart = $currtime;
    if (!$logrows) {  // This is the first cron cycle, after plugin is just installed.
        mtrace("   [Local Reminder] This is the first cron cycle");
        $timewindowstart = $timewindowstart - ASSIGN_REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // Info field includes that starting time of last cron cycle.
        $firstrecord = current($logrows);
        $timewindowstart = $firstrecord->time + 1;
    }

    // End of the time window will be set as current.
    $timewindowend = $currtime;

    // Now lets filter appropiate events to send reminders.
    $secondsaheads = array(ASSIGN_REMINDERS_7DAYSBEFORE_INSECONDS,
        ASSIGN_REMINDERS_3DAYSBEFORE_INSECONDS,
        ASSIGN_REMINDERS_1DAYBEFORE_INSECONDS);

    // Only send reminders for assignment submission.
    $whereclause = '(modulename = \'assign\') AND (eventtype = \'due\') AND ';
    $whereclause .= '(timestart > '.$timewindowend.') AND (';
    $flagor = false;
    foreach($secondsaheads as $sahead) {
        if ($flagor) {
            $whereclause .= ' OR ';
        }
        $whereclause .= '(timestart - '.$sahead.' >= '.$timewindowstart.' AND '.
                        'timestart - '.$sahead.' <= '.$timewindowend.')';
        $flagor = true;
    }
    $whereclause .= ')';

    if (isset($CFG->local_assign_reminders_filterevents)) {
        if ($CFG->local_assign_reminders_filterevents == ASSIGN_REMINDERS_SEND_ONLY_VISIBLE) {
            $whereclause .= ' AND visible = 1';
        }
    }

    mtrace("   [Local Reminder] Time window: ".userdate($timewindowstart)." to ".userdate($timewindowend));

    $upcomingevents = $DB->get_records_select('event', $whereclause);
    if (!$upcomingevents) {
        mtrace("   [Local Reminder] No upcoming events. Aborting...");

        add_flag_record($timewindowend, 'no_events');
        return;
    }

    mtrace("   [Local Reminder] Found ".count($upcomingevents)." upcoming events. Continuing...");

    $allnotificationfailed = true;
    foreach ($upcomingevents as $event) {
        $event = new calendar_event($event);

        $aheadday = 0;

        if ($event->timestart - ASSIGN_REMINDERS_1DAYBEFORE_INSECONDS >= $timewindowstart &&
                $event->timestart - ASSIGN_REMINDERS_1DAYBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 1;
        } else if ($event->timestart - ASSIGN_REMINDERS_3DAYSBEFORE_INSECONDS >= $timewindowstart &&
                $event->timestart - ASSIGN_REMINDERS_3DAYSBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 3;
        } else if ($event->timestart - ASSIGN_REMINDERS_7DAYSBEFORE_INSECONDS >= $timewindowstart &&
                $event->timestart - ASSIGN_REMINDERS_7DAYSBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 7;
        }

        mtrace("   [Local Reminder] Processing event#$event->id [Type: $event->eventtype, inaheadof=$aheadday days]...");

        $optionstr = 'local_assign_reminders_rdays';
        if (!isset($CFG->$optionstr)) {
            mtrace("   [Local Reminder] Couldn't find option for assignment $event->id");
            continue;
        }

        $options = $CFG->$optionstr;

        if (empty($options)) {
            mtrace("   [Local Reminder] No configuration for assignment, [event#$event->id is ignored!]...");
            continue;
        }
        
        $reminderref = null;
        mtrace("   [Local Reminder] Finding out users for assignment#".$event->id."...");

        $assignroleids = array(1 => 5);
        try {
            $reminderref = process_event($event, $aheadday, $assignroleids);
        } catch (Exception $ex) {
            mtrace("  [Local Reminder - ERROR] Error occured when initializing ".
                    "for event#[$event->id] ".$ex->getMessage());
            mtrace("  [Local Reminder - ERROR] ".$ex->getTraceAsString());
            continue;
        }

        if ($reminderref == null) {
            mtrace("  [Local Reminder] Reminder is not available for the event $event->id [type: $event->eventtype]");
            continue;
        }

        $usize = $reminderref->get_total_users_to_send();
        if ($usize == 0) {
            mtrace("  [Local Reminder] No users found to send reminder for the event#$event->id");
            continue;
        }

        mtrace("  [Local Reminder] Starting sending reminders for $event->id [type: $event->eventtype]");
        $failedcount = 0;

        $fromuser = get_admin();
        $sendusers = $reminderref->get_sending_users();

        foreach ($sendusers as $touser) {
            $eventdata = $reminderref->get_event_to_send($fromuser, $touser);

            try {
                $sendresult = message_send($eventdata);
                mtrace('  [LOCAL_REMINDERS] Mail Result: '.(int)$sendresult);

                if (!$sendresult) {
                    throw new coding_exception("Could not send out message for event#$event->id to user $eventdata->userto");
                } 
            } catch (moodle_exception $mex) {
                $failedcount++;
                mtrace('Error: local/assign_reminders/lib.php local_assign_reminders_cron(): '.$mex->getMessage());
            }
        }

        if ($failedcount > 0) {
            mtrace("  [Local Reminder] Failed to send $failedcount reminders to users for event#$event->id");
        } else {
            mtrace("  [Local Reminder] All reminders was sent successfully for event#$event->id !");
        }

        if ($usize != $failedcount) {
            $allnotificationfailed = false;
        }
        $reminderref->cleanup();
    }
    

    if (!$allnotificationfailed) {
        add_flag_record($timewindowend, 'sent');
        mtrace('  [Local Reminder] Marked this reminder execution as success.');
    } else {
        mtrace('  [Local Reminder] Failed to send any email to any user! Will retry again next time.');
    }
}

/**
 * Adds a database record to local_reminders table, to mark
 * that the current cron cycle is over. Then we flag the time
 * of end of the cron time window, so that no reminders sent
 * twice.
 *
 * @param int $timewindowend cron window time end.
 * @param string $crontype type of reminders cron.
 * @return void nothing.
 */
function add_flag_record($timewindowend, $crontype = '') {
    global $DB;

    $newrecord = new stdClass();
    $newrecord->time = $timewindowend;
    $newrecord->type = $crontype;
    $DB->insert_record("local_assign_reminders", $newrecord);
}