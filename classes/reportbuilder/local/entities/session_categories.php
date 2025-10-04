<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_qpractice\reportbuilder\local\entities;

use lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\date;

/**
 * Reportbuilder entity sessions.
 *
 * @package     mod_qpractice
 * @copyright   2024 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_categories extends base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    /**
     * Returns the default database tables used by this entity.
     *
     * This method defines the database tables that are used by this entity. The
     * returned array should map the table aliases to the actual table names.
     *
     * @return string[] Array of table aliases and their corresponding table names
     */
    protected function get_default_tables(): array {
        return [
            'sessions' => 'qpractice_sessions',
            'session_categoriess' => 'qpractice_session_cats',
            'qpractice_categories' => 'qpractice_categories',
            'question_categories' => 'question_categories',
        ];
    }

    /**
     * Returns the default entity title.
     *
     * @return lang_string The default entity title as a lang_string object.
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('qpractice_session_categories', 'mod_qpractice');
    }

    /**
     * Initialise the entity with default columns
     *
     * @return self
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }
        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $column = (new column(
            'practicedate',
            new lang_string('practicedate', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('practicedate');
        $column->add_field('id');
        $columns[] = $column;

        return $columns;
    }
    /**
     * Allow selection on the firstname field.
     * A real report would have more filters
     *
     * @return array
     */
    public function get_all_filters(): array {
        $filters = [];
        return $filters;
    }
}
