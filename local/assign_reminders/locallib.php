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
 * Local helper functions for reminders cron function.
 *
 * @package    local_reminders
 * @author     Isuru Weerarathna <uisurumadushanka89@gmail.com>
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/lib.php');

require_once($CFG->dirroot . '/local/assign_reminders/reminder.class.php');

/**
 * Process activity event and creates a reminder instance wrapping it.
 *
 * @param object $event calendar event.
 * @param int $aheadday number of days ahead.
 * @param array $activityroleids role ids for activities.
 * @param boolean $showtrace whether to print logs or not.
 * @return reminder_ref reminder reference instance.
 */
function process_event($event, $aheadday, $activityroleids=null, $showtrace=true) {
    global $DB, $PAGE;
    
    if (!isset($event->modulename) || empty($event->modulename) || trim($event->modulename) === '') {
        return null;
    }

    try {
        $courseandcm = get_course_and_cm_from_instance($event->instance, $event->modulename, $event->courseid);
    } catch (Exception $ex) {
        return null;
    }
    $course = $courseandcm[0];
    $cm = $courseandcm[1];

    if (!empty($course) && !empty($cm)) {
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);
        $sendusers = array();
        $reminder = new assign_reminder($event, $course, $context, $cm, $aheadday);

        if ($event->courseid <= 0 && $event->userid > 0) {
            // A user overridden activity.
            $showtrace && mtrace("  [Local Reminder] Event #".$event->id." is a user overridden ".$event->modulename." event.");
            $user = $DB->get_record('user', array('id' => $event->userid));
            $sendusers[] = $user;
        } else if ($event->courseid <= 0 && $event->groupid > 0) {
            // A group overridden activity.
            $showtrace && mtrace("  [Local Reminder] Event #".$event->id." is a group overridden ".$event->modulename." event.");
            $group = $DB->get_record('groups', array('id' => $event->groupid));
            $sendusers = get_users_in_group($group);
        } else {
            // Here 'ra.id field added to avoid printing debug message,
            // from get_role_users (has odd behaivior when called with an array for $roleid param'.
            $sendusers = assign_get_active_role_users($activityroleids, $context);

            foreach ($activityroleids as $key=>$value) {
                mtrace($key. ' '. $value);
            }
            // Filter user list,
            // see: https://docs.moodle.org/dev/Availability_API.
            $info = new \core_availability\info_module($cm);
            $sendusers = $info->filter_user_list($sendusers);
        }

        $filteredusers = $reminder->filter_authorized_users($sendusers);
        // $filteredusers = $sendusers;
        return new assign_reminder_ref($reminder, $filteredusers);
    }
    return null;
}

/**
 * Function to retrive module instace from corresponding module
 * table. This function is written because when sending reminders
 * it can restrict showing some fields in the message which are sensitive
 * to user. (Such as some descriptions are hidden until defined date)
 * Function is very similar to the function in datalib.php/get_coursemodule_from_instance,
 * but by below it returns all fields of the module.
 *
 * Eg: can get the quiz instace from quiz table, can get the new assignment
 * instace from assign table, etc.
 *
 * @param string $modulename name of module type, eg. resource, assignment,...
 * @param int $instance module instance number (id in resource, assignment etc. table)
 * @param int $courseid optional course id for extra validation
 * @param boolean $showtrace optional to print trace logs.
 * @return individual module instance (a quiz, a assignment, etc).
 *          If fails returns null
 */
function assign_fetch_module_instance($modulename, $instance, $courseid=0, $showtrace=true) {
    global $DB;

    $params = array('instance' => $instance, 'modulename' => $modulename);

    $courseselect = "";

    if ($courseid) {
        $courseselect = "AND cm.course = :courseid";
        $params['courseid'] = $courseid;
    }

    $sql = "SELECT m.*
              FROM {course_modules} cm
                   JOIN {modules} md ON md.id = cm.module
                   JOIN {".$modulename."} m ON m.id = cm.instance
             WHERE m.id = :instance AND md.name = :modulename
                   $courseselect";

    try {
        return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
    } catch (moodle_exception $mex) {
        $showtrace && mtrace('  [Local Reminder - ERROR] Failed to fetch module instance! '.$mex->getMessage());
        return null;
    }
}

/**
 * Returns array of users active (not suspended) in the provided contexts and
 * at the same time belongs to the given roles.
 *
 * @param array $activityroleids role ids
 * @param object $context context to search for users
 * @return array of user records
 */
function assign_get_active_role_users($activityroleids, $context) {
    return get_role_users($activityroleids, $context, true, 'ra.id, u.*',
                    null, false, '', '', '',
                    'ue.status = :userenrolstatus',
                    array('userenrolstatus' => ENROL_USER_ACTIVE));
}


/**
 * Reminder reference class.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_reminder_ref {
    /**
     * created reminder reference.
     *
     * @var local_assign_reminder
     */
    protected $reminder;
    /**
     * Array of users to send this reminder.
     *
     * @var array
     */
    protected $sendusers;

    /**
     * Creates new reminder reference.
     *
     * @param local_assign_reminder $reminder created reminder.
     * @param array $sendusers array of users.
     */
    public function __construct($reminder, $sendusers) {
        $this->reminder = $reminder;
        $this->sendusers = $sendusers;
    }

    /**
     * Returns total number of users eligible to send this reminder.
     *
     * @return int total number of users.
     */
    public function get_total_users_to_send() {
        return count($this->sendusers);
    }

    /**
     * Returns the ultimate notification event instance to send for given user.
     *
     * @param object $fromuser from user.
     * @param object $touser user to send.
     * @return object new notification instance.
     */
    public function get_event_to_send($fromuser, $touser) {
        return $this->reminder->get_sending_event($fromuser, $touser);
    }

    /**
     * Returns the notification event instance based on change type.
     *
     * @param string $changetype change type PRE|OVERDUE.
     * @param object $fromuser from user.
     * @param object $touser user to send.
     * @return object new notification instance.
     */
    public function get_updating_send_event($changetype, $fromuser, $touser) {
        return $this->reminder->get_updating_event_message($changetype, $fromuser, $touser);
    }

    /**
     * Returns eligible sending users as array.
     *
     * @return array users eligible to receive message.
     */
    public function get_sending_users() {
        return $this->sendusers;
    }

    /**
     * Cleanup the reminder memory.
     *
     * @return void nothing.
     */
    public function cleanup() {
        unset($this->sendusers);
        if (isset($this->reminder)) {
            $this->reminder->cleanup();
        }
    }
}