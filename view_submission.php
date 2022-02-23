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
 * This file launches LTI-tools enabled to be launched from a rich text editor
 *
 * @package    mod_panoptosubmission
 * @copyright  2021 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This page creates an lti request and echos the content from the response. Used to view submissions and other Panopto content.
 */
function init_panoptosubmission_view() {
    global $CFG;
    if (empty($CFG)) {
        require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
    }
    require_once(dirname(__FILE__) . '/lib/panoptosubmission_lti_utility.php');
    require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/lib.php');
    require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/locallib.php');

    $courseid  = required_param('course', PARAM_INT);
    $contenturl = urldecode(optional_param('contenturl', '', PARAM_URL));
    $customdata = urldecode(optional_param('custom', '', PARAM_RAW_TRIMMED));

    $course = get_course($courseid);

    $context = context_course::instance($courseid);

    require_login($course);

    // Get a matching LTI tool for the course.
    $toolid = \panoptosubmission_lti_utility::get_course_tool_id($courseid);

    // If no lti tool exists then we can not continue.
    if (is_null($toolid)) {
        throw new moodle_exception('no_existing_lti_tools', 'panoptosubmission');
        return;
    }

    $lti = new stdClass();

    // Give it some random id, this is not used in the code but will create a PHP notice if not provided.
    $lti->id = 99999;
    $lti->typeid = $toolid;
    $lti->launchcontainer = LTI_LAUNCH_CONTAINER_WINDOW;
    $lti->toolurl = $contenturl;
    $lti->custom = new stdClass();
    $lti->instructorcustomparameters = [];
    $lti->debuglaunch = false;
    if ($customdata) {
        $decoded = json_decode($customdata, true);

        foreach ($decoded as $key => $value) {
            $lti->custom->$key = $value;
        }
    }

    echo \panoptosubmission_lti_utility::launch_tool($lti);
}

init_panoptosubmission_view();
