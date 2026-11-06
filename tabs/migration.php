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

use contentmarketplace_goonetwo\migration;
use core\output\notification;

defined('MOODLE_INTERNAL') || die();

global $OUTPUT;

$pending = get_config('contentmarketplace_goonetwo', migration::CONFIG_STATUS) === migration::STATUS_PENDING;

$status_message = migration::pop_status_message();
if ($status_message !== null) {
    echo $OUTPUT->notification(...$status_message);
}

if ($pending) {
    return;
}

$actions = migration::get_actions();

if ($actions === null) {
    echo html_writer::tag('p', get_string('migration_coregoonenotinstalled', 'contentmarketplace_goonetwo'));
    return;
}

if ($actions['to'] === migration::CORE) {
    echo html_writer::tag('p', get_string('migration_reverse_info', 'contentmarketplace_goonetwo'));
} else {
    if ($actions['migrate']) {
        $counts = migration::get_unmigrated_counts(migration::CORE, migration::V2);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable contentmarketplace_goonetwo_migration_report';
        foreach (['learning_objects', 'course_module_sources', 'courses', 'files', 'orphan_files'] as $key) {
            $table->data[] = [get_string('migration_report_' . $key, 'contentmarketplace_goonetwo'), $counts[$key]];
        }

        echo $OUTPUT->heading(get_string('migration_report', 'contentmarketplace_goonetwo'), 3);
        echo $OUTPUT->render($table);
    }

    if ($actions['disable'] !== null) {
        echo $OUTPUT->notification(get_string('migration_coregooneenabled', 'contentmarketplace_goonetwo'), notification::NOTIFY_WARNING);
    }
}

$button = new single_button(
    new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/migrate.php'),
    get_string(migration::get_button_string_key($actions), 'contentmarketplace_goonetwo'),
    'post',
    true
);
// First confirmation step of the migration back to the core plugin. migrate.php shows the second step.
if ($actions['to'] === migration::CORE) {
    $button->add_confirm_action(get_string('migration_reverse_confirm', 'contentmarketplace_goonetwo'));
}
echo $OUTPUT->render($button);
