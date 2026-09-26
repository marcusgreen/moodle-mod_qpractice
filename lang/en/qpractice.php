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
 * English strings for Question Practice
 *
 * @package    mod_qpractice
 * @copyright  2013 Jayesh Anandani
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allowwrongonly'] = 'Allow practising previously incorrect questions';
$string['allowwrongonly_help'] = 'When enabled, students who have already attempted this activity can choose to start a session containing only questions they most recently answered incorrectly. Not available when a category path is enabled.';
$string['answered'] = 'Answered';
$string['atleastonecategory'] = 'At least one category must be selected';
$string['attemptprogress'] = 'Your progress';
$string['backurl'] = 'Go back to main page';
$string['behaviour'] = 'Behaviour';
$string['behaviourandcategories'] = 'Behaviour and categories';
$string['behaviours'] = 'Behaviours';
$string['categories'] = 'Categories';
$string['category'] = 'Category';
$string['categorybreakdown'] = 'Score by category';
$string['categoryname'] = 'Category name';
$string['categoryselect'] = 'Categories';
$string['categoryselect_help'] = 'Select from question categories. The number shows the count of questions available';
$string['categoryselected'] = 'Topic Selected';
$string['continueurl'] = 'Continue last session';
$string['continueurl_desc'] = 'Resume the session you left unfinished.';
$string['correct'] = 'Correct';
$string['createsessions'] = 'Create a new session';
$string['createurl'] = 'Create a new session';
$string['createurl_desc'] = 'Pick topics and question types, then start practising.';
$string['error:atleastonecategory'] = 'At least one category must be selected';
$string['marksobtained'] = 'Marks obtained';
$string['modulename'] = 'Question Practice';
$string['modulename_help'] = 'Create settings for students to practice standard Moodle question types';
$string['modulename_link'] = 'Question_practice_module';
$string['modulenameplural'] = 'Question Practices';
$string['nextquestion'] = 'View next question';
$string['nocategoriesselected'] = 'You must select at least one category';
$string['nomorequestions'] = 'Sorry, no more questions to display. Try a different category';
$string['noofquestionsright'] = 'No. of questions right';
$string['noofquestionsviewed'] = 'No. of Questions viewed';
$string['nopermission'] = 'You do not have permission to view this';
$string['noquestionbanks'] = 'No question banks/categories found';
$string['normalpractice'] = 'Normal Practice';
$string['onachieve_nextstage'] = 'Move to next stage';
$string['onachieve_stay'] = 'Stay on this stage';
$string['onlyincorrect'] = 'Only give me questions I previously got wrong';
$string['pastsessions'] = 'Past Sessions';
$string['pathaddstage'] = 'Add stage';
$string['pathduplicatecategory'] = 'Category "{$a}" is used in more than one stage';
$string['pathmode'] = 'Enable category path';
$string['pathmode_help'] = 'When enabled, students work through the categories below in order. Each stage can require a target percentage score before the next stage unlocks. When disabled, students can freely choose among any of the selected categories, as normal.';
$string['pathmovedown'] = '↓ Move down';
$string['pathmovedownstage'] = 'Move stage {$a} down';
$string['pathmoveup'] = '↑ Move up';
$string['pathmoveupstage'] = 'Move stage {$a} up';
$string['pathremovestage'] = 'Remove stage {$a}';
$string['pathstage'] = 'Stage {$a}';
$string['pathstagecategory'] = 'Stage {$a} category';
$string['pathstagecategoryempty'] = 'This category has no questions of its own. Choose one of the categories inside it.';
$string['pathstagecategorynone'] = 'No category selected';
$string['pathstagecategoryoption'] = '{$a->name} ({$a->count}) — {$a->path}';
$string['pathstagecategorysearch'] = 'Search categories';
$string['pathstagecolumn'] = 'Stage';
$string['pathstagefinal'] = 'Final stage: students keep practising this category. There is no target, as there is no further stage to move on to.';
$string['pathstageminquestions'] = 'Stage {$a} minimum questions answered';
$string['pathstageminquestionshelp'] = 'Minimum questions answered';
$string['pathstageminquestionshelp_help'] = 'The number of questions a student must answer on this stage before reaching the target can move them on. Use it to stop a lucky first answer from ending the stage early. 0 means no minimum.';
$string['pathstageminquestionsinvalid'] = 'Stage {$a}: the minimum number of questions cannot be negative';
$string['pathstagemissingcategory'] = 'Stage {$a}: select a category';
$string['pathstageofstages'] = 'Stage {$a->stage} of {$a->total}';
$string['pathstageonachieve'] = 'Stage {$a} when target reached';
$string['pathstagerequired'] = 'At least one stage is required for a category path';
$string['pathstagetarget'] = 'Stage {$a} target to advance (%)';
$string['pathstagetargethelp'] = 'Target to advance';
$string['pathstagetargethelp_help'] = 'The score a student needs on this stage to move on. The score is the marks they have earned as a percentage of the marks available, across every question they have answered in this stage. It starts again at zero on each new stage.

The score is checked after every answer, so without a minimum a target can be met early: with a target of 50%, a correct first answer (100%) moves the student on straight away. Set a minimum number of questions answered to prevent this.';
$string['pathstagetargetrequired'] = 'Stage {$a}: enter a target percentage, or set "when target reached" to stay on this stage';
$string['pathtargetunlocked'] = 'You have reached the target for this stage. The next category is now unlocked.';
$string['percentage'] = 'Percentage';
$string['pluginadministration'] = 'Question Practice administration';
$string['pluginname'] = 'Question Practice';
$string['pluginname_help'] = 'The Question Practice activity enables a teacher to create instances comprising questions of various types. The practice created by teacher can
 is later used by students in their own way to analyze their learning.';
$string['practicedate'] = 'Practice Date';
$string['practicesession'] = 'Practice Session';
$string['privacy:metadata:qpractice_user_path_progress'] = 'Information about how far a student has progressed through a category path.';
$string['privacy:metadata:qpractice_user_path_progress:currentsortorder'] = 'The position of the stage the user has currently reached.';
$string['privacy:metadata:qpractice_user_path_progress:qpracticeid'] = 'The ID of the Question Practice activity.';
$string['privacy:metadata:qpractice_user_path_progress:stageanswered'] = 'Questions answered so far on the current stage.';
$string['privacy:metadata:qpractice_user_path_progress:stagecorrect'] = 'Marks obtained so far on the current stage.';
$string['privacy:metadata:qpractice_user_path_progress:stagetotal'] = 'Marks available so far on the current stage.';
$string['privacy:metadata:qpractice_user_path_progress:timemodified'] = 'The time the progress was last updated.';
$string['privacy:metadata:qpractice_user_path_progress:userid'] = 'The ID of the user.';
$string['qpractice'] = 'Qpractice';
$string['qpractice:addinstance'] = 'Add a Question Practice instance';
$string['qpractice:attempt'] = 'Attempt a Question Practice session';
$string['qpractice:view'] = 'View a Question Practice session';
$string['qpractice:viewallreports'] = 'View all Question Practice reports';
$string['qpractice:viewmyreport'] = 'View my Question Practice report';
$string['qpractice_categories'] = 'Qpractice categories';
$string['qpractice_session_categories'] = 'Qpractice session categories';
$string['qpractice_sessions'] = 'Qpractice sessions';
$string['qpracticebehaviour'] = 'Question Behaviour ';
$string['qpracticefieldset'] = 'Custom example fieldset';
$string['qpracticename'] = 'Question Practice name';
$string['qpracticename_help'] = 'This is the name that will show on the course';
$string['qpracticeset'] = 'Select any one type of Practice';
$string['questionbank'] = 'Question bank';
$string['questioncount'] = 'Questions attempted';
$string['reporturl'] = 'Show Past Sessions';
$string['reporturl_desc'] = 'Review your previous sessions and scores.';
$string['resumepractice'] = 'Resume Practice';
$string['score'] = 'Marks Obtained';
$string['selectallnone'] = 'Select all / none';
$string['selectcategories'] = 'Select categories';
$string['selectonebehaviourerror'] = 'You must select at least one behaviour';
$string['sessionid'] = 'sessionid';
$string['setuppractice'] = 'Setup Practice';
$string['startpractice'] = 'Start Practice';
$string['stoppractice'] = 'Stop practice';
$string['submitandfinish'] = 'Submit and Finish Practice';
$string['systemcontext'] = 'System context';
$string['systemcontext_text'] = 'Show Questions avilable site wide';
$string['timeelapsed'] = 'Time';
$string['timegoalset'] = 'Time/Goal Set';
$string['topcategory'] = 'Top category';
$string['totalmarks'] = 'Total marks obtained';
$string['totalnoofquestions'] = 'No of questions viewed';
$string['totalnoofquestionsright'] = 'No of questions right';
$string['totalquestions'] = 'Total questions attempted';
$string['typeofpractice'] = 'Type of Practice';
$string['viewdetails'] = 'View details';
$string['viewpastsessions'] = 'View categories covered';
$string['viewurl'] = 'No Records exist';
