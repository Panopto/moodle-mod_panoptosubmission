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
 * LTI launch script for the Panopto Student Submission module.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/locallib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/lib/panoptosubmission_lti_utility.php');

$courseid = required_param('courseid', PARAM_INT);

// Check access and capabilities.
$course = get_course($courseid);
require_login($course);

$toolid = \panoptosubmission_lti_utility::get_course_tool_id($courseid);

// If no lti tool exists then we can not continue.
if (is_null($toolid)) {
    throw new moodle_exception('no_existing_lti_tools', 'panoptosubmission');
    return;
}

// Set the return URL. We send the launch container along to help us avoid
// frames-within-frames when the user returns.
$returnurlparams = [
    'course' => $course->id,
    'id' => $toolid,
    'sesskey' => sesskey()
];

$returnurl = new \moodle_url('/mod/panoptosubmission/contentitem_return.php', $returnurlparams);

// Prepare the request.
$request = lti_build_content_item_selection_request(
    $toolid, $course, $returnurl, '', '', [], [],
    false, false, false, false, false
);

// Get the launch HTML.
$content = lti_post_launch_html($request->params, $request->url, false);

echo $content;
