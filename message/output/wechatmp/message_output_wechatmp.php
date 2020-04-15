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
 * Wechat Miniprogram message processor to send messages to the APNS provider: airnotfier. (https://github.com/dongsheng/airnotifier)
 *
 * @package    message_wechatmp
 * @category   external
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.8
 */


require_once($CFG->dirroot . '/message/output/lib.php');

define('WECHAT_SERVERURL', 'https://api.weixin.qq.com/cgi-bin/token');

/**
 * Message processor class
 *
 * @package   message_wechatmp
 * @copyright 2020 Zheng Zhibin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_output_wechatmp extends message_output {

    /**
     * Processes the message and sends a notification to Wechat
     *
     * @param stdClass $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     * @return true if ok, false if error
     */
    public function send_message($eventdata) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/filelib.php');

        if (!empty($CFG->noemailever)) {
            // Hidden setting for development sites, set in config.php if needed.
            debugging('$CFG->noemailever active, no wechatmp message sent.', DEBUG_MINIMAL);
            return true;
        }

        // Skip any messaging suspended and deleted users.
        if ($eventdata->userto->auth === 'nologin' or
            $eventdata->userto->suspended or
            $eventdata->userto->deleted) {
            return true;
        }
        
        
        if (empty($eventdata->modulename) || empty($eventdata->eventtype) ||
                $eventdata->modulename != 'assign' || $eventdata->eventtype != 'due') { // Only assignment notification can be sent.
            return false;
        }

        $manager = new message_wechatmp_manager();
        if ($openid = $manager->get_user_openid()) {
            
            $assignname = $eventdata->name;
            $coursename = $DB->get_field('course', 'fullname', array('id' => $eventdata->courseid));
            return self::send_due_assignment_message($coursename, $assignname, $openid);
        }

        // Cannot find the openid for the user.
        return false;
    }

    /**
     * Creates necessary fields in the messaging config form.
     *
     * @param array $preferences An array of user preferences
     */
    public function config_form($preferences) {
        return null;
    }

    /**
     * Parses the submitted form data and saves it into preferences array.
     *
     * @param stdClass $form preferences form class
     * @param array $preferences preferences array
     */
    public function process_form($form, &$preferences) {
        return true;
    }

    /**
     * Loads the config data from database to put on the form during initial form display
     *
     * @param array $preferences preferences array
     * @param int $userid the user id
     */
    public function load_data(&$preferences, $userid) {
        return true;
    }

    /**
     * Tests whether the wechat miniprogram settings have been configured
     * @return boolean true if wechat miniprogram is configured
     */
    public function is_system_configured() {
        $manager = new message_wechatmp_manager();
        return $manager->is_system_configured();
    }

    private function send_due_assignment_message($coursename, $assignname, $useropenid) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $cache = cache::make('message_wechatmp', 'access_token');
        if ($token = $cache->get('access_token')) {
            $serverurl = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $token;
            $curl = new curl();
            $params = array(
                'touser' => $useropenid,
                'template_id' => $CFG->dueassigntemplateid,
                'data' => array(
                    'thing7' => array(
                        'value' => "$coursename"
                    ),
                    'thing9' => array(
                        'value' => "$assignname"
                    )
                )
            );
            // JSON POST raw body request.
            $resp = $curl->post($serverurl, json_encode($params));

            if ($key = json_decode($resp, true)) {
                if (!empty($key['errCode'])) {
                    if (!$key['errCode']) { // errCode = 0, success
                        return true;
                    }
                }
            }
        }

        return false; // fail
    }
}

