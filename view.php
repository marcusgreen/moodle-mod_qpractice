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
 * Views options for starting a new session or see past reports.
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $CFG, $USER;
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once("$CFG->libdir/formslib.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$n = optional_param('n', 0, PARAM_INT);  // Qpractice instance ID - it should be named as the first character of the module.
if ($id) {
    if (!$cm = get_coursemodule_from_id('qpractice', $id)) {
        throw new moodle_exception('invalidcoursemoduleid', 'error', '', $id);
    }
    if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
        throw new moodle_exception('coursemisconf', 'error', '', $cm->course);
    }
    $qpractice = $DB->get_record('qpractice', ['id' => $cm->instance]);
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('mod/qpractice:view', $context);

$params = [
    'objectid' => $cm->id,
    'context' => $context,
];
$event = mod_qpractice\event\qpractice_viewed::create($params);
$event->trigger();

$PAGE->set_url('/mod/qpractice/view.php', ['id' => $cm->id, 'courseid' => $course->id]);
$PAGE->set_title(format_string($qpractice->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canview = has_capability('mod/qpractice:view', $context);

$createurl = new moodle_url('/mod/qpractice/startattempt.php', ['id' => $cm->id]);
$createtext = get_string('createurl', 'qpractice');
$reporturl = new moodle_url('/mod/qpractice/report.php', ['id' => $cm->id]);
$reporttext = get_string('reporturl', 'qpractice');

if (!$canview) {
    throw new moodle_exception(get_string('nopermission', 'qpractice'));
}

// Look for the most recent session for this user.
$sessions = $DB->get_records('qpractice_session', [
    'userid' => $USER->id,
    'qpracticeid' => $cm->instance,
], 'id desc', '*', '0', '1');
$latestsession = $sessions ? reset($sessions) : null;
$inprogress = $latestsession && $latestsession->status == 'inprogress';

$actions = [];

// Continue an unfinished session (primary action when present).
if ($inprogress) {
    $continueurl = new moodle_url('/mod/qpractice/attempt.php', ['id' => $latestsession->id]);
    $actions[] = [
        'url' => $continueurl->out(false),
        'title' => get_string('continueurl', 'qpractice'),
        'description' => get_string('continueurl_desc', 'qpractice'),
        'icon' => $OUTPUT->pix_icon('i/return', '', 'moodle', ['class' => 'qpractice-action-icon']),
        'btnclass' => 'btn-primary',
    ];
}

// Start a new session.
$actions[] = [
    'url' => $createurl->out(false),
    'title' => $createtext,
    'description' => get_string('createurl_desc', 'qpractice'),
    'icon' => $OUTPUT->pix_icon('t/add', '', 'moodle', ['class' => 'qpractice-action-icon']),
    'btnclass' => $inprogress ? 'btn-secondary' : 'btn-primary',
];

// View past sessions (only if the user has run at least one).
if ($latestsession) {
    $actions[] = [
        'url' => $reporturl->out(false),
        'title' => $reporttext,
        'description' => get_string('reporturl_desc', 'qpractice'),
        'icon' => $OUTPUT->pix_icon('i/report', '', 'moodle', ['class' => 'qpractice-action-icon']),
        'btnclass' => 'btn-secondary',
    ];
}

$templatecontext = [
    'intro' => trim(strip_tags($qpractice->intro))
        ? format_module_intro('qpractice', $qpractice, $cm->id)
        : '',
    'actions' => $actions,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_qpractice/view', $templatecontext);

// Finish the page.
echo $OUTPUT->footer();
