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

namespace contentmarketplace_goonetwo\userdata;

use context;
use core\orm\query\builder;
use totara_userdata\userdata\export;
use totara_userdata\userdata\item;
use totara_userdata\userdata\target_user;

/**
 * The webhook log entries of a Totara user (marketplace_goonetwo_webhook_log).
 *
 * An entry belongs to a user when the webhook found that user for the Go1 user of the event (user_id).
 * The entry keeps the Go1 user data and the raw request. Purge removes the entries.
 * The scheduled task prune_webhook_log_task also removes old entries.
 */
class webhook_log extends item {

    public static function get_main_component(): string {
        return 'totara_contentmarketplace';
    }

    /**
     * Can user data of this item be somehow counted?
     *
     * @return bool
     */
    public static function is_countable(): bool {
        return true;
    }

    /**
     * Count user data for this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the log is independent of courses
     * @return int amount of data or negative integer status code (self::RESULT_STATUS_ERROR or self::RESULT_STATUS_SKIPPED)
     */
    protected static function count(target_user $user, context $context): int {
        return static::query($user->id)->count();
    }

    /**
     * Can user data of this item data be exported from the system?
     *
     * @return bool
     */
    public static function is_exportable(): bool {
        return true;
    }

    /**
     * Export user data from this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the log is independent of courses
     * @return export result object
     */
    protected static function export(target_user $user, context $context) {
        $data = static::query($user->id)
            ->order_by('id')
            ->get()
            ->map(static function ($log) {
                return [
                    'id' => (int) $log->id,
                    'timecreated' => (int) $log->timecreated,
                    'result' => (int) $log->result,
                    'enrollment_id' => $log->enrollment_id === null ? null : (int) $log->enrollment_id,
                    'go1_userid' => $log->go1_userid === null ? null : (int) $log->go1_userid,
                    'go1_username' => $log->go1_username,
                    'go1_email' => $log->go1_email,
                    'cm_ids' => $log->cm_ids === null ? null : json_decode($log->cm_ids, true),
                    'message' => $log->message,
                    'request' => $log->request,
                ];
            })
            ->all();

        $export = new export();
        $export->data = [static::get_name() => $data];
        return $export;
    }

    /**
     * Can user data of this item data be purged from system?
     *
     * @param int $userstatus target_user::STATUS_ACTIVE, target_user::STATUS_DELETED or target_user::STATUS_SUSPENDED
     * @return bool
     */
    public static function is_purgeable(int $userstatus) {
        return true;
    }

    /**
     * Purge user data for this item.
     *
     * @param target_user $user
     * @param context $context Not used, as the log is independent of courses
     * @return int result self::RESULT_STATUS_SUCCESS, self::RESULT_STATUS_ERROR or self::RESULT_STATUS_SKIPPED
     */
    protected static function purge(target_user $user, context $context) {
        static::query($user->id)->delete();

        return static::RESULT_STATUS_SUCCESS;
    }

    /**
     * Query for the webhook log entries of the user.
     *
     * @param int $userid
     * @return builder
     */
    protected static function query(int $userid): builder {
        return builder::table('marketplace_goonetwo_webhook_log')
            ->where('user_id', $userid);
    }

}
