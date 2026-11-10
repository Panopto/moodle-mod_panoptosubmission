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
 * Handles content item return.
 *
 * @package    mod_panoptosubmission
 * @copyright  2024 Panopto
 * @author     Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Login is enforced via panoptosubmission_require_login_or_repost() below.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/mod/lti/locallib.php');

$courseid = required_param('course', PARAM_INT);
$id = required_param('id', PARAM_INT);
$jwt = optional_param('JWT', '', PARAM_RAW);

panoptosubmission_require_login_or_repost($courseid);

require_sesskey();

$context = context_course::instance($courseid);

// Submitters need this capability. Others need addinstance.
if (!is_enrolled($context, $USER, 'mod/panoptosubmission:submit')) {
    require_capability('moodle/course:manageactivities', $context);
    require_capability('mod/lti:addcoursetool', $context);
}

$config = lti_get_type_type_config($id);
$islti1p3 = $config->lti_ltiversion === LTI_VERSION_1P3;

if (!empty($jwt)) {
    // Verifies the JWT signature against the tool's keys.
    $params = lti_convert_from_jwt($id, $jwt);
    $items = $params['content_items'] ?? '';
} else {
    // An LTI 1.3 tool must answer with a signed JWT.
    if ($islti1p3) {
        throw new moodle_exception('invalid_launch_parameters', 'panoptosubmission');
    }

    // Trust the items only when the tool signed the response with its shared secret, as mod/lti does.
    lti_verify_oauth_signature($id, required_param('oauth_consumer_key', PARAM_RAW));
    $items = optional_param('content_items', '', PARAM_RAW_TRIMMED);
}

// Behind the sesskey and a verified response, queue provisioning for courses nothing else provisioned.
panoptosubmission_queue_course_provisioning($courseid);

$contentitems = json_decode($items);

$errors = [];

// Affirm that the content item is a JSON object.
if (!is_object($contentitems) && !is_array($contentitems)) {
    $errors[] = 'invalid_content_item';
}

// Get and validate frame and thumbnail sizes.
$framewidth = panoptosubmission_get_property_or_default(
    $contentitems->{'@graph'}[0]->placementAdvice ?? new stdClass(),
    'displayWidth',
    720
);

$frameheight = panoptosubmission_get_property_or_default(
    $contentitems->{'@graph'}[0]->placementAdvice ?? new stdClass(),
    'displayHeight',
    480
);

$title = panoptosubmission_get_property_or_default(
    $contentitems->{'@graph'}[0] ?? new stdClass(),
    'title',
    ''
);

if (!empty($title)) {
    $invalidcharacters = ["$", "%", "#", "<", ">"];
    $title = str_replace($invalidcharacters, "", $title);
}

$url = panoptosubmission_get_property_or_default(
    $contentitems->{'@graph'}[0] ?? new stdClass(),
    'url',
    ''
);

// Reject hosts outside the shared allowlist.
if (!empty($url) && !panopto_is_allowed_panopto_host($url)) {
    $errors[] = 'invalid_content_host';
    $url = '';
}

$thumbnail = $contentitems->{'@graph'}[0]->thumbnail ?? new stdClass();
$thumbnailurlfinal = panoptosubmission_get_property_or_default(
    $thumbnail,
    'id',
    panoptosubmission_get_property_or_default($thumbnail, '@id', '')
);

$thumbnailurlfinal = panoptosubmission_sanitise_thumbnail_url($thumbnailurlfinal);

$thumbnailwidth = panoptosubmission_get_property_or_default($thumbnail, 'width', 128);
$thumbnailheight = panoptosubmission_get_property_or_default($thumbnail, 'height', 72);

$customdata = $contentitems->{'@graph'}[0]->custom ?? new stdClass();

// In this version of Moodle LTI contentitem request we do not want the interactive viewer.
unset($customdata->use_panopto_interactive_view);

$ltiviewerurl = new moodle_url("/mod/panoptosubmission/view_submission.php");

$detail = [
    'title' => $title,
    'ltiViewerUrl' => $ltiviewerurl->out(false),
    'contentUrl' => $url,
    'customData' => urlencode(json_encode($customdata)),
    'width' => (int) $framewidth,
    'height' => (int) $frameheight,
    'thumbnailUrl' => $thumbnailurlfinal,
    'thumbnailWidth' => (int) $thumbnailwidth,
    'thumbnailHeight' => (int) $thumbnailheight,
];

// Escape script delimiters.
$detailjson = panoptosubmission_encode_script_json($detail);

if ($detailjson === false) {
    $errors[] = 'invalid_content_item';
}

if (count($errors) > 0) {
    // Tell the activity page why the selection was rejected, so it can close the popup and say so.
    $messages = array_map(fn($error) => get_string($error, 'panoptosubmission'), array_values(array_unique($errors)));
    $js = 'const sessionSelectionFailedEvent = new CustomEvent("sessionSelectionFailed", {' .
        'detail: ' . panoptosubmission_encode_script_json(['messages' => $messages]) . ', bubbles: false, cancelable: false});' .
        'parent.document.body.dispatchEvent(sessionSelectionFailedEvent);';
} else {
    // Pass the session details to the activity page, which closes the popup and loads the new session.
    $js = 'const sessionSelectedEvent = new CustomEvent("sessionSelected", {' .
        'detail: ' . $detailjson . ', bubbles: false, cancelable: false});' .
        'parent.document.body.dispatchEvent(sessionSelectedEvent);';
}

// Every dynamic value above is JSON encoded with script delimiters escaped.
echo html_writer::script($js);
