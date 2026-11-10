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
 * Data generator for the Panopto Student Submission module.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator for the Panopto Student Submission module.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_panoptosubmission_generator extends testing_module_generator {
    /**
     * Create an activity instance with the settings the add form would supply.
     *
     * @param array|stdClass|null $record Activity settings.
     * @param array|null $options Course module options.
     * @return stdClass The activity record with the course module id.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record + [
            'grade' => 100,
            'timeavailable' => 0,
            'timedue' => 0,
            'cutofftime' => 0,
            'preventlate' => 0,
            'resubmit' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'sendstudentnotifications' => 0,
        ];

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a submission record.
     *
     * @param stdClass $activity The activity record returned by create_instance().
     * @param int $userid The submitting user.
     * @param array $record Fields that override the defaults.
     * @return stdClass The submission record.
     */
    public function create_submission($activity, $userid, array $record = []) {
        global $DB;

        $time = time();
        $submission = (object) ($record + [
            'panactivityid' => $activity->id,
            'userid' => $userid,
            'grade' => -1,
            'source' => 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1',
            'title' => 'Session one',
            'timecreated' => $time,
            'timemodified' => $time,
        ]);

        $submission->id = $DB->insert_record('panoptosubmission_submission', $submission);

        return $submission;
    }
}
