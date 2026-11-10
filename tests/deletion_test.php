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
 * Tests for deleting, backing up and restoring the activity.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_panoptosubmission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/panoptosubmission/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use mod_panoptosubmission\privacy\provider;

/**
 * Tests for deleting, backing up and restoring the activity.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion_test extends \advanced_testcase {
    /**
     * Deleting finishes the clean-up even when an earlier attempt already removed the activity row.
     *
     * Releases from July 2024 to November 2025 deleted the row and then threw, leaving the rest behind.
     *
     * @covers ::panoptosubmission_delete_instance
     */
    public function test_delete_instance_finishes_after_partial_delete(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $activity] = $this->create_activity_with_submission(['timedue' => time() + DAYSECS]);

        $DB->delete_records('panoptosubmission', ['id' => $activity->id]);

        $this->assertTrue(panoptosubmission_delete_instance($activity->id));
        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['panactivityid' => $activity->id]));
        $this->assertFalse($DB->record_exists('event', ['modulename' => 'panoptosubmission', 'instance' => $activity->id]));
        $this->assertFalse(\grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'panoptosubmission',
            'iteminstance' => $activity->id,
        ]));
    }

    /**
     * A background deletion that an older release interrupted completes on its next run.
     *
     * @covers ::panoptosubmission_delete_instance
     */
    public function test_interrupted_background_deletion_completes(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('coursebinenable', 0, 'tool_recyclebin');
        [, $activity, $cm] = $this->create_activity_with_submission();

        // Queue the deletion the way core does when an activity is deleted in the background.
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm->id]);
        $task = new \core_course\task\course_delete_modules();
        $task->set_custom_data([
            'cms' => [$DB->get_record('course_modules', ['id' => $cm->id])],
            'userid' => $USER->id,
            'realuserid' => $USER->id,
        ]);
        \core\task\manager::queue_adhoc_task($task);

        // The interrupted first attempt had already removed the activity row.
        $DB->delete_records('panoptosubmission', ['id' => $activity->id]);

        ob_start();
        try {
            $this->runAdhocTasks('\core_course\task\course_delete_modules');
        } finally {
            ob_end_clean();
        }

        $this->assertFalse($DB->record_exists('course_modules', ['id' => $cm->id]));
    }

    /**
     * Restoring over a course with "Delete the contents of this course" replaces the activity and its data.
     *
     * @covers \restore_panoptosubmission_activity_structure_step
     * @covers \backup_panoptosubmission_activity_structure_step
     */
    public function test_restore_replacing_course_contents(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('coursebinenable', 0, 'tool_recyclebin');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $activity = $generator->create_module('panoptosubmission', [
            'course' => $course->id,
            'timedue' => time() + DAYSECS,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,
        ]);
        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);

        $submission = $this->get_plugin_generator()->create_submission($activity, $student->id, [
            'title' => 'Session one',
            'customdata' => '{"session":"abc"}',
            'grade' => 75,
            'teacher' => $teacher->id,
            'timemarked' => time(),
            'submissioncomment' => 'See the attached notes.',
            'format' => FORMAT_HTML,
        ]);
        $this->add_feedback_file($cm, $submission->id);
        panoptosubmission_grade_item_update(
            (object) array_merge((array) $activity, ['cmidnumber' => '']),
            (object) ['userid' => $student->id, 'rawgrade' => 75]
        );

        $backupid = $this->backup_course($course->id);

        $this->restore_backup($backupid, $course->id, \backup::TARGET_CURRENT_DELETING);

        // The old activity is gone and exactly one restored copy remains.
        $this->assertFalse($DB->record_exists('panoptosubmission', ['id' => $activity->id]));
        $restored = $DB->get_records('panoptosubmission', ['course' => $course->id]);
        $this->assertCount(1, $restored);
        $restored = reset($restored);

        $restoredsubmission = $DB->get_record('panoptosubmission_submission', ['panactivityid' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $restoredsubmission->userid);
        $this->assertSame('Session one', $restoredsubmission->title);
        $this->assertSame('{"session":"abc"}', $restoredsubmission->customdata);
        $this->assertEquals($teacher->id, $restoredsubmission->teacher);

        $restoredcm = get_coursemodule_from_instance('panoptosubmission', $restored->id);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($restoredcm->id)->id,
            'mod_panoptosubmission',
            'studentsubmission_files',
            $restoredsubmission->id,
            'filename',
            false
        );
        $this->assertCount(1, $files);
    }

    /**
     * Restoring into a course with a later start date moves the activity's dates with it.
     *
     * @covers \restore_panoptosubmission_activity_structure_step
     */
    public function test_restore_shifts_activity_dates(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $start = make_timestamp(2026, 1, 5);
        $course = $this->getDataGenerator()->create_course(['startdate' => $start]);
        $activity = $this->getDataGenerator()->create_module('panoptosubmission', [
            'course' => $course->id,
            'timeavailable' => $start + DAYSECS,
            'timedue' => $start + 7 * DAYSECS,
            'cutofftime' => $start + 10 * DAYSECS,
        ]);

        $backupid = $this->backup_course($course->id);

        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $this->restore_backup($backupid, $newcourseid, \backup::TARGET_NEW_COURSE, $start + 28 * DAYSECS);

        $restored = $DB->get_record('panoptosubmission', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals($activity->timeavailable + 28 * DAYSECS, $restored->timeavailable);
        $this->assertEquals($activity->timedue + 28 * DAYSECS, $restored->timedue);
        $this->assertEquals($activity->cutofftime + 28 * DAYSECS, $restored->cutofftime);
    }

    /**
     * Deleting a user's data does not fail, and removes their submission (the error reported on user deletion).
     *
     * @covers \mod_panoptosubmission\privacy\provider::delete_data_for_user
     */
    public function test_privacy_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        [, $activity, $cm, $submission, $student] = $this->create_activity_with_submission();
        $context = \context_module::instance($cm->id);

        provider::delete_data_for_user(new approved_contextlist($student, 'mod_panoptosubmission', [$context->id]));

        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['id' => $submission->id]));
    }

    /**
     * Deleting a list of users' data copes with an activity whose row is already gone.
     *
     * @covers \mod_panoptosubmission\privacy\provider::delete_data_for_users
     */
    public function test_privacy_delete_data_for_users_without_activity(): void {
        global $DB;

        $this->resetAfterTest();
        [, $activity, $cm, , $student] = $this->create_activity_with_submission();
        $context = \context_module::instance($cm->id);

        $DB->delete_records('panoptosubmission', ['id' => $activity->id]);

        provider::delete_data_for_users(new approved_userlist($context, 'mod_panoptosubmission', [$student->id]));

        // Nothing to delete through the activity, and no error either.
        $this->assertTrue($DB->record_exists('panoptosubmission_submission', ['panactivityid' => $activity->id]));
    }

    /**
     * Deleting all data in a context removes every submission and feedback file without loading locallib.php.
     *
     * @covers \mod_panoptosubmission\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_privacy_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        [, $activity, $cm, $submission] = $this->create_activity_with_submission();
        $context = \context_module::instance($cm->id);
        $this->add_feedback_file($cm, $submission->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['panactivityid' => $activity->id]));
        $this->assertEmpty(get_file_storage()->get_area_files(
            $context->id,
            'mod_panoptosubmission',
            'studentsubmission_files',
            false,
            'filename',
            false
        ));
    }

    /**
     * Create an activity with one graded student submission.
     *
     * @param array $record Activity settings that override the defaults.
     * @return array [course, activity record, course module, submission, student]
     */
    protected function create_activity_with_submission(array $record = []): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $activity = $generator->create_module('panoptosubmission', ['course' => $course->id] + $record);
        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);
        $submission = $this->get_plugin_generator()->create_submission($activity, $student->id, ['grade' => 60]);

        return [$course, $activity, $cm, $submission, $student];
    }

    /**
     * Store a feedback file for a submission.
     *
     * @param \stdClass $cm The course module.
     * @param int $submissionid The submission id, used as the file item id.
     * @return void
     */
    protected function add_feedback_file($cm, $submissionid): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($cm->id)->id,
            'component' => 'mod_panoptosubmission',
            'filearea' => 'studentsubmission_files',
            'itemid' => $submissionid,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'Feedback notes');
    }

    /**
     * Back up a course with user data and return the backup id to restore from.
     *
     * @param int $courseid The course id.
     * @return string The backup id.
     */
    protected function backup_course($courseid): string {
        global $CFG, $USER;

        // No file logger, as in core's tests, so the backup folder can be removed on Windows.
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // A full backup copies the files into the archive, as a backup downloaded from the site would.
        // An import backup only points at the originals, which "Delete the contents" removes before restoring.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();

        $backupid = 'panoptosubmission_' . $courseid;
        $bc->get_results()['backup_destination']->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupid)
        );
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restore a backup with user data.
     *
     * @param string $backupid The backup id.
     * @param int $courseid The course to restore into.
     * @param int $target One of the backup::TARGET_* constants.
     * @param int|null $startdate A new course start date, or null to keep the backed up one.
     * @return void
     */
    protected function restore_backup(string $backupid, int $courseid, int $target, ?int $startdate = null): void {
        global $USER;

        $rc = new \restore_controller($backupid, $courseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id, $target);
        $rc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(true);
        if (!is_null($startdate)) {
            $rc->get_plan()->get_setting('course_startdate')->set_value($startdate);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * Return the plugin's data generator.
     *
     * @return \mod_panoptosubmission_generator
     */
    protected function get_plugin_generator() {
        return $this->getDataGenerator()->get_plugin_generator('mod_panoptosubmission');
    }
}
