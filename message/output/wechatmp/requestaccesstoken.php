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
    $msg = get_string('accesstokenretrievedsuccessfully', 'message_wechatmp');
    $msg .= ': |';
    $msg .= $token;
} else {
    $msg = get_string('errorretrievingkey', 'message_airnotifier');
}

$msg .= $OUTPUT->continue_button($returl);

echo $OUTPUT->header();
echo $OUTPUT->box($msg, 'generalbox ');
echo $OUTPUT->footer();
