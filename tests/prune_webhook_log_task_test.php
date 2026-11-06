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

use contentmarketplace_goonetwo\task\prune_webhook_log_task;
use core\orm\query\builder;
use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the scheduled task that removes old webhook log entries.
 */
#[CoversClass(prune_webhook_log_task::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_prune_webhook_log_task_test extends testcase {

    public function test_execute_removes_only_entries_older_than_30_days(): void {
        $table = builder::table('marketplace_goonetwo_webhook_log');
        $table->insert(['message' => 'old', 'timecreated' => time() - 31 * DAYSECS]);
        $table->insert(['message' => 'recent', 'timecreated' => time() - 29 * DAYSECS]);
        $table->insert(['message' => 'new', 'timecreated' => time()]);

        (new prune_webhook_log_task())->execute();

        $messages = builder::table('marketplace_goonetwo_webhook_log')->order_by('id')->get()->pluck('message');
        self::assertSame(['recent', 'new'], $messages);
    }

    public function test_task_is_registered(): void {
        $task = \core\task\manager::get_scheduled_task(prune_webhook_log_task::class);

        self::assertInstanceOf(prune_webhook_log_task::class, $task);
        self::assertSame('Remove old Go1 webhook log entries', $task->get_name());
    }

}
