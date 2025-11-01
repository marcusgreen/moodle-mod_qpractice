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
use mod_qpractice\reportbuilder\local\entities\session_categories;

/**
 *
 * @package    mod_qpractice
 * @copyright  2024 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qpractice_session_categories_report extends system_report {
    /**
     * Initialise report, we need to set the main table, load our entities and set columns/filters
     */
    protected function initialise(): void {
        $sessioncategories = new session_categories();
        $this->add_entity($sessioncategories);

        // Get the session ID from the URL parameters.
        $sessionid = optional_param('sessionid', 0, PARAM_INT);

        // Add joins to connect sessions with categories.
        $this->add_join("JOIN {qpractice_session_cats} sessioncats ON {qpractice_session}.id = sessioncats.session");
        $this->add_join("JOIN {question_categories} qcats ON sessioncats.category = qcats.id");

        // Add condition to filter by specific session.
        if ($sessionid) {
            $paramname = \core_reportbuilder\local\helpers\database::generate_param_name();
            $this->add_base_condition_sql("{qpractice_session}.id = :$paramname", [$paramname => $sessionid]);
        }

        $this->add_columns();
        $this->set_main_table('qpractice_session', 'qpractice_session');
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
            'session_categories:categoryname',
            'session_categories:questioncount',
        ];
        $this->add_columns_from_entities($columns);
    }
}
