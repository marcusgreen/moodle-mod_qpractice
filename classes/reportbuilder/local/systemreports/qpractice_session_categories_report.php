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

        $join = "LEFT JOIN {qpractice_session_cats} scats ON {qpractice_sessions}.id = scats.sessionid";

        $sessioncategories->add_join($join);

        $alias = $sessioncategories->get_table_alias('qpractice_sessions');

        $this->add_columns();
        $this->set_main_table('qpractice_session', $alias);
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
            'session_categories:practicedate',
        ];
        $this->add_columns_from_entities($columns);
    }
}
