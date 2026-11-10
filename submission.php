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
 * submission page for the Panopto Student Submission module.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

global $USER, $OUTPUT, $DB, $PAGE;

if (! $cm = get_coursemodule_from_id('panoptosubmission', $cmid)) {
    throw new moodle_exception('invalidcoursemodule');
}

if (! $course = $DB->get_record('course', ['id' => $cm->course])) {
    throw new moodle_exception('coursemisconf');
}

if (! $pansubmissionactivity = $DB->get_record('panoptosubmission', ['id' => $cm->instance])) {
    throw new moodle_exception('invalidid', 'panoptosubmission');
}

require_course_login($course->id, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/panoptosubmission:submit', $context);

// This writes a submission, so only accept the form post, never a link.
if (!data_submitted()) {
    throw new moodle_exception('invalidrequest', 'error');
}
require_sesskey();

$source = required_param('source', PARAM_URL);
$customdata = required_param('customdata', PARAM_TEXT);
$width = required_param('width', PARAM_INT);
$height = required_param('height', PARAM_INT);
$title = required_param('sessiontitle', PARAM_TEXT);
$thumbnailsource = required_param('thumbnailsource', PARAM_URL);
$thumbnailwidth = required_param('thumbnailwidth', PARAM_INT);
$thumbnailheight = required_param('thumbnailheight', PARAM_INT);

$PAGE->set_url('/mod/panoptosubmission/view.php', ['id' => $course->id]);
$PAGE->set_title(format_string($pansubmissionactivity->name));
$PAGE->set_heading($course->fullname);

if (panoptosubmission_submission_past_cutoff($pansubmissionactivity)) {
    throw new moodle_exception('assignmentexpired', 'panoptosubmission', 'course/view.php?id=' . $course->id);
} else if (panoptosubmission_submission_past_due($pansubmissionactivity)) {
    throw new moodle_exception('assignmentpastdue', 'panoptosubmission', 'course/view.php?id=' . $course->id);
}

// These URLs are stored and later rendered, so constrain them here.
$url = new moodle_url('/mod/panoptosubmission/view.php', ['id' => $cm->id]);

// A submission needs a real video on an allowed host.
if (empty($source) || !panoptosubmission_is_allowed_source_url($source)) {
    // Return to the activity and leave the submission unchanged.
    redirect(
        $url,
        get_string('invalid_content_host', 'panoptosubmission'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$thumbnailsource = panoptosubmission_sanitise_thumbnail_url($thumbnailsource);

echo $OUTPUT->header();

$param = ['panactivityid' => $pansubmissionactivity->id, 'userid' => $USER->id];
$submission = $DB->get_record('panoptosubmission_submission', $param);

$time = time();
$isnew = empty($submission);

if ($isnew) {
    $submission = new stdClass();
    $submission->userid = $USER->id;
    $submission->panactivityid = $pansubmissionactivity->id;
    $submission->grade = -1;
    $submission->timecreated = $time;
} else if (0 == $submission->timecreated) {
    // A grader may have created the record before the student submitted.
    $submission->timecreated = $time;
}

$submission->source = $source;
$submission->customdata = $customdata;
$submission->width = $width;
$submission->height = $height;
$submission->title = $title;
$submission->thumbnailsource = $thumbnailsource;
$submission->thumbnailwidth = $thumbnailwidth;
$submission->thumbnailheight = $thumbnailheight;
$submission->timemodified = $time;

if ($isnew) {
    $saved = $submission->id = $DB->insert_record('panoptosubmission_submission', $submission);
} else {
    $saved = $DB->update_record('panoptosubmission_submission', $submission);
}

if (!$saved) {
    notice(get_string('failedtoinsertsubmission', 'panoptosubmission'), $url, $course);
}

echo $OUTPUT->notification(get_string('assignmentsubmitted', 'panoptosubmission'), 'success');

echo html_writer::start_tag('center');
echo $OUTPUT->single_button($url, get_string('continue'), 'post');
echo html_writer::end_tag('center');

$event = \mod_panoptosubmission\event\assignment_submitted::create([
    'objectid' => $pansubmissionactivity->id,
    'context' => $context,
]);
$event->trigger();

// Email an alert to the teacher.
panoptosubmission_notify_graders(
    $pansubmissionactivity,
    $submission,
    $cm,
    $context,
    $course
);

echo $OUTPUT->footer();
