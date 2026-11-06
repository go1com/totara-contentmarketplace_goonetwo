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

defined('MOODLE_INTERNAL') || die();

/**
 * Go1 content marketplace plugin upgrade.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_contentmarketplace_goonetwo_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026110300) {
        // Add the fields that record processed webhook events to the webhook log.
        $table = new xmldb_table('marketplace_goonetwo_webhook_log');

        $fields = [
            new xmldb_field('result', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timecreated'),
            new xmldb_field('enrollment_id', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'result'),
            new xmldb_field('go1_userid', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'enrollment_id'),
            new xmldb_field('go1_username', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'go1_userid'),
            new xmldb_field('go1_email', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'go1_username'),
            new xmldb_field('user_id', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'go1_email'),
            new xmldb_field('cm_ids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'user_id'),
            new xmldb_field('request', XMLDB_TYPE_TEXT, null, null, null, null, null, 'cm_ids'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $key = new xmldb_key('user_id_fk', XMLDB_KEY_FOREIGN, ['user_id'], 'user', ['id'], 'setnull');
        if (!$dbman->key_exists($table, $key)) {
            $dbman->add_key($table, $key);
        }

        upgrade_plugin_savepoint(true, 2026110300, 'contentmarketplace', 'goonetwo');
    }

    if ($oldversion < 2026110400) {
        // Add the index that the scheduled task prune_webhook_log_task uses.
        $table = new xmldb_table('marketplace_goonetwo_webhook_log');
        $index = new xmldb_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026110400, 'contentmarketplace', 'goonetwo');
    }

    return true;
}
