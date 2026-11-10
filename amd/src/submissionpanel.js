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
 * AMD module for displaying an LTI launch within a AMD panel.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2024
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    "jquery",
    "core/notification",
    "core/modal",
    "core/str",
    "mod_panoptosubmission/viewerlaunch",
], ($, notification, Modal, str, ViewerLaunch) =>
{
    let courseId = null;

    /**
     * Bind the "Add video" button to open the Panopto selection popup.
     *
     * @param {Object} params Settings passed from view.php.
     * @param {string} params.addvidbtnid Id of the button that opens the popup.
     * @param {string} params.ltilaunchurl URL of contentitem.php, loaded in the popup.
     * @param {number} params.courseid The course id.
     * @param {number} params.height Popup height in pixels.
     * @param {number} params.width Popup width in pixels.
     */
    var init = (params) =>
    {
        if (   "0" === params.addvidbtnid
            || "0" === params.ltilaunchurl
            || 0 === params.courseid
            || 0 === params.height
            || 0 === params.width)
        {
            return;
        }

        courseId = params.courseid;

        let addVideoBtn = $("#" + params.addvidbtnid);
        addVideoBtn.on("click", () =>
        {
            open_panopto_window_callback(
                params.ltilaunchurl,
                params.height,
                params.width
            );
        });

        // Listen once for the page, not once per popup, so a selection is only applied once.
        document.body.addEventListener("sessionSelected", close_popup_callback, false);
        document.body.addEventListener("sessionSelectionFailed", selection_failed_callback, false);
    };

    /**
     * Open the Panopto selection popup in a modal.
     *
     * The popup posts back to contentitem_return.php, which fires the sessionSelected event.
     *
     * @param {string} url URL loaded in the popup iframe.
     * @param {number} height Requested popup height, unused as the modal sizes itself responsively.
     * @param {number} width Requested popup width, unused as the modal sizes itself responsively.
     */
    var open_panopto_window_callback = (url, height, width) =>
    {
        // Modal custom size class.
        let modalClass = "mod-panoptosubmission-modal-custom-size";

        // Ensure unique class names for dynamic styling.
        let modalDialogClass = "mod-panoptosubmission-modal-dialog-custom";
        let modalContentClass = "mod-panoptosubmission-modal-content-custom";
        let modalBodyClass = "mod-panoptosubmission-modal-body-custom";
        let iframeClass = "mod-panoptosubmission-iframe-custom";

        Promise.all([
            str.get_string("select_submission", "panoptosubmission"),
            // Destroy the modal when it is closed, so popups closed with X do not pile up.
            Modal.create({removeOnClose: true}),
        ])
            .then(([selectText, modal]) =>
            {
                modal.setTitle(selectText);
                modal.getRoot().addClass(modalClass);

                // Build the iframe through the DOM so the URL is set as an attribute, never parsed as markup.
                modal.getBody().append(
                    $("<iframe>").addClass(iframeClass).attr({src: url, frameborder: "0"})
                );
                
                // Get screen width for responsive sizing
                const screenWidth = window.innerWidth;
                
                let modalDialog = modal.getRoot().find(".modal-dialog");
                let modalContent = modal.getRoot().find(".modal-content");
                let modalBody = modal.getRoot().find(".modal-body");
                
                modalDialog.addClass(modalDialogClass);
                modalContent.addClass(modalContentClass);
                modalBody.addClass(modalBodyClass);
                
                // Responsive sizing logic
                if (screenWidth <= 600) {
                    // Small screens - use viewport dimensions with scrolling
                    modalDialog.css({
                        width: "98vw",
                        "max-width": "98vw",
                        margin: "0.25rem auto"
                    });
                    modalContent.css({
                        height: "50vh",
                        "max-height": "50vh"
                    });
                    modalBody.css({
                        overflow: "auto",
                        padding: "0"
                    });
                } else if (screenWidth <= 768) {
                    // Medium screens
                    modalDialog.css({
                        width: "95vw",
                        "max-width": "95vw",
                        margin: "0.5rem auto"
                    });
                    modalContent.css({
                        height: "60vh",
                        "max-height": "60vh"
                    });
                    modalBody.css({
                        overflow: "auto",
                        padding: "0"
                    });
                } else if (screenWidth <= 900) {
                    // Large screens but still constrained
                    modalDialog.css({
                        width: "90vw",
                        "max-width": "90vw",
                        margin: "1rem auto"
                    });
                    modalContent.css({
                        height: "70vh",
                        "max-height": "70vh"
                    });
                    modalBody.css({
                        overflow: "auto",
                        padding: "0"
                    });
                } else {
                    // Desktop - use responsive dimensions without forcing scroll
                    modalDialog.css({
                        width: "90vw",
                        "max-width": "1200px",
                        margin: "2rem auto"
                    });
                    modalContent.css({
                        height: "80vh",
                        "max-height": "80vh"
                    });
                    modalBody.css({
                        overflow: "hidden",
                        padding: "0"
                    });
                }
                
                modal.show();

                document.body.panoptoWindow = modal;
            }).catch(notification.exception);
    };

    /**
     * Apply the session chosen in the popup to the submission form and preview, then close the popup.
     *
     * @param {CustomEvent} closeEvent The sessionSelected event from contentitem_return.php.
     * @param {Object} closeEvent.detail The chosen session: title, ltiViewerUrl, contentUrl, customData,
     *     width, height, thumbnailUrl, thumbnailWidth and thumbnailHeight.
     */
    var close_popup_callback = (closeEvent) =>
    {
        $("input[id=submit_video]").removeAttr("disabled");

        let iFrameNode = $("iframe[id=contentframe]"),
            contentWrapperNode = $("div[id=contentcontainer]"),
            thumbnailNode = $("img[id=panoptothumbnail]"),
            thumbnailLinkNode = $("a[id=panoptothumbnaillink]"),
            titleNode = $("a[id=panoptosessiontitle]"),
            newSubmissionSource = new URL(closeEvent.detail.ltiViewerUrl),
            searchParams = newSubmissionSource.searchParams;

        searchParams.set("course", courseId);
        searchParams.set("custom", decodeURI(closeEvent.detail.customData));
        searchParams.set("contenturl", closeEvent.detail.contentUrl);

        newSubmissionSource.search = searchParams.toString();

        $("input[id=sessiontitle]").attr("value", closeEvent.detail.title);
        $("input[id=source]").attr("value", closeEvent.detail.contentUrl);
        $("input[id=customdata]").attr(
            "value",
            closeEvent.detail.customData
        );
        $("input[id=width]").attr("value", closeEvent.detail.width);
        $("input[id=height]").attr("value", closeEvent.detail.height);
        $("input[id=thumbnailsource]").attr(
            "value",
            closeEvent.detail.thumbnailUrl
        );
        $("input[id=thumbnailwidth]").attr(
            "value",
            closeEvent.detail.thumbnailWidth
        );
        $("input[id=thumbnailheight]").attr(
            "value",
            closeEvent.detail.thumbnailHeight
        );

        titleNode.text(closeEvent.detail.title);
        titleNode.attr("href", newSubmissionSource.toString());

        thumbnailLinkNode.attr("href", newSubmissionSource.toString());

        // Create the image when the first submission has no thumbnail yet.
        if (closeEvent.detail.thumbnailUrl)
        {
            if (0 === thumbnailNode.length)
            {
                thumbnailNode = $("<img>", {
                    id: "panoptothumbnail",
                    class: "mod-panoptosubmission-thumbnail",
                }).appendTo(thumbnailLinkNode);
            }

            thumbnailNode.attr("src", closeEvent.detail.thumbnailUrl);
            thumbnailNode.attr("alt", closeEvent.detail.title);
            thumbnailNode.attr("width", closeEvent.detail.thumbnailWidth);
            thumbnailNode.attr("height", closeEvent.detail.thumbnailHeight);
        }
        else
        {
            // An empty src would make the browser request this page as an image.
            thumbnailNode.remove();
        }

        iFrameNode.attr("width", closeEvent.detail.width);
        iFrameNode.attr("height", closeEvent.detail.height);

        // A hidden preview is launched by sessionpreview.js when it is shown. The class is on its container.
        if (iFrameNode.length && 0 === iFrameNode.closest(".session-hidden").length)
        {
            // The viewer only accepts a POST with the session key.
            ViewerLaunch.launch(newSubmissionSource.toString(), iFrameNode.attr("name"));
        }

        contentWrapperNode.removeClass("no-session");

        str.get_string("replacevideo", "panoptosubmission")
            .done((replaceText) =>
            {
                $("#id_add_video").val(replaceText);
            })
            .fail(notification.exception);

        $("#id_add_video").addClass("btn-secondary");
        $("#submit_video").addClass("btn-primary");
        $("#id_add_video").removeClass("btn-primary");
        $("#submit_video").removeClass("btn-secondary");
        document.body.panoptoWindow.destroy();
    };

    /**
     * Close the popup and tell the user why the chosen session was rejected.
     *
     * The submission form is left unchanged, so nothing from the rejected selection can be submitted.
     *
     * @param {CustomEvent} failEvent The sessionSelectionFailed event from contentitem_return.php.
     * @param {Object} failEvent.detail Holds messages, the translated reasons to show.
     */
    var selection_failed_callback = (failEvent) =>
    {
        if (document.body.panoptoWindow)
        {
            document.body.panoptoWindow.destroy();
        }

        str.get_string("error", "moodle")
            .then((title) => notification.alert(title, failEvent.detail.messages.join("<br>")))
            .catch(notification.exception);
    };

    return {
        initsubmissionpanel: init,
    };
});
