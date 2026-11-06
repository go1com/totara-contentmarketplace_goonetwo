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
 * Helper class related to webhook.
 *
 * @package    contentmarketplace_goonetwo
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace contentmarketplace_goonetwo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/completionlib.php');

use core\orm\query\builder;
use contentmarketplace_goonetwo\api;

class webhook {

    /**
     * Maximum age (in seconds) of a webhook signature timestamp that will be
     * accepted, to guard against replay of a previously-signed request.
     */
    const WEBHOOK_TOLERANCE_SECONDS = 600;

    /**
     * Maximum size (in bytes) of a webhook request body that will be read.
     * A Go1 event payload is less than 1 KB.
     */
    const MAX_REQUEST_BYTES = 65536;

    /**
     * Event types that the webhook of this site must send.
     */
    const EVENT_TYPES = ['enrollment.complete'];

    /**
     * Problem codes returned by check_webhook().
     */
    const PROBLEM_INACTIVE = 'inactive';
    const PROBLEM_SECRET_KEY = 'secret_key';
    const PROBLEM_EVENT_TYPES = 'event_types';

    /**
     * Get the Go1 webhook that sends events to this site.
     *
     * @param $api
     *
     * @return bool|object Webhook or false if not exists
     */
    public static function get_webhook($api): bool|object {
        $data = $api->get_webhooks();
        foreach ($data->hits ?? [] as $webhook) {
            if (isset($webhook->url, $webhook->id) && (string) $webhook->url === self::get_webhook_url()) {
                return $webhook;
            }
        }
        return false;
    }



    /**
     * Generate a random string to use when signing webhooks.
     * Internal method. Use get_webhook_secret_key()
     *
     * @param int $n Number of characters in the secret key.
     *
     * @return string
     */
    public static function generate_webhook_secret_key($n = 20): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*(){}[]_+-';
        $max = strlen($characters) - 1;
        $randsecret = '';

        for ($i = 0; $i < $n; $i++) {
            $index = random_int(0, $max);
            $randsecret .= $characters[$index];
        }

        return $randsecret;
    }


    /**
     * Get secret key to use when signing webhooks. Generate key if the config value does not exist.
     *
     * @return string
     */
    public static function get_webhook_secret_key(): string {
        $key = get_config('contentmarketplace_goonetwo', 'webhook_secret_key');
        if (!$key) {
            $key = self::generate_webhook_secret_key();
            set_config('webhook_secret_key', $key, 'contentmarketplace_goonetwo');
        }
        return $key;
    }


    /**
     * Create webhook. Won't check if it already exists.
     *
     * @param mixed $api
     *
     * @return void
     */
    public static function create_webhook($api): void {
        $params = self::get_webhook_params();
        $api->create_webhook($params);
    }


    /**
     * Update existing webhook with the standard params for this site, and set it to active.
     *
     * @param string $id
     * @param mixed $api
     *
     * @return void
     */
    public static function update_webhook(string $id, $api): void {
        $params = self::get_webhook_params();
        // The create request cannot set the status, thus only the update sets it.
        $params->status = 'active';
        $api->update_webhook($id, $params);
    }


    /**
     * Return standard Webhook params for this site.
     *
     * @return \stdClass
     */
    public static function get_webhook_params(): \stdClass {
        $params = new \stdClass();
        $params->name = "Totara LMS integration webhook";
        $params->url = self::get_webhook_url();
        $params->secret_key = self::get_webhook_secret_key();
        $params->event_types = self::EVENT_TYPES;

        return $params;
    }

    /**
     * Return the URL of the webhook endpoint of this site.
     *
     * @return string
     */
    public static function get_webhook_url(): string {
        global $CFG;

        return $CFG->wwwroot . '/totara/contentmarketplace/contentmarketplaces/goonetwo/payload.php';
    }

    /**
     * Set a webhook to inactive. The Go1 API cannot delete a webhook.
     *
     * @param string $id
     * @param mixed $api
     *
     * @return void
     */
    public static function disable_webhook(string $id, $api): void {
        $api->update_webhook($id, ['status' => 'inactive']);
    }

    /**
     * Deactivate the Go1 webhook of this site. Used when the plugin is uninstalled.
     *
     * Webhooks of other sites in the same Go1 portal do not change. An error does not stop the caller.
     *
     * @param api|null $api API instance, created internally when not supplied
     *
     * @return bool True when the webhook was deactivated
     */
    public static function deactivate_site_webhook(?api $api = null): bool {
        if (empty(get_config('contentmarketplace_goonetwo', 'oauth_client_id'))) {
            return false;
        }

        try {
            $api = $api ?? new api();
            $webhook = self::get_webhook($api);
            if ($webhook === false) {
                return false;
            }
            self::disable_webhook((string) $webhook->id, $api);
            return true;
        } catch (\Throwable $e) {
            debugging('Could not deactivate the Go1 webhook: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }


    /**
     * Check the settings of an existing Go1 webhook against the settings of this site.
     *
     * @param object $webhook Webhook as returned by Go1.
     *
     * @return string[] PROBLEM_* codes, empty when the webhook is correct.
     */
    public static function check_webhook(object $webhook): array {
        $problems = [];

        if (!isset($webhook->status) || (string) $webhook->status !== 'active') {
            $problems[] = self::PROBLEM_INACTIVE;
        }

        $secret_key = (string) get_config('contentmarketplace_goonetwo', 'webhook_secret_key');
        if ($secret_key === '' || !isset($webhook->secret_key) || !hash_equals($secret_key, (string) $webhook->secret_key)) {
            $problems[] = self::PROBLEM_SECRET_KEY;
        }

        $event_types = isset($webhook->event_types) ? (array) $webhook->event_types : [];
        if (array_diff(self::EVENT_TYPES, $event_types)) {
            $problems[] = self::PROBLEM_EVENT_TYPES;
        }

        return $problems;
    }


    /**
     * Process POST request coming from Go1 via webhook.
     *
     * @return void
     */
    public static function process_request(): void {
        // Read one byte more than the limit to find an oversized body without reading all of it.
        $request = file_get_contents('php://input', false, null, 0, self::MAX_REQUEST_BYTES + 1);
        if ($request === false || strlen($request) > self::MAX_REQUEST_BYTES) {
            self::add_to_log([], null, 'Webhook request body exceeds the size limit.');
            return;
        }

        $header = isset($_SERVER['HTTP_GO1_SIGNATURE']) ? (string) $_SERVER['HTTP_GO1_SIGNATURE'] : '';

        $error = self::verify_signature($request, $header, self::get_webhook_secret_key());
        if ($error !== '') {
            // Anyone can send a request to the endpoint, thus the log keeps only the error message.
            self::add_to_log([], null, $error);
            return;
        }

        self::process_payload($request);
    }


    /**
     * Verify the signature of a webhook request against the shared secret.
     * See https://developers.go1.com/docs/developer-tools/webhooks/security/#Signatures
     *
     * @param string $request Raw request body.
     * @param string $header Value of the Go1 signature header, "t=<timestamp>,v1=<signature>".
     * @param string $secret_key Shared secret used to sign the request.
     * @return string Empty string when the signature is valid, otherwise a description of the failure.
     */
    public static function verify_signature(string $request, string $header, string $secret_key): string {
        if ($header === '') {
            return 'Missing Go1 signature header.';
        }

        // Parse the header into ordered values, skipping any segment with no "=".
        $signature = [];
        foreach (explode(',', $header) as $value) {
            $item = explode('=', $value, 2);
            if (count($item) === 2) {
                $signature[] = $item[1];
            }
        }
        if (!isset($signature[0], $signature[1])) {
            return 'Malformed Go1 signature header.';
        }

        $timestamp = $signature[0];

        // Support both seconds (10 digits) and milliseconds (13 digits)
        $timestamp_seconds = (int) substr($timestamp, 0, 10);

        // Only a timestamp within the tolerance window is accepted.
        if (!is_numeric($timestamp) || abs(time() - $timestamp_seconds) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return 'Go1 signature timestamp outside the accepted tolerance.';
        }

        $calculated = hash_hmac('sha256', (string) $timestamp . "." . $request, $secret_key);
        if (!hash_equals($calculated, $signature[1])) {
            return 'Failed to verify signature.';
        }

        return '';
    }


    /**
     * Process request payload after signature has been verified.
     *
     * @param string $request - raw request string
     *
     * @return void
     */
    public static function process_payload(string $request): void {
        $postjson = json_decode($request);
        if (!is_object($postjson) || empty($postjson->event_type)) {
            self::add_to_log([], $request, 'Malformed webhook payload.');
            return;
        }
        switch ($postjson->event_type) {
            case 'enrollment.complete':
                self::process_payload_completion($postjson, $request);
                break;
            default:
                // Only subscribed event types are processed, anything else is logged.
                self::add_to_log([], $request, 'Unhandled webhook event type: ' . $postjson->event_type);
                break;
        }
    }


    /**
     * Process an "enrollment.complete" webhook payload.
     *
     * @param \stdClass $postjson Decoded payload
     * @param string $request Raw request body (for logging)
     * @param api|null $api API instance, created internally when not supplied
     *
     * @return void
     */
    public static function process_payload_completion($postjson, string $request, ?api $api = null): void {
        $db = builder::get_db();
        $api = $api ?? new api();

        try {
            $enrolinfo = $api->get_enrolment($postjson->data->id);

            $go1_userid = $enrolinfo->user_account_id;
            $lo_id = $postjson->data->lo_id;

            $result = [
                'enrollment_id' => $postjson->data->id,
                'go1_userid' => $go1_userid,
                'cm_ids' => [],
            ];

            if (empty($enrolinfo->pass)) {
                self::add_to_log($result, $request, 'Webhook event processed successfully');
                return;
            }

            $user = self::get_lms_user($go1_userid, $api);
            if (!$user) {
               throw new \Exception('User not found: '. $enrolinfo->user_account_id);
            }
            $result['go1_username'] = $user->go1_username;
            $result['go1_email'] = $user->go1_email;
            $result['user_id'] = $user->id;

            $sql = "SELECT s.id, s.course
                FROM {scorm} s
                INNER JOIN {modules} m on m.name = 'scorm'
                INNER JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id
                INNER JOIN {totara_contentmarketplace_course_module_source} tccm
                    ON (tccm.cm_id = cm.id AND tccm.marketplace_component = 'contentmarketplace_goonetwo')
                INNER JOIN {marketplace_goonetwo_learning_object} mglo ON mglo.id = tccm.learning_object_id
                WHERE 1=1
                    AND mglo.external_id = :lo_id";
            $params = ['lo_id' => $lo_id];
            $scorms = $db->get_records_sql($sql, $params);
            if (!$scorms) {
                throw new \Exception('SCORMs not found by lo_id: ' . $lo_id);
            }
            $mod = $db->get_record('modules', array('name' => 'scorm'));
            foreach ($scorms as $scorm) {
                $cm = $db->get_record('course_modules', array('course' => $scorm->course, 'module' => $mod->id, 'instance' => $scorm->id));
                $cmc = $db->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id));
                $course = $db->get_record('course', ['id' => $scorm->course]);

                if ($cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }

                // Ensure the user is enrolled in course.
                $context = \context_course::instance($scorm->course);
                if (!is_enrolled($context, $user)) {
                    continue;
                }

                if (!$cmc || !in_array($cmc->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
                    $completion = new \completion_info($course);
                    $completion->set_module_viewed($cm, $user->id);
                    $cm->completion = COMPLETION_TRACKING_MANUAL; // In-memory only: Force update on update_state()
                    $newstate = COMPLETION_COMPLETE;
                    $completion->update_state($cm, $newstate, $user->id);
                    $result['cm_ids'][] = ['courseid' => (int) $scorm->course, 'cmid' => (int) $cm->id];
                }
            }

            self::add_to_log($result, $request, 'Webhook event processed successfully');
        } catch (\Throwable $e) {
            // The webhook endpoint must always fail soft into the log, never with a 500.
            self::add_to_log([], $request, $e->getMessage());
        }
    }


    /**
     * Get Totara user record given Go1 user ID.
     *
     * The returned record also has go1_username (Go1 external_user_id, empty when not set) and go1_email.
     *
     * @param string|int $gooneuserid
     * @param api|null $api API instance, created internally when not supplied
     *
     * @return \stdClass|false
     */
    public static function get_lms_user(string|int $gooneuserid, ?api $api = null): \stdClass|false {
        $db = builder::get_db();

        $api = $api ?? new api();
        $userinfofromgo = $api->get_user($gooneuserid);
        $email = $userinfofromgo->user->email;
        $firstname = $userinfofromgo->user->given_name;
        $lastname = $userinfofromgo->user->family_name;
        $external_user_id = isset($userinfofromgo->custom_fields->external_user_id) ? $userinfofromgo->custom_fields->external_user_id : '';
        if (empty($external_user_id) && isset($userinfofromgo->standard_fields->external_user_id)) {
            $external_user_id = $userinfofromgo->standard_fields->external_user_id;
        }
        $username = $external_user_id;

        $active = array('deleted' => 0, 'suspended' => 0);

        if (!empty($username)) {
            $user = $db->get_record('user', array('username' => $username) + $active);
        }

        // Try email as-is.
        if (empty($user)) {
            $user = self::get_unique_active_user_by_email($email);
        }

        if (empty($user)) {
            $user = $db->get_record('user', array('username' => $email) + $active);
        }

        // Try email from external_user_id.
        if (empty($user) && !empty($username)) {
            $user = self::get_unique_active_user_by_email($username);
        }

        // Try the user part of the email as username.
        if (empty($user)) {
            $regexp = '/([^@]+)@.*/i';
            if (preg_match($regexp, $email, $p) && !empty($p[1])) {
                $username = $p[1];
                $user = $db->get_record('user', array('username' => $username) + $active);
            }
        }

        // Try firstname and lastname as last resort, but proceed only if one match.
        if (empty($user)) {
            $users = $db->get_records('user', array('firstname' => $firstname, 'lastname' => $lastname) + $active);
            if (sizeof($users) == 1) {
                reset($users);
                $user = current($users);
            }
        }

        if ($user) {
            $user->go1_username = (string) $external_user_id;
            $user->go1_email = (string) $email;
            return $user;
        } else {
            return false;
        }
    }

    /**
     * Get the active Totara user with the given email, compared case-insensitively.
     *
     * Returns false when the email is empty, or when no user or more than one user has the email.
     *
     * @param string $email
     *
     * @return \stdClass|false
     */
    private static function get_unique_active_user_by_email(string $email): \stdClass|false {
        if (trim($email) === '') {
            return false;
        }

        $db = builder::get_db();

        $select = $db->sql_equal('email', ':email', false) . ' AND deleted = 0 AND suspended = 0';
        $users = $db->get_records_select('user', $select, ['email' => $email]);
        if (count($users) !== 1) {
            return false;
        }
        return reset($users);
    }


    /**
     * Add an entry to the webhook log. The scheduled task prune_webhook_log_task removes old entries.
     *
     * An empty $result records an error. A non-empty $result records a success with the data in it:
     * enrollment_id, go1_userid, go1_username, go1_email, user_id, and cm_ids as a list of
     * ['courseid' => courseid, 'cmid' => cmid] pairs, which is stored as JSON.
     *
     * @param array $result Data of the processed event, empty for an error
     * @param string|null $request Raw request, null when the log must not keep it
     * @param string $message
     *
     * @return void
     */
    public static function add_to_log(array $result, ?string $request, string $message): void {
        $db = builder::get_db();

        // Insert log entry.
        $log = new \stdClass();
        $log->result = empty($result) ? 0 : 1;
        $log->enrollment_id = $result['enrollment_id'] ?? null;
        $log->go1_userid = $result['go1_userid'] ?? null;
        $log->go1_username = $result['go1_username'] ?? null;
        $log->go1_email = $result['go1_email'] ?? null;
        $log->user_id = $result['user_id'] ?? null;
        $log->cm_ids = isset($result['cm_ids']) ? json_encode($result['cm_ids']) : null;
        $log->message = $message;
        $log->request = $request;
        $log->timecreated = time();
        $db->insert_record('marketplace_goonetwo_webhook_log', $log);
    }

}