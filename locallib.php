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
 * Main locallib.php file for the Panopto Student Submission mod
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('PANOPTOSUBMISSION_ALL', 0);
define('PANOPTOSUBMISSION_REQ_GRADING', 1);
define('PANOPTOSUBMISSION_SUBMITTED', 2);
define('PANOPTOSUBMISSION_NOT_SUBMITTED', 3);

// The feedback file area constants are defined in lib.php, which is loaded below.

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');
require_once($CFG->dirroot . '/mod/panoptosubmission/lib.php');

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
 * Check if the assignment submission cut off has passed
 *
 * @param object $targetactivity - Instance of a Panopto Student Submission activity
 * @return bool - true if past cut off, otherwise false
 */
function panoptosubmission_submission_past_cutoff($targetactivity) {
    return (0 != $targetactivity->cutofftime) && (time() > $targetactivity->cutofftime);
}

/**
 * Returns true when a source URL may be stored or launched.
 *
 * An empty URL is allowed. Anything else must be a known Panopto host.
 *
 * @param string $url The submitted or stored content URL.
 * @return bool True when the URL is empty or belongs to a known Panopto host.
 */
function panoptosubmission_is_allowed_source_url($url) {
    return empty($url) || panopto_is_allowed_panopto_host($url);
}

/**
 * Returns the thumbnail URL if it belongs to a known Panopto host, otherwise empty.
 *
 * @param string $url The submitted or stored thumbnail URL.
 * @return string The URL to keep, or an empty string.
 */
function panoptosubmission_sanitise_thumbnail_url($url) {
    if (empty($url) || !panopto_is_allowed_panopto_host($url)) {
        return '';
    }

    return $url;
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

    $param = ['instanceid' => $targetinstance];
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

    $param = ['instanceid' => $targetinstanceid, 'userid' => $userid];
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
 * @return object|null A data object consisting of the user's submission, or null when there is none.
 */
function panoptosubmission_get_submission_grade_object($targetinstanceid, $userid) {
    global $DB;

    $param = ['panactivityid' => $targetinstanceid, 'userid' => $userid];

    $sql = "SELECT u.id, u.id AS userid, s.grade AS rawgrade, s.submissioncomment AS feedback, s.format AS feedbackformat, " .
                   "s.teacher AS usermodified, s.timemarked AS dategraded, s.timemodified AS datesubmitted " .
              "FROM {user} u, {panoptosubmission_submission} s " .
             "WHERE u.id = s.userid AND s.panactivityid = :panactivityid " .
                   "AND u.id = :userid";

    $data = $DB->get_record_sql($sql, $param);

    if (!$data) {
        return null;
    }

    if (-1 == $data->rawgrade) {
        $data->rawgrade = null;
    }

    return $data;
}

/**
 * This function validates the course module id and returns the course module object, course object and activity instance object.
 *
 * @param int $cmid the id of the context for the module instance
 * @return array an array with the following values [course module object, $course object, activity instance object].
 * @throws moodle_exception
 */
function panoptosubmission_validate_cmid($cmid) {
    global $DB;

    if (!$cm = get_coursemodule_from_id('panoptosubmission', $cmid)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Check if the course module is marked for deletion (in recycle bin).
    if (!empty($cm->deletioninprogress)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
        throw new moodle_exception('coursemisconf');
    }

    if (!$targetpanoptosubmission = $DB->get_record('panoptosubmission', ['id' => $cm->instance])) {
        throw new moodle_exception('invalidid', 'panoptosubmission');
    }

    return [$cm, $course, $targetpanoptosubmission];
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
        return ' (<span class="late">' . $timetext . '</span>)';
    } else {
        $timetext = get_string('early', 'panoptosubmission', format_time($time));
        return ' (<span class="early">' . $timetext . '</span>)';
    }
}

/**
 * Alerts users by email of new or changed assignments.
 *
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param object $course The course object.
 * @param string $name Name of the Panopto activity instance.
 * @param object $submission The submission that has changed.
 * @param stdClass $userfrom User that is sending notification.
 * @param stdClass $userto User that is receiving notification.
 * @param string $messagetype Message type.
 */
function panoptosubmission_send_notification(
    $cm,
    $course,
    $name,
    $submission,
    $userfrom,
    $userto,
    $messagetype
) {
    global $CFG;

    $modulename = get_string('modulename', 'panoptosubmission');
    $strsubmitted = get_string('submitted', 'panoptosubmission');
    $courseid = $cm->course ?? $course->id;

    $info = new stdClass();
    $info->username = fullname($userfrom, true);
    $info->assignment = format_string($name, true);
    $info->url = $CFG->wwwroot . '/mod/panoptosubmission/grade_submissions.php?cmid=' . $cm->id;
    // A grader can create the record before the student submits, so timemodified may be unset or 0.
    $info->timeupdated = date('c', empty($submission->timemodified) ? time() : $submission->timemodified);
    $info->courseid = $courseid;
    $info->cmid = $cm->id;

    $postsubject = $strsubmitted . ': ' . $userfrom->username . ' -> ' . $name;
    $posttext = panoptosubmission_format_notification_message_text($messagetype, $course, $info, $modulename);
    $posthtml = ($userto->mailformat == 1)
        ? panoptosubmission_format_notification_message_html($messagetype, $course, $info)
        : '';

    $eventdata = new \core\message\message();
    $eventdata->courseid = $courseid;
    $eventdata->modulename = 'panoptosubmission';
    $eventdata->userfrom = $userfrom;
    $eventdata->userto = $userto;
    $eventdata->subject = $postsubject;
    $eventdata->fullmessage = $posttext;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml = $posthtml;
    $eventdata->smallmessage = $postsubject;

    $eventdata->name = 'panoptosubmission_updates';
    $eventdata->component = 'mod_panoptosubmission';
    $eventdata->notification = 1;
    $eventdata->contexturl = $info->url;
    $eventdata->contexturlname = $info->assignment;

    message_send($eventdata);
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
    $potgraders = get_enrolled_users(
        $context,
        'mod/panoptosubmission:gradesubmission',
        0,
        'u.*',
        null,
        0,
        0,
        true
    );

    // Do not send self.
    unset($potgraders[$user->id]);

    if (empty($potgraders) || groups_get_activity_groupmode($cm) != SEPARATEGROUPS) {
        return $potgraders;
    }

    // Separate groups: load group membership once rather than per grader.
    if ($usergroups = groups_get_all_groups($cm->course, $user->id, 0, 'g.id')) {
        // Graders who share a group with the user.
        return array_intersect_key($potgraders, panoptosubmission_get_group_member_ids(array_keys($usergroups)));
    }

    // User not in a group, so find graders who are not in any group either.
    $allgroups = groups_get_all_groups($cm->course, 0, 0, 'g.id');
    if (empty($allgroups)) {
        return $potgraders;
    }

    return array_diff_key($potgraders, panoptosubmission_get_group_member_ids(array_keys($allgroups)));
}

/**
 * Returns the members of the given groups, keyed by user id.
 *
 * Reads the membership table directly, so the result does not depend on which
 * groups the current user is allowed to see.
 *
 * @param int[] $groupids The group ids.
 * @return array User ids as keys.
 */
function panoptosubmission_get_group_member_ids(array $groupids) {
    global $DB;

    if (empty($groupids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

    return array_flip($DB->get_fieldset_select('groups_members', 'userid', "groupid $insql", $params));
}

/**
 * Returns the ids of the users the current grader may grade in this activity.
 *
 * The grading table is built from this list and saves are checked against it, so a
 * crafted request cannot grade users outside the grader's groups under separate groups.
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param context $context The module context.
 * @return int[] The gradable user ids.
 */
function panoptosubmission_get_gradable_userids($cm, $context) {
    $students = array_keys(panoptosubmission_get_assignment_students($cm));

    $gradergroups = panoptosubmission_get_grader_group_limit($cm, $context);
    if (empty($students) || is_null($gradergroups)) {
        return $students;
    }

    return array_values(array_intersect($students, array_keys(panoptosubmission_get_group_member_ids($gradergroups))));
}

/**
 * Returns true when the current grader may grade one user in this activity.
 *
 * Applies the same rule as panoptosubmission_get_gradable_userids() without loading every student.
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param context $context The module context.
 * @param int $userid The user to grade.
 * @return bool True when the user is gradable.
 */
function panoptosubmission_can_grade_user($cm, $context, $userid) {
    global $DB;

    if (!is_enrolled($context, $userid, 'mod/panoptosubmission:submit')) {
        return false;
    }

    $gradergroups = panoptosubmission_get_grader_group_limit($cm, $context);
    if (is_null($gradergroups)) {
        return true;
    }

    if (empty($gradergroups)) {
        return false;
    }

    [$insql, $params] = $DB->get_in_or_equal($gradergroups, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;

    return $DB->record_exists_select('groups_members', "userid = :userid AND groupid $insql", $params);
}

/**
 * Returns the groups the current grader is limited to, or null when they may grade everyone.
 *
 * Matches the grading table, which only filters by group when the course has groups.
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param context $context The module context.
 * @return int[]|null The grader's group ids, or null for no group limit.
 */
function panoptosubmission_get_grader_group_limit($cm, $context) {
    global $USER;

    if (
        groups_get_activity_groupmode($cm) != SEPARATEGROUPS
        || has_capability('moodle/site:accessallgroups', $context)
        || !groups_get_all_groups($cm->course)
    ) {
        return null;
    }

    // Index 0 holds every group the grader belongs to, across all groupings.
    return groups_get_user_groups($cm->course, $USER->id)[0] ?? [];
}

/**
 * Cleans a grade posted from the quick grading table.
 *
 * @param string $rawgrade The posted value, already cleaned with PARAM_RAW_TRIMMED.
 * @param float $activitygrade The activity's grade setting: the maximum points, or a negative scale id.
 * @return float|null The grade to store, -1 for no grade, or null when the value is invalid.
 */
function panoptosubmission_clean_quickgrade($rawgrade, $activitygrade) {
    // An empty points box or the scale's "No grade" option.
    if ($rawgrade === '' || $rawgrade === '-1') {
        return -1.0;
    }

    if (!is_numeric($rawgrade)) {
        return null;
    }

    $grade = (float) $rawgrade;

    if ($activitygrade > 0) {
        return ($grade >= 0 && $grade <= $activitygrade) ? $grade : null;
    }

    // Scales only accept one of their own item keys.
    $menu = make_grades_menu($activitygrade);
    return array_key_exists((int) $grade, $menu) && $grade == (int) $grade ? $grade : null;
}

/**
 * Returns stored feedback as the plain text the quick grading table shows.
 *
 * The result is unescaped text; escape it with s() before output.
 *
 * @param string $comment The stored feedback, which may be HTML.
 * @param int $format The feedback's text format.
 * @return string Plain text.
 */
function panoptosubmission_comment_as_plain_text($comment, $format) {
    $comment = $comment ?? '';

    if ($format == FORMAT_PLAIN) {
        return $comment;
    }

    if ($format == FORMAT_MOODLE) {
        // Line breaks are significant in this format, as format_text() treats them.
        $comment = text_to_html($comment, null, false, true);
    }

    // Same conversion as html_to_text($comment, 0, false), but keeping the grader's words unchanged.
    $converter = new \mod_panoptosubmission\local\html2text($comment, ['width' => 0, 'do_links' => 'none']);

    return trim($converter->getText());
}

/**
 * Normalises quick grading feedback so an unedited textarea compares equal to the stored text.
 *
 * @param string $comment The feedback text, already cleaned with PARAM_TEXT.
 * @return string The text with unified line endings and no surrounding whitespace.
 */
function panoptosubmission_normalise_quickgrade_comment($comment) {
    return trim(str_replace("\r\n", "\n", $comment));
}

/**
 * Saves the grades and feedback posted from the quick grading table.
 *
 * @param object $cm Panopto Student Submission activity course module object.
 * @param object $course The course object.
 * @param object $pansubmissionactivity The activity record.
 * @param context $context The module context.
 * @param int[] $gradableuserids The users the current grader may grade.
 * @param array $postedusers The posted users[] values, cleaned with PARAM_INT.
 * @param array $postedgrades The posted menu[] values, cleaned with PARAM_RAW_TRIMMED.
 * @param array $postedcomments The posted submissioncomment[] values, cleaned with PARAM_TEXT.
 * @return int The number of edited grades that were not valid for this activity.
 */
function panoptosubmission_save_quickgrades(
    $cm,
    $course,
    $pansubmissionactivity,
    $context,
    array $gradableuserids,
    array $postedusers,
    array $postedgrades,
    array $postedcomments
) {
    global $DB, $USER;

    // Ignore any posted user the grading table would not have shown.
    $userids = array_values(array_intersect(array_map('intval', array_keys($postedusers)), $gradableuserids));
    if (empty($userids)) {
        return 0;
    }

    [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $params['panactivityid'] = $pansubmissionactivity->id;

    $existing = [];
    $records = $DB->get_records_select(
        'panoptosubmission_submission',
        "panactivityid = :panactivityid AND userid $insql",
        $params
    );
    foreach ($records as $record) {
        $existing[$record->userid] = $record;
    }

    // The table shows locked and overridden grades read-only, so they are never changed here.
    $gradinginfo = grade_get_grades($course->id, 'mod', 'panoptosubmission', $cm->instance, $userids);
    $gradebookgrades = $gradinginfo->items[0]->grades ?? [];

    $recipients = $DB->get_records_list('user', 'id', $userids);
    $pansubmissionactivity->cmidnumber = $cm->idnumber;
    $time = time();
    $changed = [];
    $invalidgrades = 0;

    foreach ($userids as $userid) {
        $gradebookgrade = $gradebookgrades[$userid] ?? null;
        if ($gradebookgrade && ($gradebookgrade->locked || $gradebookgrade->overridden)) {
            continue;
        }

        $isnew = !isset($existing[$userid]);

        if ($isnew) {
            // The instructor is grading a user who has not submitted yet.
            $usersubmission = new stdClass();
            $usersubmission->panactivityid = $pansubmissionactivity->id;
            $usersubmission->userid = $userid;
            $usersubmission->grade = -1;
        } else {
            $usersubmission = $existing[$userid];
        }

        $gradechanged = false;
        $commentchanged = false;

        if (array_key_exists($userid, $postedgrades)) {
            $rawgrade = $postedgrades[$userid];

            // An unedited grade is left alone, even one the current grade settings would no longer accept.
            if (!is_numeric($rawgrade) || (float) $rawgrade != (float) $usersubmission->grade) {
                $grade = panoptosubmission_clean_quickgrade($rawgrade, $pansubmissionactivity->grade);

                if (is_null($grade)) {
                    $invalidgrades++;
                } else if ($grade != $usersubmission->grade) {
                    $usersubmission->grade = $grade;
                    $gradechanged = true;
                }
            }
        }

        if (array_key_exists($userid, $postedcomments)) {
            // The table shows feedback as plain text, so only an edit to that text counts as a change.
            $shown = panoptosubmission_normalise_quickgrade_comment(clean_param(
                panoptosubmission_comment_as_plain_text(
                    $usersubmission->submissioncomment ?? '',
                    $usersubmission->format ?? FORMAT_MOODLE
                ),
                PARAM_TEXT
            ));

            if (panoptosubmission_normalise_quickgrade_comment($postedcomments[$userid]) !== $shown) {
                // Stored like older rows, so format_text() cleans it and the grading editor still opens as HTML.
                $usersubmission->submissioncomment = $postedcomments[$userid];
                $usersubmission->format = FORMAT_MOODLE;
                $commentchanged = true;
            }
        }

        // Leave untouched rows alone so students are not notified about unchanged feedback.
        if (!$gradechanged && !$commentchanged) {
            continue;
        }

        // Only a grade marks the submission as graded and names its grader; a comment alone does neither.
        if ($gradechanged || $isnew) {
            $usersubmission->teacher = $USER->id;
        }
        if ($gradechanged) {
            $usersubmission->timemarked = $time;
        }

        if ($isnew) {
            $usersubmission->id = $DB->insert_record('panoptosubmission_submission', $usersubmission);
        } else {
            $DB->update_record('panoptosubmission_submission', $usersubmission);
        }

        $changed[$userid] = ['submission' => $usersubmission, 'isnew' => $isnew];
    }

    if (!empty($changed)) {
        // Push every changed grade to the gradebook in one update.
        $grades = [];
        foreach (array_keys($changed) as $userid) {
            if ($grade = panoptosubmission_get_submission_grade_object($pansubmissionactivity->id, $userid)) {
                $grades[$userid] = $grade;
            }
        }
        panoptosubmission_grade_item_update($pansubmissionactivity, $grades);
    }

    foreach ($changed as $userid => $change) {
        // Send notification to student.
        if ($pansubmissionactivity->sendstudentnotifications && isset($recipients[$userid])) {
            panoptosubmission_send_notification(
                $cm,
                $course,
                $pansubmissionactivity->name,
                $change['submission'],
                $USER,
                $recipients[$userid],
                'feedbackavailable'
            );
        }

        $event = \mod_panoptosubmission\event\grades_updated::create([
            'context' => $context,
            'other' => [
                'crud' => $change['isnew'] ? 'c' : 'u',
            ],
        ]);
        $event->trigger();
    }

    return $invalidgrades;
}

/**
 * Send notifications to graders upon student submissions.
 *
 * @param object $pansubmissionactivity The submission object or NULL in which case it will be loaded
 * @param object $submission The submission that has changed.
 * @param object $cm Panopto Student Submission activity course module object.
 * @param object $context The context object.
 * @param object $course The course object.
 * @return void
 */
function panoptosubmission_notify_graders(
    $pansubmissionactivity,
    $submission,
    $cm,
    $context,
    $course
) {
    global $DB, $USER;

    $late = $pansubmissionactivity->timedue && ($pansubmissionactivity->timedue < time());

    if (!$pansubmissionactivity->sendnotifications && !($late && $pansubmissionactivity->sendlatenotifications)) {
        // No need to do anything.
        return;
    }

    if ($submission->userid) {
        $user = $DB->get_record('user', ['id' => $submission->userid]);
    } else {
        $user = $USER;
    }

    if ($teachers = panoptosubmission_get_graders($cm, $user, $context)) {
        foreach ($teachers as $teacher) {
            panoptosubmission_send_notification(
                $cm,
                $course,
                $pansubmissionactivity->name,
                $submission,
                $user,
                $teacher,
                'gradersubmissionupdated'
            );
        }
    }
}

/**
 * Creates the text content for emails.
 *
 * @param string $messagetype Message type.
 * @param object $course Course object.
 * @param object $info The info used by the messagetype language strings.
 * @param string $modulename Module name.
 * @return string
 */
function panoptosubmission_format_notification_message_text($messagetype, $course, $info, $modulename) {
    global $DB;

    if (empty($course)) {
        $param = ['id' => $info->courseid];
        $course = $DB->get_record('course', $param);
    }

    $posttext = '';

    if (!empty($course)) {
        $posttext = format_string($course->shortname, true, $course->id) .
                    ' -> ' .
                    $modulename .
                    '  -> ' .
                    format_string($info->assignment, true, $course->id) . "\n";
        $posttext .= '---------------------------------------------------------------------' . "\n";
        $posttext .= get_string($messagetype . 'text', 'panoptosubmission', $info) . "\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
    }

    return $posttext;
}

/**
 * Creates the html content for emails.
 *
 * @param string $messagetype Message type.
 * @param object $course Course object.
 * @param object $info The info used by the messagetype language strings.
 * @return string
 */
function panoptosubmission_format_notification_message_html($messagetype, $course, $info) {
    global $DB;

    if (empty($course)) {
        $param = ['id' => $info->courseid];
        $course = $DB->get_record('course', $param);
    }

    $posthtml = '';

    if (!empty($course)) {
        $posthtml .= html_writer::start_tag('p');
        $attr = ['href' => new moodle_url('/course/view.php', ['id' => $course->id])];
        $posthtml .= html_writer::tag('a', format_string($course->shortname, true, $course->id), $attr);
        $posthtml .= '->';
        $attr = ['href' => new moodle_url('/mod/panoptosubmission/view.php', ['id' => $info->cmid])];
        $posthtml .= html_writer::tag('a', format_string($info->assignment, true, $course->id), $attr);
        $posthtml .= html_writer::end_tag('p');
        $posthtml .= html_writer::start_tag('hr');
        $posthtml .= html_writer::tag('p', get_string($messagetype . 'html', 'panoptosubmission', $info));
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
    global $USER;

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
                $gradinginstance = $controller->get_or_create_instance(
                    $instanceid,
                    $USER->id,
                    $itemid
                );
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

/**
 * Creates an panoptosubmission_submissions_feedback_status renderable.
 *
 * @param object $cm Panopto video assignment course module object.
 * @param object $pansubmissionactivity The submission object or NULL in which case it will be loaded
 * @param object $submission current submission with grade information
 * @param object $context A context object.
 * @param string $userid of the user to get the report for
 * @param object $grade user grade
 * @param object $teacher user that graded the submission
 * @return panoptosubmission_submissions_feedback_status renderable object
 */
function panoptosubmission_get_feedback_status_renderable(
    $cm,
    $pansubmissionactivity,
    $submission,
    $context,
    $userid,
    $grade,
    $teacher
) {
    global $DB, $PAGE;

    $gradinginfo = grade_get_grades(
        $pansubmissionactivity->course,
        'mod',
        'panoptosubmission',
        $cm->instance,
        $userid
    );

    $gradingitem = null;
    $gradebookgrade = null;
    if (isset($gradinginfo->items[0])) {
        $gradingitem = $gradinginfo->items[0];
        $gradebookgrade = $gradingitem->grades[$userid];
    }

    $cangrade = has_capability('mod/panoptosubmission:gradesubmission', $context);
    $hasgrade = !is_null($gradebookgrade) && !is_null($gradebookgrade->grade);
    $gradevisible = $cangrade || (!is_null($gradebookgrade) && !$gradebookgrade->hidden);

    // If there is a visible grade, show the summary.
    if ($hasgrade && $gradevisible) {
        $gradefordisplay = null;
        $gradeddate = null;
        $grader = null;
        $gradingmanager = get_grading_manager($context, 'mod_panoptosubmission', 'submissions');

        // Criteria feedback.
        if ($controller = $gradingmanager->get_active_controller()) {
            $menu = make_grades_menu($submission->grade);
            $controller->set_grade_range($menu, $submission->grade > 0);
            $gradefordisplay = $controller->render_grade(
                $PAGE,
                $submission->id,
                $gradingitem,
                $gradebookgrade->str_long_grade,
                $cangrade
            );
        } else {
            // Normal feedback, which is just a grade.
            $gradefordisplay = $grade->str_long_grade;
        }
        $gradeddate = $gradebookgrade->dategraded;

        if (isset($teacher)) {
            $grader = $DB->get_record('user', ['id' => $teacher->id]);
        } else if (
            isset($gradebookgrade->usermodified)
            && $gradebookgrade->usermodified > 0
            && has_capability('mod/panoptosubmission:gradesubmission', $context, $gradebookgrade->usermodified)
        ) {
            // Grader not provided. Check that usermodified is a user who can grade.
            // Case 1: When an assignment is reopened an empty grade is created so the feedback
            // plugin can know which attempt it's referring to. In this case, usermodifed is a student.
            // Case 2: When an assignment's grade is overrided via the gradebook, usermodified is a grader.
            $grader = $DB->get_record('user', ['id' => $gradebookgrade->usermodified]);
        }

        $viewfullnames = has_capability('moodle/site:viewfullnames', $context);
        $feedbackstatus = new panoptosubmission_submissions_feedback_status(
            $gradefordisplay,
            $gradeddate,
            $grader,
            $grade,
            $cm->id,
            $viewfullnames
        );

        return $feedbackstatus;
    }
    return;
}

/**
 * Creates an panoptosubmission_grading_summary renderable.
 *
 * @param object $cm Panopto video assignment course module object.
 * @param object $course Course object.
 * @return panoptosubmission_grading_summary renderable object
 */
function panoptosubmission_get_grading_summary_renderable($cm, $course) {
    global $DB;
    $instance = $DB->get_record('panoptosubmission', ['id' => $cm->instance], '*', MUST_EXIST);

    $isvisible = $cm->visible;
    $countparticipants = count(array_keys(panoptosubmission_get_assignment_students($cm)));

    $submissionssubmitted = panoptosubmission_get_submissions($cm->instance, PANOPTOSUBMISSION_SUBMITTED);
    $submissionssubmittedcount = $submissionssubmitted ? count($submissionssubmitted) : 0;

    $submissionrequiregrading = panoptosubmission_get_submissions($cm->instance, PANOPTOSUBMISSION_REQ_GRADING);
    $submissionrequiregradingcount = $submissionrequiregrading ? count($submissionrequiregrading) : 0;

    $summary = new panoptosubmission_grading_summary(
        $countparticipants,
        true,
        $submissionssubmittedcount,
        $instance->cutofftime,
        $instance->timedue,
        $instance->timeavailable,
        $course->id,
        $submissionrequiregradingcount,
        $course->relativedatesmode,
        $course->startdate,
        $isvisible
    );

    return $summary;
}

/**
 * Provision the course if not provisioned already.
 *
 * @param int $courseid - the id of the course we are targetting in moodle.
 * @return bool if success or failure
 */
function panoptosubmission_verify_panopto($courseid) {
    $targetautoservername = get_config('block_panopto', 'automatic_operation_target_server');
    if (empty($targetautoservername)) {
        throw new moodle_exception('no_automatic_operation_target_server', 'panoptosubmission');
    }

    try {
        $targetserver = panopto_get_target_panopto_server();
        $panopto = new \panopto_data($courseid);

        if (!$panopto->has_valid_panopto()) {
            $panopto->servername = $targetserver->name;
            $panopto->applicationkey = $targetserver->appkey;
            $provisioninginfo = $panopto->get_provisioning_info();

            if (
                (isset($provisioninginfo->unknownerror) && $provisioninginfo->unknownerror === true)
                || (isset($provisioninginfo->accesserror) && $provisioninginfo->accesserror === true)
            ) {
                return false;
            }

            $panopto->provision_course($provisioninginfo, false);
        }
        return true;
    } catch (Exception $e) {
        \panopto_data::print_log($e->getMessage());
        return false;
    }
}

/**
 * Ensure the user is logged in, or perform a cross-site repost if cookies were blocked.
 *
 * @param int $courseid Course to require login for after repost succeeds.
 */
function panoptosubmission_require_login_or_repost(int $courseid): void {
    global $CFG, $PAGE;

    require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');

    $context = context_course::instance($courseid);
    $PAGE->set_pagelayout('popup');
    $PAGE->set_context($context);

    // The block version this plugin depends on always provides the shared helper.
    \panoptoblock_lti_utility::require_login_or_repost($courseid, $context);
}
