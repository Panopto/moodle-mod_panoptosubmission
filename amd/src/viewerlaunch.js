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
 * AMD module that opens view_submission.php with a POST carrying the session key.
 *
 * The viewer launches the Panopto LTI tool, which creates the course's folder when it has none,
 * so it only accepts a POST with the session key. The pages keep plain viewer URLs, without the
 * key, and this module posts their parameters into the player iframe or a new tab.
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2026
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["core/config"], (Config) =>
{
    /**
     * Add a hidden field to a form.
     *
     * @param {HTMLFormElement} form The form.
     * @param {string} name The field name.
     * @param {string} value The field value.
     */
    var add_field = (form, name, value) =>
    {
        let field = document.createElement("input");

        field.type = "hidden";
        field.name = name;
        field.value = value;
        form.appendChild(field);
    };

    /**
     * Open the viewer by posting a viewer URL's parameters, plus the session key, to its address.
     *
     * @param {string} url A view_submission.php URL, whose query parameters become the posted fields.
     * @param {string} target The name of the iframe or window to load it in, such as "_blank".
     */
    var launch = (url, target) =>
    {
        let launchUrl = new URL(url, window.location.href),
            form = document.createElement("form");

        form.method = "post";
        form.action = launchUrl.origin + launchUrl.pathname;
        form.target = target;
        form.hidden = true;

        launchUrl.searchParams.forEach((value, name) => add_field(form, name, value));
        add_field(form, "sesskey", Config.sesskey);

        document.body.appendChild(form);
        form.submit();
        form.remove();
    };

    /**
     * Open a viewer link through a POST instead of following it.
     *
     * The link's href is read at click time, as submissionpanel.js changes it when a video is picked.
     *
     * @param {Event} clickEvent The click on a data-post-launch link.
     */
    var launch_link = (clickEvent) =>
    {
        let link = clickEvent.currentTarget;

        clickEvent.preventDefault();
        launch(link.href, link.target || "_self");
    };

    /**
     * Load each visible player iframe, and open viewer links through a POST.
     *
     * Iframes carry their viewer URL in data-launch-url and links opt in with data-post-launch.
     * An iframe inside a hidden preview is skipped: sessionpreview.js launches it when it is shown.
     * Each element is marked once handled, so calling this again only picks up new ones.
     */
    var init = () =>
    {
        document.querySelectorAll("iframe[data-launch-url]:not([data-launched])").forEach((frame) =>
        {
            if (!frame.closest(".session-hidden"))
            {
                frame.dataset.launched = "1";
                launch(frame.dataset.launchUrl, frame.name);
            }
        });

        document.querySelectorAll("a[data-post-launch]:not([data-post-launch-bound])").forEach((link) =>
        {
            link.dataset.postLaunchBound = "1";
            link.addEventListener("click", launch_link);
        });
    };

    return {
        init: init,
        launch: launch,
    };
});
