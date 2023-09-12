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

namespace mod_qpractice\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;

use mod_qpractice\reportbuilder\local\entities\sessions;

/**
 *
 * @package    mod_qpractice
 * @copyright  2023 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpractice_sessions_report extends system_report {
    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $sessions = new sessions();
        $alias = $sessions->get_table_alias('sessions');
        $this->add_entity($sessions);
        $this->set_main_table('qpractice_session', $alias);
        $this->add_columns();
        $this->add_filters_from_entities(['sessions:marksobtained']);
        $this->add_filters_from_entities(['sessions:practicedate']);
        $this->set_downloadable(true, get_string('pluginname', 'mod_qpractice'));
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return true;
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_columns(): void {
        $columns = [
            'sessions:practicedate',
            'sessions:marksobtained',
            'sessions:totalnoofquestions',
            'sessions:totalnoofquestionsright'
        ];
        $this->add_columns_from_entities($columns);
    }
}