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
 * YUI module for displaying an LTI launch within a YUI panel.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This panel will create the Panopto LTI embed window and update the submission buttons on view.php once a submission is selected.
 * @method PANOPTOSUBMISSIONFRAME
 */
var PANOPTOSUBMISSIONFRAME = function() {
    PANOPTOSUBMISSIONFRAME.superclass.constructor.apply(this, arguments);
};

Y.extend(PANOPTOSUBMISSIONFRAME, Y.Base, {

    courseid: null,

    /**
     * Init function for the checkboxselection module
     * @property params
     * @type {Object}
     */
    init : function(params) {
        // Check to make sure parameters are initialized
        if ('0' === params.addvidbtnid || '0' === params.ltilaunchurl || 0 === params.courseid || 0 === params.height || 0 === params.width) {
            return;
        }

        this.courseid = params.courseid

        var addvideobtn = Y.one('#' + params.addvidbtnid);
        addvideobtn.on('click', this.open_panopto_window_callback, this, params.ltilaunchurl, params.height, params.width);
    },

    /**
     * Event handler callback for when the panel content is changed
     * @property e
     * @type {Object}
     */
    open_panopto_window_callback: function(e, url, height, width) {
        var panoptoWindow = new M.core.dialogue({
            bodyContent: '<iframe src="' + url + '" width="100%" height="100%"></iframe>',
            headerContent: M.util.get_string('select_submission', 'panoptosubmission'),
            width: width,
            height: height,
            draggable: false,
            visible: true,
            zindex: 100,
            modal: true,
            focusOnPreviousTargetAfterHide: true,
            render: true
        });

        document.body.panoptoWindow = panoptoWindow;
        document.body.addEventListener('sessionSelected', this.close_popup_callback.bind(this), false);
    },

    close_popup_callback: function(closeEvent) {
        Y.one('input[id=submit_video]').removeAttribute('disabled');
        // Update the iframe element attributes and sec to point to correct content.
        var iframenode = Y.one('iframe[id=contentframe]'),
            contentwrappernode = Y.one('div[id=contentcontainer]'),
            thumbnailnode = Y.one('img[id=panoptothumbnail]'),
            thumbnaillinknode = Y.one('a[id=panoptothumbnaillink]'),
            titlenode = Y.one('a[id=panoptosessiontitle]'),
            newSubmissionSource = new URL(closeEvent.detail.ltiViewerUrl),
            search_params = newSubmissionSource.searchParams;

        // This will encode the params so decode the json once to make sure it is not double encoded.
        search_params.set('course', this.courseid);
        search_params.set('custom', decodeURI(closeEvent.detail.customData));
        search_params.set('contentUrl', closeEvent.detail.contentUrl);

        // change the search property of the main url
        newSubmissionSource.search = search_params.toString();

        Y.one('input[id=sessiontitle]').setAttribute('value', closeEvent.detail.title);
        Y.one('input[id=source]').setAttribute('value', closeEvent.detail.contentUrl);
        Y.one('input[id=customdata]').setAttribute('value', closeEvent.detail.customData);
        Y.one('input[id=width]').setAttribute('value', closeEvent.detail.width);
        Y.one('input[id=height]').setAttribute('value', closeEvent.detail.height);
        Y.one('input[id=thumbnailsource]').setAttribute('value', closeEvent.detail.thumbnailUrl);
        Y.one('input[id=thumbnailwidth]').setAttribute('value', closeEvent.detail.thumbnailWidth);
        Y.one('input[id=thumbnailheight]').setAttribute('value', closeEvent.detail.thumbnailHeight);

        titlenode.setContent(closeEvent.detail.title);
        titlenode.setAttribute('href', newSubmissionSource.toString());

        thumbnaillinknode.setAttribute('href', newSubmissionSource.toString());
        thumbnailnode.setAttribute('src', closeEvent.detail.thumbnailUrl);
        thumbnailnode.setAttribute('width', closeEvent.detail.thumbnailWidth);
        thumbnailnode.setAttribute('height', closeEvent.detail.thumbnailHeight);

        // Do not set the iframe src yet, it will cause the video to play when the iframe is not visible.
        iframenode.setAttribute('width', closeEvent.detail.width);
        iframenode.setAttribute('height', closeEvent.detail.height);

        if (!iframenode.hasClass('session-hidden')) {
            iframenode.setAttribute('src', newSubmissionSource.toString());
        }

        contentwrappernode.removeClass('no-session');

        Y.one('#id_add_video').set('value', M.util.get_string('replacevideo', 'panoptosubmission'));
        // Update button classes.
        Y.one('#id_add_video').addClass('btn-secondary');
        Y.one('#submit_video').addClass('btn-primary');
        Y.one('#id_add_video').removeClass('btn-primary');
        Y.one('#submit_video').removeClass('btn-secondary');
        document.body.panoptoWindow.destroy();
    },

},
{
    NAME : 'moodle-mod_panoptosubmission-submissionpanel',
    ATTRS : {
        addvidbtnid : {
            value: '0'
        },
        ltilaunchurl : {
            value: '0'
        },
        height : {
            value: 0
        },
        width : {
            value: 0
        },
        courseid : {
            value: 0
        }
    }
});

M.mod_panoptosubmission = M.mod_panoptosubmission || {};

/**
 * Entry point for PANOPTOSUBMISSIONFRAME module
 * @param string params additional parameters.
 * @return object the submissionpanel object
 */
M.mod_panoptosubmission.initsubmissionpanel = function(params) {
    return new PANOPTOSUBMISSIONFRAME(params);
};