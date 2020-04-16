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
 * Wechat notifier configuration page
 *
 * @package    message_wechatmp
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // The processor should be enabled by the same enable mobile setting.
    $settings->add(new admin_setting_configtext('wechatappid',
                    get_string('wechatappid', 'message_wechatmp'),
                    get_string('configappid', 'message_wechatmp'), '', PARAM_ALPHANUMEXT));
    $settings->add(new admin_setting_configtext('wechatappsecret',
                    get_string('wechatappsecret', 'message_wechatmp'),
                    get_string('configappsecret', 'message_wechatmp'), '', PARAM_ALPHANUMEXT));
    $settings->add(new admin_setting_configtext('dueassigntemplateid',
                    get_string('dueassigntemplateid', 'message_wechatmp'),
                    get_string('configdueassigntemplateid', 'message_wechatmp'), '', PARAM_ALPHANUMEXT));

    $url = new moodle_url('/message/output/wechatmp/requestaccesstoken.php', array('sesskey' => sesskey()));
    $link = html_writer::link($url, get_string('requestaccesstoken', 'message_wechatmp'));
    $settings->add(new admin_setting_heading('requestaccesstoken', '', $link));
}
