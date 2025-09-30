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
 * Global configuration for new instances of qpractice
 *
 * @package    mod_qpractice
 * @copyright  2016 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/questionlib.php');

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'qpractice/questionbehaviours',
        'Question Behaviours',
        ''
    ));
    $settings->add(new admin_setting_configcheckbox(
        'qpractice/systemcontext',
        get_string('systemcontext', 'qpractice'),
        get_string('systemcontext_text', 'qpractice'),
        0
    ));

    $behaviours = question_engine::get_behaviour_options('');
    $enabled = false;
    foreach ($behaviours as $key => $langstring) {
        if ($key == 'interactive') {
            $enabled = true;
        }
        if (!in_array('correctness', question_engine::get_behaviour_unused_display_options($key))) {
            $settings->add(
                new admin_setting_configcheckbox('mod_qpractice/' . $key, $key, $langstring, $enabled)
            );
            $enabled = false;
        }
    }
}
