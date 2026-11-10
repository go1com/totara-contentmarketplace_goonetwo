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

use contentmarketplace_goonetwo\helper;
use contentmarketplace_goonetwo\migration;
use contentmarketplace_goonetwo\task\migrate_task;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use totara_contentmarketplace\plugininfo\contentmarketplace;

#[Group('totara_contentmarketplace')]
#[CoversClass(migration::class)]
#[CoversClass(migrate_task::class)]
class contentmarketplace_goonetwo_migration_test extends testcase {

    protected function setUp(): void {
        parent::setUp();
        self::setAdminUser();
    }

    public function test_forward_migration(): void {
        global $DB;

        // Core activity, its learning object is not in goonetwo yet.
        $cm_a = $this->create_go1_module('goone', 111);
        // Core activity, its learning object is already in goonetwo.
        $cm_b = $this->create_go1_module('goone', 222);
        $DB->insert_record('marketplace_goonetwo_learning_object', (object) ['external_id' => 222]);
        // Core package without a course module source row.
        $cm_c = $this->create_go1_module('goone', 333, false);
        // Go1 v2 activity.
        $cm_d = $this->create_go1_module('goonetwo', 444);

        $this->assertSame(
            ['learning_objects' => 1, 'course_module_sources' => 2, 'courses' => 2, 'files' => 3, 'orphan_files' => 1],
            migration::get_unmigrated_counts('goone', 'goonetwo')
        );
        $this->assertTrue(migration::has_unmigrated_core_data());
        $goonetwo_source_d = $DB->get_record('totara_contentmarketplace_course_module_source', ['cm_id' => $cm_d]);

        migration::migrate('goone', 'goonetwo');

        $this->assertSame(
            ['learning_objects' => 0, 'course_module_sources' => 0, 'courses' => 0, 'files' => 0, 'orphan_files' => 0],
            migration::get_unmigrated_counts('goone', 'goonetwo')
        );
        $this->assertFalse(migration::has_unmigrated_core_data());

        $this->assertEqualsCanonicalizing(
            [111, 222, 333, 444],
            array_map('intval', $DB->get_fieldset_select('marketplace_goonetwo_learning_object', 'external_id', '1=1'))
        );
        foreach ([$cm_a => 111, $cm_b => 222, $cm_c => 333] as $cm_id => $external_id) {
            $this->assert_module_belongs_to($cm_id, 'goonetwo', $external_id);
        }
        $this->assertEquals(
            $goonetwo_source_d,
            $DB->get_record('totara_contentmarketplace_course_module_source', ['cm_id' => $cm_d])
        );

        // The core learning object rows and the draft files stay.
        $this->assertSame(2, $DB->count_records('marketplace_goone_learning_object'));
        $this->assertSame(4, $DB->count_records_select('files', "filearea = 'draft' AND " . $DB->sql_like('source', ':source'), [
            'source' => 'content-marketplace://goone%',
        ]));
    }

    public function test_reverse_migration(): void {
        $cm = $this->create_go1_module('goonetwo', 444);
        $this->assertFalse(migration::has_unmigrated_core_data());

        migration::migrate('goonetwo', 'goone');

        $this->assert_module_belongs_to($cm, 'goone', 444);
        $this->assertSame(
            ['learning_objects' => 0, 'course_module_sources' => 1, 'courses' => 1, 'files' => 1, 'orphan_files' => 0],
            migration::get_unmigrated_counts('goone', 'goonetwo')
        );
        // The task sets this status after a migration back to the core plugin.
        set_config(migration::CONFIG_STATUS, migration::STATUS_REVERTED, 'contentmarketplace_goonetwo');
        $this->assertTrue(migration::has_unmigrated_core_data());
    }

    public function test_completed_status_skips_the_table_check(): void {
        // No core data: the check sets the completed status.
        $this->assertFalse(migration::has_unmigrated_core_data());
        $this->assertSame(migration::STATUS_COMPLETED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));

        // With the completed status and a disabled core plugin, the tables are not examined.
        $this->create_go1_module('goone', 111);
        $this->assertFalse(migration::has_unmigrated_core_data());

        // With an enabled core plugin, the tables are examined.
        contentmarketplace::plugin('goone')->enable();
        $this->assertTrue(migration::has_unmigrated_core_data());
        $this->assertSame(migration::STATUS_COMPLETED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
    }

    public function test_check_keeps_pending_and_reverted_status(): void {
        foreach ([migration::STATUS_PENDING, migration::STATUS_REVERTED] as $status) {
            set_config(migration::CONFIG_STATUS, $status, 'contentmarketplace_goonetwo');
            $this->assertFalse(migration::has_unmigrated_core_data());
            $this->assertSame($status, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
        }
    }

    public function test_second_migration_changes_nothing(): void {
        global $DB;

        $this->create_go1_module('goone', 111);
        $this->create_go1_module('goone', 222, false);

        migration::migrate('goone', 'goonetwo');
        $tables = ['marketplace_goone_learning_object', 'marketplace_goonetwo_learning_object', 'totara_contentmarketplace_course_module_source'];
        $before = array_map(fn($table) => $DB->get_records($table), $tables);

        migration::migrate('goone', 'goonetwo');

        $this->assertEquals($before, array_map(fn($table) => $DB->get_records($table), $tables));
    }

    public function test_invalid_direction(): void {
        $this->expectException(coding_exception::class);
        migration::migrate('goone', 'goone');
    }

    public function test_get_actions(): void {
        // All data is migrated and the core plugin is disabled.
        $actions = migration::get_actions();
        $this->assertSame(['from' => 'goonetwo', 'to' => 'goone', 'migrate' => true, 'disable' => 'goonetwo'], $actions);
        $this->assertSame('migration_button_reverse', migration::get_button_string_key($actions));

        // The core plugin is enabled.
        contentmarketplace::plugin('goone')->enable();
        $actions = migration::get_actions();
        $this->assertSame(['from' => 'goone', 'to' => 'goonetwo', 'migrate' => false, 'disable' => 'goone'], $actions);
        $this->assertSame('migration_button_disable', migration::get_button_string_key($actions));

        // Unmigrated data and the core plugin is enabled.
        $this->create_go1_module('goone', 111);
        $actions = migration::get_actions();
        $this->assertSame(['from' => 'goone', 'to' => 'goonetwo', 'migrate' => true, 'disable' => 'goone'], $actions);
        $this->assertSame('migration_button_migrate_disable', migration::get_button_string_key($actions));

        // Unmigrated data and the core plugin is disabled. The first check above set the completed status,
        // which would skip the table check, so it is removed.
        contentmarketplace::plugin('goone')->disable();
        unset_config(migration::CONFIG_STATUS, 'contentmarketplace_goonetwo');
        $actions = migration::get_actions();
        $this->assertSame(['from' => 'goone', 'to' => 'goonetwo', 'migrate' => true, 'disable' => null], $actions);
        $this->assertSame('migration_button_migrate', migration::get_button_string_key($actions));
    }

    public function test_task_migrates_and_disables_core_plugin(): void {
        $cm = $this->create_go1_module('goone', 111);
        contentmarketplace::plugin('goone')->enable();

        $this->execute_task(migration::get_actions());

        $this->assert_module_belongs_to($cm, 'goonetwo', 111);
        $this->assertFalse(migration::is_core_goone_enabled());
        $this->assertSame(migration::STATUS_COMPLETED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
        $this->assertSame('migration_completed', get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS_LANG));
    }

    public function test_task_reverse_migration_disables_goonetwo(): void {
        $cm = $this->create_go1_module('goonetwo', 444);
        contentmarketplace::plugin('goonetwo')->enable();

        $this->execute_task(migration::get_actions());

        $this->assert_module_belongs_to($cm, 'goone', 444);
        $this->assertFalse(contentmarketplace::plugin('goonetwo')->is_enabled());
        $this->assertFalse(migration::is_core_goone_enabled());
        $this->assertSame(migration::STATUS_REVERTED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
        $this->assertSame('migration_completed', get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS_LANG));
    }

    public function test_task_failure(): void {
        $cm = $this->create_go1_module('goone', 111);
        contentmarketplace::plugin('goone')->enable();

        $this->expectOutputRegex('/Go1 migration failed: .*Invalid Go1 migration direction/');
        $this->execute_task(['from' => 'goone', 'to' => 'goone', 'migrate' => true, 'disable' => 'goone']);

        $this->assert_module_belongs_to($cm, 'goone', 111);
        $this->assertTrue(migration::is_core_goone_enabled());
        $this->assertSame(migration::STATUS_FAILED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
        $this->assertSame('migration_failed', get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS_LANG));
    }

    public function test_task_failure_in_transaction(): void {
        global $DB;

        $cm_a = $this->create_go1_module('goone', 111);
        $cm_b = $this->create_go1_module('goone', 222);
        contentmarketplace::plugin('goone')->enable();
        // A course module source row that points to a learning object row that does not exist.
        $DB->delete_records('marketplace_goone_learning_object', ['external_id' => 222]);

        $this->expectOutputRegex('/Go1 migration failed: /');
        $this->execute_task(migration::get_actions());

        $this->assertFalse($DB->is_transaction_started());
        $this->assertSame(migration::STATUS_FAILED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
        $this->assertTrue(migration::is_core_goone_enabled());
        $this->assert_module_belongs_to($cm_a, 'goone', 111);
        $this->assertSame(
            'contentmarketplace_goone',
            $DB->get_field('totara_contentmarketplace_course_module_source', 'marketplace_component', ['cm_id' => $cm_b])
        );
        $this->assertSame(0, $DB->count_records('marketplace_goonetwo_learning_object'));
    }

    public function test_pop_status_message(): void {
        $this->assertNull(migration::pop_status_message());

        set_config(migration::CONFIG_STATUS, migration::STATUS_PENDING, 'contentmarketplace_goonetwo');
        set_config(migration::CONFIG_STATUS_LANG, 'migration_pending_message', 'contentmarketplace_goonetwo');
        $this->assertSame(
            [get_string('migration_pending_message', 'contentmarketplace_goonetwo'), \core\output\notification::NOTIFY_SUCCESS],
            migration::pop_status_message()
        );
        // The pending status stays.
        $this->assertNotNull(migration::pop_status_message());

        set_config(migration::CONFIG_STATUS, migration::STATUS_COMPLETED, 'contentmarketplace_goonetwo');
        set_config(migration::CONFIG_STATUS_LANG, 'migration_completed', 'contentmarketplace_goonetwo');
        $this->assertSame(
            [get_string('migration_completed', 'contentmarketplace_goonetwo'), \core\output\notification::NOTIFY_SUCCESS],
            migration::pop_status_message()
        );
        $this->assertNull(migration::pop_status_message());
        // The message is removed, the completed status stays.
        $this->assertFalse(get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS_LANG));
        $this->assertSame(migration::STATUS_COMPLETED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));

        set_config(migration::CONFIG_STATUS, migration::STATUS_REVERTED, 'contentmarketplace_goonetwo');
        set_config(migration::CONFIG_STATUS_LANG, 'migration_completed', 'contentmarketplace_goonetwo');
        $this->assertSame(
            [get_string('migration_completed', 'contentmarketplace_goonetwo'), \core\output\notification::NOTIFY_SUCCESS],
            migration::pop_status_message()
        );
        $this->assertNull(migration::pop_status_message());
        $this->assertSame(migration::STATUS_REVERTED, get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));

        set_config(migration::CONFIG_STATUS, migration::STATUS_FAILED, 'contentmarketplace_goonetwo');
        set_config(migration::CONFIG_STATUS_LANG, 'migration_failed', 'contentmarketplace_goonetwo');
        $this->assertSame(
            [get_string('migration_failed', 'contentmarketplace_goonetwo'), \core\output\notification::NOTIFY_ERROR],
            migration::pop_status_message()
        );
        $this->assertNull(migration::pop_status_message());
        // The failed status is removed with its message.
        $this->assertFalse(get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS));
    }

    public function test_general_tab_warning(): void {
        $this->assertNull(helper::get_core_goone_migration_warning());

        contentmarketplace::plugin('goone')->enable();
        $this->assert_warning('warning:coregooneenabled');

        $this->create_go1_module('goone', 111);
        $this->assert_warning('warning:coregoonemigrateenabled');

        // The first check above set the completed status, which would skip the table check, so it is removed.
        contentmarketplace::plugin('goone')->disable();
        unset_config(migration::CONFIG_STATUS, 'contentmarketplace_goonetwo');
        $this->assert_warning('warning:coregoonemigrate');
    }

    /**
     * Creates a course with a Go1 SCORM activity.
     *
     * @param string $plugin goone or goonetwo
     * @param int $external_id Go1 learning object ID
     * @param bool $linked Make the learning object and course module source rows
     *
     * @return int Course module ID
     */
    private function create_go1_module(string $plugin, int $external_id, bool $linked = true): int {
        global $CFG, $DB, $USER;

        $course = self::getDataGenerator()->create_course();

        $file_itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'filepath' => '/',
            'filename' => "go1_{$external_id}.zip",
            'itemid' => $file_itemid,
            'source' => "content-marketplace://{$plugin}/{$external_id}",
        ], $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip');

        $module = self::getDataGenerator()->create_module('scorm', [
            'course' => $course,
            'packagefile' => $file_itemid,
        ]);

        if ($linked) {
            $learning_object_id = $DB->insert_record("marketplace_{$plugin}_learning_object", (object) ['external_id' => $external_id]);
            $DB->insert_record('totara_contentmarketplace_course_module_source', (object) [
                'cm_id' => $module->cmid,
                'learning_object_id' => $learning_object_id,
                'marketplace_component' => "contentmarketplace_{$plugin}",
            ]);
        }

        return (int) $module->cmid;
    }

    /**
     * @param int $cm_id
     * @param string $plugin goone or goonetwo
     * @param int $external_id
     */
    private function assert_module_belongs_to(int $cm_id, string $plugin, int $external_id): void {
        global $DB;

        $source = $DB->get_record('totara_contentmarketplace_course_module_source', ['cm_id' => $cm_id], '*', MUST_EXIST);
        $this->assertSame("contentmarketplace_{$plugin}", $source->marketplace_component);
        $this->assertEquals(
            $external_id,
            $DB->get_field("marketplace_{$plugin}_learning_object", 'external_id', ['id' => $source->learning_object_id])
        );

        $package = $DB->get_record('files', [
            'contextid' => context_module::instance($cm_id)->id,
            'component' => 'mod_scorm',
            'filearea' => 'package',
            'filepath' => '/',
            'filename' => "go1_{$external_id}.zip",
        ], '*', MUST_EXIST);
        $this->assertSame("content-marketplace://{$plugin}/{$external_id}", $package->source);
    }

    /**
     * @param array $actions
     */
    private function execute_task(array $actions): void {
        $task = new migrate_task();
        $task->set_custom_data($actions);
        $task->execute();
    }

    /**
     * @param string $warning Lang string key of the warning
     */
    private function assert_warning(string $warning): void {
        $html = helper::get_core_goone_migration_warning();
        $link = html_writer::link(
            new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', ['tab' => 'migration']),
            'Go to Migration tab'
        );
        $this->assertStringContainsString(get_string($warning, 'contentmarketplace_goonetwo', $link), $html);
    }

}
