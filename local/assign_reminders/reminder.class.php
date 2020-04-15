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
     * Creates a new reminder instance with event and no of days ahead value.
     *
     * @param object $event calendar event.
     * @param integer $aheaddays number of days ahead.
     */
    public function __construct($event, $aheaddays = 1) {
        $this->event = $event;
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
     * @param string $type type of request. Pre|Post for now.
     * @return array array of filtered users.
     */
    public function filter_authorized_users($users, $type=null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        $filteredusers = array();
        foreach ($users as $auser) {
            $cansubmit = has_capability('mod/assign:submit', $coursemodulecontext, $auser);
            if (!$cansubmit) {
                continue;
            }
            $status = assign_get_completion_state($course, $coursemodule, $auser->id, false);
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
        return 'assignment reminders';
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
        global $CFG;

        if ($admin == null) {
            $admin = get_admin();
        }

        $eventdata = new \core\message\message();

        $eventdata->component           = 'local_assign_reminders';
        $eventdata->name                = $this->get_message_provider();
        $eventdata->userfrom            = $admin;
        $eventdata->subject             = '';
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = '';
        $eventdata->smallmessage        = '';
        $eventdata->notification        = $this->notification;

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
        global $CFG;

        $fromuser = $admin;
        if ($fromuser == null) {
            $fromuser = get_admin();
        }

        $eventdata = new \core\message\message();

        $eventdata->component           = 'local_reminders';
        $eventdata->name                = $this->get_message_provider();
        $eventdata->userfrom            = $fromuser;
        $eventdata->userto              = $touser;
        $eventdata->subject             = '';
        $eventdata->fullmessage         = '';
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = '';
        $eventdata->smallmessage        = '';
        $eventdata->notification        = $this->notification;

        return $eventdata;
    }

}
