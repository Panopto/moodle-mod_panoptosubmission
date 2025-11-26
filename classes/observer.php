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
 * Event observer for panoptosubmission.
 *
 * @package    mod_panoptosubmission
 * @copyright  Panopto 2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/panoptosubmission/lib.php');
require_once($CFG->libdir . '/moodlelib.php');

/**
 * Event observer for panoptosubmission events.
 *
 * @package    mod_panoptosubmission
 * @copyright  Panopto 2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_panoptosubmission_observer {
    /**
     * Handle course content deleted event.
     *
     * @param \core\event\course_content_deleted $event
     */
    public static function course_content_deleted(\core\event\course_content_deleted $event) {
        global $DB;

        $courseid = $event->courseid;
        try {
            // Safety check: Only delete data if this is permanent deletion, not recycle bin move.
            // If course still exists but is in recycle bin, don't delete the module data yet.
            $course = $DB->get_record('course', ['id' => $courseid]);
            if ($course && !empty($course->deletioninprogress)) {
                // Course is in recycle bin, not permanently deleted. Don't delete module data.
                return;
            }

            // Get all panoptosubmission activities for the course.
            $activities = $DB->get_records('panoptosubmission', ['course' => $courseid]);

            foreach ($activities as $activity) {
                // Delete each activity instance.
                panoptosubmission_delete_instance($activity->id);
            }
        } catch (Exception $e) {
            // Log error but don't fail the entire course deletion process.
            debugging('Panoptosubmission: Error deleting activities during course content deletion for course ' .
                $courseid . ': ' . $e->getMessage());
            mtrace('Warning: Failed to delete some panoptosubmission activities during course content deletion ' .
                'for course ' . $courseid);
        }
    }

    /**
     * Handle course deleted event.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        $courseid = $event->courseid;

        try {
            // At this point, the course should be permanently deleted, but double-check for safety.
            // This event should only fire for permanent deletion, not recycle bin moves.
            // Clean up any remaining panoptosubmission data that might not have been deleted during content deletion.
            $activities = $DB->get_records('panoptosubmission', ['course' => $courseid]);

            foreach ($activities as $activity) {
                // Delete each activity instance.
                panoptosubmission_delete_instance($activity->id);
            }

            // Also clean up any orphaned submissions.
            $DB->delete_records_select(
                'panoptosubmission_submission',
                'panactivityid NOT IN (SELECT id FROM {panoptosubmission})'
            );
        } catch (Exception $e) {
            // Log error but don't fail the entire course deletion process.
            debugging('Panoptosubmission: Error cleaning up data during course deletion for course ' .
                $courseid . ': ' . $e->getMessage());
            mtrace('Warning: Failed to clean up some panoptosubmission data during course deletion ' .
                'for course ' . $courseid);
        }
    }
}
