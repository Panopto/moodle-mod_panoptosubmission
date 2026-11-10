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
        $courseid = $event->courseid;
        try {
            // This event only fires once core has deleted the course contents, so anything left here is a leftover.
            // Delete all panoptosubmission activities left in the course.
            panoptosubmission_delete_course_instances($courseid);
        } catch (Exception $e) {
            // Log error but don't fail the entire course deletion process.
            debugging('Panoptosubmission: Error deleting activities during course content deletion for course ' .
                $courseid . ': ' . $e->getMessage());
            mtrace('Warning: Failed to delete some panoptosubmission activities during course content deletion ' .
                'for course ' . $courseid);
        }
    }

    /**
     * Handle course restored event, which also covers imports and course copies.
     *
     * Restores do not call add_instance(), so queue provisioning for any restored activity here.
     *
     * @param \core\event\course_restored $event
     */
    public static function course_restored(\core\event\course_restored $event) {
        global $DB;

        if ($DB->record_exists('panoptosubmission', ['course' => $event->objectid])) {
            panoptosubmission_queue_course_provisioning($event->objectid);
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
            panoptosubmission_delete_course_instances($courseid);

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
