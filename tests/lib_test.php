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
 * Unit tests for mod_panoptosubmission library helpers.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_panoptosubmission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/panoptosubmission/lib.php');

/**
 * Unit tests for mod_panoptosubmission library helpers.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Property lookup is case-insensitive and falls back when the value is empty.
     *
     * @covers ::panoptosubmission_get_property_or_default
     */
    public function test_get_property_or_default(): void {
        $item = (object) [
            'Title' => 'Session one',
            'width' => 720,
            'empty' => '',
            'zero' => 0,
        ];

        $this->assertSame('Session one', panoptosubmission_get_property_or_default($item, 'title', 'fallback'));
        $this->assertSame(720, panoptosubmission_get_property_or_default($item, 'WIDTH', 128));
        $this->assertSame('fallback', panoptosubmission_get_property_or_default($item, 'missing', 'fallback'));
        $this->assertSame('fallback', panoptosubmission_get_property_or_default($item, 'empty', 'fallback'));
        $this->assertSame('fallback', panoptosubmission_get_property_or_default($item, 'zero', 'fallback'));
        $this->assertSame('fallback', panoptosubmission_get_property_or_default(null, 'title', 'fallback'));
        $this->assertSame('fallback', panoptosubmission_get_property_or_default(['title' => 'x'], 'title', 'fallback'));
    }

    /**
     * Values emitted into the <script> block cannot close the literal or the element.
     *
     * @covers ::panoptosubmission_encode_script_json
     */
    public function test_encode_script_json_neutralises_script_delimiters(): void {
        $title = '</script><script>alert(1)</script>';
        $contenturl = 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1&amp;foo=2';
        $quoted = 'He said "hi" and \'bye\'';

        $encoded = panoptosubmission_encode_script_json([
            'title' => $title,
            'contentUrl' => $contenturl,
            'quoted' => $quoted,
        ]);

        $this->assertIsString($encoded);

        // Script delimiters and escaped quotes must not survive.
        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('>', $encoded);
        $this->assertStringNotContainsString('&', $encoded);
        $this->assertStringNotContainsString("'", $encoded);
        $this->assertStringNotContainsString('\\"', $encoded);

        // Escaping must not corrupt the payload the browser reads back.
        $decoded = json_decode($encoded, true);
        $this->assertSame($title, $decoded['title']);
        $this->assertSame($contenturl, $decoded['contentUrl']);
        $this->assertSame($quoted, $decoded['quoted']);
    }

    /**
     * An unencodable value reports failure so the caller can take its error branch.
     *
     * @covers ::panoptosubmission_encode_script_json
     */
    public function test_encode_script_json_reports_failure(): void {
        $this->assertFalse(panoptosubmission_encode_script_json(['title' => "\xB1\x31"]));
    }

    /**
     * Feedback files are readable by the submission's owner and by graders only.
     *
     * @covers ::panoptosubmission_can_access_submission_files
     */
    public function test_submission_files_access(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $otheractivity = $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);
        $context = \context_module::instance($cm->id);

        $owner = $generator->create_and_enrol($course, 'student');
        $classmate = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'teacher');

        $plugingenerator = $generator->get_plugin_generator('mod_panoptosubmission');
        $submission = $plugingenerator->create_submission($activity, $owner->id);
        $elsewhere = $plugingenerator->create_submission($otheractivity, $owner->id);

        $this->setUser($owner);
        $this->assertTrue(panoptosubmission_can_access_submission_files($cm, $context, $submission->id));

        $this->setUser($classmate);
        $this->assertFalse(panoptosubmission_can_access_submission_files($cm, $context, $submission->id));

        $this->setUser($teacher);
        $this->assertTrue(panoptosubmission_can_access_submission_files($cm, $context, $submission->id));

        // An item id from another activity is never served through this module's context.
        $this->assertFalse(panoptosubmission_can_access_submission_files($cm, $context, $elsewhere->id));
        $this->assertFalse(panoptosubmission_can_access_submission_files($cm, $context, 0));
    }

    /**
     * Course clean-up removes every activity and submission in the course, and nothing elsewhere.
     *
     * @covers ::panoptosubmission_delete_course_instances
     */
    public function test_delete_course_instances(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('mod_panoptosubmission');

        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $first = $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $second = $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $plugingenerator->create_submission($first, $student->id);
        $plugingenerator->create_submission($second, $student->id);

        $othercourse = $generator->create_course();
        $kept = $generator->create_module('panoptosubmission', ['course' => $othercourse->id]);
        $plugingenerator->create_submission($kept, $student->id);

        panoptosubmission_delete_course_instances($course->id);

        $this->assertFalse($DB->record_exists('panoptosubmission', ['course' => $course->id]));

        [$insql, $params] = $DB->get_in_or_equal([$first->id, $second->id]);
        $this->assertEquals(0, $DB->count_records_select('panoptosubmission_submission', "panactivityid $insql", $params));

        $this->assertTrue($DB->record_exists('panoptosubmission', ['id' => $kept->id]));
        $this->assertTrue($DB->record_exists('panoptosubmission_submission', ['panactivityid' => $kept->id]));
    }

    /**
     * Saving the activity queues provisioning in the background, once per course.
     *
     * @covers ::panoptosubmission_add_instance
     * @covers ::panoptosubmission_queue_course_provisioning
     */
    public function test_add_instance_queues_provisioning_once(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $generator->create_module('panoptosubmission', ['course' => $course->id]);

        $tasks = \core\task\manager::get_adhoc_tasks('\\mod_panoptosubmission\\task\\provision_course');
        $this->assertCount(1, $tasks);
        $this->assertEquals($course->id, reset($tasks)->get_custom_data()->courseid);
    }

    /**
     * The provisioning task fails on a missing target server, so core retries it once one is set.
     *
     * @covers \mod_panoptosubmission\task\provision_course::execute
     */
    public function test_provision_task_fails_without_target_server(): void {
        $this->resetAfterTest();

        set_config('automatic_operation_target_server', '', 'block_panopto');
        $course = $this->getDataGenerator()->create_course();

        $task = new \mod_panoptosubmission\task\provision_course();
        $task->set_custom_data(['courseid' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('no_automatic_operation_target_server', 'panoptosubmission'));
        $task->execute();
    }

    /**
     * Restoring a course that holds the activity queues provisioning, as restores skip add_instance().
     *
     * @covers \mod_panoptosubmission_observer::course_restored
     */
    public function test_course_restored_queues_provisioning(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $plain = $generator->create_course();
        $generator->create_module('panoptosubmission', ['course' => $course->id]);

        // Start from an empty queue so only the restore events count.
        $DB->delete_records('task_adhoc', ['classname' => '\\mod_panoptosubmission\\task\\provision_course']);

        foreach ([$course, $plain] as $restored) {
            \core\event\course_restored::create([
                'objectid' => $restored->id,
                'context' => \context_course::instance($restored->id),
                'other' => [
                    'type' => 'course',
                    'target' => 1,
                    'mode' => 10,
                    'operation' => 'restore',
                    'samesite' => true,
                ],
            ])->trigger();
        }

        $tasks = \core\task\manager::get_adhoc_tasks('\\mod_panoptosubmission\\task\\provision_course');
        $this->assertCount(1, $tasks);
        $this->assertEquals($course->id, reset($tasks)->get_custom_data()->courseid);
    }

    /**
     * The edit form shows the grade type that was saved, although the decimal column loads "None" as '0.00'.
     *
     * @covers \mod_panoptosubmission_mod_form::data_preprocessing
     */
    public function test_edit_form_keeps_saved_grade_type(): void {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/panoptosubmission/mod_form.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $scale = $generator->create_scale(['scale' => 'Bad,Good,Great']);

        $PAGE->set_url('/course/modedit.php');
        $PAGE->set_course($course);

        $cases = ['none' => 0, 'point' => 100, 'scale' => -$scale->id];
        foreach ($cases as $expectedtype => $grade) {
            $activity = $generator->create_module('panoptosubmission', ['course' => $course->id, 'grade' => $grade]);
            $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);

            [$cm, , , $data, $cw] = get_moduleinfo_data($cm, $course);
            $form = new \mod_panoptosubmission_mod_form($data, $cw->section, $cm, $course);
            $form->set_data($data);

            // Read the grade type select inside the modgrade group.
            $group = (fn() => $this->_form)->call($form)->getElement('grade');
            $type = null;
            foreach ($group->getElements() as $element) {
                if ($element->getName() === 'modgrade_type') {
                    $type = $element->getValue();
                }
            }

            $this->assertSame([$expectedtype], $type, "Grade {$grade} should edit as {$expectedtype}");
        }
    }

    /**
     * The LTI return page checks the sesskey only after the session exists.
     *
     * @coversNothing
     */
    public function test_contentitem_return_checks_sesskey_after_login(): void {
        global $CFG;

        $src = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/contentitem_return.php');

        $login = strpos($src, 'panoptosubmission_require_login_or_repost(');
        $sesskey = strpos($src, 'require_sesskey();');

        $this->assertNotFalse($login, 'contentitem_return.php no longer establishes the session');
        $this->assertNotFalse($sesskey, 'contentitem_return.php no longer calls require_sesskey()');
        $this->assertGreaterThan(
            $login,
            $sesskey,
            'require_sesskey() must run after the cross-site repost has restored the session'
        );
    }

    /**
     * The viewer launches Panopto only for a POST with the session key, which another site cannot forge.
     *
     * A launch makes Panopto create the course's folder, so a plain GET must not reach it (audit finding H6).
     *
     * @coversNothing
     */
    public function test_view_submission_launches_only_for_posted_sesskey(): void {
        global $CFG;

        // Regression guard on the source: the page runs as a script and exits, so it can't be called here.
        // It fails if the checks are reworded (say, to confirm_sesskey()), which only means it needs updating.
        $src = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/view_submission.php');

        $posted = strpos($src, 'if (!data_submitted()) {');
        $getpageexit = strpos($src, 'exit;', (int) $posted);
        $sesskey = strpos($src, 'require_sesskey();');
        $launches = [
            'lti_initiate_login' => strpos($src, 'lti_initiate_login('),
            'launch_tool' => strpos($src, 'launch_tool('),
        ];

        $this->assertNotFalse($posted, 'view_submission.php no longer handles requests that are not posted');
        $this->assertNotFalse($sesskey, 'view_submission.php no longer checks the session key');
        $this->assertNotFalse($getpageexit, 'The page shown for a GET no longer exits');
        $this->assertLessThan($sesskey, $getpageexit, 'The page shown for a GET must exit before the session key check');
        foreach ($launches as $call => $position) {
            $this->assertNotFalse($position, "view_submission.php no longer calls {$call}()");
            $this->assertLessThan($position, $posted, "The POST check must run before {$call}()");
            $this->assertLessThan($position, $sesskey, "The session key check must run before {$call}()");
        }
    }

    /**
     * The viewer refuses a course that has no Panopto submission activity, before the Open video
     * button and before any launch.
     *
     * A launch makes Panopto create the course's folder, so a GET for a course with no submission
     * activity must not offer a button that would create it on click (audit finding H6). The same
     * check runs before the POST path, so a posted session key is not enough on its own.
     *
     * @coversNothing
     */
    public function test_view_submission_requires_a_submission_activity(): void {
        global $CFG;

        // Regression guard on the source: the page runs as a script and exits, so it can't be called here.
        $src = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/view_submission.php');

        $activity = strpos($src, "get_coursemodules_in_course('panoptosubmission', \$courseid)");
        $refused = strpos($src, "throw new moodle_exception('invalidrequest', 'error');");
        $posted = strpos($src, 'if (!data_submitted()) {');
        $launches = [
            'lti_initiate_login' => strpos($src, 'lti_initiate_login('),
            'launch_tool' => strpos($src, 'launch_tool('),
        ];

        $this->assertNotFalse($activity, 'view_submission.php no longer checks for a submission activity');
        $this->assertNotFalse($refused, 'view_submission.php no longer refuses a course with no submission activity');
        $this->assertNotFalse($posted, 'view_submission.php no longer handles requests that are not posted');
        $this->assertLessThan(
            $refused,
            $activity,
            'The activity check must decide before the request is refused'
        );
        $this->assertLessThan(
            $posted,
            $refused,
            'A course with no submission activity must be refused before the Open video page'
        );
        foreach ($launches as $call => $position) {
            $this->assertNotFalse($position, "view_submission.php no longer calls {$call}()");
            $this->assertLessThan(
                $position,
                $refused,
                "A course with no submission activity must be refused before {$call}()"
            );
        }
    }

    /**
     * Rejected selections are reported through an event the activity page listens for, with readable messages.
     *
     * The page used to call parent.document.CALLBACKS.handleError(), which nothing defined.
     *
     * @coversNothing
     */
    public function test_contentitem_return_reports_errors_to_the_page(): void {
        global $CFG;

        $src = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/contentitem_return.php');
        $js = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/amd/src/submissionpanel.js');

        $this->assertStringNotContainsString('CALLBACKS', $src);
        $this->assertStringContainsString('"sessionSelectionFailed"', $src);
        $this->assertStringContainsString('addEventListener("sessionSelectionFailed"', $js);

        // Every error code is shown to the user, so each must be a language string.
        preg_match_all("/\\\$errors\\[\\] = '([a-z_]+)';/", $src, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach (array_unique($matches[1]) as $error) {
            $this->assertTrue(
                get_string_manager()->string_exists($error, 'panoptosubmission'),
                "Missing language string for error '{$error}'"
            );
        }
    }

    /**
     * An LTI 1.1 content item response is only trusted once its OAuth signature is verified.
     *
     * @coversNothing
     */
    public function test_contentitem_return_verifies_lti11_signature(): void {
        global $CFG;

        $src = file_get_contents($CFG->dirroot . '/mod/panoptosubmission/contentitem_return.php');

        $verify = strpos($src, 'lti_verify_oauth_signature(');
        $items = strpos($src, "optional_param('content_items'");

        $this->assertNotFalse($verify, 'contentitem_return.php no longer verifies the OAuth signature');
        $this->assertNotFalse($items, 'contentitem_return.php no longer reads the LTI 1.1 content items');
        $this->assertLessThan($items, $verify, 'The signature must be verified before the content items are read');
    }
}
