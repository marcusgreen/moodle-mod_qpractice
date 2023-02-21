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

use core_reportbuilder\local\entities\base;
use lang_string;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\text;
////


use context_helper;
use html_writer;
use stdClass;

////

/**
 * Reportbuilder entity sessions.
 *
 * @package     local_sessions
 * @copyright   2023 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions extends base {

    protected function get_default_table_aliases(): array {
        return ['sessions' => 'sessions'];
    }

    protected function get_default_entity_title(): lang_string {
        return new lang_string('qpractice_sessions', 'mod_qpractice');
    }

    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
            $this->add_condition($filter);
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
        $column->set_is_sortable(true);
        $column->add_callback(
            static function (string $practicedate) :string {
                return userdate($practicedate);
            }
        );

        $columns[] = $column;

        $column = (new column(
            'marksobtained',
            new lang_string('marksobtained', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('marksobtained');
        $column->set_is_sortable(true);

        $columns[] = $column;
        return $columns;
    }
    /**
     * Allow selection on the firstname field.
     * A real report would have more filters
     *
     * @return array
     */
    public function get_all_filters() : array {
        return [];
        // $tablealias = $this->get_table_alias('demonames');
        // $filters[] = (new filter(
        //     text::class,
        //     'demo_firstname',
        //     new lang_string('demo_firstname', 'local_reportdemo'),
        //     $this->get_entity_name(),
        //     "{$tablealias}.demo_firstname"
        // ))->add_joins($this->get_joins());
        // return $filters;
    }

}