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
 * Internal library of functions for module qpractice
 *
 * All the qpractice specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Consider for deletion.
 * @todo this doesn't seem to be used
 *
 * @param \context $context
 * @return void
 */
function qpractice_make_default_categories($context) {
    if (empty($context)) {
        return false;
    }

    // Create default question categories.
    $defaultcategoryobj = question_make_default_categories(array($context));

    return $defaultcategoryobj;
}
/*

*/

/**
 * This function returns an array of question bank categories accessible to the
 * current user in the given context
 *
 * @return array
 * @param \context $context
 * @param int $top
 * @return array  keys are the question category ids and values the name of the question category
 *
 */
function qpractice_get_question_categories(\context $context, int $top=null) : array {
    if (empty($context)) {

        return [];
    }
    $options = [];
    /* Get all categories in course/system context (for settings form) */
    if (get_config('qpractice', 'systemcontext')) {
        $questioncats = qbank_managecategories\helper::question_category_options([context_system::instance()]);
        // $questioncats = question_category_options([context_system::instance()]);
    }

    $coursecats = qbank_managecategories\helper::question_category_options([$context]);
    // $coursecats = question_category_options([$context]);
    $key = key($coursecats);
    $questioncats[$key] = $coursecats[$key];

    if (!empty($questioncats)) {
        foreach ($questioncats as $questioncatcourse) {
            foreach ($questioncatcourse as $key => $questioncat) {
                // Key format is [question cat id, question cat context id], we need to explode it.
                $questidcontext = explode(',', $key);
                $questid = array_shift($questidcontext);
                $options[$questid] = $questioncat;
            }
        }
    }
    if ($top) {
        /* Get sub categories (for runtime)*/
        $catlist = question_categorylist($top);
        /* Filter out stuff up the hierarchy */
        $options = array_intersect_key($options, $catlist);
    }

    return $options;
}

/**
 * Create a qpractice attempt.
 *
 * @param stdClass $fromform data from form
 * @param \context $context the quiz object.
 * @return integer
 */
function qpractice_session_create(stdClass $fromform, \context $context) : int {
    global $DB, $USER;
    $X = optional_param('xx', 1, PARAM_INT);

    $qpractice = new stdClass();
     /* $value = $fromform->optiontype;
     * type of practice (optiontype), is being set to 1 normal
     * as the other types (goalpercentage and time) have not been
     * implemented. it might be good to implement them in a later
     * release
     */
    $value = 1;

    if ($value == 1) {
        $qpractice->time = null;
        $qpractice->goalpercentage = null;
        $qpractice->noofquestions = null;
    }

    $quba = question_engine::make_questions_usage_by_activity('mod_qpractice', $context);

    $qpractice->timecreated = time();
    $qpractice->practicedate = time();

    $qpractice->typeofpractice = $value;
    $qpractice->categoryid = $fromform->categories;
    $behaviour = $fromform->behaviour;
    $qpractice->userid = $USER->id;
    $quba->set_preferred_behaviour($behaviour);
    $qpractice->qpracticeid = $fromform->instanceid;

    /* The next block of code replaces
     * question_engine::save_questions_usage_by_activity($quba);
     * which was throwing an exception due to the array_merge
     * call that was added since qpractice was first created.
     */
    $record = new stdClass();
    $record->contextid = $quba->get_owning_context()->id;
    $record->component = $quba->get_owning_component();
    $record->preferredbehaviour = $quba->get_preferred_behaviour();
    global $DB;
    $newid = $DB->insert_record('question_usages', $record);
    $quba->set_id_from_database($newid);

    $qpractice->questionusageid = $quba->get_id();
    $sessionid = $DB->insert_record('qpractice_session', $qpractice);

    return $sessionid;
}

 /**
  * Delete a qpractice attempt.
  *
  * @param int $sessionid
  * @return void
  */
function qpractice_delete_attempt(int $sessionid) {
    global $DB;

    if (is_numeric($sessionid)) {
        if (!$session = $DB->get_record('qpractice_session', array('id' => $sessionid))) {
            return;
        }
    }

    question_engine::delete_questions_usage_by_activity($session->questionusageid);
    $DB->delete_records('qpractice_session', array('id' => $session->id));
}

/**
 * Get questionid's from category and any subcategories
 *
 * @param int $categoryid
 * @return array
 */
function get_available_questions_from_category(int $categoryid) : array {

    if (question_categorylist($categoryid)) {
        $categoryids = question_categorylist($categoryid);
    } else {
        $categoryids = [$categoryid];
    }
    /**@todo not implemented ? */
    $excludedqtypes = null;
    $questionids = question_bank::get_finder()->get_questions_from_categories($categoryids, $excludedqtypes);

    return $questionids;
}

/**
 * Get another question (at runtime)
 *
 * @param int $categoryid
 * @param array $excludedquestions
 * @param bool $allowshuffle
 * @return \stdClass
 */
function choose_other_question(int $categoryid, array $excludedquestions, bool $allowshuffle = true) {
    $available = get_available_questions_from_category($categoryid);
    shuffle($available);

    foreach ($available as $questionid) {
        if (in_array($questionid, $excludedquestions)) {
            continue;
        }
        $question = question_bank::load_question($questionid, $allowshuffle);
        return $question;
    }

    return null;
}

/**
 * Get behaviour for this instance
 *
 * @param stdClass $cm
 * @return array
 */
function get_options_behaviour(stdClass $cm) : array {
    global $DB, $CFG;
    $behaviour = $DB->get_record('qpractice', array('id' => $cm->instance), 'behaviour');
    $comma = explode(",", $behaviour->behaviour);
    $currentbehaviour = '';
    $behaviours = question_engine::get_behaviour_options($currentbehaviour);
    $showbehaviour = [];
    foreach ($comma as $id => $values) {
        foreach ($behaviours as $key => $langstring) {
            if ($values == $key) {
                $showbehaviour[$key] = $langstring;
            }
        }
    }
    return $showbehaviour;
}
/**
 * Get slot for next question
 *
 * @param int $sessionid
 * @param question_usage_by_activity $quba
 * @return integer
 */
function get_next_question(int $sessionid, question_usage_by_activity $quba) : int {

    global $DB;

    $session = $DB->get_record('qpractice_session', array('id' => $sessionid));
    $categoryid = $session->categoryid;
    $results = $DB->get_records_menu('question_attempts', array('questionusageid' => $session->questionusageid),
            'id', 'id, questionid');
    $questionid = choose_other_question($categoryid, $results);

    if ($questionid == null) {
        $viewurl = new moodle_url('/mod/qpractice/summary.php', array('id' => $sessionid));
        redirect($viewurl, get_string('nomorequestions', 'qpractice'));
    }

    $question = question_bank::load_question($questionid->id, false);
    $slot = $quba->add_question($question);
    $quba->start_question($slot);
    question_engine::save_questions_usage_by_activity($quba);
    $DB->set_field('qpractice_session', 'totalnoofquestions', $slot, array('id' => $sessionid));
    return $slot;
}
