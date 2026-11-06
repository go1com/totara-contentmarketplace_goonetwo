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

namespace contentmarketplace_goonetwo\task;

defined('MOODLE_INTERNAL') || die();

use core\orm\query\builder;
use core\task\scheduled_task;

/**
 * Removes the webhook log entries that are older than the retention period.
 *
 * The setting webhook_log_retention_days on the Webhook tab gives the retention period.
 */
class prune_webhook_log_task extends scheduled_task {

    /**
     * Number of days that the webhook log keeps an entry when the setting has no valid value.
     */
    const RETENTION_DAYS_DEFAULT = 30;

    /**
     * @return string
     */
    public function get_name() {
        return get_string('task_prune_webhook_log', 'contentmarketplace_goonetwo');
    }

    /**
     * @return void
     */
    public function execute() {
        $retention_days = (int) get_config('contentmarketplace_goonetwo', 'webhook_log_retention_days');
        if ($retention_days < 1) {
            $retention_days = self::RETENTION_DAYS_DEFAULT;
        }

        builder::table('marketplace_goonetwo_webhook_log')
            ->where('timecreated', '<', time() - $retention_days * DAYSECS)
            ->delete();
    }

}
