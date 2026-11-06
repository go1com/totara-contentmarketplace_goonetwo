<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * @author Mark Metcalfe <mark.metcalfe@totaralearning.com>
 * @package contentmarketplace_goonetwo
 */

use container_course\course;
use contentmarketplace_goonetwo\entity\learning_object;
use core\orm\query\builder;
use core_container\container;
use core_container\factory;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\Group;
use totara_contentmarketplace\plugininfo\contentmarketplace;
use totara_contentmarketplace\entity\course_module_source;

#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_course_module_source_test extends testcase {

    /**
     * Make sure that a Go1 scorm activity can be backed up and restored, and that a course module source record is created.
     */
    public function test_backup_and_restore_successful(): void {
        self::setAdminUser();

        $scorm_course = self::getDataGenerator()->create_course(['shortname' => 'simple scorm']);
        $scorm_module = self::getDataGenerator()->create_module('scorm', ['course' => $scorm_course]);

        $go1_id = '12345678';
        $go1_module = $this->create_goone_course_module('go1 scorm', $go1_id);

        $go1_course_backup = $this->backup($go1_module->course);

        $this->assertEquals(0, course_module_source::repository()->count());

        $go1_course_restored = $this->restore($go1_course_backup);
        $go1_module_restored = builder::table('scorm')
            ->where('course', $go1_course_restored->id)
            ->one();

        $this->assertNotNull($go1_module_restored);
        $this->assertNotEquals($scorm_module->id, $go1_module_restored->id);
        $this->assertNotEquals($go1_module->id, $go1_module_restored->id);
        $this->assertNotEquals($scorm_module->course, $go1_module_restored->course);
        $this->assertNotEquals($go1_module->course, $go1_module_restored->course);

        $go1_cm_restored = builder::table('course_modules')
            ->where('course', $go1_course_restored->id)
            ->one();
        $this->assertNotNull($go1_cm_restored);
        $this->assertNotEquals($scorm_module->cmid, $go1_cm_restored->id);
        $this->assertNotEquals($go1_module->cmid, $go1_cm_restored->id);

        /** @var learning_object $learning_object */
        $learning_object = learning_object::repository()->one();
        $this->assertNotNull($learning_object);
        $this->assertEquals($go1_id, $learning_object->external_id);

        /** @var course_module_source $course_module_source */
        $course_module_source = course_module_source::repository()->one();
        $this->assertNotNull($course_module_source);
        $this->assertEquals($go1_cm_restored->id, $course_module_source->cm_id);
        $this->assertEquals('contentmarketplace_goonetwo', $course_module_source->marketplace_component);
        $this->assertEquals($learning_object->id, $course_module_source->learning_object_id);
    }

    /**
     * Make sure that a restored core Go1 scorm activity is linked to goonetwo when the core Go1 plugin is disabled.
     */
    public function test_restore_core_goone_activity_when_core_disabled(): void {
        self::setAdminUser();
        contentmarketplace::plugin('goonetwo')->enable();

        $go1_module = $this->create_goone_course_module('core go1 scorm', 12345678, 'goone');
        $go1_course_restored = $this->restore($this->backup($go1_module->course));

        $this->assert_restored_module_belongs_to($go1_course_restored->id, 'goonetwo', 12345678);
    }

    /**
     * Make sure that a restored core Go1 scorm activity stays with the core Go1 plugin when the core plugin is enabled.
     */
    public function test_restore_core_goone_activity_when_core_enabled(): void {
        self::setAdminUser();
        contentmarketplace::plugin('goone')->enable();
        contentmarketplace::plugin('goonetwo')->enable();

        $go1_module = $this->create_goone_course_module('core go1 scorm', 12345678, 'goone');
        $go1_course_restored = $this->restore($this->backup($go1_module->course));

        $this->assert_restored_module_belongs_to($go1_course_restored->id, 'goone', 12345678);
        $this->assertSame(0, builder::table('marketplace_goonetwo_learning_object')->count());
    }

    /**
     * Make sure that a restored core Go1 scorm activity stays with the core Go1 plugin when both plugins are disabled,
     * for example after a migration back to the core plugin.
     */
    public function test_restore_core_goone_activity_when_both_disabled(): void {
        self::setAdminUser();

        $go1_module = $this->create_goone_course_module('core go1 scorm', 12345678, 'goone');
        $go1_course_restored = $this->restore($this->backup($go1_module->course));

        $this->assert_restored_module_belongs_to($go1_course_restored->id, 'goone', 12345678);
        $this->assertSame(0, builder::table('marketplace_goonetwo_learning_object')->count());
    }

    /**
     * @param int $course_id Restored course
     * @param string $plugin goone or goonetwo
     * @param int $go1_id
     */
    private function assert_restored_module_belongs_to(int $course_id, string $plugin, int $go1_id): void {
        $cm = builder::table('course_modules')->where('course', $course_id)->one(true);

        $sources = course_module_source::repository()->where('cm_id', $cm->id)->get();
        $this->assertCount(1, $sources);
        /** @var course_module_source $source */
        $source = $sources->first();
        $this->assertEquals("contentmarketplace_{$plugin}", $source->marketplace_component);
        $this->assertEquals(
            $go1_id,
            builder::table("marketplace_{$plugin}_learning_object")->where('id', $source->learning_object_id)->value('external_id')
        );

        $package_source = builder::table('files')
            ->where('contextid', context_module::instance($cm->id)->id)
            ->where('component', 'mod_scorm')
            ->where('filearea', 'package')
            ->where('filename', '<>', '.')
            ->value('source');
        $this->assertSame("content-marketplace://{$plugin}/{$go1_id}", $package_source);
    }

    /**
     * Create a Go1 course and corresponding scorm module with zip file.
     * Note that this deliberately does not create a course_module_source record.
     *
     * @param string $course_name
     * @param int $go1_id
     * @param string $plugin Plugin in the package file source, goone or goonetwo
     * @return object Course module record
     */
    private function create_goone_course_module(string $course_name, int $go1_id, string $plugin = 'goonetwo'): object {
        global $CFG, $USER;

        $course = self::getDataGenerator()->create_course(['shortname' => $course_name]);

        $file_itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_pathname([
            'contextid' => context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'filepath' => '/',
            'filename' => $course_name . '.zip',
            'itemid' => $file_itemid,
            'source' => "content-marketplace://$plugin/$go1_id",
        ], $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip');

        $module = self::getDataGenerator()->create_module('scorm', [
            'course' => $course,
            'packagefile' => $file_itemid,
        ]);

        return $module;
    }

    /**
     * @param int $course_id
     * @return string
     */
    private function backup(int $course_id): string {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        // Turn off file logging, otherwise the logs can't be deleted on Windows.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $backup_controller = new backup_controller(
            backup::TYPE_1COURSE,
            $course_id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id
        );
        $backup_id = $backup_controller->get_backupid();

        $backup_controller->execute_plan();
        $file = $backup_controller->get_results()['backup_destination'];
        $backup_controller->destroy();

        $backup_base_path = $backup_controller->get_plan()->get_basepath();
        if (!file_exists($backup_base_path . '/moodle_backup.xml')) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backup_base_path);
        }

        return $backup_id;
    }

    /**
     * @param string $backup_id
     * @return course|container
     */
    private function restore(string $backup_id): course {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $new_course = \core\testing\generator::instance()->create_course();

        $restore_controller = new restore_controller(
            $backup_id,
            $new_course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $this->assertTrue($restore_controller->execute_precheck());
        $restore_controller->execute_plan();
        $restore_controller->destroy();

        return factory::from_record($new_course);
    }

}