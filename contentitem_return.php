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
 * @copyright  2021 Panopto
 * @author     Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib/panoptosubmission_lti_utility.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');

$courseid = required_param('course', PARAM_INT);
$contentitemsraw = required_param('content_items', PARAM_RAW_TRIMMED);

require_login($courseid);

$context = context_course::instance($courseid);

$contentitems = json_decode($contentitemsraw);

$errors = [];

// Affirm that the content item is a JSON object.
if (!is_object($contentitems) && !is_array($contentitems)) {
    $errors[] = 'invalidjson';
}

// Get and validate frame and thumbnail sizes.
$framewidth = 720;
$fwidth = $contentitems->{'@graph'}[0]->placementAdvice->displayWidth;
if (!empty($fwidth)) {
    $framewidth = is_numeric($fwidth) ? $fwidth : $framewidth;
}

$frameheight = 480;
$fheight = $contentitems->{'@graph'}[0]->placementAdvice->displayHeight;
if (!empty($fheight)) {
    $frameheight = is_numeric($fheight) ? $fheight : $frameheight;
}

$thumbnailwidth = 128;
$twidth = $contentitems->{'@graph'}[0]->thumbnail->width;
if (!empty($twidth)) {
    $thumbnailwidth = is_numeric($twidth) ? $twidth : $thumbnailwidth;
}

$thumbnailheight = 72;
$theight = $contentitems->{'@graph'}[0]->thumbnail->height;
if (!empty($theight)) {
    $thumbnailheight = is_numeric($theight) ? $theight : $thumbnailheight;
}

$title = "";
$itemtitle = $contentitems->{'@graph'}[0]->title;
if (!empty($itemtitle)) {
    $invalidcharacters = array("$", "%", "#", "<", ">");
    $cleantitle = str_replace($invalidcharacters, "", $itemtitle);
    $title = is_string($cleantitle) ? $cleantitle : $title;
}

$url = "";
$contenturl = $contentitems->{'@graph'}[0]->url;
if (!empty($contenturl)) {
    $panoptodata = new \panopto_data($courseid);
    $baseurl = parse_url($contenturl, PHP_URL_HOST);
    if (strcmp($panoptodata->servername, $baseurl) === 0) {
        $url = $contenturl;
    }
}

$customdata = $contentitems->{'@graph'}[0]->custom;

// In this version of Moodle LTI contentitem request we do not want the interactive viewer.
unset($customdata->use_panopto_interactive_view);

$ltiviewerurl = new moodle_url("/mod/panoptosubmission/view_submission.php");
?>

<script type="text/javascript">
    <?php if (count($errors) > 0): ?>
        parent.document.CALLBACKS.handleError(<?php echo json_encode($errors); ?>);
    <?php else: ?>
        // This event should close the panopto popup and pass the new content url to the existing iframe.
        var sessionSelectedEvent;
        var detailObject = {
            'detail': {
                'title': "<?php echo $title ?>",
                'ltiViewerUrl': "<?php echo $ltiviewerurl->out(false) ?>",
                'contentUrl': "<?php echo $url ?>",
                'customData': "<?php echo urlencode(json_encode($customdata)) ?>",
                'width': <?php echo $framewidth ?>,
                'height': <?php echo $frameheight ?>,
                'thumbnailUrl': "<?php echo $contentitems->{'@graph'}[0]->thumbnail->id ?>",
                'thumbnailWidth': <?php echo $thumbnailwidth ?>,
                'thumbnailHeight': <?php echo $thumbnailheight ?>,
            }
        };

        if (typeof window.CustomEvent === 'function') {
            sessionSelectedEvent = new CustomEvent('sessionSelected', detailObject);
        } else {
            // ie >= 9
            sessionSelectedEvent = document.createEvent('CustomEvent');
            sessionSelectedEvent.initCustomEvent('sessionSelected', false, false, detailObject);
        }

        parent.document.body.dispatchEvent(sessionSelectedEvent);
    <?php endif; ?>
</script>
