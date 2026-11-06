<?php
/**
 * This file is part of Totara Learn
 *
 * Copyright (C) 2021 onwards Totara Learning Solutions LTD
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Nathan Lewis <mark.metcalfe@totaralearning.com>
 * @package contentmarketplace_goonetwo
 */

namespace contentmarketplace_goonetwo\watcher;

defined('MOODLE_INTERNAL') || die();

use container_course\module\course_module;
use contentmarketplace_goonetwo\migration;
use contentmarketplace_goonetwo\model\learning_object;
use core\orm\query\builder;
use restore_activity_task;
use totara_contentmarketplace\model\course_module_source;
use totara_contentmarketplace\plugininfo\contentmarketplace;
use totara_core\hook\backup_post_restore_task;

class restore_task_watcher {

    /**
     * Create a course module source record if a Go1 scorm activity was restored.
     *
     * @param backup_post_restore_task $hook
     */
    public static function post_scorm_activity_restored(backup_post_restore_task $hook): void {
        $task = $hook->get_task();

        if (!$task instanceof restore_activity_task || $task->get_modulename() !== 'scorm') {
            return;
        }

        $source_prefix = 'content-marketplace://goonetwo/';

        $go1_scorm_package_file = builder::table('files')
            ->select('source')
            ->where('contextid', $task->get_contextid())
            ->where('filearea', 'package')
            ->where_like_starts_with('source', $source_prefix)
            ->one();

        if ($go1_scorm_package_file === null) {
            self::post_core_scorm_activity_restored($task);
            return;
        }

        $course_module = course_module::from_id($task->get_moduleid());

        $go1_id = str_replace($source_prefix, '', $go1_scorm_package_file->source);
        $learning_object = learning_object::load_by_external_id($go1_id);

        course_module_source::create($course_module, $learning_object);
    }

    /**
     * Link a restored core Go1 scorm activity to this plugin when this plugin is enabled and the core Go1 plugin is disabled.
     * If the core Go1 restore watcher runs first, it links the activity to the core plugin, and this watcher changes the link.
     * If this watcher runs first, it changes the package source, thus the core Go1 restore watcher ignores the activity.
     *
     * @param restore_activity_task $task
     */
    private static function post_core_scorm_activity_restored(restore_activity_task $task): void {
        if (migration::is_core_goone_enabled() || !contentmarketplace::plugin(migration::V2)->is_enabled()) {
            return;
        }

        $source_prefix = migration::get_source_prefix(migration::CORE);

        $core_scorm_package_file = builder::table('files')
            ->select('source')
            ->where('contextid', $task->get_contextid())
            ->where('filearea', 'package')
            ->where_like_starts_with('source', $source_prefix)
            ->one();

        if ($core_scorm_package_file === null) {
            return;
        }

        $course_module = course_module::from_id($task->get_moduleid());
        $go1_id = (int) str_replace($source_prefix, '', $core_scorm_package_file->source);

        migration::migrate_restored_module($course_module, $task->get_contextid(), $go1_id);
    }

}
