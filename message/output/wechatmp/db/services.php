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
 * Wechat miniprogram external functions and service definitions.
 *
 * @package    message_wechatmp
 * @category   webservice
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(
    'message_wechatmp_is_system_configured' => array(
        'classname'   => 'message_wechatmp_external',
        'methodname'  => 'is_system_configured',
        'classpath'   => 'message/output/wechatmp/externallib.php',
        'description' => 'Check whether the wechatmp settings have been configured',
        'type'        => 'read',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'message_wechatmp_bind_wechat_account' => array(
        'classname'   => 'message_wechatmp_external',
        'methodname'  => 'bind_wechat_account',
        'classpath'   => 'message/output/wechatmp/externallib.php',
        'description' => 'Bind a user to a wechat account',
        'type'        => 'write',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'message_wechatmp_unbind_wechat_account' => array(
        'classname'   => 'message_wechatmp_external',
        'methodname'  => 'unbind_wechat_account',
        'classpath'   => 'message/output/wechatmp/externallib.php',
        'description' => 'Unbind a user to a wechat account',
        'type'        => 'write',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'message_wechatmp_has_user_bound_wechat_account' => array(
        'classname'   => 'message_wechatmp_external',
        'methodname'  => 'has_user_bound_wechat_account',
        'classpath'   => 'message/output/wechatmp/externallib.php',
        'description' => 'Check whether the user has bount a wechat account',
        'type'        => 'read',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'message_wechatmp_subscribe' => array(
        'classname'   => 'message_wechatmp_external',
        'methodname'  => 'subscribe',
        'classpath'   => 'message/output/wechatmp/externallib.php',
        'description' => 'Subscribe a wechat miniprogram notification',
        'type'        => 'write',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
