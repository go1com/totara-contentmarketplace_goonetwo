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
 * Code that runs before core removes the tables, config and capabilities of the plugin.
 *
 * @package    contentmarketplace_goonetwo
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use contentmarketplace_goonetwo\task\migrate_task;
use contentmarketplace_goonetwo\webhook;

defined('MOODLE_INTERNAL') || die();

/**
 * Remove the data of the plugin that core does not remove.
 *
 * @return bool
 */
function xmldb_contentmarketplace_goonetwo_uninstall(): bool {
    global $DB;

    // Go1 must stop sending events to this site. The Go1 API cannot delete a webhook, thus it is deactivated.
    webhook::deactivate_site_webhook();

    // Disable the workflows that db/install.php enabled.
    contentmarketplace_goonetwo\workflow\core_course\coursecreate\contentmarketplace::instance()->disable();
    contentmarketplace_goonetwo\workflow\totara_contentmarketplace\exploremarketplace\goonetwo::instance()->disable();
    contentmarketplace_goonetwo\workflow\mod_contentmarketplace\create_marketplace_activity\goonetwo::instance()->disable();

    // The catalogue and the reports load the marketplace plugin of each course module source row,
    // which fails when the plugin is not installed. The courses and their SCORM activities stay.
    $DB->delete_records('totara_contentmarketplace_course_module_source', ['marketplace_component' => 'contentmarketplace_goonetwo']);

    // Core removes only scheduled tasks. A queued ad-hoc task would refer to a class that does not exist.
    $DB->delete_records('task_adhoc', ['classname' => '\\' . migrate_task::class]);

    return true;
}
