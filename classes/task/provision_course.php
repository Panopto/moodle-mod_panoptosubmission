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

namespace mod_panoptosubmission\task;

/**
 * Provisions a course with Panopto in the background.
 *
 * Queued from sesskey-protected saves and course restores, so neither a page view nor a
 * form save waits on, or prints output from, the Panopto provisioning calls.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision_course extends \core\task\adhoc_task {
    /**
     * Provision the course named in the custom data.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        $courseid = (int) ($this->get_custom_data()->courseid ?? 0);
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return;
        }

        // Throw rather than return, so core marks the task failed and retries it with backoff.
        if (empty(get_config('block_panopto', 'automatic_operation_target_server'))) {
            throw new \moodle_exception('no_automatic_operation_target_server', 'panoptosubmission');
        }

        // Loaded only now, as it pulls in the Panopto block's libraries.
        require_once($CFG->dirroot . '/mod/panoptosubmission/locallib.php');

        if (!panoptosubmission_verify_panopto($courseid)) {
            throw new \moodle_exception('provisioncoursefailed', 'panoptosubmission', '', $courseid);
        }
    }
}
