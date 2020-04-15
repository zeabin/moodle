<?php
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
 * Wechat Miniprogram manager class
 *
 * @package    message_wechatmp
 * @category   external
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.8
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Wechat Miniprogram helper manager class
 *
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_wechatmp_manager {

    /**
     * Return the user openid.
     *
     * @param int $userid if empty take the current user.
     * @return mixed openid of the user or false if the user hasn't bind a wechat account.
     */
    public function get_user_openid($userid = null) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        if ($openid = $DB->get_record('wechat_openid', array('userid' => $userid))) {
            return $openid;
        }
        return false;
    }

    /**
     * Request an access token to Wechat server
     *
     * @return mixed The access token or false in case of error
     */
    public function request_accesstoken() {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        // Sending the request access token request to Wechat.
            // 'appid' => 'wx5e34e980b0706455',
            // 'secret' => '244313e158dccc397b62d37243d75d90'
        $serverurl = 'https://api.weixin.qq.com/cgi-bin/token';
        $curl = new curl();
        $params = array(
            'grant_type' => 'client_credential',
            'appid' => $CFG->wechatappid,
            'secret' => $CFG->wechatappsecret,
            );
        $resp = $curl->get($serverurl, $params);

        if ($key = json_decode($resp, true)) {
            if (!empty($key['access_token'])) {
                return $key['access_token'];
            }
        }
        debugging("Unexpected response from the Wechat server: $resp");
        return false;
    }

    /**
     * Request a user's openid from code
     *
     * @return mixed The access token or false in case of error
     */
    public function request_openid($code) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        // Sending the request openid request to Wechat server.
        $serverurl = 'https://api.weixin.qq.com/sns/jscode2session';
        $curl = new curl();
        $params = array(
            'appid' => $CFG->wechatappid,
            'secret' => $CFG->wechatappsecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
            );
        $resp = $curl->get($serverurl, $params);

        if ($key = json_decode($resp, true)) {
            if (!empty($key['openid'])) {
                return $key['openid'];
            }
        }
        debugging("Unexpected response from the Wechat server: $resp");
        return false;
    }

    /**
     * Bind a user to a wechat account
     * @param $code
     */
    public function bind_wechat_account($code) {
        global $DB, $USER;

        if ($openid = self::request_openid($code)) {
            if ($user = $DB->get_record('message_wechatmp_user', array('openid' => $openid), '*')) {
                if ($user->openid != $openid) {
                    $DB->set_field('message_wechatmp_user', 'openid', $openid);
                }
            }

            $user = new stdClass();
            $user->userid = $USER->id;
            $user->openid = $openid;
            $DB->insert_record('message_wechatmp_user', $user);
            return true;
        }

        return false;
    }

    /**
     * Tests whether the wechatmp settings have been configured
     * @return boolean true if wechatmp is configured
     */
    public function is_system_configured() {
        global $CFG;

        return (!empty($CFG->wechatappid) && !empty($CFG->wechatappsecret));
    }
    
    /**
     * 
     */
    public function subscribe($openid) {
        global $DB, $USER;
        
        if (!$user = $DB->get_record('message_wechatmp_user', array('openid' => $openid), '*')) {
            $user = new stdClass();
            $user->userid = $USER->id;
            $user->openid = $openid;
            $user->remainingnumber = 1;
            $DB->insert_record('message_wechatmp_user', $user);
            return 1;
        }

        $number = $user->remainingnumber + 1;
        $DB->set_field('message_wechatmp_user', 'remainingnumber', $number, array('openid' => $openid));
        return $number;
    }
}
