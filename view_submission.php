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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/locallib.php');

$courseid = required_param('course', PARAM_INT);
// PHP has already decoded the query string, so decoding again would corrupt encoded values in the URL.
$contenturl = optional_param('contenturl', '', PARAM_URL);
$customdata = urldecode(optional_param('custom', '', PARAM_RAW_TRIMMED));
$resourcelinkid = optional_param('resourcelinkid', '', PARAM_ALPHANUMEXT);

$course = get_course($courseid);

$context = context_course::instance($courseid);

// Authenticate before launching.
require_login($course);
require_capability('mod/lti:view', $context);

// Allow an empty URL. Reject any other unknown host.
if (!empty($contenturl) && !panopto_is_allowed_panopto_host($contenturl)) {
    throw new moodle_exception('invalid_content_host', 'panoptosubmission');
}

// A launch makes Panopto create the course's folder when it has none. A course with no submission
// activity has nothing to open, so refuse it before the Open video button and before any launch.
// A posted session key is not enough on its own. Real viewer links always come from a course that
// already has the activity.
if (empty(get_coursemodules_in_course('panoptosubmission', $courseid))) {
    throw new moodle_exception('invalidrequest', 'error');
}

// Only accept a launch this site posted with the session key. A link or GET from another site must
// not be able to trigger it.
if (!data_submitted()) {
    // A GET, such as a viewer link opened in a new tab, gets a button that posts the same parameters
    // with the session key. It needs the user's click: submitting it automatically would let a
    // forged link launch again.
    $launchurl = new moodle_url(qualified_me());

    $openvideo = get_string('openvideo', 'panoptosubmission');

    $PAGE->set_url($launchurl);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title($openvideo);

    // Only this site may frame the button, so another site can't trick the user into clicking it.
    @header("Content-Security-Policy: frame-ancestors 'self'");

    // A single_button posts its URL's parameters plus the session key.
    echo $OUTPUT->header();
    echo $OUTPUT->box(
        $OUTPUT->render(new single_button($launchurl, $openvideo, 'post', single_button::BUTTON_PRIMARY)),
        'generalbox text-center'
    );
    echo $OUTPUT->footer();
    exit;
}
require_sesskey();

if (empty($resourcelinkid)) {
    $ltiviewerurl = new moodle_url("/mod/panoptosubmission/view_submission.php");
    $resourcelinkid = sha1($ltiviewerurl->out(false) .
        '&' . $courseid .
        '&' . $course->timecreated);
}

// This page only plays sessions. The course is provisioned by a background task, queued when the
// activity is added or updated, when a course holding it is restored, and on upgrade.

// Get a matching LTI tool for the course.
$toolid = \panoptoblock_lti_utility::get_course_tool_id($courseid, 'panopto_student_submission_tool');

if (is_null($toolid)) {
    throw new moodle_exception('no_existing_lti_tools', 'panoptosubmission');
}

$lti = new stdClass();

// Give it some random id, this is not used in the code but will create a PHP notice if not provided.
$lti->id = $resourcelinkid;
$lti->typeid = $toolid;
$lti->launchcontainer = LTI_LAUNCH_CONTAINER_WINDOW;
$lti->toolurl = $contenturl;
$lti->custom = \panoptoblock_lti_utility::filter_launch_custom_parameters(
    $customdata,
    'mod_panoptosubmission'
);
$lti->instructorcustomparameters = [];
$lti->debuglaunch = false;

// LTI 1.3 login request.
$config = lti_get_type_type_config($toolid);
if ($config->lti_ltiversion === LTI_VERSION_1P3) {
    if (!isset($SESSION->lti_initiatelogin_status)) {
        echo lti_initiate_login(
            $courseid,
            \panoptoblock_lti_utility::build_playback_launch_hint(
                'mod_panoptosubmission',
                '',
                $toolid,
                $resourcelinkid,
                $contenturl,
                $lti->custom
            ),
            $lti,
            $config
        );
        exit;
    } else {
        unset($SESSION->lti_initiatelogin_status);
    }
}

// Set the platform-owned flag the allowlist omits.
$lti->custom->grading_not_supported = true;

echo \panoptoblock_lti_utility::launch_tool($lti);
