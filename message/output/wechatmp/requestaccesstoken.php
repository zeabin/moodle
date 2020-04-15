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
 * Request access token to Wechat server
 *
 * @package    message_wechatmp
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

$PAGE->set_url(new moodle_url('/message/output/wechatmp/requestaccesstoken.php'));
$PAGE->set_context(context_system::instance());

require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

// function send_due_assignment_message($coursename, $assignname, $useropenid, $token) {
//     global $CFG;

//     require_once($CFG->libdir . '/filelib.php');

//     $serverurl = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $token;
//     $curl = new curl();
//     $params = array(
//         'touser' => $useropenid,
//         'template_id' => $CFG->dueassigntemplateid,
//         'data' => array(
//             'thing7' => array(
//                 'value' => "$coursename"
//             ),
//             'thing9' => array(
//                 'value' => "$assignname"
//             )
//         )
//     );
//     // JSON POST raw body request.
//     $resp = $curl->post($serverurl, json_encode($params));

//     return json_decode($resp, true);
//     // if ($key = json_decode($resp, true)) {
//     //     if (!empty($key['errCode'])) {
//     //         if (!$key['errCode']) { // errCode = 0, success
//     //             return true;
//     //         }
//     //     }
//     // }

//     // return false; // fail
// }

// $strheading = get_string('requestaccesskey', 'message_airnotifier');
$strheading = get_string('requestaccesstoken', 'message_wechatmp');
$PAGE->navbar->add(get_string('administrationsite'));
$PAGE->navbar->add(get_string('plugins', 'admin'));
$PAGE->navbar->add(get_string('messageoutputs', 'message'));
$returl = new moodle_url('/admin/settings.php', array('section' => 'messagesettingwechatmp'));
$PAGE->navbar->add(get_string('pluginname', 'message_wechatmp'), $returl);
$PAGE->navbar->add($strheading);

$PAGE->set_heading($strheading);
$PAGE->set_title($strheading);

$msg = "";

$cache = cache::make('message_wechatmp', 'access_token');
if ($token = $cache->get('access_token')) {

// $manager = new message_wechatmp_manager();

// // if ($token = $manager->request_openid('061FmHn02hIbXV04kwk02mk7o02FmHns')) {
// if ($token = $manager->request_accesstoken()) {
    $msg = get_string('accesstokenretrievedsuccessfully', 'message_wechatmp');
    $msg .= ': |';
    $msg .= $token;

    // if ($ret = send_due_assignment_message('Moodle小程序', '作业1', 'oCk4g5ZtNMrhTcx3_vrkKNxK-pFU', $token)) {
    //     $msg .= '|         ';
    //     $msg .= $ret['errmsg'];
    // } else {
    //     $msg .= '\n 发送失败';
    // }

} else {
    $msg = get_string('errorretrievingkey', 'message_airnotifier');
}

$msg .= $OUTPUT->continue_button($returl);

echo $OUTPUT->header();
echo $OUTPUT->box($msg, 'generalbox ');
echo $OUTPUT->footer();
