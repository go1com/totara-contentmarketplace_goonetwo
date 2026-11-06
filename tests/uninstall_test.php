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

use contentmarketplace_goonetwo\task\migrate_task;
use contentmarketplace_goonetwo\workflow\core_course\coursecreate\contentmarketplace as coursecreate_workflow;
use contentmarketplace_goonetwo\workflow\mod_contentmarketplace\create_marketplace_activity\goonetwo as activity_workflow;
use contentmarketplace_goonetwo\workflow\totara_contentmarketplace\exploremarketplace\goonetwo as explore_workflow;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\Group;

#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_uninstall_test extends testcase {

    public function test_uninstall_removes_data_that_core_does_not_remove(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goonetwo/db/uninstall.php');

        $course = self::getDataGenerator()->create_course();
        foreach (['contentmarketplace_goonetwo', 'contentmarketplace_goone'] as $component) {
            $module = self::getDataGenerator()->create_module('label', ['course' => $course]);
            $DB->insert_record('totara_contentmarketplace_course_module_source', (object) [
                'cm_id' => $module->cmid,
                'learning_object_id' => 1,
                'marketplace_component' => $component,
            ]);
        }
        $workflows = [coursecreate_workflow::instance(), explore_workflow::instance(), activity_workflow::instance()];
        foreach ($workflows as $workflow) {
            $workflow->enable();
        }
        \core\task\manager::queue_adhoc_task(new migrate_task());

        $this->assertTrue(xmldb_contentmarketplace_goonetwo_uninstall());

        $this->assertSame(
            ['contentmarketplace_goone'],
            array_values($DB->get_fieldset_select('totara_contentmarketplace_course_module_source', 'marketplace_component', '1=1'))
        );
        foreach ($workflows as $workflow) {
            $this->assertFalse($workflow->is_enabled(), get_class($workflow));
        }
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . migrate_task::class]));
    }

}
