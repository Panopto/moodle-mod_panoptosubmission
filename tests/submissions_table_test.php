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
 * Unit tests for the Panopto Student Submission submissions table.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_panoptosubmission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/panoptosubmission/classes/table/panoptosubmission_submissions_table.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Unit tests for the Panopto Student Submission submissions table.
 *
 * The table is loaded on its own rather than through locallib.php, which requires block_panopto.
 * CI installs this plugin alone, so these tests have to run without it.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class submissions_table_test extends \advanced_testcase {
    /**
     * Render the grade cell for one student of a scale graded activity.
     *
     * The scale is "Bad,Good,Great", so the stored grade 3 is "Great".
     *
     * @param int $storedgrade The grade stored for the student, -1 for "No grade".
     * @param bool $quickgrade Whether the table renders the quick grading form.
     * @param bool $marked Whether the submission has ever been graded.
     * @return string The rendered cell.
     */
    private function render_grade_cell(int $storedgrade, bool $quickgrade = true, bool $marked = true): string {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Bad,Good,Great']);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_panoptosubmission');
        $activity = $generator->create_instance(['course' => $course->id, 'grade' => -$scale->id]);

        $record = ['grade' => $storedgrade];
        if ($marked) {
            $record['timemarked'] = time();
        }
        $generator->create_submission($activity, $student->id, $record);

        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id, $course->id);

        // Read the row back so the grade carries the representation the database returns, which for
        // a NUMBER column is a string such as "3.00" rather than the integer that was inserted.
        $rowdata = $DB->get_record('panoptosubmission_submission', [
            'panactivityid' => $activity->id,
            'userid' => $student->id,
        ]);

        // The table is keyed by user, as the submissions query returns it.
        $rowdata->id = $student->id;

        $currentgrades = grade_get_grades($course->id, 'mod', 'panoptosubmission', $activity->id, [$student->id]);

        $table = new \panoptosubmission_submissions_table('testtable', $cm, $currentgrades, $quickgrade);

        return $table->col_selectgrade($rowdata);
    }

    /**
     * Return the value of the selected option, or null when no option is marked selected.
     *
     * html_writer emits selected before value, so read the value rather than match a fixed order.
     *
     * @param string $html The rendered cell.
     * @return string|null The selected option's value.
     */
    private function selected_option_value(string $html): ?string {
        if (!preg_match('/<option\b(?=[^>]*\bselected\b)[^>]*\bvalue="([^"]*)"/i', $html, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * A grade already recorded on a scale graded activity is preselected in the quick grading menu.
     *
     * The stored grade arrives as "3.00" while the menu keys are plain integers, and html_writer
     * compares them as strings. Without a cast nothing is preselected, the browser posts the leading
     * "No grade" option, and saving quick grades clears every already graded row on the page.
     *
     * @covers \panoptosubmission_submissions_table::col_selectgrade
     */
    public function test_scale_grade_is_preselected_for_quick_grading(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame('3', $this->selected_option_value($this->render_grade_cell(3)));
    }

    /**
     * A row that was graded and then cleared selects "No grade".
     *
     * @covers \panoptosubmission_submissions_table::col_selectgrade
     */
    public function test_cleared_scale_row_selects_no_grade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame('-1', $this->selected_option_value($this->render_grade_cell(-1)));
    }

    /**
     * A row that was never graded marks nothing selected, so the leading "No grade" option applies.
     *
     * @covers \panoptosubmission_submissions_table::col_selectgrade
     */
    public function test_never_graded_scale_row_offers_no_grade_first(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $output = $this->render_grade_cell(-1, true, false);

        $this->assertNull($this->selected_option_value($output));
        $this->assertSame(1, preg_match('/<option\b[^>]*\bvalue="([^"]*)"/i', $output, $first));
        $this->assertSame('-1', $first[1]);
    }

    /**
     * With quick grading off, a stored scale grade is shown by its scale item, not as "-".
     *
     * @covers \panoptosubmission_submissions_table::display_grade
     */
    public function test_scale_grade_is_displayed_without_quick_grading(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertStringContainsString('Great', $this->render_grade_cell(3, false));
    }
}
