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

use contentmarketplace_goonetwo\api;
use contentmarketplace_goonetwo\config_db_storage as config;
use contentmarketplace_goonetwo\form\webhook_settings_form;
use contentmarketplace_goonetwo\helper;
use contentmarketplace_goonetwo\rest_client_response_exception;
use contentmarketplace_goonetwo\task\prune_webhook_log_task;
use contentmarketplace_goonetwo\webhook;

defined('MOODLE_INTERNAL') || die();

global $OUTPUT;

require_capability('totara/contentmarketplace:config', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);

$oauth_client_id = get_config('contentmarketplace_goonetwo', 'oauth_client_id');
if (empty($oauth_client_id)) {
    throw new moodle_exception('error:pluginnotconfigured', 'contentmarketplace_goonetwo');
}

$config = new config();
$retention_days = (int) $config->get('webhook_log_retention_days');
$settings_form = new webhook_settings_form([
    'webhook_log_retention_days' => $retention_days > 0 ? $retention_days : prune_webhook_log_task::RETENTION_DAYS_DEFAULT,
]);
$settings_data = $settings_form->get_data();
if ($settings_data) {
    $config->set_and_log('webhook_log_retention_days', (int) $settings_data->webhook_log_retention_days);
    redirect(
        new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', ['tab' => 'webhook']),
        get_string('settings_saved', 'contentmarketplace_goonetwo'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$api = new api();

$allow_accounts_same_email_warning = helper::get_allow_accounts_same_email_warning();

if ($action !== '') {
    require_sesskey();
    $tab_url = new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', ['tab' => 'webhook']);

    try {
        $webhook = webhook::get_webhook($api);

        // The warning blocks the actions that make the webhook active. Disable stays available.
        if ($allow_accounts_same_email_warning === null || $action === 'disable') {
            switch ($action) {
                case 'create':
                    if ($webhook === false) {
                        webhook::create_webhook($api);
                    }
                    break;
                case 'fix':
                    if ($webhook !== false) {
                        webhook::update_webhook($webhook->id, $api);
                    }
                    break;
                case 'disable':
                    if ($webhook !== false) {
                        webhook::disable_webhook($webhook->id, $api);
                    }
                    break;
            }
        }
    } catch (rest_client_response_exception $e) {
        $error = $e->get_go1_message();
        if ($error === '') {
            $error = get_string('webhook_error_http', 'contentmarketplace_goonetwo', $e->get_http_code());
        }
        redirect($tab_url, get_string('webhook_error', 'contentmarketplace_goonetwo', s($error)), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect($tab_url);
}

$webhook = webhook::get_webhook($api);
$webhook_exists = ($webhook !== false);

$data = new \stdClass();
$data->webhook_exists = $webhook_exists;
$data->allow_accounts_same_email_warning = $allow_accounts_same_email_warning;
$data->webhook_problems = [];
if ($webhook_exists) {
    $data->webhook_data = json_encode($webhook, JSON_PRETTY_PRINT);
    $data->webhook_data = preg_replace('/\"secret_key\"\: \"[^"]+\"/', '"secret_key": "***********"', $data->webhook_data);
    $data->webhook_problems = array_map(
        fn(string $problem) => get_string('webhook_problem_' . $problem, 'contentmarketplace_goonetwo'),
        webhook::check_webhook($webhook)
    );
}

if (!$webhook_exists) {
    $button_action = 'create';
} elseif ($data->webhook_problems) {
    $button_action = 'fix';
} else {
    $button_action = 'disable';
}
$button = new single_button(
    new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', ['tab' => 'webhook', 'action' => $button_action]),
    get_string('webhook_' . $button_action, 'contentmarketplace_goonetwo'),
    'post',
    true
);
if ($button_action === 'disable') {
    $button->add_confirm_action(get_string('webhook_disable_confirm', 'contentmarketplace_goonetwo'));
} else {
    $button->disabled = ($allow_accounts_same_email_warning !== null);
}
$data->webhook_button = $OUTPUT->render($button);
$data->webhook_settings_form = $settings_form->render();
$data->webhook_log_url = (new moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/webhook_log.php'))->out(false);

echo $OUTPUT->render_from_template('contentmarketplace_goonetwo/webhook', $data);

