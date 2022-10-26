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
 * This file contains the renderers for the Panopto Student Submission activity within Moodle
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))).'/lib/tablelib.php');
require_once(dirname(dirname(dirname(__FILE__))).'/lib/moodlelib.php');
require_once($CFG->dirroot.'/mod/panoptosubmission/classes/renderable/panoptosubmission_course_index_summary.php');

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
        $this->tifirst  = $tifirst;
        $this->tilast   = $tilast;
        $this->page     = $page;
        $this->courseId = $cm->course;

        $instance = $DB->get_record('panoptosubmission', array('id' => $cm->instance), 'id,grade,timedue');
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

        $attr = array('type' => 'hidden', 'name' => 'users['.$rowdata->id.']', 'value' => $rowdata->id);
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

        require_once(dirname(dirname(dirname(__FILE__))).'/lib/weblib.php');

        $url = new moodle_url('/mod/panoptosubmission/single_submission.php',
            array('cmid' => $this->cminstance->cmid, 'userid' => $data->id, 'sesskey' => sesskey()));

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
            $gradedstatusattributes = array(
                'class' => 'panopto-status-graded'
            );
            $output .= html_writer::tag('div', get_string('has_grade', 'panoptosubmission'), $gradedstatusattributes);
        } else {
            $gradedstatusattributes = array(
                'class' => 'panopto-status-not-graded'
            );
            $output .= html_writer::tag('div', get_string('needs_grade', 'panoptosubmission'), $gradedstatusattributes);
        }

        $due = $this->cminstance->timedue;
        if (!empty($this->quickgrade) && $submitted && ($data->timemodified > $due)) {
            $latestr = get_string('late', 'panoptosubmission', format_time($data->timemodified - $due));
            $lateattributes = array(
                'class' => 'panopto-latesubmission'
            );
            $output .= html_writer::tag('div', $latestr, $lateattributes);
        }

        if (!$submitted) {
            $gradedstatusattributes = array(
                'class' => 'panopto-status-not-submitted'
            );
            $output .= html_writer::tag('div', get_string('nosubmission', 'panoptosubmission'), $gradedstatusattributes);

            $now = time();
            if ($due && ($now > $due)) {
                $overduestr = get_string('overdue', 'assign', format_time($now - $due));
                $overdueattributes = array(
                    'class' => 'panopto-overduesubmission'
                );
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

        $output      = '';
        $finalgrade = false;

        if (array_key_exists($rowdata->id, $this->currentgrades->items[0]->grades)) {

            $finalgrade = $this->currentgrades->items[0]->grades[$rowdata->id];

            if ($CFG->enableoutcomes) {
                $finalgrade->formatted_grade = $this->currentgrades->items[0]->grades[$rowdata->id]->str_grade;
            } else {
                // Taken from mod/assignment/lib.php display_submissions().
                $finalgrade->formatted_grade = round($finalgrade->grade, 2) . ' / ' . round($this->grademax, 2);
            }
        }

        if (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden) ) {

            $lockedoverridden = 'locked';

            if ($finalgrade->overridden) {
                $lockedoverridden = 'overridden';
            }
            $attr = array('id' => 'g'.$rowdata->id, 'class' => $lockedoverridden);

            $output = html_writer::tag('div', $finalgrade->formatted_grade, $attr);

        } else if (!empty($this->quickgrade)) {

            $gradesmenu = make_grades_menu($this->cminstance->grade);

            $default = array(-1 => get_string('nograde'));

            $grade = null;

            if (!empty($rowdata->timemarked)) {
                $grade = $rowdata->grade;
            }

            if ($this->cminstance->grade > 0) {
                $gradeinputattributes = array(
                    'id' => 'panoptogradeinputbox',
                    'class' => 'panopto-grade-input-box',
                    'type' => 'number',
                    'step' => 'any',
                    'min' => 0,
                    'max' => $this->cminstance->grade,
                    'name' => 'menu[' . $rowdata->id . ']',
                    'value' => $grade
                );
                $gradeinput = html_writer::empty_tag('input', $gradeinputattributes);

                $gradecontainerattributes = array(
                    'id' => 'panoptogradeinputcontainer',
                    'class' => 'panopto-grade-input-container'
                );
                $output = html_writer::tag('span', $gradeinput . ' / ' . $this->cminstance->grade , $gradecontainerattributes);
            } else {
                $gradeselectattributes = array();
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

        $output     = '';
        $finalgrade = false;

        if (array_key_exists($rowdata->id, $this->currentgrades->items[0]->grades)) {
            $finalgrade = $this->currentgrades->items[0]->grades[$rowdata->id];
        }

        if ( (!is_bool($finalgrade) && ($finalgrade->locked || $finalgrade->overridden)) ) {

            $output = shorten_text(strip_tags($rowdata->submissioncomment), 15);

        } else if (!empty($this->quickgrade)) {

            $param = array(
                'id' => 'comments_' . $rowdata->submitid,
                'rows' => $this->rows,
                'cols' => $this->cols,
                'name' => 'submissioncomment[' . $rowdata->id.']');

            $output .= html_writer::tag('textarea', strip_tags($rowdata->submissioncomment), $param);

        } else {
            $output = shorten_text(strip_tags($rowdata->submissioncomment), 15);
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
        $attr = array('name' => 'media_submission');

        $attr = array('id' => 'ts'.$rowdata->id);

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

        $attr = array('id' => 'finalgrade_' . $rowdata->id);
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

                $attr = array('id' => 'tt'.$rowdata->id);
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

        static $panoptoscalegrades = array();

        // Normal number.
        if ($this->cminstance->grade >= 0) {
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->cminstance->grade;
            }

        } else {
            // Scale.
            if (empty($panoptoscalegrades[$this->cminstance->id])) {

                if ($scale = $DB->get_record('scale', array('id' => -($this->cminstance->grade)))) {

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

        require_once(dirname(dirname(dirname(__FILE__))).'/lib/weblib.php');

        $url = new moodle_url('/mod/panoptosubmission/single_submission.php',
            array('cmid' => $this->cminstance->cmid, 'userid' => $rowdata->id, 'sesskey' => sesskey()));

        if (!empty($this->tifirst)) {
            $url->param('tifirst', $this->tifirst);
        }

        if (!empty($this->tilast)) {
            $url->param('tilast', $this->tilast);
        }

        if (!empty($this->page)) {
            $url->param('page', $this->page);
        }

        $buttontext = '';
        // Check if the user submitted the assignment.
        $submitted = !is_null($rowdata->timemarked);

        $class = 'btn btn-primary';
        if ($rowdata->timemarked > 0) {
            $class = 'btn btn-secondary';
            $buttontext = get_string('update');
        } else {
            $buttontext  = get_string('gradenoun', 'panoptosubmission');
        }

        $attr = array('id' => 'up'.$rowdata->id,
                      'class' => $class);

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

/**
 * This class renders the submission pages.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_panoptosubmission_renderer extends plugin_renderer_base {
    /**
     * The function displays information about the assignment settings.
     * @param object $pansubmissiondata target row information
     * @param object $context the context for the current assignment
     * @return string HTML markup.
     */
    public function display_mod_info($pansubmissiondata, $context) {
        global $DB;
        $html = '';

        if (!empty($pansubmissiondata->timeavailable)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('availabledate', 'panoptosubmission').': ');
            $html .= userdate($pansubmissiondata->timeavailable);
            $html .= html_writer::end_tag('p');
        }

        if (!empty($pansubmissiondata->timedue)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('duedate', 'panoptosubmission').': ');
            $html .= userdate($pansubmissiondata->timedue);
            $html .= html_writer::end_tag('p');
        }

        if (!empty($pansubmissiondata->cutofftime)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('cutoffdate', 'panoptosubmission').': ');
            $html .= userdate($pansubmissiondata->cutofftime);
            $html .= html_writer::end_tag('p');
        }

        // Display a count of the numuber of submissions.
        if (has_capability('mod/panoptosubmission:gradesubmission', $context)) {

            $param = array('panactivityid' => $pansubmissiondata->id, 'timecreated' => 0, 'timemodified' => 0);

            $csql = "SELECT COUNT(*) " .
                      "FROM {panoptosubmission_submission} " .
                     "WHERE panactivityid = :panactivityid " .
                           "AND (timecreated > :timecreated OR timemodified > :timemodified) ";

            $count = $DB->count_records_sql($csql, $param);

            if ($count) {
                $html .= html_writer::start_tag('p');
                $html .= get_string('numberofsubmissions', 'panoptosubmission', $count);
                $html .= html_writer::end_tag('p');
            }

        }

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @param bool $disablesubmit Set to true to disable the submit button.
     * @return string Returns HTML markup.
     */
    public function display_student_submit_buttons($cm, $userid, $disablesubmit = false) {
        $html = '';

        $target = new moodle_url('/mod/panoptosubmission/submission.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array(
            'type' => 'hidden',
            'name' => 'cmid',
            'value' => $cm->id
        );
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        );
        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'width', 'name' => 'width', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'height', 'name' => 'height', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'source', 'name' => 'source', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'customdata', 'name' => 'customdata', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'sessiontitle', 'name' => 'sessiontitle', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailwidth', 'name' => 'thumbnailwidth', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailheight', 'name' => 'thumbnailheight', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailsource', 'name' => 'thumbnailsource', 'value' => 0));

        $html .= html_writer::start_tag('center', ['class' => 'm-t-2 m-b-1']);

        $attr = array(
            'class' => 'btn btn-primary mr-2',
            'type' => 'button',
            'id' => 'id_add_video',
            'name' => 'add_video',
            'value' => get_string('addvideo', 'panoptosubmission')
        );

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'class' => 'btn btn-secondary',
            'type' => 'submit',
            'name' => 'submit_video',
            'id' => 'submit_video',
            'disabled' => 'disabled',
            'value' => get_string('submitvideo', 'panoptosubmission'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @param bool $disablesubmit Set to true to disable the submit button.
     * @return string Returns HTML markup.
     */
    public function display_student_resubmit_buttons($cm, $userid, $disablesubmit = false) {
        global $DB;

        $param = array('panactivityid' => $cm->instance, 'userid' => $userid);
        $submissionrec = $DB->get_record('panoptosubmission_submission', $param);

        $html = '';

        $target = new moodle_url('/mod/panoptosubmission/submission.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array(
            'type' => 'hidden',
            'name'  => 'cmid',
            'value' => $cm->id
        );

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'type' => 'hidden',
            'name'  => 'sesskey',
            'value' => sesskey()
        );

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'width', 'name' => 'width', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'height', 'name' => 'height', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'source', 'name' => 'source', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'customdata', 'name' => 'customdata', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'sessiontitle', 'name' => 'sessiontitle', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailwidth', 'name' => 'thumbnailwidth', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailheight', 'name' => 'thumbnailheight', 'value' => 0));
        $html .= html_writer::empty_tag('input',
            array('type' => 'hidden', 'id' => 'thumbnailsource', 'name' => 'thumbnailsource', 'value' => 0));

        $html .= html_writer::start_tag('center', ['class' => 'm-t-2 m-b-1']);

        // Add submit and review buttons.
        $attr = array(
            'class' => 'btn btn-primary mr-2',
            'type' => 'button',
            'name' => 'add_video',
            'id' => 'id_add_video',
            'value' => get_string('replacevideo', 'panoptosubmission')
        );

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array(
            'class' => 'btn btn-secondary',
            'type' => 'submit',
            'id'   => 'submit_video',
            'name' => 'submit_video',
            'disabled' => 'disabled',
            'value' => get_string('submitvideo', 'panoptosubmission')
        );

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a form and submission buttons.
     * @param object $cm A course module object.
     * @param int $userid The current user id.
     * @return string Returns HTML markup.
     */
    public function display_instructor_buttons($cm,  $userid) {
        $html = '';

        $target = new moodle_url('/mod/panoptosubmission/grade_submissions.php');

        $attr = array('method' => 'POST', 'action' => $target);

        $html .= html_writer::start_tag('form', $attr);

        $html .= html_writer::start_tag('center');

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('class' => 'btn btn-secondary',
                     'type' => 'submit',
                     'name' => 'grade_submissions',
                     'value' => get_string('gradesubmission', 'panoptosubmission'),
                     'class' => 'btn btn-secondary');

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function returns HTML markup to render a the submissions table
     * @param object $cm A course module object.
     * @param int $perpage The number of submissions to display on a page.
     * @param int $groupfilter The group id to filter against.
     * @param string $filter Filter users who have submitted, submitted and graded or everyone.     *
     * @param bool $quickgrade True if quick grading was enabled
     * @param string $tifirst The first initial of the first name.
     * @param string $tilast The first initial of the last name.
     * @param int $page The current page to render.
     * @return string Returns HTML markup.
     */
    public function display_submissions_table(
        $cm, $perpage, $groupfilter = 0, $filter = 'all', $quickgrade = false, $tifirst = '', $tilast = '', $page = 0) {

        global $DB, $COURSE, $USER, $CFG;

        // Get a list of users who have submissions and retrieve grade data for those users.
        $users = panoptosubmission_get_submissions($cm->instance, $filter);

        $definecolumns = array(
            'picture',
            'fullname',
            'email',
            'status',
            'selectgrade',
            'timemodified',
            'timemarked',
            'submissioncomment',
            'grade'
        );

        if (empty($users)) {
            $users = array();
        }

        // Compare student who have submitted to the assignment with students who are.
        // Currently enrolled in the course.
        $students = array_keys(panoptosubmission_get_assignment_students($cm));
        $users = array_intersect(array_keys($users), $students);

        if (empty($students)) {
            echo html_writer::tag('p', get_string('noenrolledstudents', 'panoptosubmission'));
            return;
        }

        $currentgrades = grade_get_grades($cm->course, 'mod', 'panoptosubmission', $cm->instance, $users);

        $where = '';
        switch ($filter) {
            case PANOPTOSUBMISSION_SUBMITTED:
                $where = ' ps.timemodified > 0 AND ';
                break;
            case PANOPTOSUBMISSION_REQ_GRADING:
                $where = ' ps.timemarked < ps.timemodified AND ';
                break;
        }

        // Determine logic needed for groups mode.
        $param        = array();
        $groupswhere  = '';
        $groupscolumn = '';
        $groupsjoin   = '';
        $groups       = array();
        $mergedgroups = array();
        $groupids     = '';
        $context      = context_course::instance($COURSE->id);

        // Get all groups that the user belongs to, check if the user has capability to access all groups.
        if (!has_capability('moodle/site:accessallgroups', $context, $USER->id)) {
            // It's very important we use the group limited user function here.
            $groups = groups_get_user_groups($COURSE->id, $USER->id);

            if (empty($groups)) {
                $message = get_string('nosubmissions', 'panoptosubmission');
                echo html_writer::tag('center', $message);
                return;
            }
            // Collapse all the group ids into one array for use later.
            // We have to do this here as the user groups function returns different data than the all groups function.
            foreach ($groups as $group) {
                foreach ($group as $value) {
                    $value = trim($value);
                    if (!in_array($value, (array)$mergedgroups)) {
                        $mergedgroups[] = $value;
                    }
                }
            }
        } else {
            // Here we can use the all groups function as it ensures non-group-bound users can see/grade all groups.
            $groups = groups_get_all_groups($COURSE->id);
            // Collapse all the group ids into one array for use later.
            // We have to do this here as the all groups function returns different data than the user groups function.
            foreach ($groups as $group) {
                $mergedgroups[] = $group->id;
            }
        }

        // Create a comma separated list of group ids.
        $groupids .= implode(',', (array)$mergedgroups);
        // If the user is not a member of any groups, set $groupids = 0 to avoid issues.
        $groupids = $groupids ? $groupids : 0;

        // Ignore all this if there are no course groups.
        if (groups_get_all_groups($COURSE->id)) {
            switch (groups_get_activity_groupmode($cm)) {
                case NOGROUPS:
                    // No groups, do nothing if all groups selected.
                    // If non-group limited, user can select and limit by group.
                    if (0 != $groupfilter) {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id' .
                            ' RIGHT JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND gm.groupid = :groupid ';
                    }
                    break;
                case SEPARATEGROUPS:
                    // If separate groups, but displaying all users then we must display only users.
                    // Who are in the same group as the current user. Otherwise, show only groupmembers.
                    // Of the selected group.
                    if (0 == $groupfilter) {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' INNER JOIN {groups_members} gm ON gm.userid = u.id' .
                            ' INNER JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND g.id IN ('.$groupids.') ';
                    } else {
                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' INNER JOIN {groups_members} gm ON gm.userid = u.id' .
                            ' INNER JOIN {groups} g ON g.id = gm.groupid ';
                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';
                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND g.id IN ('.$groupids.') AND g.id = :groupid ';

                    }
                    break;

                case VISIBLEGROUPS:
                    // If visible groups but displaying a specific group then we must display users within.
                    // That group, if displaying all groups then display all users in the course.
                    if (0 != $groupfilter) {

                        $groupscolumn = ', gm.groupid ';
                        $groupsjoin   = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id' .
                            ' RIGHT JOIN {groups} g ON g.id = gm.groupid ';

                        $param['courseid'] = $cm->course;
                        $groupswhere  .= ' AND g.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groupswhere .= ' AND gm.groupid = :groupid ';

                    }
                    break;
            }
        }

        $table = new panoptosubmission_submissions_table('panopto_submit_table', $cm, $currentgrades, $quickgrade, $tifirst, $tilast, $page);

        // If Moodle version is less than 3.11.0 use user_picture, otherwise use core_user api.
        $userfields = $CFG->version < 2021051700
            ? user_picture::fields('u')
            : \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;

        // In order for the sortable first and last names to work.  User ID has to be the first column returned and must be.
        // Returned as id.  Otherwise the table will display links to user profiles that are incorrect or do not exist.
        $columns = $userfields .', ps.id AS submitid, ';
        $columns .= ' ps.grade, ps.submissioncomment, ps.timemodified, ps.source, ps.width, ps.height, ps.timemarked, ';
        $columns .= '1 AS status, 1 AS selectgrade ' . $groupscolumn;
        $where .= ' u.deleted = 0 AND u.id IN (' . implode(',', $students) . ') ' . $groupswhere;

        $param['instanceid'] = $cm->instance;
        $from = "{user} u LEFT JOIN {panoptosubmission_submission} ps ON ps.userid = u.id AND ps.panactivityid = :instanceid " .
            $groupsjoin;

        $baseurl = new moodle_url('/mod/panoptosubmission/grade_submissions.php', array('cmid' => $cm->id));

        $table->set_sql($columns, $from, $where, $param);
        $table->define_baseurl($baseurl);
        $table->collapsible(true);

        $table->define_columns($definecolumns);

        $col1 = get_string('userpicture', 'panoptosubmission');
        $col2 = get_string('fullname', 'panoptosubmission');
        $col3 = get_string('useremail', 'panoptosubmission');
        $col4 = get_string('status', 'panoptosubmission');
        $col5 = get_string('gradenoun', 'panoptosubmission');
        $col6 = get_string('timemodified', 'panoptosubmission');
        $col7 = get_string('grademodified', 'panoptosubmission');
        $col8 = get_string('submissioncomment', 'panoptosubmission');
        $col9 = get_string('finalgrade', 'panoptosubmission');
        $table->define_headers(array($col1, $col2, $col3, $col4, $col5, $col6, $col7, $col8, $col9));

        echo html_writer::start_tag('center');

        $attributes = array('action' => new moodle_url('grade_submissions.php'), 'id' => 'fastgrade', 'method' => 'post');
        echo html_writer::start_tag('form', $attributes);

        $attributes = array('type' => 'hidden', 'name' => 'cmid', 'value' => $cm->id);
        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'mode';
        $attributes['value'] = 'fastgrade';

        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'sesskey';
        $attributes['value'] = sesskey();

        echo html_writer::empty_tag('input', $attributes);

        $table->out($perpage, true);

        if ($quickgrade) {
            $attributes = array('type' => 'submit', 'name' => 'save_feedback',
                'value' => get_string('savefeedback', 'panoptosubmission'));

            echo html_writer::empty_tag('input', $attributes);
        }

        echo html_writer::end_tag('form');

        echo html_writer::end_tag('center');
    }

    /**
     * Displays the activities listing table.
     *
     * @param object $course The course odject.
     */
    public function display_panoptosubmission_activities_table($course) {
        global $CFG, $DB, $USER;

        echo html_writer::start_tag('center');

        if (!$cms = get_coursemodules_in_course('panoptosubmission', $course->id, 'm.timedue')) {
            echo get_string('noassignments', 'mod_panoptosubmission');
            echo $this->output->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }

        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);
        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }

        $courseformatname  = get_string('sectionname', 'format_' . $course->format);
        $courseindexsummary = new panoptosubmission_course_index_summary($usesections, $courseformatname);
        $activitycount = 0;
        if (array_key_exists('panoptosubmission', $modinfo->instances)) {
            foreach ($modinfo->instances['panoptosubmission'] as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                $activitycount++;

                $sectionname = '';
                if ($usesections && $cm->sectionnum) {
                    $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
                }

                $submitted = '';

                $context = context_module::instance($cm->id);
                if (has_capability('mod/panoptosubmission:gradesubmission', $context)) {
                    $submitted = $DB->count_records('panoptosubmission_submission', array('panactivityid' => $cm->instance));
                } else if (has_capability('mod/panoptosubmission:submit', $context)) {
                    if ($DB->count_records('panoptosubmission_submission',
                        array('panactivityid' => $cm->instance, 'userid' => $USER->id)) > 0) {

                        $submitted = get_string('submitted', 'mod_panoptosubmission');
                    } else {
                        $submitted = get_string('nosubmission', 'mod_panoptosubmission');
                    }
                }

                $currentgrades = grade_get_grades($course->id, 'mod', 'panoptosubmission', $cm->instance, $USER->id);
                if (isset($currentgrades->items[0]->grades[$USER->id]) && !$currentgrades->items[0]->grades[$USER->id]->hidden ) {
                    $grade = $currentgrades->items[0]->grades[$USER->id]->str_grade;
                } else {
                    $grade = '-';
                }

                $courseindexsummary->add_assign_info(
                    $cm->id, $cm->name, $sectionname, $cms[$cm->id]->timedue, $submitted, $grade
                );
            }
        }

        if ($activitycount > 0) {
            $pagerenderer = $this->page->get_renderer('mod_panoptosubmission');
            echo $pagerenderer->render($courseindexsummary);
        }

        echo html_writer::end_tag('center');
    }

    /**
     * This function displays HTML needed by the submissionpanel YUI module to display a popup window containing the LTI launch.
     * @param object $submission A Panopto Student Submission submission table object.
     * @param int $courseid The course id.
     * @param int $cmid The ccourse module id.
     * @return string HTML markup.
     */
    public function get_video_container($submission, $courseid, $cmid) {
        $iframe = $this->get_video_iframe($submission, $courseid, $cmid);

        $iframecontainer = html_writer::tag('div', $iframe, array(
            'class' => 'panopto-session-container'
        ));

        return $iframecontainer;
    }

    /**
     * This function displays HTML needed by the submissionpanel YUI module to display a popup window containing the LTI launch.
     * @param object $submission A Panopto Student Submission submission table object.
     * @param int $courseid The course id.
     * @param int $cmid The ccourse module id.
     * @return string HTML markup.
     */
    private function get_video_iframe($submission, $courseid, $cmid) {
        $params = array(
            'id' => 'contentframe',
            'class' => 'panopto-player-iframe',
            'allowfullscreen' => 'true',
            'allow' => 'autoplay *; fullscreen *;',
            'height' => !is_null($submission) && !empty($submission->height) ? $submission->height : '',
            'width' => !is_null($submission) && !empty($submission->width) ? $submission->width : ''
        );

        $ltiviewerparams = array(
            'course' => $courseid
        );

        if (!is_null($submission) && !empty($submission->source)) {
            $contenturl = new moodle_url($submission->source);
            $ltiviewerparams['resourcelinkid'] =
                sha1($submission->source . '&' . $courseid . '&' . $submission->id . '&' . $submission->timemodified);
            $ltiviewerparams['custom'] = $submission->customdata;
            $ltiviewerparams['contenturl'] = $contenturl->out(false);
        } else {
            $viewsubmissionurl = new moodle_url("/mod/panoptosubmission/view_submission.php");
            $ltiviewerparams['resourcelinkid'] = sha1($viewsubmissionurl->out(false) . '&' . $courseid . '&' . $cmid);
        }

        $ltiviewerurl = new moodle_url("/mod/panoptosubmission/view_submission.php", $ltiviewerparams);
        $params['src'] = $ltiviewerurl->out(false);

        $iframe = html_writer::tag('iframe', '', $params);

        return $iframe;
    }

    /**
     * This function displays HTML needed by the submissionpanel YUI module to display a popup window containing the LTI launch.
     * @param object $submission A Panopto Student Submission submission table object.
     * @param int $courseid The course id.
     * @param int $cmid The ccourse module id.
     * @return string HTML markup.
     */
    public function get_view_video_container($submission, $courseid, $cmid) {
        $containerparams = array(
            'id' => 'contentcontainer',
            'class' => 'panopto-player-container'
        );

        $ltiviewerparams = array(
            'course' => $courseid
        );

        $sessiontitle = '';
        $thumbnailsource = new moodle_url('');
        $thumbnailwidth = '';
        $thumbnailheight = '';

        if (!is_null($submission) && !empty($submission->source)) {

            $contenturl = new moodle_url($submission->source);

            $thumbnailsource = new moodle_url($submission->thumbnailsource);
            $thumbnailwidth = $submission->thumbnailwidth;
            $thumbnailheight = $submission->thumbnailheight;

            $ltiviewerparams['custom'] = $submission->customdata;
            $ltiviewerparams['contenturl'] = $contenturl->out(false);
            $sessiontitle = $submission->title;
        } else {
            // Do not display an empty iframe and do not give it an invalid source.
            $containerparams['class'] = 'panopto-player-container no-session';
        }

        $ltiviewerurl = new moodle_url("/mod/panoptosubmission/view_submission.php", $ltiviewerparams);

        $output = '<script type="text/javascript">' .
                    'function panoptosubmission_toggleSessionDisplay() {' .
                        'var showSessionPreviewToggle = document.getElementById("showsessiontoggle");' .
                        'var sessionContainerDiv = document.getElementById("sessioncontainer");' .
                        'var sessionIframe = document.getElementById("contentframe");' .
                        'var titleElem = document.getElementById("panoptosessiontitle");' .

                        'if(sessionContainerDiv.classList.contains("session-hidden")) {' .
                            'sessionContainerDiv.classList.remove("session-hidden");' .
                            'sessionIframe.setAttribute("src", titleElem.getAttribute("href"));' .
                            'showSessionPreviewToggle.textContent = "' . get_string('sessionpreview_hide', 'panoptosubmission') .
                            '";' .
                        '} else {' .
                            'sessionContainerDiv.classList.add("session-hidden");' .
                            'sessionIframe.setAttribute("src", "about:blank");' .
                            'showSessionPreviewToggle.textContent = "' . get_string('sessionpreview_show', 'panoptosubmission') .
                            '";' .
                        '}' .
                    '}' .
                '</script>';

        $output .= html_writer::start_tag('div', $containerparams);

        $showsessiontoggleparams = array(
            'id' => 'showsessiontoggle',
            'class' => 'panopto-show-session-toggle',
            'href' => 'javascript:panoptosubmission_toggleSessionDisplay()'
        );

        $output .= html_writer::tag('a', get_string('sessionpreview_show', 'panoptosubmission'), $showsessiontoggleparams);

        $sessioncontainerparams = array (
            'id' => 'sessioncontainer',
            'class' => 'panopto-session-container session-hidden'
        );
        $output .= html_writer::start_tag('div', $sessioncontainerparams);

        $thumbnaillinkparams = array(
            'id' => 'panoptothumbnaillink',
            'class' => 'panopto-thumbnail-link',
            'href' => $ltiviewerurl,
            'target' => '_blank'
        );
        $thumbnailparams = array(
            'id' => 'panoptothumbnail',
            'class' => 'panopto-thumbnail',
            'width' => $thumbnailwidth,
            'height' => $thumbnailheight
        );
        $thumbnailcontainerparams = array(
            'id' => 'thumbnailcontainer',
            'class' => 'panopto-thumbnail-container'
        );

        $output .= html_writer::start_tag('div', $thumbnailcontainerparams);
        $output .= html_writer::tag('a',
            html_writer::img($thumbnailsource, $sessiontitle, $thumbnailparams), $thumbnaillinkparams);

        $titleparams = array(
            'id' => 'panoptosessiontitle',
            'class' => 'panopto-session-title',
            'href' => $ltiviewerurl,
            'target' => '_blank'

        );

        $output .= html_writer::tag('a', $sessiontitle, $titleparams);
        $output .= html_writer::end_tag('div');

        $output .= $this->get_video_iframe($submission, $courseid, $cmid, $ltiviewerparams);

        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submission comment.
     *
     * @param object $pansubmissionactivity The submission object or NULL in which case it will be loaded
     * @param object $context the context for the current submission
     */
    public function display_grade_feedback($pansubmissionactivity, $context) {
        global $USER, $CFG, $DB;

        require_once($CFG->libdir.'/gradelib.php');

        // Check if the user is enrolled to the coruse and can submit to the assignment.
        if (!is_enrolled($context, $USER, 'mod/panoptosubmission:submit')) {
            // Can not submit assignments -> no feedback.
            return;
        }

        // Get the user's submission obj.
        $currentgrades = grade_get_grades($pansubmissionactivity->course,
            'mod', 'panoptosubmission', $pansubmissionactivity->id, $USER->id);

        $item = $currentgrades->items[0];
        $grade = $item->grades[$USER->id];

        // Hidden or error.
        if ($grade->hidden || $grade->grade === false) {
            return;
        }

        // Nothing to show yet.
        if ($grade->grade === null && empty($grade->str_feedback)) {
            return;
        }

        $gradedate = $grade->dategraded;
        $gradeby   = $grade->usermodified;

        // We need the teacher info.
        if (!$teacher = $DB->get_record('user', array('id' => $gradeby))) {
            throw new moodle_exception('cannotfindteacher');
        }

        // Print the feedback.
        echo $this->output->heading(get_string('feedbackfromteacher', 'panoptosubmission', fullname($teacher)));

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        if ($teacher) {
            echo $this->output->user_picture($teacher);
        }
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        if ($teacher) {
            echo '<div class="fullname">'.fullname($teacher).'</div>';
        }
        echo '<div class="time">'.userdate($gradedate).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        echo '<div class="grade">';
        echo get_string("gradenoun", "panoptosubmission").': '.$grade->str_long_grade;
        echo '</div>';
        echo '<div class="clearer"></div>';

        echo '<div class="comment">';
        echo $grade->str_feedback;
        echo '</div>';
        echo '</tr>';

        echo '</table>';
    }

    /**
     * Render a course index summary.
     *
     * @param panoptosubmission_course_index_summary $indexsummary Structure for index summary.
     * @return string HTML for assignments summary table
     */
    public function render_panoptosubmission_course_index_summary(panoptosubmission_course_index_summary $indexsummary) {
        $modulename = get_string('modulenameplural', 'panoptosubmission');
        $courseformatname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'panoptosubmission');
        $strsubmission = get_string('submission', 'panoptosubmission');
        $strgrade = get_string('gradenoun', 'panoptosubmission');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($courseformatname, $modulename, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($modulename, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->activities as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/panoptosubmission/view.php', $params), $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
}
