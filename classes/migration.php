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

namespace contentmarketplace_goonetwo;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use container_course\module\course_module;
use contentmarketplace_goonetwo\model\learning_object;
use core\orm\query\builder;
use core\output\notification;
use totara_contentmarketplace\entity\course_module_source as course_module_source_entity;
use totara_contentmarketplace\model\course_module_source;
use totara_contentmarketplace\plugininfo\contentmarketplace;

/**
 * Moves Go1 activities between the core Go1 plugin (goone) and this plugin (goonetwo).
 *
 * A Go1 SCORM activity belongs to a plugin through:
 * - a learning object row in marketplace_{plugin}_learning_object,
 * - a totara_contentmarketplace_course_module_source row with the plugin component,
 * - the source content-marketplace://{plugin}/{external_id} of its SCORM package file.
 */
class migration {

    /** Name of the core Go1 plugin. */
    const CORE = 'goone';

    /** Name of this plugin. */
    const V2 = 'goonetwo';

    /** Config setting with the migration status. */
    const CONFIG_STATUS = 'migration_pending';

    /** Config setting with the lang string key of the migration status message. */
    const CONFIG_STATUS_LANG = 'migration_pending_lang';

    /** A migration task is queued. */
    const STATUS_PENDING = 'pending';

    /** All Go1 data belongs to this plugin. The status stays until the next migration back to the core plugin. */
    const STATUS_COMPLETED = 'completed';

    /** The data was migrated back to the core plugin. The status stays until the next migration to this plugin. */
    const STATUS_REVERTED = 'reverted';

    /** The last migration task failed. The status is cleared when its message shows. */
    const STATUS_FAILED = 'failed';

    /**
     * Returns true when the core Go1 plugin is installed and enabled.
     *
     * @return bool
     */
    public static function is_core_goone_enabled(): bool {
        $core_goone = contentmarketplace::plugin(self::CORE, false);
        return $core_goone !== null && $core_goone->is_enabled();
    }

    /**
     * Returns true when the learning object table of the core Go1 plugin exists.
     *
     * @return bool
     */
    public static function is_core_goone_installed(): bool {
        return builder::get_db()->get_manager()->table_exists(self::get_learning_object_table(self::CORE));
    }

    /**
     * Returns true when some Go1 data belongs to the core Go1 plugin.
     *
     * With the completed status, the tables are not examined, because a disabled core plugin cannot make new data.
     * When the tables are examined and have no core data, the status changes to completed, unless a migration
     * is pending or the data was migrated back to the core plugin.
     *
     * @return bool
     */
    public static function has_unmigrated_core_data(): bool {
        $status = get_config('contentmarketplace_goonetwo', self::CONFIG_STATUS);
        if ($status === self::STATUS_COMPLETED && !self::is_core_goone_enabled()) {
            return false;
        }

        $unmigrated = false;
        if (self::is_core_goone_installed()) {
            $counts = self::get_unmigrated_counts(self::CORE, self::V2);
            $unmigrated = $counts['learning_objects'] > 0
                || $counts['course_module_sources'] > 0
                || $counts['files'] > 0
                || $counts['orphan_files'] > 0;
        }

        if (!$unmigrated && !in_array($status, [self::STATUS_PENDING, self::STATUS_REVERTED, self::STATUS_COMPLETED], true)) {
            set_config(self::CONFIG_STATUS, self::STATUS_COMPLETED, 'contentmarketplace_goonetwo');
        }
        return $unmigrated;
    }

    /**
     * Counts the Go1 data that belongs to the $from plugin and is not migrated to the $to plugin.
     *
     * @param string $from Plugin name, goone or goonetwo
     * @param string $to Plugin name, goone or goonetwo
     *
     * @return int[] Counts with the keys learning_objects, course_module_sources, courses, files and orphan_files
     */
    public static function get_unmigrated_counts(string $from, string $to): array {
        self::validate_direction($from, $to);
        $db = builder::get_db();

        $from_table = self::get_learning_object_table($from);
        $to_table = self::get_learning_object_table($to);
        $source_like = $db->sql_like('f.source', ':source');
        $source = $db->sql_like_escape(self::get_source_prefix($from)) . '%';
        $component = self::get_component($from);

        return [
            'learning_objects' => $db->count_records_sql(
                "SELECT COUNT(1)
                   FROM {{$from_table}} from_lo
              LEFT JOIN {{$to_table}} to_lo ON to_lo.external_id = from_lo.external_id
                  WHERE to_lo.id IS NULL"
            ),
            'course_module_sources' => $db->count_records(
                'totara_contentmarketplace_course_module_source',
                ['marketplace_component' => $component]
            ),
            'courses' => $db->count_records_sql(
                "SELECT COUNT(DISTINCT cm.course)
                   FROM {totara_contentmarketplace_course_module_source} cms
                   JOIN {course_modules} cm ON cm.id = cms.cm_id
                  WHERE cms.marketplace_component = :component",
                ['component' => $component]
            ),
            'files' => $db->count_records_sql(
                "SELECT COUNT(1)
                   FROM {files} f
                  WHERE {$source_like}
                    AND f.filearea <> 'draft'",
                ['source' => $source]
            ),
            'orphan_files' => $db->count_records_sql(
                "SELECT COUNT(1)
                   FROM {files} f
                   JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = :contextlevel
              LEFT JOIN {totara_contentmarketplace_course_module_source} cms ON cms.cm_id = ctx.instanceid
                  WHERE f.component = 'mod_scorm'
                    AND f.filearea = 'package'
                    AND {$source_like}
                    AND cms.id IS NULL",
                ['contextlevel' => CONTEXT_MODULE, 'source' => $source]
            ),
        ];
    }

    /**
     * Returns the migration status message.
     *
     * The message of a pending migration stays. Any other message is cleared, so that it shows one time only.
     * A failed status is cleared with its message. A completed or reverted status stays.
     *
     * @return array|null [message, notification type], or null when there is no message
     */
    public static function pop_status_message(): ?array {
        $status = get_config('contentmarketplace_goonetwo', self::CONFIG_STATUS);
        $status_lang = get_config('contentmarketplace_goonetwo', self::CONFIG_STATUS_LANG);
        if ($status_lang === false) {
            return null;
        }

        if ($status !== self::STATUS_PENDING) {
            unset_config(self::CONFIG_STATUS_LANG, 'contentmarketplace_goonetwo');
        }
        if ($status === self::STATUS_FAILED) {
            unset_config(self::CONFIG_STATUS, 'contentmarketplace_goonetwo');
        }

        if (!get_string_manager()->string_exists($status_lang, 'contentmarketplace_goonetwo')) {
            return null;
        }
        return [
            get_string($status_lang, 'contentmarketplace_goonetwo'),
            $status === self::STATUS_FAILED ? notification::NOTIFY_ERROR : notification::NOTIFY_SUCCESS,
        ];
    }

    /**
     * Returns the actions that the migration does for the current state of the site.
     *
     * When some data belongs to the core Go1 plugin, or the core Go1 plugin is enabled, the actions move the data
     * to this plugin and disable the core plugin as necessary. Otherwise the actions move the data back
     * to the core Go1 plugin and disable this plugin, if the core plugin tables exist.
     *
     * @return array|null Keys: from, to (plugin names), migrate (bool), disable (plugin name or null).
     *     Null when there is no action.
     */
    public static function get_actions(): ?array {
        $unmigrated = self::has_unmigrated_core_data();
        $core_enabled = self::is_core_goone_enabled();

        if ($unmigrated || $core_enabled) {
            return [
                'from' => self::CORE,
                'to' => self::V2,
                'migrate' => $unmigrated,
                'disable' => $core_enabled ? self::CORE : null,
            ];
        }

        if (!self::is_core_goone_installed()) {
            return null;
        }

        return [
            'from' => self::V2,
            'to' => self::CORE,
            'migrate' => true,
            'disable' => self::V2,
        ];
    }

    /**
     * Returns the lang string key of the button that starts the given actions.
     *
     * @param array $actions Actions from get_actions()
     *
     * @return string
     */
    public static function get_button_string_key(array $actions): string {
        if ($actions['to'] === self::CORE) {
            return 'migration_button_reverse';
        }
        if ($actions['migrate'] && $actions['disable'] !== null) {
            return 'migration_button_migrate_disable';
        }
        if ($actions['migrate']) {
            return 'migration_button_migrate';
        }
        return 'migration_button_disable';
    }

    /**
     * Moves all Go1 data from the $from plugin to the $to plugin in one transaction.
     * The learning object rows of the $from plugin stay.
     *
     * @param string $from Plugin name, goone or goonetwo
     * @param string $to Plugin name, goone or goonetwo
     */
    public static function migrate(string $from, string $to): void {
        self::validate_direction($from, $to);

        $transaction = builder::get_db()->start_delegated_transaction();
        try {
            self::migrate_in_transaction($from, $to);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * @param string $from Plugin name, goone or goonetwo
     * @param string $to Plugin name, goone or goonetwo
     */
    private static function migrate_in_transaction(string $from, string $to): void {
        $db = builder::get_db();

        $from_table = self::get_learning_object_table($from);
        $to_table = self::get_learning_object_table($to);
        $from_prefix = self::get_source_prefix($from);
        $source_like = $db->sql_like('f.source', ':source');
        $source = $db->sql_like_escape($from_prefix) . '%';

        // Package files without a course module source row: link them to the $to plugin.
        $orphans = $db->get_records_sql(
            "SELECT ctx.instanceid AS cm_id, MIN(f.source) AS source
               FROM {files} f
               JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = :contextlevel
          LEFT JOIN {totara_contentmarketplace_course_module_source} cms ON cms.cm_id = ctx.instanceid
              WHERE f.component = 'mod_scorm'
                AND f.filearea = 'package'
                AND {$source_like}
                AND cms.id IS NULL
           GROUP BY ctx.instanceid",
            ['contextlevel' => CONTEXT_MODULE, 'source' => $source]
        );
        foreach ($orphans as $orphan) {
            $external_id = (int) substr($orphan->source, strlen($from_prefix));
            $learning_object_id = self::get_or_create_learning_object_id($to, $external_id);
            $db->insert_record('totara_contentmarketplace_course_module_source', (object) [
                'cm_id' => $orphan->cm_id,
                'learning_object_id' => $learning_object_id,
                'marketplace_component' => self::get_component($to),
            ]);
        }

        // Learning objects.
        $db->execute(
            "INSERT INTO {{$to_table}} (external_id)
             SELECT from_lo.external_id
               FROM {{$from_table}} from_lo
          LEFT JOIN {{$to_table}} to_lo ON to_lo.external_id = from_lo.external_id
              WHERE to_lo.id IS NULL"
        );

        // Course module source rows.
        $db->execute(
            "UPDATE {totara_contentmarketplace_course_module_source}
                SET marketplace_component = :to_component,
                    learning_object_id = (
                        SELECT to_lo.id
                          FROM {{$to_table}} to_lo
                          JOIN {{$from_table}} from_lo ON from_lo.external_id = to_lo.external_id
                         WHERE from_lo.id = {totara_contentmarketplace_course_module_source}.learning_object_id
                    )
              WHERE marketplace_component = :from_component",
            ['to_component' => self::get_component($to), 'from_component' => self::get_component($from)]
        );

        // File sources. Draft files are temporary and stay as they are.
        $new_source = $db->sql_concat(':new_prefix', $db->sql_substr('source', strlen($from_prefix) + 1));
        $db->execute(
            "UPDATE {files}
                SET source = {$new_source}
              WHERE " . $db->sql_like('source', ':source') . "
                AND filearea <> 'draft'",
            ['new_prefix' => self::get_source_prefix($to), 'source' => $source]
        );
    }

    /**
     * Links a restored SCORM activity with a core Go1 package to this plugin.
     * If the core Go1 restore watcher has already made a course module source row, the row changes to this plugin.
     *
     * @param course_module $course_module
     * @param int $contextid Context of the course module
     * @param int $external_id Go1 learning object ID
     */
    public static function migrate_restored_module(course_module $course_module, int $contextid, int $external_id): void {
        $db = builder::get_db();

        $transaction = $db->start_delegated_transaction();
        try {
            $learning_object = learning_object::load_by_external_id($external_id);

            /** @var course_module_source_entity|null $source_entity */
            $source_entity = course_module_source_entity::repository()
                ->where('cm_id', $course_module->get_id())
                ->one();
            if ($source_entity === null) {
                course_module_source::create($course_module, $learning_object);
            } else {
                $source_entity->marketplace_component = self::get_component(self::V2);
                $source_entity->learning_object_id = $learning_object->get_id();
                $source_entity->save();
            }

            $db->execute(
                "UPDATE {files}
                    SET source = :new_source
                  WHERE contextid = :contextid
                    AND component = 'mod_scorm'
                    AND filearea = 'package'
                    AND source = :old_source",
                [
                    'new_source' => self::get_source_prefix(self::V2) . $external_id,
                    'contextid' => $contextid,
                    'old_source' => self::get_source_prefix(self::CORE) . $external_id,
                ]
            );

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Returns the source prefix of the SCORM package files of a plugin.
     *
     * @param string $plugin Plugin name, goone or goonetwo
     *
     * @return string
     */
    public static function get_source_prefix(string $plugin): string {
        return "content-marketplace://{$plugin}/";
    }

    /**
     * @param string $plugin Plugin name, goone or goonetwo
     *
     * @return string
     */
    private static function get_component(string $plugin): string {
        return 'contentmarketplace_' . $plugin;
    }

    /**
     * @param string $plugin Plugin name, goone or goonetwo
     *
     * @return string
     */
    private static function get_learning_object_table(string $plugin): string {
        return "marketplace_{$plugin}_learning_object";
    }

    /**
     * @param string $plugin Plugin name, goone or goonetwo
     * @param int $external_id Go1 learning object ID
     *
     * @return int Learning object row ID
     */
    private static function get_or_create_learning_object_id(string $plugin, int $external_id): int {
        $db = builder::get_db();
        $table = self::get_learning_object_table($plugin);

        $id = $db->get_field($table, 'id', ['external_id' => $external_id]);
        if ($id !== false) {
            return (int) $id;
        }
        return (int) $db->insert_record($table, (object) ['external_id' => $external_id]);
    }

    /**
     * @param string $from
     * @param string $to
     */
    private static function validate_direction(string $from, string $to): void {
        $plugins = [self::CORE, self::V2];
        if (!in_array($from, $plugins, true) || !in_array($to, $plugins, true) || $from === $to) {
            throw new coding_exception("Invalid Go1 migration direction: '{$from}' to '{$to}'");
        }
    }

}
