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
        global $CFG;
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
        
        
        if (empty($eventdata->modulename) || empty($eventdata->smallmessage) || empty($eventdata->customdata) ||
                $eventdata->modulename != 'assign' || $eventdata->smallmessage != 'assign_due') { // Only assignment notification can be sent.
            return false;
        }

        $manager = new message_wechatmp_manager();
        if ($user = $manager->get_wechat_user($eventdata->userto->id)) {
            $customdata = json_decode($eventdata->customdata, true);
            $assignname = $customdata['assignname'];
            $coursename = $customdata['coursename'];

            return self::send_due_assignment_message($coursename, $assignname, $user);
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
     * Send assignment submittion notification to wechat server.
     * @param string $coursename course name
     * @param string $assignname assignment name
     * @param object wechat user object
     * @return boolean true if success, false otherwise
     */
    private function send_due_assignment_message($coursename, $assignname, $user) {
        global $CFG, $DB;

        // 一次性订阅消息需要检查用户订阅次数是否大于0
        if ($user->remainingnumber <= 0) {
            return false;
        }

        require_once($CFG->libdir . '/filelib.php');

        $cache = cache::make('message_wechatmp', 'access_token');
        if ($token = $cache->get('access_token')) {
            $serverurl = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $token;
            $curl = new curl();
            $params = array(
                'touser' => $user->openid,
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
                if (!$key['errcode']) { // errCode = 0, success
                    // 发送消息后订阅次数减1
                    $DB->set_field('message_wechatmp_user', 'remainingnumber', $user->remainingnumber - 1, array('userid' => $user->userid));
                    return true;
                }
            }
        }

        return false; // fail
    }
}

