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
 * This file keeps track of upgrades to the qpractice module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_qpractice
 * @copyright  2019 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute qpractice upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_qpractice_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2019031900) {
        // Define field topcategory to be added to qpractice.
        $table = new xmldb_table('qpractice');
        $field = new xmldb_field('topcategory', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'intro');

        // Conditionally launch add field topcategory.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Qpractice savepoint reached.
        upgrade_mod_savepoint(true, 2019031900, 'qpractice');
    }

    if ($oldversion < 2026091400) {
        // Define field pathmode to be added to qpractice.
        $table = new xmldb_table('qpractice');
        $field = new xmldb_field('pathmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'behaviour');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table qpractice_category_path to be created.
        $table = new xmldb_table('qpractice_category_path');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('qpracticeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetpercent', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('onachieve', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'nextstage');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('qpracticeid', XMLDB_KEY_FOREIGN, ['qpracticeid'], 'qpractice', ['id']);
        $table->add_index('qpracticeid_sortorder', XMLDB_INDEX_NOTUNIQUE, ['qpracticeid', 'sortorder']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table qpractice_user_path_progress to be created.
        $table = new xmldb_table('qpractice_user_path_progress');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('qpracticeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currentsortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('stagecorrect', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('stagetotal', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('qpracticeid', XMLDB_KEY_FOREIGN, ['qpracticeid'], 'qpractice', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('qpracticeid_userid', XMLDB_INDEX_UNIQUE, ['qpracticeid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026091400, 'qpractice');
    }

    if ($oldversion < 2026091800) {
        // Define field allowwrongonly to be added to qpractice.
        $table = new xmldb_table('qpractice');
        $field = new xmldb_field('allowwrongonly', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'pathmode');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field wrongonly to be added to qpractice_session.
        $table = new xmldb_table('qpractice_session');
        $field = new xmldb_field('wrongonly', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'totalmarks');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026091800, 'qpractice');
    }

    if ($oldversion < 2026092600) {
        // Define field minquestions to be added to qpractice_category_path.
        $table = new xmldb_table('qpractice_category_path');
        $field = new xmldb_field('minquestions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'targetpercent');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field stageanswered to be added to qpractice_user_path_progress.
        $table = new xmldb_table('qpractice_user_path_progress');
        $field = new xmldb_field('stageanswered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'stagetotal');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026092600, 'qpractice');
    }

    return true;
}
