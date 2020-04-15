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
 * Upgrade code for wechat miniprogram message processor
 *
 * @package    message_wechatmp
 * @copyright  2020 Zheng Zhibin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for the wechat miniprogram message processor
 *
 * @param int $oldversion The version that we are upgrading from
 */
function xmldb_message_wechatmp_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2020040320) {

        // Define table message_wechatmp_user to be created.
        $table = new xmldb_table('message_wechatmp_user');

        // Adding fields to table message_wechatmp_user.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('openid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('remainingnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table message_wechatmp_user.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('foreign key', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for message_wechatmp_user.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Wechatmp savepoint reached.
        upgrade_plugin_savepoint(true, 2020040320, 'message', 'wechatmp');
    }


    return true;
}
