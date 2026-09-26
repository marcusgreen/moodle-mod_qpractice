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

/**
 * Consider for deletion.
 * This doesn't seem to be used
 *
 * @param \context $context
 * @return void
 */
function qpractice_make_default_categories($context) {
    if (empty($context)) {
        return false;
    }

    // Create default question categories.
    $defaultcategoryobj = question_make_default_categories([$context]);

    return $defaultcategoryobj;
}

/**
 * This function returns an array of question bank categories accessible to the
 * current user in the given context.
 *
 * @param \context $context The context in which to check for question categories.
 * @param \moodleform $mform The Moodle form object (if applicable).
 * @param int|null $top The top category ID (optional).
 * @param array|null $categories An array of categories (optional).
 * @return array An array of question bank categories.
 */
function qpractice_get_question_categories(\context $context, $mform, ?int $top, ?array $categories): array {
    global $DB;
    if (empty($context)) {
        return '';
    }
    $options = [];
    /* Get all categories in course/system context (for settings form) */
    if (get_config('qpractice', 'systemcontext')) {
        $questioncats = \qbank_managecategories([context_system::instance()]);
    }
    $instanceid = optional_param('update', null, PARAM_INT);

    $contextcategories = qbank_managecategories\helper::get_categories_for_contexts($context->id, 'parent', false);

    $instancecategories = $DB->get_records_menu('qpractice_categories', ['qpracticeid' => $instanceid], '', 'id, categoryid');
    foreach ($contextcategories as $category) {
        if (in_array($category->id, $instancecategories)) {
            $category->checked = true;
        } else {
            $category->checked = false;
        }
    }

    $catarray = [];
    foreach ($contextcategories as $category) {
        $catarray[$category->id] = $category->id;
    }
    $top = 0;
    if (count($catarray) > 0) {
        $top = min($catarray);
    }

    $ct = new CatTree();

    $ct->buildtree($mform, $contextcategories, $top - 1);

    $ct->html = '<div id="fgroup_id_categories101" class="form-group row  fitem femptylabel  " data-groupname="mavg">
    ' . $ct->html;
    $ct->html .= '</div>';
    return [$contextcategories, $ct->html];
}

/**
 * Class to build a tree of question categories with checkboxes for selection.
 */
class CatTree {
    /**
     * Class to build a tree of question categories with checkboxes for selection.
     * @var string $html;
     *
     * /
    public $html;

    /**
     * Build tree of categories with checkboxes to select the ones to
     * appear for a student to select from.
     *
     * @param \MoodleQuickForm $mform The Moodle form object.
     * @param array $elements An array of category elements.
     * @param int $parentid The parent category ID.
     * @return void
     */
    public function buildtree(\MoodleQuickForm $mform, array $elements, int $parentid = 0) {
        $this->html .= "<ul>";
        foreach ($elements as $element) {
            if ($element->parent === (string) $parentid) {
                $this->html .= '<li class="category_list_item">';
                $questioncount = '<span class="question_count">(' . $element->questioncount . ')</span>';
                $id = 'categories[' . $element->id . ']_parent[' . $element->parent . ']';
                $checked = ($element->checked) ? "checked" : "";
                $this->html .= $mform->createElement(
                    'checkbox',
                    $id,
                    '',
                    $element->name,
                    [
                        'class' => 'question_category',
                        'checked' => $checked,
                        'group' => 1,
                    ]
                )->toHtml() . $questioncount;
                $children = $this->buildtree($mform, $elements, $element->id);
                if ($children) {
                    $element->children = $children;
                }
                $this->html .= "</li>";
                $element->name;
            }
        }
        $this->html .= "</ul>";
    }
}

/**
 * Create a qpractice attempt.
 *
 * @param stdClass $fromform data from form
 * @param \context $context the quiz object.
 * @return integer
 */
function qpractice_session_create(stdClass $fromform, \context $context): int {
    global $DB, $USER;
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
    $newid = $DB->insert_record('question_usages', $record);
    $quba->set_id_from_database($newid);

    $qpractice->questionusageid = $quba->get_id();
    $qpractice->wrongonly = !empty($fromform->wrongonly) ? 1 : 0;
    $sessionid = $DB->insert_record('qpractice_session', $qpractice);
    foreach ($fromform->categories as $categoryid => $value) {
        $DB->insert_record('qpractice_session_cats', ['category' => $categoryid, 'session' => $sessionid]);
    }
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
        if (!$session = $DB->get_record('qpractice_session', ['id' => $sessionid])) {
            return;
        }
    }

    question_engine::delete_questions_usage_by_activity($session->questionusageid);
    $DB->delete_records('qpractice_session', ['id' => $session->id]);
}

/**
 * Get questionid's from category and any subcategories.
 *
 * @param array $categories
 * @return array
 */
function get_available_questions_from_categories(array $categories): array {
    $excludedqtypes = null;
    $questionids = question_bank::get_finder()->get_questions_from_categories($categories, $excludedqtypes);

    return $questionids;
}

/**
 * Get another question (at runtime)
 *
 * @param array $categories
 * @param array $excludedquestions
 * @param bool $allowshuffle
 * @return \stdClass
 */
function choose_other_question(array $categories, array $excludedquestions, bool $allowshuffle = true) {
    $available = get_available_questions_from_categories($categories);
    return choose_other_question_from_ids($available, $excludedquestions, $allowshuffle);
}

/**
 * Get another question (at runtime) from a fixed pool of candidate question ids.
 *
 * @param array $available candidate question ids
 * @param array $excludedquestions
 * @param bool $allowshuffle
 * @return \stdClass
 */
function choose_other_question_from_ids(array $available, array $excludedquestions, bool $allowshuffle = true) {
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
 * Return the ids of questions the student most recently answered incorrectly (fraction <= 0)
 * across their past sessions for a given qpractice instance. A question that was answered
 * wrong once but later answered correctly is not included, since the latest attempt wins.
 *
 * @param int $qpracticeid
 * @param int $userid
 * @return array List of question ids.
 */
function qpractice_get_incorrect_questionids(int $qpracticeid, int $userid): array {
    global $DB;

    $sessions = $DB->get_records(
        'qpractice_session',
        ['qpracticeid' => $qpracticeid, 'userid' => $userid],
        'id ASC'
    );

    $latestfraction = [];
    foreach ($sessions as $session) {
        if (empty($session->questionusageid)) {
            continue;
        }
        try {
            $quba = question_engine::load_questions_usage_by_activity($session->questionusageid);
        } catch (\Exception $e) {
            continue;
        }
        foreach ($quba->get_slots() as $slot) {
            $question = $quba->get_question($slot, false);
            if (!$question) {
                continue;
            }
            $fraction = $quba->get_question_fraction($slot);
            if ($fraction === null) {
                // Not yet attempted; leave any earlier recorded result untouched.
                continue;
            }
            $latestfraction[$question->id] = $fraction;
        }
    }

    $incorrect = [];
    foreach ($latestfraction as $questionid => $fraction) {
        if ($fraction <= 0) {
            $incorrect[] = $questionid;
        }
    }
    return $incorrect;
}

/**
 * Get behaviour for this instance
 *
 * @param stdClass $cm
 * @return array
 */
function get_options_behaviour(stdClass $cm): array {
    global $DB, $CFG;
    $behaviour = $DB->get_record('qpractice', ['id' => $cm->instance], 'behaviour');
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
function get_next_question(int $sessionid, question_usage_by_activity $quba): int {

    global $DB;

    $session = $DB->get_record('qpractice_session', ['id' => $sessionid]);
    $results = $DB->get_records_menu(
        'question_attempts',
        ['questionusageid' => $session->questionusageid],
        'id',
        'id, questionid'
    );

    $qpractice = $DB->get_record('qpractice', ['id' => $session->qpracticeid]);
    if (!empty($qpractice->pathmode)) {
        // Path mode: only offer questions from the category the student is currently on.
        $categoryid = qpractice_current_path_category($qpractice->id, $session->userid);
        $categories = $categoryid ? [$categoryid] : [];
    } else {
        $categories = $DB->get_records_menu('qpractice_session_cats', ['session' => $sessionid], '', 'id, category');
    }

    if (!empty($session->wrongonly)) {
        $incorrectids = qpractice_get_incorrect_questionids($qpractice->id, $session->userid);
        $available = array_intersect(get_available_questions_from_categories($categories), $incorrectids);
        $questionid = choose_other_question_from_ids($available, $results);
    } else {
        $questionid = choose_other_question($categories, $results);
    }

    if ($questionid == null) {
        $viewurl = new moodle_url('/mod/qpractice/summary.php', ['id' => $sessionid]);
        redirect($viewurl, get_string('nomorequestions', 'qpractice'));
    }

    $question = question_bank::load_question($questionid->id, false);
    $slot = $quba->add_question($question);
    $quba->start_question($slot);
    question_engine::save_questions_usage_by_activity($quba);
    $DB->set_field('qpractice_session', 'totalnoofquestions', $slot, ['id' => $sessionid]);
    return $slot;
}

/**
 * Return the ordered category path stages for a qpractice instance.
 *
 * @param int $qpracticeid
 * @return array List of stdClass rows from qpractice_category_path, ordered by sortorder.
 */
function qpractice_get_path_stages(int $qpracticeid): array {
    global $DB;
    return array_values($DB->get_records('qpractice_category_path', ['qpracticeid' => $qpracticeid], 'sortorder ASC'));
}

/**
 * Get (creating if necessary) the path progress row for a student, starting them on the
 * first stage the first time they attempt a path-mode instance.
 *
 * @param int $qpracticeid
 * @param int $userid
 * @return stdClass|null The progress row, or null if this instance has no path stages.
 */
function qpractice_get_or_create_path_progress(int $qpracticeid, int $userid): ?stdClass {
    global $DB;

    $stages = qpractice_get_path_stages($qpracticeid);
    if (!$stages) {
        return null;
    }

    $progress = $DB->get_record('qpractice_user_path_progress', ['qpracticeid' => $qpracticeid, 'userid' => $userid]);
    if ($progress) {
        return $progress;
    }

    $progress = (object) [
        'qpracticeid' => $qpracticeid,
        'userid' => $userid,
        'currentsortorder' => $stages[0]->sortorder,
        'stagecorrect' => 0,
        'stagetotal' => 0,
        'timemodified' => time(),
    ];
    $progress->id = $DB->insert_record('qpractice_user_path_progress', $progress);
    return $progress;
}

/**
 * Return the category id of the stage a student is currently working through.
 *
 * @param int $qpracticeid
 * @param int $userid
 * @return int|null The category id, or null if this instance has no path stages.
 */
function qpractice_current_path_category(int $qpracticeid, int $userid): ?int {
    $stages = qpractice_get_path_stages($qpracticeid);
    if (!$stages) {
        return null;
    }
    $progress = qpractice_get_or_create_path_progress($qpracticeid, $userid);
    foreach ($stages as $stage) {
        if ($stage->sortorder == $progress->currentsortorder) {
            return (int) $stage->categoryid;
        }
    }
    // The student's stage no longer exists (path was edited); fall back to the first stage.
    return (int) $stages[0]->categoryid;
}

/**
 * Record the outcome of one answered question against the student's current path stage,
 * and advance them to the next stage if their running percentage now meets its target.
 *
 * @param int $qpracticeid
 * @param int $userid
 * @param float $obtainedmarks Marks obtained for the question just answered.
 * @param float $maxmarks Marks available for the question just answered.
 * @return bool True if the student advanced to a new stage as a result of this answer.
 */
function qpractice_record_path_answer(int $qpracticeid, int $userid, float $obtainedmarks, float $maxmarks): bool {
    global $DB;

    $stages = qpractice_get_path_stages($qpracticeid);
    if (!$stages) {
        return false;
    }
    $progress = qpractice_get_or_create_path_progress($qpracticeid, $userid);

    $stage = null;
    $stageindex = null;
    foreach ($stages as $i => $s) {
        if ($s->sortorder == $progress->currentsortorder) {
            $stage = $s;
            $stageindex = $i;
            break;
        }
    }
    if ($stage === null) {
        return false;
    }

    $progress->stagecorrect += $obtainedmarks;
    $progress->stagetotal += $maxmarks;
    $progress->timemodified = time();

    $advanced = false;
    $nextstage = $stages[$stageindex + 1] ?? null;
    if (
        $stage->targetpercent !== null
        && $stage->onachieve === 'nextstage'
        && $nextstage
        && $progress->stagetotal > 0
        && ($progress->stagecorrect / $progress->stagetotal * 100) >= $stage->targetpercent
    ) {
        $progress->currentsortorder = $nextstage->sortorder;
        $progress->stagecorrect = 0;
        $progress->stagetotal = 0;
        $advanced = true;
    }

    $DB->update_record('qpractice_user_path_progress', $progress);
    return $advanced;
}
