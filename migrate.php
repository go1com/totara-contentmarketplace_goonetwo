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

// Queues the Go1 migration task and returns to the Migration settings tab.
// When there is no data to migrate, it disables the core Go1 plugin immediately, with no task.

use contentmarketplace_goonetwo\migration;
use contentmarketplace_goonetwo\task\migrate_task;
use totara_contentmarketplace\plugininfo\contentmarketplace;

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/migrate.php');

require_login();
require_capability('totara/contentmarketplace:config', $context);
require_sesskey();

$return_url = new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', ['tab' => 'migration']);

$actions = migration::get_actions();

if ($actions === null || get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS) === migration::STATUS_PENDING) {
    redirect($return_url);
}

// No data to migrate: only the core plugin must be disabled, which needs no ad-hoc task.
if (!$actions['migrate']) {
    contentmarketplace::plugin($actions['disable'])->disable();
    redirect($return_url, get_string('migration_coregoonedisabled', 'contentmarketplace_goonetwo'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Second confirmation step of the migration back to the core plugin. The Migration tab button shows the first step.
if ($actions['to'] === migration::CORE && !optional_param('confirm', false, PARAM_BOOL)) {
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title(get_string('migration', 'contentmarketplace_goonetwo'));

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('migration_reverse_confirm_final', 'contentmarketplace_goonetwo'),
        new single_button(new moodle_url($PAGE->url, ['confirm' => 1]), get_string('migration_button_reverse', 'contentmarketplace_goonetwo')),
        $return_url
    );
    echo $OUTPUT->footer();
    exit;
}

// The status is set before the task is queued, so that the task status cannot be overwritten.
set_config(migration::CONFIG_STATUS, migration::STATUS_PENDING, 'contentmarketplace_goonetwo');
set_config(migration::CONFIG_STATUS_LANG, 'migration_pending_message', 'contentmarketplace_goonetwo');

try {
    $task = new migrate_task();
    $task->set_custom_data($actions);
    \core\task\manager::queue_adhoc_task($task);
} catch (\Throwable $e) {
    unset_config(migration::CONFIG_STATUS, 'contentmarketplace_goonetwo');
    unset_config(migration::CONFIG_STATUS_LANG, 'contentmarketplace_goonetwo');
    redirect($return_url, get_string('migration_queue_failed', 'contentmarketplace_goonetwo'), null, \core\output\notification::NOTIFY_ERROR);
}

redirect($return_url);
