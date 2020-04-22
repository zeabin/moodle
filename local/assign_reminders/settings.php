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
 * This file contains all reminder plugin settings.
 *
 * @package    local_reminders
 * @author     Isuru Weerarathna <uisurumadushanka89@gmail.com>
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    require_once($CFG->dirroot.'/local/assign_reminders/lib.php');

    $settings = new admin_settingpage('local_assign_reminders', get_string('admintreelabel', 'local_assign_reminders'));
    $ADMIN->add('localplugins', $settings);

    // Adds a checkbox to enable/disable sending reminders.
    $settings->add(new admin_setting_configcheckbox('local_assign_reminders_enable',
            get_string('enabled', 'local_assign_reminders'),
            get_string('enableddescription', 'local_assign_reminders'), 1));

    $choices = array(ASSIGN_REMINDERS_SEND_ALL_EVENTS => get_string('filtereventssendall', 'local_assign_reminders'),
                     ASSIGN_REMINDERS_SEND_ONLY_VISIBLE => get_string('filtereventsonlyvisible', 'local_assign_reminders'));

    $settings->add(new admin_setting_configselect('local_assign_reminders_filterevents',
            get_string('filterevents', 'local_assign_reminders'),
            get_string('filtereventsdescription', 'local_assign_reminders'),
            ASSIGN_REMINDERS_SEND_ONLY_VISIBLE, $choices));

    $daysarray = array('days7' => ' '.get_string('days7', 'local_assign_reminders'),
            'days3' => ' '.get_string('days3', 'local_assign_reminders'),
            'days1' => ' '.get_string('days1', 'local_assign_reminders'));
    $defaultdue = array('days7' => 0, 'days3' => 1, 'days1' => 0);

    $settings->add(new admin_setting_configmulticheckbox2('local_assign_reminders_rdays',
            get_string('reminderdaysahead', 'local_assign_reminders'),
            get_string('explaindueheading', 'local_assign_reminders'),
            $defaultdue, $daysarray));
}