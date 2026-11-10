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
 * AMD module that shows and hides the session preview on the activity page.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["mod_panoptosubmission/viewerlaunch"], (ViewerLaunch) =>
{
    /**
     * Show or hide the embedded session preview.
     *
     * The toggle carries its labels in its data-show-text and data-hide-text attributes.
     *
     * @param {Event} clickEvent The click on the toggle link.
     */
    var toggle_session_display = (clickEvent) =>
    {
        clickEvent.preventDefault();

        let toggleLink = clickEvent.currentTarget,
            sessionContainer = document.getElementById("sessioncontainer"),
            sessionIframe = document.getElementById("contentframe"),
            titleLink = document.getElementById("panoptosessiontitle");

        if (!sessionContainer || !sessionIframe || !titleLink)
        {
            return;
        }

        if (sessionContainer.classList.contains("session-hidden"))
        {
            sessionContainer.classList.remove("session-hidden");
            // The viewer only accepts a POST with the session key.
            ViewerLaunch.launch(titleLink.href, sessionIframe.name);
            toggleLink.textContent = toggleLink.dataset.hideText;
        }
        else
        {
            sessionContainer.classList.add("session-hidden");
            sessionIframe.setAttribute("src", "about:blank");
            toggleLink.textContent = toggleLink.dataset.showText;
        }
    };

    /**
     * Attach the click handler to the preview toggle.
     */
    var init = () =>
    {
        let toggleLink = document.getElementById("showsessiontoggle");

        if (toggleLink)
        {
            toggleLink.addEventListener("click", toggle_session_display);
        }
    };

    return {
        init: init,
    };
});
