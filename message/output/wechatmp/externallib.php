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
 * External functions
 *
 * @package    message_wechatmp
 * @category   external
 * @copyright  2012 Jerome Mouneyrac <jerome@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */

defined('MOODLE_INTERNAL') || die;
define('WECHAT_USER_TABLE', 'message_wechatmp_user');

require_once("$CFG->libdir/externallib.php");

/**
 * External API for wechat miniprogram web services
 *
 * @package    message_wechatmp
 * @category   external
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.8
 */
class message_wechatmp_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @since Moodle 3.8
     */
    public static function is_system_configured_parameters() {
        return new external_function_parameters(
                array()
        );
    }

    /**
     * Tests whether the wechatmp settings have been configured
     *
     * @since Moodle 3.8
     */
    public static function is_system_configured() {
        global $DB;

        // First, check if the plugin is disabled.
        $processor = $DB->get_record('message_processors', array('name' => 'wechatmp'), '*', MUST_EXIST);
        if (!$processor->enabled) {
            return 0;
        }

        // Then, check if the plugin is completly configured.
        $manager = new message_wechatmp_manager();
        return (int) $manager->is_system_configured();
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     * @since Moodle 3.8
     */
    public static function is_system_configured_returns() {
        return new external_value( PARAM_INT, '0 if the system is not configured, 1 otherwise');
    }

    /**
     * Returns description of method parameters
     *
     * @since Moodle 3.8
     */
    public static function bind_wechat_account_parameters() {
        return new external_function_parameters(
            array(
                'code' => new external_value(PARAM_ALPHANUM, 'wechat auth code')
            )
        );
    }

     /**
     * Subscribe for wechat notification
     *
     * @since Moodle 3.8
     */
    public static function bind_wechat_account($code) {
        
        $params = self::validate_parameters(self::bind_wechat_account_parameters(),
                array('code' => $code));

        $manager = $manager = new message_wechatmp_manager();

        if (!$manager->is_system_configured()) {
            return 0;
        }
        return (int) $manager->bind_wechat_account($params['code']);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     * @since Moodle 3.8
     */
    public static function bind_wechat_account_returns() {
        return new external_value(PARAM_INT, '1 if success, 0 otherwise');
    }

    public static function unbind_wechat_account_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    public static function unbind_wechat_account() {
        global $DB, $USER;

        $userid = $USER->id;
        return (int) $DB->delete_records(WECHAT_USER_TABLE, array('userid' => $userid));
    }

    public static function unbind_wechat_account_returns() {
        return new external_value(PARAM_INT, '1 for success');
    }

    public static function has_user_bound_wechat_account_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    /**
     * Check whether the user has bount a wechat account.
     */
    public static function has_user_bound_wechat_account() {
        global $DB, $USER;

        $userid = $USER->id;
        return $DB->count_records(WECHAT_USER_TABLE, array('userid' => $userid)) == 1;
    }

    public static function has_user_bound_wechat_account_returns() {
        return new external_value(PARAM_INT, '1 if user has bound a wechat account, 0 otherwise');
    }

    public static function subscribe_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    public static function subscribe() {
        global $DB, $USER;

        $userid = $USER->id;
        if ($DB->count_records(WECHAT_USER_TABLE, array('userid' => $userid)) == 1) {
            $cur_num = $DB->get_field(WECHAT_USER_TABLE, 'remainingnumber', array('userid' => $userid));
            $DB->set_field(WECHAT_USER_TABLE, 'remainingnumber', $cur_num + 1, array('userid' => $userid));
            return $cur_num + 1;
        }
    }

    public static function subscribe_returns() {
        return new external_value(PARAM_INT, '1 if success, 0 otherwise');
    }

    public static function remaining_number_parameters() {
        return new external_function_parameters(
            array()
        );
    }

    public static function remaining_number() {
        global $DB, $USER;

        $userid = $USER->id;
        if ($DB->count_records(WECHAT_USER_TABLE, array('userid' => $userid)) == 1) {
            return $DB->get_field(WECHAT_USER_TABLE, 'remainingnumber', array('userid' => $userid));
        }

        return -1;
    }

    public static function remaining_number_returns() {
        return new external_value(PARAM_INT, 'Nonnegetive value of remaining number of notification can be set to wecchat, -1 if the user\'s wechat account not exist');
    }
}
