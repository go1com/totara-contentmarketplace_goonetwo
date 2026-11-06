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

use contentmarketplace_goonetwo\migration;
use core\task\adhoc_task;
use totara_contentmarketplace\plugininfo\contentmarketplace;

/**
 * Migrates the Go1 data and disables a Go1 plugin, as the custom data specifies.
 *
 * Custom data: the actions from migration::get_actions().
 * The task records the result in the migration status config settings. It does not throw exceptions,
 * so that the cron does not run it again and change the status.
 */
class migrate_task extends adhoc_task {

    /**
     * @return void
     */
    public function execute() {
        $actions = (array) $this->get_custom_data();

        try {
            if (!empty($actions['migrate'])) {
                migration::migrate((string) ($actions['from'] ?? ''), (string) ($actions['to'] ?? ''));
            }
            if (!empty($actions['disable'])) {
                contentmarketplace::plugin($actions['disable'])->disable();
            }

            $status = ($actions['to'] ?? '') === migration::CORE ? migration::STATUS_REVERTED : migration::STATUS_COMPLETED;
            $status_lang = 'migration_completed';
        } catch (\Throwable $e) {
            mtrace('Go1 migration failed: ' . $e->getMessage());
            $status = migration::STATUS_FAILED;
            $status_lang = 'migration_failed';
        }

        set_config(migration::CONFIG_STATUS, $status, 'contentmarketplace_goonetwo');
        set_config(migration::CONFIG_STATUS_LANG, $status_lang, 'contentmarketplace_goonetwo');
    }

}
