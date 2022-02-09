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
 * Panopto Submission backup stepslib.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Panopto Submission backup stepslib backup structure class
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_panoptosubmission_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the structure needed to backup panopto student submissions
     *
     * @return the backup structure object
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $columns = array(
                'course',
                'name',
                'intro',
                'introformat',
                'timeavailable',
                'timedue',
                'preventlate',
                'resubmit',
                'emailteachers',
                'grade',
                'timecreated',
                'timemodified'
        );
        $panoptosubmission = new backup_nested_element('panoptosubmission', array('id'), $columns);

        $issues = new backup_nested_element('submissions');

        $columns = array(
                'userid',
                'entry_id',
                'source',
                'width',
                'height',
                'grade',
                'submissioncomment',
                'format',
                'teacher',
                'mailed',
                'timemarked',
                'timecreated',
                'timemodified'
        );
        $issue = new backup_nested_element('submission', array('id'), $columns);

        $panoptosubmission->add_child($issues);
        $issues->add_child($issue);
        $panoptosubmission->set_source_table('panoptosubmission', array('id' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $issue->set_source_table('panoptosubmission_submission', array('panactivityid' => backup::VAR_PARENTID));
        }

        $issue->annotate_ids('user', 'userid');
        $issue->annotate_files('mod_panoptosubmission', 'submission', 'id');

        return $this->prepare_activity_structure($panoptosubmission);
    }
}
