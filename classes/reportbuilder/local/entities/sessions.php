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
 * @copyright   2023 Marcus Green
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessions extends base {
    /**
     * base table for entity, could use alias i.e. []'s'=> 'sessions]
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return ['sessions'];
    }

    /**
     * Description used in user interface.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('qpractice_sessions', 'mod_qpractice');
    }

    /**
     * Initialises  report by adding all columns and filters.
     * It also adds a condition for each filter.
     *
     * @return base The current instance of the report builder.
     */
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
        $sessionsalias = $this->get_table_alias('sessions');
        $column = (new column(
            'id',
            new lang_string('sessionid', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field("{$sessionsalias}.id", 'sessionid');

         $columns[] = $column;

        $column = (new column(
            'practicedate',
            new lang_string('practicedate', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('practicedate');
        $column->add_field("{$sessionsalias}.id", 'sessionid');

        $column->set_is_sortable(true);
        $column->add_callback(
            static function (string $practicedate, \stdClass $sessionfields, $cmid, $sessionid): string {
                return userdate($practicedate) . ' ';
            }
        );

        $columns[] = $column;

        $column = (new column(
            'totalmarks',
            new lang_string('totalmarks', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('totalmarks');
        $column->set_is_sortable(true);

        $columns[] = $column;

        $column = (new column(
            'marksobtained',
            new lang_string('marksobtained', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('marksobtained');
        $column->add_field('totalmarks');
        $column->set_is_sortable(true);
        $column->add_callback(
            static function (string $marksobtained, \stdClass $r): string {
                return $marksobtained . '/' . $r->totalmarks;
            }
        );

        $columns[] = $column;

        $column = (new column(
            'totalnoofquestions',
            new lang_string('totalnoofquestions', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('totalnoofquestions');
        $column->set_is_sortable(true);

        $columns[] = $column;

        $column = (new column(
            'totalnoofquestionsright',
            new lang_string('totalnoofquestionsright', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        $column->add_field('totalnoofquestionsright');
        $column->set_is_sortable(true);

        $columns[] = $column;

        // Add a column with magnifying glass icon for viewing details
        $column = (new column(
            'viewdetails',
            new lang_string('viewdetails', 'mod_qpractice'),
            $this->get_entity_name()
        ));
        // Set column title and description
        $column->set_title(new lang_string('viewdetails', 'mod_qpractice'));
        $column->add_field("{$sessionsalias}.id", 'sessionid');
        // Add callback to generate the view details icon with link
        $column->add_callback(static function (string $value, \stdClass $row): string {
            $url = new \moodle_url('/mod/qpractice/summary.php', ['id' => $row->sessionid]);
            return \html_writer::link($url, \html_writer::empty_tag('i', ['class' => 'fa fa-search', 'aria-hidden' => 'true']));
        });

        $columns[] = $column;

        return $columns;
    }
    /**
     * Returns list of all available filters
     *
     * These are all the filters available to use in any report that uses this entity.
     *
     * @return filter[]
     */
    public function get_all_filters(): array {
        $tablealias = $this->get_table_alias('sessions');
        $filters[] = (new filter(
            number::class,
            'marksobtained',
            new lang_string('marksobtained', 'mod_qpractice'),
            $this->get_entity_name(),
            "{$tablealias}.marksobtained"
        ))->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'practicedate',
            new lang_string('practicedate', 'mod_qpractice'),
            $this->get_entity_name(),
            "{$tablealias}.practicedate"
        ))->add_joins($this->get_joins());
        return $filters;
    }
}
