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
 * Panopto lti helper object. Contains info required for Panopto LTI tools to be used in text editors
 *
 * @package mod_panoptosubmission
 * @copyright  Panopto 2021
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panoptosubmission_lti_utility {

    /**
     * Get the id of the pre-configured LTI tool that matched the Panopto server a course is provisioned to.
     * If multiple LTI tools are configured to a single server this will get the first one.
     *
     * @param int $courseid - the id of the course we are targetting in moodle.
     * @return int the id of the first matching tool
     */
    public static function get_course_tool_id($courseid) {
        global $DB;

        $ltitooltypes = $DB->get_records('lti_types', null, 'name');
        $targetservername = $DB->get_field('block_panopto_foldermap', 'panopto_server', array('moodleid' => $courseid));

        $tooltypes = [];
        foreach ($ltitooltypes as $type) {
            $type->config = lti_get_config(
                (object)[
                    'typeid' => $type->id,
                ]
            );

            if (!empty($targetservername) && strpos($type->config['toolurl'], $targetservername) !== false &&
                $type->state == LTI_TOOL_STATE_CONFIGURED) {
                $currentconfig = lti_get_type_config($type->id);

                if (!empty($currentconfig['customparameters']) &&
                    strpos($currentconfig['customparameters'], 'panopto_student_submission_tool') !== false) {
                    return $type->id;
                }
            }
        }

        return null;
    }
    /**
     * Launch an external tool activity.
     *
     * @param  stdClass $instance the external tool activity settings
     * @return string The HTML code containing the javascript code for the launch
     */
    public static function launch_tool($instance) {
        list($endpoint, $params) = self::get_launch_data($instance);

        $debuglaunch = ( $instance->debuglaunch == 1 );

        $content = lti_post_launch_html($params, $endpoint, $debuglaunch);

        return $content;
    }

    /**
     * Return the launch data required for opening the external tool.
     *
     * @param  stdClass $instance the external tool activity settings
     * @param  string $nonce  the nonce value to use (applies to LTI 1.3 only)
     * @return array the endpoint URL and parameters (including the signature)
     * @since  Moodle 3.0
     */
    private static function get_launch_data($instance, $nonce = '') {
        global $PAGE, $CFG, $USER;

        if (empty($CFG)) {
            require_once(dirname(__FILE__) . '/../../../../../config.php');
            require_login();
        }

        require_once($CFG->dirroot . '/mod/lti/lib.php');
        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        if (empty($instance->typeid)) {
            $tool = lti_get_tool_by_url_match($instance->toolurl, $instance->course);
            if ($tool) {
                $typeid = $tool->id;
                $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
            } else {
                $tool = lti_get_tool_by_url_match($instance->securetoolurl,  $instance->course);
                if ($tool) {
                    $typeid = $tool->id;
                    $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
                } else {
                    $typeid = null;
                    $ltiversion = LTI_VERSION_1;
                }
            }
        } else {
            $typeid = $instance->typeid;
            $tool = lti_get_type($typeid);
            $ltiversion = isset($tool->ltiversion) ? $tool->ltiversion : LTI_VERSION_1;
        }

        if ($typeid) {
            $typeconfig = lti_get_type_config($typeid);
        } else {
            // There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
            $typeconfig = (array)$instance;

            $typeconfig['sendname'] = $instance->instructorchoicesendname;
            $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
            $typeconfig['customparameters'] = $instance->instructorcustomparameters;
            $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
            $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
            $typeconfig['forcessl'] = '0';
        }

        // Default the organizationid if not specified.
        if (empty($typeconfig['organizationid'])) {
            $urlparts = parse_url($CFG->wwwroot);

            $typeconfig['organizationid'] = $urlparts['host'];
        }

        if (isset($tool->toolproxyid)) {
            $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;
        } else {
            $toolproxy = null;
            if (!empty($instance->resourcekey)) {
                $key = $instance->resourcekey;
            } else if (defined('LTI_VERSION_1P3') && ($ltiversion === LTI_VERSION_1P3)) {
                $key = $tool->clientid;
            } else if (!empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            } else {
                $key = '';
            }
            if (!empty($instance->password)) {
                $secret = $instance->password;
            } else if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            } else {
                $secret = '';
            }
        }

        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
        $endpoint = trim($endpoint);

        // If the current request is using SSL and a secure tool URL is specified, use it.
        if (lti_request_is_using_ssl() && !empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            if (!empty($instance->securetoolurl)) {
                $endpoint = trim($instance->securetoolurl);
            }

            $endpoint = lti_ensure_url_is_https($endpoint);
        } else {
            if (!strstr($endpoint, '://')) {
                $endpoint = 'http://' . $endpoint;
            }
        }

        $orgid = $typeconfig['organizationid'];

        $course = $PAGE->course;
        $islti2 = isset($tool->toolproxyid);

        if (!property_exists($instance, 'course')) {
            $instance->course = $course->id;
        }

        $allparams = lti_build_request($instance, $typeconfig, $course, $typeid, $islti2);

        if (property_exists($instance, 'custom')) {
            foreach ($instance->custom as $customkey => $customvalue) {
                $allparams['custom_' . $customkey] = $customvalue;
            }
        }

        if ($islti2) {
            $requestparams = lti_build_request_lti2($tool, $allparams);
        } else {
            $requestparams = $allparams;
        }

        // This is needed to make the lti tool support moodle v3.5.0.
        if (function_exists('lti_build_standard_message')) {
            $requestparams = array_merge($requestparams, lti_build_standard_message($instance, $orgid, $ltiversion));
        } else {
            $requestparams = array_merge($requestparams, lti_build_standard_request($instance, $orgid, $islti2));
        }

        $customstr = '';
        if (isset($typeconfig['customparameters'])) {
            $customstr = $typeconfig['customparameters'];
        }
        $requestparams = array_merge($requestparams, lti_build_custom_parameters(
            $toolproxy,
            $tool,
            $instance,
            $allparams,
            $customstr,
            $instance->instructorcustomparameters,
            $islti2
        ));

        $launchcontainer = lti_get_launch_container($instance, $typeconfig);
        $returnurlparams = array('course' => $course->id,
                                 'launch_container' => $launchcontainer,
                                 'instanceid' => $instance->typeid,
                                 'sesskey' => sesskey());

        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        $url = new \moodle_url('/mod/lti/return.php', $returnurlparams);
        $returnurl = $url->out(false);

        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            $returnurl = lti_ensure_url_is_https($returnurl);
        }

        $target = '';
        switch($launchcontainer) {
            case LTI_LAUNCH_CONTAINER_EMBED:
            case LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }
        if (!empty($target)) {
            $requestparams['launch_presentation_document_target'] = $target;
        }

        $requestparams['launch_presentation_return_url'] = $returnurl;

        // Add the parameters configured by the LTI services.
        if ($typeid && !$islti2) {
            $services = lti_get_services();
            foreach ($services as $service) {
                $serviceparameters = $service->get_launch_parameters('basic-lti-launch-request',
                        $course->id, $USER->id , $typeid, $instance->typeid);
                foreach ($serviceparameters as $paramkey => $paramvalue) {
                    $requestparams['custom_' . $paramkey] = lti_parse_custom_parameter(
                        $toolproxy, $tool, $requestparams, $paramvalue, $islti2
                    );
                }
            }
        }

        // Allow request params to be updated by sub-plugins.
        $plugins = core_component::get_plugin_list('ltisource');
        foreach (array_keys($plugins) as $plugin) {
            $pluginparams = component_callback('ltisource_'.$plugin, 'before_launch',
                array($instance, $endpoint, $requestparams), array());

            if (!empty($pluginparams) && is_array($pluginparams)) {
                $requestparams = array_merge($requestparams, $pluginparams);
            }
        }

        if ((!empty($key) && !empty($secret)) || (defined('LTI_VERSION_1P3') && $ltiversion === LTI_VERSION_1P3)) {

            // Lti_sign_jwt was not added until 3.7 so we need to support the original style of processing this.
            if (defined('LTI_VERSION_1P3') && function_exists('lti_sign_jwt')) {
                if ($ltiversion !== LTI_VERSION_1P3) {
                    $params = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
                } else {
                    $params = lti_sign_jwt($requestparams, $endpoint, $key, $typeid, $nonce);
                }
            } else {
                $params = lti_sign_parameters($requestparams, $endpoint, 'POST', $key, $secret);
            }

            $endpointurl = new \moodle_url($endpoint);
            $endpointparams = $endpointurl->params();

            // Strip querystring params in endpoint url from $params to avoid duplication.
            if (!empty($endpointparams) && !empty($params)) {
                foreach (array_keys($endpointparams) as $paramname) {
                    if (isset($params[$paramname])) {
                        unset($params[$paramname]);
                    }
                }
            }

        } else {
            // If no key and secret, do the launch unsigned.
            $returnurlparams['unsigned'] = '1';
            $params = $requestparams;
        }

        return array($endpoint, $params);
    }

    /**
     * Returns true or false depending on if the active user is enrolled in a context
     *
     * @param object $targetcontext the context we are checking enrollment for
     * @return bool true or false if the user is enrolled in the context
     */
    public static function is_active_user_enrolled($targetcontext) {
        global $USER;

        return is_enrolled($targetcontext, $USER, 'mod/assignment:submit');
    }
}
