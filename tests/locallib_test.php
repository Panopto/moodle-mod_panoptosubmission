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
 * Unit tests for the host-constrained submission helpers.
 *
 * @package    mod_panoptosubmission
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_panoptosubmission;

/**
 * Unit tests for the host-constrained submission helpers.
 *
 * Skipped when block_panopto is not installed.
 *
 * @package    mod_panoptosubmission
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locallib_test extends \advanced_testcase {
    /**
     * Load locallib, or skip when block_panopto is not installed.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();

        $blocklib = $CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php';
        if (!file_exists($blocklib)) {
            $this->markTestSkipped('block_panopto is not installed');
        }

        require_once($CFG->dirroot . '/mod/panoptosubmission/locallib.php');
    }

    /**
     * Empty source URLs are allowed so a launch can target the tool's own endpoint.
     *
     * @covers ::panoptosubmission_is_allowed_source_url
     */
    public function test_allowed_source_url_accepts_empty(): void {
        $this->assertTrue(panoptosubmission_is_allowed_source_url(''));
    }

    /**
     * A source URL is accepted only when its host belongs to a configured Panopto server.
     *
     * @covers ::panoptosubmission_is_allowed_source_url
     */
    public function test_allowed_source_url_uses_configured_hosts(): void {
        $this->resetAfterTest();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $this->assertTrue(
            panoptosubmission_is_allowed_source_url('https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1')
        );
        $this->assertFalse(
            panoptosubmission_is_allowed_source_url('https://demo.panopto.com.attacker.example/Panopto/')
        );
        $this->assertFalse(
            panoptosubmission_is_allowed_source_url('https://evil.example/video')
        );
    }

    /**
     * Thumbnail URLs from an unknown host are dropped rather than stored or rendered.
     *
     * @covers ::panoptosubmission_sanitise_thumbnail_url
     */
    public function test_sanitise_thumbnail_url_drops_unknown_hosts(): void {
        $this->resetAfterTest();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $allowed = 'https://demo.panopto.com/Panopto/Pages/Viewer/Thumb.aspx?id=1';
        $this->assertSame($allowed, panoptosubmission_sanitise_thumbnail_url($allowed));
        $this->assertSame('', panoptosubmission_sanitise_thumbnail_url('https://evil.example/x.png'));
        $this->assertSame('', panoptosubmission_sanitise_thumbnail_url(''));
    }

    /**
     * An unknown thumbnail host must not appear in the grading-page markup.
     *
     * @covers \mod_panoptosubmission_renderer::get_view_video_container
     */
    public function test_renderer_omits_untrusted_thumbnail(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_url('/mod/panoptosubmission/view.php');

        $submission = (object) [
            'id' => 1,
            'source' => 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1',
            'thumbnailsource' => 'https://evil.example/x.png',
            'thumbnailwidth' => 128,
            'thumbnailheight' => 72,
            'customdata' => '',
            'title' => 'Session one',
            'timemodified' => time(),
        ];

        $renderer = $PAGE->get_renderer('mod_panoptosubmission');
        $html = $renderer->get_view_video_container($submission, $course->id, 1);

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * A thumbnail on a configured Panopto host is rendered.
     *
     * @covers \mod_panoptosubmission_renderer::get_view_video_container
     */
    public function test_renderer_includes_trusted_thumbnail(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_url('/mod/panoptosubmission/view.php');

        $thumb = 'https://demo.panopto.com/Panopto/Pages/Viewer/Thumb.aspx?id=1';
        $submission = (object) [
            'id' => 1,
            'source' => 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1',
            'thumbnailsource' => $thumb,
            'thumbnailwidth' => 128,
            'thumbnailheight' => 72,
            'customdata' => '',
            'title' => 'Session one',
            'timemodified' => time(),
        ];

        $renderer = $PAGE->get_renderer('mod_panoptosubmission');
        $html = $renderer->get_view_video_container($submission, $course->id, 1);

        $this->assertStringContainsString('demo.panopto.com', $html);
        $this->assertStringContainsString('<img', $html);
    }

    /**
     * The session title is escaped and the preview toggle no longer relies on an inline script.
     *
     * @covers \mod_panoptosubmission_renderer::get_view_video_container
     */
    public function test_renderer_escapes_title_without_inline_script(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_url('/mod/panoptosubmission/view.php');

        $submission = (object) [
            'id' => 1,
            'source' => 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1',
            'thumbnailsource' => '',
            'thumbnailwidth' => 128,
            'thumbnailheight' => 72,
            'customdata' => '',
            'title' => 'Tom & Jerry <3',
            'timemodified' => time(),
        ];

        $renderer = $PAGE->get_renderer('mod_panoptosubmission');
        $html = $renderer->get_view_video_container($submission, $course->id, 1);

        $this->assertStringContainsString('Tom &amp; Jerry &lt;3', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        // Viewing never provisions, so the player URL carries no session key.
        $this->assertStringNotContainsString('sesskey', $html);
    }

    /**
     * The player and its links load the viewer through a POST, so no viewer URL launches Panopto on its own.
     *
     * @covers \mod_panoptosubmission_renderer::get_view_video_container
     */
    public function test_renderer_player_launches_through_post(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->clear_server_configuration();
        set_config('server_name1', 'demo.panopto.com', 'block_panopto');

        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_pagelayout('incourse');
        $PAGE->set_url('/mod/panoptosubmission/view.php');

        $submission = (object) [
            'id' => 1,
            'source' => 'https://demo.panopto.com/Panopto/Pages/Viewer.aspx?id=1',
            'thumbnailsource' => 'https://demo.panopto.com/Panopto/Pages/Viewer/Thumb.aspx?id=1',
            'thumbnailwidth' => 128,
            'thumbnailheight' => 72,
            'customdata' => '',
            'title' => 'Session one',
            'timemodified' => time(),
        ];

        $renderer = $PAGE->get_renderer('mod_panoptosubmission');
        $html = $renderer->get_view_video_container($submission, $course->id, 1);

        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);

        $iframe = $dom->getElementById('contentframe');
        $this->assertFalse($iframe->hasAttribute('src'), 'The player must not load the viewer by URL');
        $this->assertSame('contentframe', $iframe->getAttribute('name'));
        $this->assertStringContainsString('/mod/panoptosubmission/view_submission.php', $iframe->getAttribute('data-launch-url'));

        foreach (['panoptosessiontitle', 'panoptothumbnaillink'] as $linkid) {
            $this->assertSame('1', $dom->getElementById($linkid)->getAttribute('data-post-launch'), "{$linkid} must post");
        }

        // The session key travels in the posted form, never in a URL.
        $this->assertStringNotContainsString('sesskey', $html);
    }

    /**
     * Quick grading only keeps point grades inside the activity's range.
     *
     * @covers ::panoptosubmission_clean_quickgrade
     */
    public function test_clean_quickgrade_points(): void {
        $this->assertSame(-1.0, panoptosubmission_clean_quickgrade('', 10));
        $this->assertSame(-1.0, panoptosubmission_clean_quickgrade('-1', 10));
        $this->assertSame(7.5, panoptosubmission_clean_quickgrade('7.5', 10));
        $this->assertSame(0.0, panoptosubmission_clean_quickgrade('0', 10));
        $this->assertNull(panoptosubmission_clean_quickgrade('11', 10));
        $this->assertNull(panoptosubmission_clean_quickgrade('-3', 10));
        $this->assertNull(panoptosubmission_clean_quickgrade('<script>alert(1)</script>', 10));
    }

    /**
     * Quick grading only keeps whole scale items.
     *
     * @covers ::panoptosubmission_clean_quickgrade
     */
    public function test_clean_quickgrade_scale(): void {
        $this->resetAfterTest();

        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Bad,Good,Great']);

        $this->assertSame(2.0, panoptosubmission_clean_quickgrade('2', -$scale->id));
        $this->assertSame(-1.0, panoptosubmission_clean_quickgrade('-1', -$scale->id));
        $this->assertNull(panoptosubmission_clean_quickgrade('4', -$scale->id));
        $this->assertNull(panoptosubmission_clean_quickgrade('1.5', -$scale->id));
    }

    /**
     * Unedited feedback posted back from the quick grading textarea compares equal to what was shown.
     *
     * @covers ::panoptosubmission_comment_as_plain_text
     * @covers ::panoptosubmission_normalise_quickgrade_comment
     */
    public function test_quickgrade_comment_round_trip(): void {
        $shown = panoptosubmission_comment_as_plain_text('<p>Tom &amp; Jerry</p>', FORMAT_HTML);
        $this->assertSame('Tom & Jerry', $shown);

        // Browsers post textarea line breaks as CRLF.
        $this->assertSame(
            panoptosubmission_normalise_quickgrade_comment($shown),
            panoptosubmission_normalise_quickgrade_comment("Tom & Jerry\r\n")
        );
    }

    /**
     * Converting feedback to plain text keeps its line breaks in every format.
     *
     * @covers ::panoptosubmission_comment_as_plain_text
     */
    public function test_comment_as_plain_text_keeps_line_breaks(): void {
        $html = panoptosubmission_comment_as_plain_text('<p>Good intro.</p><p>Fix the ending.</p>', FORMAT_HTML);
        $this->assertMatchesRegularExpression('/Good intro\.\s*\n\s*Fix the ending\./', $html);

        $moodle = panoptosubmission_comment_as_plain_text("First line\nSecond line", FORMAT_MOODLE);
        $this->assertMatchesRegularExpression('/First line\s*\n\s*Second line/', $moodle);

        $this->assertSame('a < b & c', panoptosubmission_comment_as_plain_text('a < b & c', FORMAT_PLAIN));

        // Bold and deleted text keep their words, so an edit never saves altered feedback.
        $feedback = '<p>Good <strong>structure</strong>, <del>fix</del> citations.</p>';
        $this->assertSame('Good structure, fix citations.', panoptosubmission_comment_as_plain_text($feedback, FORMAT_HTML));
    }

    /**
     * Quick grading only saves users the grader may grade, and pushes the grade to the gradebook.
     *
     * @covers ::panoptosubmission_save_quickgrades
     */
    public function test_save_quickgrades_limits_to_gradable_users(): void {
        global $DB;

        $this->resetAfterTest();

        [$cm, $context, $users] = $this->create_grouped_activity(SEPARATEGROUPS);
        $course = get_course($cm->course);
        $activity = $DB->get_record('panoptosubmission', ['id' => $cm->instance]);

        $this->setUser($users['teacher']);
        $inside = $users['inside']->id;
        $outside = $users['outside']->id;

        $invalid = panoptosubmission_save_quickgrades(
            $cm,
            $course,
            $activity,
            $context,
            panoptosubmission_get_gradable_userids($cm, $context),
            [$inside => $inside, $outside => $outside],
            [$inside => '55', $outside => '60'],
            []
        );

        $this->assertSame(0, $invalid);
        $this->assertEquals(55, $DB->get_field('panoptosubmission_submission', 'grade', ['userid' => $inside]));
        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['userid' => $outside]));

        $gradinginfo = grade_get_grades($course->id, 'mod', 'panoptosubmission', $activity->id, $inside);
        $this->assertEquals(55, $gradinginfo->items[0]->grades[$inside]->grade);
    }

    /**
     * A comment alone neither marks the submission graded nor changes who graded it.
     *
     * @covers ::panoptosubmission_save_quickgrades
     */
    public function test_save_quickgrades_comment_keeps_grader(): void {
        global $DB;

        $this->resetAfterTest();

        [$cm, $context, $users, $activity, $course] = $this->create_activity_with_student();
        $grader = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $commenter = $this->getDataGenerator()->create_and_enrol($course, 'teacher');

        $submission = $this->getDataGenerator()->get_plugin_generator('mod_panoptosubmission')->create_submission(
            $activity,
            $users['student']->id,
            ['grade' => 70, 'teacher' => $grader->id, 'timemarked' => 1000]
        );

        $this->setUser($commenter);
        $studentid = $users['student']->id;
        panoptosubmission_save_quickgrades(
            $cm,
            $course,
            $activity,
            $context,
            [$studentid],
            [$studentid => $studentid],
            [$studentid => '70'],
            [$studentid => 'Nice work']
        );

        $saved = $DB->get_record('panoptosubmission_submission', ['id' => $submission->id]);
        $this->assertSame('Nice work', $saved->submissioncomment);
        $this->assertEquals(FORMAT_MOODLE, $saved->format);
        $this->assertEquals($grader->id, $saved->teacher);
        $this->assertEquals(1000, $saved->timemarked);
    }

    /**
     * Only edited invalid grades are reported; an unedited grade outside the current range is kept.
     *
     * @covers ::panoptosubmission_save_quickgrades
     */
    public function test_save_quickgrades_reports_only_edited_invalid_grades(): void {
        global $DB;

        $this->resetAfterTest();

        [$cm, $context, $users, $activity, $course] = $this->create_activity_with_student();
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $studentid = $users['student']->id;

        // The maximum was lowered after this student was graded 80.
        $this->getDataGenerator()->get_plugin_generator('mod_panoptosubmission')->create_submission(
            $activity,
            $studentid,
            ['grade' => 80, 'timemarked' => 1000]
        );
        $activity->grade = 50;

        $this->setAdminUser();
        $invalid = panoptosubmission_save_quickgrades(
            $cm,
            $course,
            $activity,
            $context,
            [$studentid, (int) $other->id],
            [$studentid => $studentid, $other->id => $other->id],
            [$studentid => '80', $other->id => '120'],
            []
        );

        $this->assertSame(1, $invalid);
        $this->assertEquals(80, $DB->get_field('panoptosubmission_submission', 'grade', ['userid' => $studentid]));
        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['userid' => $other->id]));
    }

    /**
     * Rows whose gradebook grade is locked are never changed.
     *
     * @covers ::panoptosubmission_save_quickgrades
     */
    public function test_save_quickgrades_skips_locked_grades(): void {
        global $DB;

        $this->resetAfterTest();

        [$cm, $context, $users, $activity, $course] = $this->create_activity_with_student();
        $studentid = $users['student']->id;

        $gradeitem = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'panoptosubmission',
            'iteminstance' => $activity->id,
        ]);
        $gradeitem->set_locked(true);

        $this->setAdminUser();
        panoptosubmission_save_quickgrades(
            $cm,
            $course,
            $activity,
            $context,
            [$studentid],
            [$studentid => $studentid],
            [$studentid => '40'],
            [$studentid => 'Changed']
        );

        $this->assertFalse($DB->record_exists('panoptosubmission_submission', ['userid' => $studentid]));
    }

    /**
     * Create an activity with one enrolled student.
     *
     * @return array [course module, module context, users keyed student, activity record, course]
     */
    protected function create_activity_with_student(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $module = $generator->create_module('panoptosubmission', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('panoptosubmission', $module->id);
        $activity = $DB->get_record('panoptosubmission', ['id' => $module->id]);

        return [
            $cm,
            \context_module::instance($cm->id),
            ['student' => $generator->create_and_enrol($course, 'student')],
            $activity,
            $course,
        ];
    }

    /**
     * Under separate groups a grader without access to all groups may only grade their own groups.
     *
     * @covers ::panoptosubmission_get_gradable_userids
     */
    public function test_gradable_userids_respect_separate_groups(): void {
        $this->resetAfterTest();

        [$cm, $context, $users] = $this->create_grouped_activity(SEPARATEGROUPS);

        // A non-editing teacher does not have moodle/site:accessallgroups.
        $this->setUser($users['teacher']);
        $this->assertSame(
            [(int) $users['inside']->id],
            array_map('intval', panoptosubmission_get_gradable_userids($cm, $context))
        );

        // Graders who can access all groups may grade every student.
        $this->setAdminUser();
        $gradable = array_map('intval', panoptosubmission_get_gradable_userids($cm, $context));
        sort($gradable);
        $expected = [(int) $users['inside']->id, (int) $users['outside']->id];
        sort($expected);
        $this->assertSame($expected, $gradable);
    }

    /**
     * The single-user check agrees with the list the grading table is built from.
     *
     * @covers ::panoptosubmission_can_grade_user
     */
    public function test_can_grade_user_matches_gradable_userids(): void {
        $this->resetAfterTest();

        [$cm, $context, $users] = $this->create_grouped_activity(SEPARATEGROUPS);

        $this->setUser($users['teacher']);
        $this->assertTrue(panoptosubmission_can_grade_user($cm, $context, $users['inside']->id));
        $this->assertFalse(panoptosubmission_can_grade_user($cm, $context, $users['outside']->id));

        // Only enrolled submitters can be graded.
        $this->assertFalse(panoptosubmission_can_grade_user($cm, $context, $users['teacher']->id));
    }

    /**
     * Separate groups does not limit a grader while the course has no groups, matching the grading table.
     *
     * @covers ::panoptosubmission_get_gradable_userids
     * @covers ::panoptosubmission_can_grade_user
     */
    public function test_separate_groups_without_course_groups(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $activity = $generator->create_module('panoptosubmission', ['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);
        $context = \context_module::instance($cm->id);

        $teacher = $generator->create_and_enrol($course, 'teacher');
        $student = $generator->create_and_enrol($course, 'student');

        $this->setUser($teacher);
        $this->assertSame([(int) $student->id], array_map('intval', panoptosubmission_get_gradable_userids($cm, $context)));
        $this->assertTrue(panoptosubmission_can_grade_user($cm, $context, $student->id));
    }

    /**
     * Without separate groups every enrolled student is gradable.
     *
     * @covers ::panoptosubmission_get_gradable_userids
     */
    public function test_gradable_userids_without_separate_groups(): void {
        $this->resetAfterTest();

        [$cm, $context, $users] = $this->create_grouped_activity(VISIBLEGROUPS);

        $this->setUser($users['teacher']);
        $gradable = array_map('intval', panoptosubmission_get_gradable_userids($cm, $context));
        sort($gradable);
        $expected = [(int) $users['inside']->id, (int) $users['outside']->id];
        sort($expected);
        $this->assertSame($expected, $gradable);
    }

    /**
     * Under separate groups students are graded by teachers in their groups, or by teachers in no group.
     *
     * @covers ::panoptosubmission_get_graders
     */
    public function test_get_graders_uses_group_membership(): void {
        $this->resetAfterTest();

        [$cm, $context, $users] = $this->create_grouped_activity(SEPARATEGROUPS);
        $generator = $this->getDataGenerator();
        $course = get_course($cm->course);

        $ungroupedteacher = $generator->create_and_enrol($course, 'teacher');
        $ungroupedstudent = $generator->create_and_enrol($course, 'student');

        $this->assertSame(
            [(int) $users['teacher']->id],
            array_map('intval', array_keys(panoptosubmission_get_graders($cm, $users['inside'], $context)))
        );
        $this->assertSame(
            [(int) $ungroupedteacher->id],
            array_map('intval', array_keys(panoptosubmission_get_graders($cm, $ungroupedstudent, $context)))
        );

        // A grader is never notified about their own action.
        $this->assertArrayNotHasKey(
            $users['teacher']->id,
            panoptosubmission_get_graders($cm, $users['teacher'], $context)
        );
    }

    /**
     * A missing submission yields null instead of dereferencing false.
     *
     * @covers ::panoptosubmission_get_submission_grade_object
     */
    public function test_submission_grade_object_without_submission(): void {
        $this->resetAfterTest();

        $this->assertNull(panoptosubmission_get_submission_grade_object(123, 456));
    }

    /**
     * Create an activity with a teacher and a student in one group and a student in another.
     *
     * @param int $groupmode The activity group mode.
     * @return array [course module, module context, users keyed teacher, inside and outside]
     */
    protected function create_grouped_activity(int $groupmode): array {
        $generator = $this->getDataGenerator();

        $course = $generator->create_course();
        $activity = $generator->create_module('panoptosubmission', ['course' => $course->id, 'groupmode' => $groupmode]);
        $cm = get_coursemodule_from_instance('panoptosubmission', $activity->id);

        $users = [
            'teacher' => $generator->create_and_enrol($course, 'teacher'),
            'inside' => $generator->create_and_enrol($course, 'student'),
            'outside' => $generator->create_and_enrol($course, 'student'),
        ];

        $group = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $users['teacher']->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $users['inside']->id]);
        $generator->create_group_member(['groupid' => $othergroup->id, 'userid' => $users['outside']->id]);

        return [$cm, \context_module::instance($cm->id), $users];
    }

    /**
     * Blank out every setting the allowlist is built from.
     *
     * @return void
     */
    protected function clear_server_configuration(): void {
        set_config('automatic_operation_target_server', '', 'block_panopto');
        set_config('default_panopto_server', '', 'mod_panoptocourseembed');
        set_config('server_number', 0, 'block_panopto');
        set_config('server_name1', '', 'block_panopto');
    }
}
