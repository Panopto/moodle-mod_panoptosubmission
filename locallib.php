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
 * main locallib.php file for the Panopto Student Submission mod
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('PANOPTOSUBMISSION_ALL', 0);
define('PANOPTOSUBMISSION_REQ_GRADING', 1);
define('PANOPTOSUBMISSION_SUBMITTED', 2);

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');

/**
 * Check if the assignment submission end date has passed or if late submissions
 * are prohibited
 *
 * @param object $targetactivity - Instance of a Panopto Student Submission activity
 * @return bool - true if past due, otherwise false
 */
function panoptosubmission_submission_past_due($targetactivity) {
    $pastdue = false;

    if ($targetactivity->preventlate) {
        $pastdue = (0 != $targetactivity->timedue) && (time() > $targetactivity->timedue);
    }

    return $pastdue;
}

/**
 * Check if the assignment submission start date is set and if it has arrived yet.
 *
 * @param object $targetactivity - Instance of a Panopto Student Submission activity
 * @return bool - true if available, otherwise false
 */
function panoptosubmission_submission_available_yet($targetactivity) {
    $availableyet = true;

    if (isset($targetactivity->timeavailable)) {
        $availableyet = time() >= $targetactivity->timeavailable;
    }

    return $availableyet;
}

/**
 * Retrieve a list of users who have submitted assignments
 *
 * @param int $targetinstance The assignment id.
 * @param string $filter Filter results by assignments that have been submitted or
 * assignment that need to be graded or no filter at all.
 * @return mixed collection of users or false.
 */
function panoptosubmission_get_submissions($targetinstance, $filter = '') {
    global $DB;

    $where = '';
    switch ($filter) {
        case PANOPTOSUBMISSION_SUBMITTED:
            $where = ' timemodified > 0 AND ';
            break;
        case PANOPTOSUBMISSION_REQ_GRADING:
            $where = ' timemarked < timemodified AND ';
            break;
    }

    $param = array('instanceid' => $targetinstance);
    $where .= ' panactivityid = :instanceid';

    // Reordering the fields returned to make it easier to use in the grade_get_grades function.
    $columns = 'userid,panactivityid,grade,submissioncomment,format,teacher,mailed,' .
        'timemarked,timecreated,timemodified,source,width,height';
    $records = $DB->get_records_select('panoptosubmission_submission', $where, $param, 'timemodified DESC', $columns);

    if (empty($records)) {
        return false;
    }

    return $records;
}

/**
 * This function retrives the user's submission record.
 *
 * @param int $targetinstanceid The panopto submission instance id.
 * @param int $userid The user id.
 * @return object A data object consisting of the user's submission.
 */
function panoptosubmission_get_submission($targetinstanceid, $userid) {
    global $DB;

    $param = array('instanceid' => $targetinstanceid, 'userid' => $userid);
    $where = '';
    $where .= ' panactivityid = :instanceid AND userid = :userid';

    // Reordering the fields returned to make it easier to use in the grade_get_grades function.
    $columns = 'userid,id,panactivityid,grade,submissioncomment,format,teacher,mailed,' .
        'timemarked,timecreated,timemodified,source,width,height';
    $record = $DB->get_record_select('panoptosubmission_submission', $where, $param, '*');

    if (empty($record)) {
        return null;
    }

    return $record;

}

/**
 * This function retrieves the submission grade object.
 *
 * @param int $targetinstanceid The activity instance id.
 * @param int $userid The user id.
 * @return object A data object consisting of the user's submission.
 */
function panoptosubmission_get_submission_grade_object($targetinstanceid, $userid) {
    global $DB;

    $param = array('panactivityid' => $targetinstanceid, 'userid' => $userid);

    $sql = "SELECT u.id, u.id AS userid, s.grade AS rawgrade, s.submissioncomment AS feedback, s.format AS feedbackformat, " .
                   "s.teacher AS usermodified, s.timemarked AS dategraded, s.timemodified AS datesubmitted " .
              "FROM {user} u, {panoptosubmission_submission} s " .
             "WHERE u.id = s.userid AND s.panactivityid = :panactivityid " .
                   "AND u.id = :userid";

    $data = $DB->get_record_sql($sql, $param);

    if (-1 == $data->rawgrade) {
        $data->rawgrade = null;
    }

    return $data;
}

/**
 * This function validates the course module id and returns the course module object, course object and activity instance object.
 *
 * @param int $cmid the id of the context for the module instance
 * @return array an array with the following values array(course module object, $course object, activity instance object).
 * @throws moodle_exception
 */
function panoptosubmission_validate_cmid ($cmid) {
    global $DB;

    if (!$cm = get_coursemodule_from_id('panoptosubmission', $cmid)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new moodle_exception('coursemisconf');
    }

    if (!$targetpanoptosubmission = $DB->get_record('panoptosubmission', array('id' => $cm->instance))) {
        throw new moodle_exception('invalidid', 'panoptosubmission');
    }

    return array($cm, $course, $targetpanoptosubmission);
}

/**
 * This function returns HTML markup to signify a submission was late.
 *
 * @param string $timesubmitted the time the assignment was submitted
 * @param string $timedue the time the assignment was due
 * @return string HTML markup
 */
function panoptosubmission_display_lateness($timesubmitted, $timedue) {
    if (!$timedue) {
        return '';
    }
    $time = $timedue - $timesubmitted;
    if ($time < 0) {
        $timetext = get_string('late', 'panoptosubmission', format_time($time));
        return ' (<span class="late">'.$timetext.'</span>)';
    } else {
        $timetext = get_string('early', 'panoptosubmission', format_time($time));
        return ' (<span class="early">'.$timetext.'</span>)';
    }
}

/**
 * Alerts teachers by email of new or changed assignments that need grading
 *
 * Sends an email to ALL teachers in the course (or in the group if using separate groups).
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param string $name Name of the Panopto activity instance.
 * @param object $submission The submission that has changed.
 * @param object $context The context object.
 */
function panoptosubmission_email_teachers($cm, $name, $submission, $context) {
    global $CFG, $DB, $COURSE;

    $user = $DB->get_record('user', array('id' => $submission->userid));

    if ($teachers = panoptosubmission_get_graders($cm, $user, $context)) {

        $strassignments = get_string('modulenameplural', 'panoptosubmission');
        $strassignment  = get_string('modulename', 'panoptosubmission');
        $strsubmitted   = get_string('submitted', 'panoptosubmission');

        foreach ($teachers as $teacher) {
            $info = new stdClass();
            $info->username = fullname($user, true);
            $info->assignment = format_string($name, true);
            $info->url = $CFG->wwwroot . '/mod/panoptosubmission/grade_submissions.php?cmid=' . $cm->id;
            $info->timeupdated = strftime('%c', $submission->timemodified);
            $info->courseid = $cm->course;
            $info->cmid     = $cm->id;

            $postsubject = $strsubmitted . ': ' . $user->username . ' -> ' . $name;
            $posttext = panoptosubmission_email_teachers_text($info);
            $posthtml = ($teacher->mailformat == 1) ? panoptosubmission_email_teachers_html($info) : '';

            $eventdata = new \core\message\message();
            $eventdata->courseid         = $COURSE->id;
            $eventdata->modulename       = 'panoptosubmission';
            $eventdata->userfrom         = $user;
            $eventdata->userto           = $teacher;
            $eventdata->subject          = $postsubject;
            $eventdata->fullmessage      = $posttext;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml  = $posthtml;
            $eventdata->smallmessage     = $postsubject;

            $eventdata->name            = 'panoptosubmission_updates';
            $eventdata->component       = 'mod_panoptosubmission';
            $eventdata->notification    = 1;
            $eventdata->contexturl      = $info->url;
            $eventdata->contexturlname  = $info->assignment;

            message_send($eventdata);
        }
    }
}

/**
 * Returns a list of teachers that should be grading given submission.
 *
 * @param object $cm Panopto video assignment course module object.
 * @param object $user The Moodle user object.
 * @param object $context A context object.
 * @return array An array of grading userids
 */
function panoptosubmission_get_graders($cm, $user, $context) {
    // Potential graders.
    $potgraders = get_enrolled_users($context, 'mod/panoptosubmission:gradesubmission', 0, 'u.*', null, 0, 0, true);

    $graders = array();
    // Separate groups are being used.
    if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
        // Try to find all groups.
        if ($groups = groups_get_all_groups($cm->course, $user->id)) {
            foreach ($groups as $group) {
                foreach ($potgraders as $potgrader) {
                    if ($potgrader->id == $user->id) {
                        continue; // Do not send self.
                    }
                    if (groups_is_member($group->id, $potgrader->id)) {
                        $graders[$potgrader->id] = $potgrader;
                    }
                }
            }
        } else {
            // User not in group, try to find graders without group.
            foreach ($potgraders as $potgrader) {
                if ($potgrader->id == $user->id) {
                    // Do not send self.
                    continue;
                }
                // Ugly hack.
                if (!groups_get_all_groups($cm->course, $potgrader->id)) {
                    $graders[$potgrader->id] = $potgrader;
                }
            }
        }
    } else {
        foreach ($potgraders as $potgrader) {
            if ($potgrader->id == $user->id) {
                // Do not send self.
                continue;
            }
            $graders[$potgrader->id] = $potgrader;
        }
    }

    return $graders;
}

/**
 * Creates the text content for emails to teachers
 *
 * @param object $info The info used by the 'emailteachermail' language string
 * @return string
 */
function panoptosubmission_email_teachers_text($info) {
    global $DB;

    $param    = array('id' => $info->courseid);
    $course   = $DB->get_record('course', $param);
    $posttext = '';

    if (!empty($course)) {
        $posttext  = format_string($course->shortname, true, $course->id) . ' -> ' .
            get_string('modulenameplural', 'panoptosubmission') . '  -> ';
        $posttext .= format_string($info->assignment, true, $course->id) . "\n";
        $posttext .= '---------------------------------------------------------------------' . "\n";
        $posttext .= get_string('emailteachermail', 'panoptosubmission', $info) . "\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
    }

    return $posttext;
}

/**
 * Creates the html content for emails to teachers
 *
 * @param object $info The info used by the 'emailteachermailhtml' language string
 * @return string
 */
function panoptosubmission_email_teachers_html($info) {
    global $CFG, $DB;

    $param    = array('id' => $info->courseid);
    $course   = $DB->get_record('course', $param);
    $posthtml = '';

    if (!empty($course)) {
        $posthtml .= html_writer::start_tag('p');
        $attr = array('href' => new moodle_url('/course/view.php', array('id' => $course->id)));
        $posthtml .= html_writer::tag('a', format_string($course->shortname, true, $course->id), $attr);
        $posthtml .= '->';
        $attr = array('href' => new moodle_url('/mod/panoptosubmission/view.php', array('id' => $info->cmid)));
        $posthtml .= html_writer::tag('a', format_string($info->assignment, true, $course->id), $attr);
        $posthtml .= html_writer::end_tag('p');
        $posthtml .= html_writer::start_tag('hr');
        $posthtml .= html_writer::tag('p', get_string('emailteachermailhtml', 'panoptosubmission', $info));
        $posthtml .= html_writer::end_tag('hr');
    }
    return $posthtml;
}

/**
 * This function retrieves a list of enrolled users with the capability to submit to the activity.
 *
 * @param object $cm the context object for the module instance
 * @return array An array of user objects.
 */
function panoptosubmission_get_assignment_students($cm) {
    $context = context_module::instance($cm->id);
    $users = get_enrolled_users($context, 'mod/panoptosubmission:submit', 0, 'u.id');

    return $users;
}

/**
 * Returns the grading instance for the assignment
 *
 * @param object $cminstance Panopto video assignment course module object.
 * @param object $context A context object.
 * @param object $submission The current submission object
 * @param bool $gradingdisabled whether or not advanced grading is disabled
 * @return array An array of grading userids
 */
function panoptosubmission_get_grading_instance($cminstance, $context, $submission, $gradingdisabled) {
    global $CFG, $USER;

    $grademenu = make_grades_menu($cminstance->grade);
    $allowgradedecimals = $cminstance->grade > 0;

    $advancedgradingwarning = false;
    $gradingmanager = get_grading_manager($context, 'mod_panoptosubmission', 'submissions');
    $gradinginstance = null;
    if ($gradingmethod = $gradingmanager->get_active_method()) {
        $controller = $gradingmanager->get_controller($gradingmethod);
        if ($controller->is_form_available()) {
            $itemid = null;
            if ($submission) {
                $itemid = $submission->id;
            }
            if ($gradingdisabled && $itemid) {
                $gradinginstance = $controller->get_current_instance($USER->id, $itemid);
            } else if (!$gradingdisabled) {
                $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                $gradinginstance = $controller->get_or_create_instance($instanceid,
                                                                       $USER->id,
                                                                       $itemid);
            }
        } else {
            $advancedgradingwarning = $controller->form_unavailable_notification();
        }
    }
    if ($gradinginstance) {
        $gradinginstance->get_controller()->set_grade_range($grademenu, $allowgradedecimals);
    }
    return $gradinginstance;
}
