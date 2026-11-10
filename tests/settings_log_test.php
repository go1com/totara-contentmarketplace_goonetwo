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

use contentmarketplace_goonetwo\config_db_storage;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test that admin changes to the plugin settings are logged in config_log.
 */
#[CoversClass(config_db_storage::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_settings_log_test extends testcase {

    /**
     * Get the config_log rows of the plugin.
     *
     * @return array
     */
    private function get_log_rows(): array {
        global $DB;

        return array_values($DB->get_records(
            'config_log',
            ['plugin' => 'contentmarketplace_goonetwo'],
            'id',
            'id, name, oldvalue, value, userid'
        ));
    }

    public function test_set_and_log_logs_each_change(): void {
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);
        $config = new config_db_storage();

        $config->set_and_log('course_type', 'single');
        $config->set_and_log('course_type', 'multi');

        self::assertSame('multi', get_config('contentmarketplace_goonetwo', 'course_type'));
        $rows = $this->get_log_rows();
        self::assertCount(2, $rows);
        self::assertSame('course_type', $rows[0]->name);
        self::assertNull($rows[0]->oldvalue);
        self::assertSame('single', $rows[0]->value);
        self::assertEquals($user->id, $rows[0]->userid);
        self::assertSame('single', $rows[1]->oldvalue);
        self::assertSame('multi', $rows[1]->value);
    }

    public function test_set_and_log_ignores_the_same_value(): void {
        $config = new config_db_storage();
        $config->set_and_log('create_courses', 1);

        $config->set_and_log('create_courses', 1);
        $config->set_and_log('create_courses', '1');

        self::assertCount(1, $this->get_log_rows());
    }
}
