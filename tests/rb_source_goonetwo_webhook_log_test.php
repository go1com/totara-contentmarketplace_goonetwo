<?php
/**
 * This file is part of Totara Learn
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    contentmarketplace_goonetwo
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use contentmarketplace_goonetwo\rb\display\webhook_cm_ids;
use contentmarketplace_goonetwo\rb\display\webhook_result;
use contentmarketplace_goonetwo\webhook;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once(__DIR__ . '/../rb_sources/rb_goonetwo_webhook_log_embedded.php');

/**
 * Test the Go1 webhook log report source, its display classes and the embedded report.
 */
#[CoversClass(rb_source_goonetwo_webhook_log::class)]
#[CoversClass(rb_goonetwo_webhook_log_embedded::class)]
#[CoversClass(webhook_cm_ids::class)]
#[CoversClass(webhook_result::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_rb_source_goonetwo_webhook_log_test extends testcase {

    /**
     * Course name longer than 40 characters, which the report does not show.
     */
    const LONG_COURSE_NAME = 'Workplace health and safety for supervisors and team leaders';

    /**
     * Activity name longer than 40 characters.
     */
    const LONG_ACTIVITY_NAME = 'Introduction to hazard identification and risk control';

    protected function setUp(): void {
        parent::setUp();
        self::setAdminUser();
    }

    /**
     * Create a course with a scorm activity, both with names longer than 40 characters.
     *
     * @return array [course record, course module id]
     */
    private function create_course_with_scorm(): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course(['fullname' => self::LONG_COURSE_NAME]);
        $module = $generator->create_module('scorm', ['course' => $course->id, 'name' => self::LONG_ACTIVITY_NAME]);
        return [$course, (int) $module->cmid];
    }

    /**
     * Get the rows of the embedded report, processed for the format, newest entry first.
     * Each row is keyed by the column value, for example 'result' or 'cm_ids'.
     *
     * @param string $format
     * @return array
     */
    private function get_report_rows(string $format): array {
        global $DB;

        $report = reportbuilder::create_embedded('goonetwo_webhook_log', (new rb_config())->set_nocache(true));
        [$sql, $params] = $report->build_query();
        $records = $DB->get_records_sql($sql . ' ORDER BY base.id DESC', $params);

        $keys = array_map(fn(rb_column $column) => $column->value, array_values($report->columns));
        $rows = [];
        foreach ($records as $record) {
            $rows[] = array_combine($keys, $report->src->process_data_row($record, $format, $report));
        }
        return $rows;
    }

    public function test_report_shows_success_and_error_entries(): void {
        [$course, $cmid] = $this->create_course_with_scorm();
        $user = self::getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Rner']);

        webhook::add_to_log([
            'enrollment_id' => 9001,
            'go1_userid' => 501,
            'go1_username' => 'extuser501',
            'go1_email' => 'learner501@example.com',
            'user_id' => $user->id,
            'cm_ids' => [['courseid' => (int) $course->id, 'cmid' => $cmid]],
        ], '{"success":1}', 'Webhook event processed successfully');
        webhook::add_to_log([], '{"error":1}', 'User not found: 501');

        [$error, $success] = $this->get_report_rows('html');

        self::assertSame('success', $success['result']);
        self::assertSame('Webhook event processed successfully', $success['message']);
        self::assertEquals(9001, $success['enrollment_id']);
        self::assertEquals(501, $success['go1_userid']);
        self::assertSame('extuser501', $success['go1_username']);
        self::assertSame('learner501@example.com', $success['go1_email']);
        self::assertStringContainsString('/user/profile.php?id=' . $user->id, $success['user']);
        self::assertStringContainsString('Lea Rner', $success['user']);
        self::assertStringContainsString('/mod/scorm/view.php?id=' . $cmid, $success['cm_ids']);
        self::assertStringNotContainsString('/course/view.php', $success['cm_ids']);
        self::assertStringContainsString('{&quot;success&quot;:1}', $success['request']);

        self::assertSame('error', $error['result']);
        self::assertSame('User not found: 501', $error['message']);
        self::assertSame('', $error['cm_ids']);
        self::assertSame('{&quot;error&quot;:1}', $error['request']);
    }

    public function test_cm_ids_html_shortens_names_at_word_boundary(): void {
        [$course, $cmid] = $this->create_course_with_scorm();

        webhook::add_to_log(['cm_ids' => [['courseid' => (int) $course->id, 'cmid' => $cmid]]], '{}', 'ok');

        [$row] = $this->get_report_rows('html');

        // Only the activity is a link, and its name is shortened to 40 characters, including '...', at a word boundary.
        preg_match_all('~<a [^>]*>([^<]*)</a>~', $row['cm_ids'], $matches);
        self::assertSame(['Introduction to hazard identification...'], $matches[1]);
        self::assertStringNotContainsString('Workplace health', $row['cm_ids']);
    }

    public function test_cm_ids_lists_one_line_for_each_activity(): void {
        [$course, $cmid] = $this->create_course_with_scorm();

        webhook::add_to_log([
            'cm_ids' => [
                ['courseid' => (int) $course->id, 'cmid' => $cmid],
                ['courseid' => 12345, 'cmid' => 67890],
            ],
        ], '{}', 'ok');

        [$html] = $this->get_report_rows('html');
        [$csv] = $this->get_report_rows('csv');

        // An activity that does not exist shows the course module ID.
        $html_lines = explode('<br />', $html['cm_ids']);
        self::assertCount(2, $html_lines);
        self::assertSame('67890', $html_lines[1]);

        // The export has the full activity names as plain text.
        self::assertSame(self::LONG_ACTIVITY_NAME . "\n67890", $csv['cm_ids']);
    }

    public function test_embedded_report_needs_config_capability(): void {
        $user = self::getDataGenerator()->create_user();
        $report = reportbuilder::create_embedded('goonetwo_webhook_log', (new rb_config())->set_nocache(true));
        $embedded = new rb_goonetwo_webhook_log_embedded();

        self::assertTrue($embedded->is_capable(get_admin()->id, $report));
        self::assertFalse($embedded->is_capable($user->id, $report));

        $role_id = self::getDataGenerator()->create_role();
        assign_capability('totara/contentmarketplace:config', CAP_ALLOW, $role_id, context_system::instance());
        role_assign($role_id, $user->id, context_system::instance());

        self::assertTrue($embedded->is_capable($user->id, $report));
    }
}
