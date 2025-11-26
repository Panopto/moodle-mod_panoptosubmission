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
 * Table class for displaying video submissions for grading
 *
 * @package mod_panoptosubmission
 * @copyright Panopto 2025
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Table class for displaying video submissions for grading
 */
class panoptosubmission_submissions_table extends table_sql {
    /**
     * @var bool $quickgrade Set to true if a quick grade form needs to be rendered.
     */
    public $quickgrade;
    /**
     * @var object $currentgrades Current grade information for an activity
     */
    public $currentgrades;
    /**
     * @var int $cminstance The course module instnace id.
     */
    public $cminstance;
    /**
     * @var int $grademax The maximum grade for an activity
     */
    public $grademax;
    /**
     * @var string $tifirst First initial of the first name, needed for name filter
     */
    public $tifirst;
    /**
     * @var string $tilast First initial of the last name, needed for name filter
     */
    public $tilast;
    /**
     * @var int $page The current page number.
     */
    public $page;
    /**
     * @var int $courseid The current course ID
     */
    public $courseid;
    /**
     * @var int $cols The number of columns of the quick grade textarea element.
     */
    public $cols = 20;
    /**
     * @var int $rows The number of rows of the quick grade textarea element.
     */
    public $rows = 4;

    /**
     * Constructor function for the submissions table class.
     * @param int $uniqueid Unique id.
     * @param int $cm Course module id.
     * @param object $currentgrades The current grades for the activity, returned from grade_get_grades.
     * @param bool $quickgrade Set to true if quick grade was enabled
     * @param string $tifirst The first initial of the first name filter.
     * @param string $tilast The first initial of the first name filter.
     * @param int $page The current page number.
     */
    public function __construct($uniqueid, $cm, $currentgrades, $quickgrade = false, $tifirst = '', $tilast = '', $page = 0) {
        global $DB;

        parent::__construct($uniqueid);

        $this->currentgrades = $currentgrades;
        $this->quickgrade = $quickgrade;
        $this->grademax = $this->currentgrades->items[0]->grademax;
        $this->tifirst = $tifirst;
        $this->tilast = $tilast;
        $this->page = $page;
        $this->courseId = $cm->course;

        $instance = $DB->get_record('panoptosubmission', ['id' => $cm->instance], 'id,grade,timedue');
        $instance->cmid = $cm->id;
        $this->cminstance = $instance;
    }

    /**
     * The function renders the picture column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_picture($rowdata) {
        global $OUTPUT;

        $user = new stdClass();
        $user->id = $rowdata->id;
        $user->picture = $rowdata->picture;
        $user->imagealt = $rowdata->imagealt;
        $user->firstname = $rowdata->firstname;
        $user->lastname = $rowdata->lastname;
        $user->email = $rowdata->email;
        $user->alternatename = $rowdata->alternatename;
        $user->middlename = $rowdata->middlename;
        $user->firstnamephonetic = $rowdata->firstnamephonetic;
        $user->lastnamephonetic = $rowdata->lastnamephonetic;

        $output = $OUTPUT->user_picture($user);

        $attr = ['type' => 'hidden', 'name' => 'users[' . $rowdata->id . ']', 'value' => $rowdata->id];
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }

    /**
     * The function renders the submission status column.
     * @param object $data information about the current row being rendered.
     * @return string HTML markup.
     */
    public function col_status($data) {
        global $OUTPUT, $CFG;

        $url = new moodle_url(
            '/mod/panoptosubmission/single_submission.php',
            ['cmid' => $this->cminstance->cmid, 'userid' => $data->id, 'sesskey' => sesskey()]
        );

        if (!empty($this->tifirst)) {
            $url->param('tifirst', $this->tifirst);
        }

        if (!empty($this->tilast)) {
            $url->param('tilast', $this->tilast);
        }

        if (!empty($this->page)) {
            $url->param('page', $this->page);
        }

        $submitted = !is_null($data->timemodified) && !empty($data->timemodified);
        $output = '';

        if ($data->timemarked > 0) {
            $gradedstatusattributes = [
                'class' => 'mod-panoptosubmission-status-graded',
            ];
            $output .= html_writer::tag('div', get_string('has_grade', 'panoptosubmission'), $gradedstatusattributes);
        } else {
            $gradedstatusattributes = [
                'class' => 'mod-panoptosubmission-status-not-graded',
            ];
            $output .= html_writer::tag('div', get_string('needs_grade', 'panoptosubmission'), $gradedstatusattributes);
        }

        $due = $this->cminstance->timedue;
        if (!empty($this->quickgrade) && $submitted && ($data->timemodified > $due)) {
            $latestr = get_string('late', 'panoptosubmission', format_time($data->timemodified - $due));
            $lateattributes = [
                'class' => 'mod-panoptosubmission-latesubmission',
            ];
            $output .= html_writer::tag('div', $latestr, $lateattributes);
        }

        if (!$submitted) {
            $gradedstatusattributes = [
                'class' => 'mod-panoptosubmission-status-not-submitted',
            ];
            $output .= html_writer::tag('div', get_string('nosubmission', 'panoptosubmission'), $gradedstatusattributes);

            $now = time();
            if ($due && ($now > $due)) {
                $overduestr = get_string('overdue', 'assign', format_time($now - $due));
                $overdueattributes = [
                    'class' => 'mod-panoptosubmission-overduesubmission',
                ];
                $output .= html_writer::tag('div', $overduestr, $overdueattributes);
            }
        }

        return $output;
    }

    /**
     * The function renders the select grade column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_selectgrade($rowdata) {
        global $CFG;

        $output = '';
        $finalgrade = false;

        if (array_key_exists($rowdata->id, $this->currentgrades->items[0]->grades)) {
            $finalgrade = $this->currentgrades->items[0]->grades[$rowdata->id];

            if ($CFG->enableoutcomes) {
                $finalgrade->formatted_grade = $this->currentgrades->items[0]->grades[$rowdata->id]->str_grade;
            } else {
                // Taken from mod/assignment/lib.php display_submissions().
                $finalgrade->formatted_grade = round($finalgrade->grade ?? 0, 2) . ' / ' . round($this->grademax, 2);
            }
        }

        if (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden)) {
            $lockedoverridden = 'locked';

            if ($finalgrade->overridden) {
                $lockedoverridden = 'overridden';
            }
            $attr = ['id' => 'g' . $rowdata->id, 'class' => $lockedoverridden];

            $output = html_writer::tag('div', $finalgrade->formatted_grade, $attr);
        } else if (!empty($this->quickgrade)) {
            $gradesmenu = make_grades_menu($this->cminstance->grade);

            $default = [-1 => get_string('nograde')];

            $grade = null;

            if (!empty($rowdata->timemarked)) {
                $grade = $rowdata->grade;
            }

            if ($this->cminstance->grade > 0) {
                $gradeinputattributes = [
                    'id' => 'panoptogradeinputbox',
                    'class' => 'mod-panoptosubmission-grade-input-box',
                    'type' => 'number',
                    'step' => 'any',
                    'min' => 0,
                    'max' => $this->cminstance->grade,
                    'name' => 'menu[' . $rowdata->id . ']',
                    'value' => $grade,
                ];
                $gradeinput = html_writer::empty_tag('input', $gradeinputattributes);

                $gradecontainerattributes = [
                    'id' => 'panoptogradeinputcontainer',
                    'class' => 'mod-panoptosubmission-grade-input-container',
                ];
                $output = html_writer::tag('span', $gradeinput . ' / ' . $this->cminstance->grade, $gradecontainerattributes);
            } else {
                $gradeselectattributes = [];
                $output = html_writer::select($gradesmenu, 'menu[' . $rowdata->id . ']', $grade, $default, $gradeselectattributes);
            }
        } else {
            $output = get_string('nograde');

            if (!empty($rowdata->timemarked)) {
                $output = $this->display_grade($rowdata->grade);
            }
        }

        $gradeoutput = $output;

        $output = $this->get_grade_button($rowdata);

        $output .= $gradeoutput;

        return $output;
    }

    /**
     * The function renders the submissions comment column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_submissioncomment($rowdata) {
        global $OUTPUT;

        $output = '';
        $finalgrade = false;

        if (array_key_exists($rowdata->id, $this->currentgrades->items[0]->grades)) {
            $finalgrade = $this->currentgrades->items[0]->grades[$rowdata->id];
        }

        $submissioncomment = strip_tags($rowdata->submissioncomment ?? '');
        if ((!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden))) {
            $output = shorten_text($submissioncomment, 15);
        } else if (!empty($this->quickgrade)) {
            $param = [
                'id' => 'comments_' . $rowdata->submitid,
                'rows' => $this->rows,
                'cols' => $this->cols,
                'name' => 'submissioncomment[' . $rowdata->id . ']',
            ];

            $output .= html_writer::tag('textarea', $submissioncomment, $param);
        } else {
            $output = shorten_text($submissioncomment, 15);
        }

        return $output;
    }

    /**
     * The function renders the grade marked column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_grademarked($rowdata) {

        $output = '';

        if (!empty($rowdata->timemarked)) {
            $output = userdate($rowdata->timemarked);
        }

        return $output;
    }

    /**
     * The function renders the time modified column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_timemodified($rowdata) {
        $attr = ['name' => 'media_submission'];
        $attr = ['id' => 'ts' . $rowdata->id];

        $datemodified = $rowdata->timemodified;
        $datemodified = is_null($datemodified) || empty($rowdata->timemodified) ? '' : userdate($datemodified);

        $output = html_writer::tag('div', $datemodified, $attr);
        return $output;
    }

    /**
     * The function renders the grade column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_grade($rowdata) {
        $finalgrade = false;

        if (array_key_exists($rowdata->id, $this->currentgrades->items[0]->grades)) {
            $finalgrade = $this->currentgrades->items[0]->grades[$rowdata->id];
        }

        $finalgrade = (!is_bool($finalgrade)) ? $finalgrade->str_grade : '-';

        $attr = ['id' => 'finalgrade_' . $rowdata->id];
        $output = html_writer::tag('span', $finalgrade, $attr);

        return $output;
    }

    /**
     * The function renders the time marked column.
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    public function col_timemarked($rowdata) {
        $output = '-';

        if (0 < $rowdata->timemarked) {
            $attr = ['id' => 'tt' . $rowdata->id];
            $output = html_writer::tag('div', userdate($rowdata->timemarked), $attr);
        } else {
            $otuput = '-';
        }

        return $output;
    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @param mixed $grade
     * @return string User-friendly representation of grade
     *
     * TODO: Move this to locallib.php
     */
    public function display_grade($grade) {
        global $DB;

        static $panoptoscalegrades = [];

        // Normal number.
        if ($this->cminstance->grade >= 0) {
            if ($grade == -1) {
                return '-';
            } else {
                return $grade . ' / ' . $this->cminstance->grade;
            }
        } else {
            // Scale.
            if (empty($panoptoscalegrades[$this->cminstance->id])) {
                if ($scale = $DB->get_record('scale', ['id' => -($this->cminstance->grade)])) {
                    $panoptoscalegrades[$this->cminstance->id] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }

            if (isset($panoptoscalegrades[$this->cminstance->id][$grade])) {
                return $panoptoscalegrades[$this->cminstance->id][$grade];
            }
            return '-';
        }
    }

    /**
     * The function renders the grade button
     * @param object $rowdata target row information
     * @return string HTML markup.
     */
    private function get_grade_button($rowdata) {
        global $OUTPUT, $CFG;

        $url = new moodle_url(
            '/mod/panoptosubmission/single_submission.php',
            ['cmid' => $this->cminstance->cmid, 'userid' => $rowdata->id, 'sesskey' => sesskey()]
        );

        if (!empty($this->tifirst)) {
            $url->param(
                'tifirst',
                $this->tifirst
            );
        }

        if (!empty($this->tilast)) {
            $url->param(
                'tilast',
                $this->tilast
            );
        }

        if (!empty($this->page)) {
            $url->param(
                'page',
                $this->page
            );
        }

        $buttontext = '';
        // Check if the user submitted the assignment.
        $submitted = !is_null($rowdata->timemarked);

        $class = 'btn btn-primary';
        if ($rowdata->timemarked > 0) {
            $class = 'btn btn-secondary';
            $buttontext = get_string('update');
        } else {
            $buttontext = get_string('gradenoun', 'panoptosubmission');
        }

        $attr = [
            'id' => 'up' . $rowdata->id,
            'class' => $class,
        ];

        if (!empty($this->quickgrade)) {
            $attr['target'] = '_blank';
            $attr['class'] = 'btn btn-primary';
            $buttontext = get_string('viewsubmission', 'panoptosubmission');
        }

        $output = html_writer::start_tag('div');
        $output .= html_writer::link($url, $buttontext, $attr);
        $output .= html_writer::end_tag('div');
        return $output;
    }
}
