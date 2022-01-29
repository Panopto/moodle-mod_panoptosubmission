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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))).'/course/moodleform_mod.php');

/**
 * This class contains the forms to create and edit an instance of this module
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_panoptosubmission_mod_form extends moodleform_mod {
    /**
     * Definition function for the form.
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'course', $COURSE->id);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'panoptosubmission'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('date_time_selector',
            'timeavailable', get_string('availabledate', 'panoptosubmission'), array('optional' => true));
        $mform->addHelpButton('timeavailable', 'availabledate', 'panoptosubmission');
        $mform->setDefault('timeavailable', time());

        $mform->addElement('date_time_selector',
            'timedue', get_string('duedate', 'panoptosubmission'), array('optional' => true));
        $mform->addHelpButton('timedue', 'duedate', 'panoptosubmission');
        $mform->setDefault('timedue', time() + 7 * 24 * 3600);

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'preventlate', get_string('preventlate', 'panoptosubmission'), $ynoptions);
        $mform->addHelpButton('preventlate', 'preventlate', 'panoptosubmission');
        $mform->setDefault('preventlate', 0);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'panoptosubmission'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'panoptosubmission');
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'panoptosubmission'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'panoptosubmission');
        $mform->setDefault('emailteachers', 0);

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
