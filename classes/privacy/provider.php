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

namespace mod_qpractice\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper; // For export helpers.
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for mod_qpractice.
 *
 * Describes, exports and deletes user data stored by the qpractice activity
 * (e.g. per-user practice sessions and categories selected).
 *
 * @package     mod_qpractice
 * @copyright   2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return metadata about this plugin's stored user data.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        // Session table stores per-user practice attempts/sessions.
        $items->add_database_table('qpractice_session', [
            'qpracticeid' => 'privacy:metadata:qpractice_session:qpracticeid',
            'userid' => 'privacy:metadata:qpractice_session:userid',
            'categoryid' => 'privacy:metadata:qpractice_session:categoryid',
            'practicedate' => 'privacy:metadata:qpractice_session:practicedate',
            'totalnoofquestions' => 'privacy:metadata:qpractice_session:totalnoofquestions',
            'marksobtained' => 'privacy:metadata:qpractice_session:marksobtained',
            'totalmarks' => 'privacy:metadata:qpractice_session:totalmarks',
        ], 'privacy:metadata:qpractice_session');

        // If there is a per-activity selection of categories per user, record it here.
        if (self::table_exists('qpractice_session_categories')) {
            $items->add_database_table('qpractice_session_categories', [
                'sessionid' => 'privacy:metadata:qpractice_session_categories:sessionid',
                'categoryid' => 'privacy:metadata:qpractice_session_categories:categoryid',
            ], 'privacy:metadata:qpractice_session_categories');
        }

        return $items;
    }

    /**
     * Get list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        // Find all course modules where the user has sessions.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {qpractice} qp ON qp.id = cm.instance
                  JOIN {qpractice_session} s ON s.qpracticeid = qp.id
                 WHERE s.userid = :userid";
        $params = [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'qpractice',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Export data for the approved contexts for a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('qpractice', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Export all sessions for this user within this activity instance.
            $sessions = $DB->get_records('qpractice_session', [
                'qpracticeid' => $cm->instance,
                'userid' => $userid,
            ], 'practicedate ASC, id ASC');

            if (!$sessions) {
                continue;
            }

            $data = [];
            foreach ($sessions as $s) {
                $data[] = (object) [
                    'categoryid' => $s->categoryid,
                    'practicedate' => transform::datetime($s->practicedate),
                    'totalnoofquestions' => $s->totalnoofquestions,
                    'marksobtained' => $s->marksobtained,
                    'totalmarks' => $s->totalmarks,
                ];
            }

            writer::with_context($context)
                ->export_data(['sessions'], (object) ['sessions' => $data]);
        }
    }

    /**
     * Delete user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->get_contextids())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('qpractice', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('qpractice_session', [
                'qpracticeid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete all user data for a set of users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('qpractice', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = ['qpracticeid' => $cm->instance] + $inparams;
        $DB->delete_records_select('qpractice_session', "qpracticeid = :qpracticeid AND userid $insql", $params);
    }

    /**
     * Add users with data in the given context to the userlist.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('qpractice', $context->instanceid);
        if (!$cm) {
            return;
        }

        $sql = "SELECT s.userid
                  FROM {qpractice_session} s
                 WHERE s.qpracticeid = :qpracticeid";
        $params = ['qpracticeid' => $cm->instance];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Utility to check if a DB table exists without fatal errors.
     *
     * @param string $tablename
     * @return bool
     */
    private static function table_exists(string $tablename): bool {
        global $DB;
        try {
            $DB->get_manager()->table_exists(new \xmldb_table($tablename));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
