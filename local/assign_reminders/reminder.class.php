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

defined('MOODLE_INTERNAL') || die;

/**
 * Class for reminder object.
 */
class assign_reminder {

    /**
     * @var int number of days in advance to actual event.
     */
    protected $aheaddays;

    /**
     * @var int indicates immediate sending of message as a notification.
     */
    protected $notification = 1;

    /**
     * @var object event object correspond to this reminder.
     */
    protected $event;

    /**
     *
     * @var object cahced reminder message object. This will be reused for other users too.
     */
    public $eventobject;

    /**
     * Course reference.
     *
     * @var object
     */
    protected $course;

    /**
     * @var object
     */
    private $coursemodule;
    /**
     * @var object
     */
    private $cm;

    /**
     * Creates a new reminder instance with event and no of days ahead value.
     *
     * @param object $event calendar event.
     * @param object $course course instance.
     * @param object $cm coursemodulecontext instance.
     * @param object $coursemodule course module.
     * @param integer $aheaddays number of days ahead.
     */
    public function __construct($event, $course, $cm, $coursemodule, $aheaddays = 1) {
        $this->event = $event;
        $this->course = $course;
        $this->cm = $cm;
        $this->coursemodule = $coursemodule;
        $this->aheaddays = $aheaddays;
    }

    /**
     * Clean up this instance.
     */
    public function cleanup() {
        if (isset($this->eventobject)) {
            unset($this->eventobject);
        }
    }

    /**
     * Filter out users who still does not have completed this activity.
     *
     * @param array $users user array to check.
     * @return array array of filtered users.
     */
    public function filter_authorized_users($users) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        $filteredusers = array();
        foreach ($users as $auser) {
            $cansubmit = has_capability('mod/assign:submit', $this->cm, $auser);
            if (!$cansubmit) {
                continue;
            }
            $status = assign_get_completion_state($this->course, $this->coursemodule, $auser->id, false);
            if (!$status) {
                $filteredusers[] = $auser;
            }
        }
        return $filteredusers;
    }

    /**
     * This function setup the corresponding message provider for each
     * reminder type. It would be called everytime at the constructor.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'assignment_reminders';
    }

    /**
     * Creates the final reminder message object from given information.
     *
     * @param object $admin impersonated user for sending messages. This
     *          name will display in 'from' field in every reminder message.
     *
     * @return object a message object which will be sent to the messaging API
     */
    public function create_reminder_message_object($admin=null) {
        global $CFG, $DB;

        if ($admin == null) {
            $admin = get_admin();
        }
        $coursename = $DB->get_field('course', 'fullname', array('id' => $this->event->courseid));
        $time = $this->format_datetime($this->event->timestart, $admin);
        $customdata = array(
            'coursename' => $coursename,
            'assignname' => substr($this->event->name, 0, strpos($this->event->name, ' ')),
            'time'       => $time,
            'eventtype'  => 'due'
        );
        $eventdata = new \core\message\message();

        $eventdata->component           = 'local_assign_reminders';
        $eventdata->name                = $this->get_message_provider();
        $eventdata->userfrom            = $admin;
        $eventdata->subject             = '';
        $eventdata->fullmessage         = '作业提交提醒';
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $this->event->name;
        $eventdata->smallmessage        = 'assign_due';
        $eventdata->notification        = $this->notification;

        $eventdata->modulename          = 'assign';
        $eventdata->customdata          = $customdata;

        // Save created object with reminder object.
        $this->eventobject = $eventdata;

        return $eventdata;
    }

    /**
     * Assign user which the reminder message is sent to
     *
     * @param object $user user object (id field must contain)
     * @param boolean $refreshcontent indicates whether content of message should
     * be refresh based on given user
     * @param object $fromuser sending user.
     * @return event object notification instance.
     */
    public function set_sendto_user($user, $fromuser=null) {
        if (!isset($this->eventobject) || empty($this->eventobject)) {
            $this->create_reminder_message_object();
        }

        $this->eventobject->userto = $user;
        if (isset($fromuser)) {
            $this->eventobject->userfrom = $fromuser;
        }

        return $this->eventobject;
    }
    
    /**
     * Returns the sending notification instance from user to user.
     *
     * @param object $fromuser from user.
     * @param object $touser to user.
     * @return object notification instance.
     */
    public function get_sending_event($fromuser, $touser) {
        return $this->set_sendto_user($touser, $fromuser);
    }

    /**
     * Returns the sending notification instance from user to user with change type.
     *
     * @param string $changetype change type.
     * @param object $admin admin user.
     * @param object $touser to user.
     * @return object notification instance.
     */
    public function get_updating_event_message($changetype, $admin=null, $touser=null) {
        global $DB;

        $fromuser = $admin;
        if ($fromuser == null) {
            $fromuser = get_admin();
        }
        $coursename = $DB->get_field('course', 'fullname', array('id' => $this->event->courseid));
        $customdata = array(
            'coursename' => $coursename,
            'assignname' => $this->event->name,
            'eventtype'  => 'due'
        );

        $eventdata = new \core\message\message();

        $eventdata->component           = 'local_reminders';
        $eventdata->name                = $this->get_message_provider();
        $eventdata->userfrom            = $fromuser;
        $eventdata->userto              = $touser;
        $eventdata->subject             = '';
        $eventdata->fullmessage         = '作业提交提醒';
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $this->event->name;
        $eventdata->smallmessage        = 'assign_due';
        $eventdata->notification        = $this->notification;

        $eventdata->modulename          = 'assign';
        $eventdata->customdata          = $customdata;

        return $eventdata;
    }

    /**
     * This function would return time formats relevent for the given user.
     * Sometimes a user might have changed time display format in his/her preferences.
     *
     * @param object $user user instance to get specific time format.
     * @return string date time format for user.
     */
    function get_correct_timeformat_user($user) {
        static $langtimeformat = null;
        if ($langtimeformat === null) {
            $langtimeformat = get_string('strftimetime', 'langconfig');
        }

        // We get user time formattings... if such exist, will return non-empty value.
        $utimeformat = get_user_preferences('calendar_timeformat', '', $user);
        if (empty($utimeformat)) {
            $utimeformat = get_config(null, 'calendar_site_timeformat');
        }
        return empty($utimeformat) ? $langtimeformat : $utimeformat;
    }

    /**
     * Formats given date and time based on given user's timezone.
     *
     * @param number $datetime epoch time.
     * @param object $user user to format for.
     * @return string formatted date time according to give user.
     */
    protected function format_datetime($datetime, $user) {
        $tzone = 99;
        if (isset($user) && !empty($user)) {
            $tzone = core_date::get_user_timezone($user);
        }

        $daytimeformat = get_string('strftimedaydate', 'langconfig');
        $utimeformat = $this->get_correct_timeformat_user($user);
        
        return userdate($datetime, $daytimeformat, $tzone).
            ' '.userdate($datetime, $utimeformat, $tzone);
    }
}
