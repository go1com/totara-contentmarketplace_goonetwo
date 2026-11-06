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

namespace contentmarketplace_goonetwo\form;

use totara_form\form\element\number;

defined('MOODLE_INTERNAL') || die();

/**
 * Settings of the Webhook tab.
 */
class webhook_settings_form extends \totara_form\form {

    /**
     * Minimum number of days that the webhook log keeps an entry.
     */
    const RETENTION_DAYS_MIN = 1;

    /**
     * Maximum number of days that the webhook log keeps an entry.
     */
    const RETENTION_DAYS_MAX = 365;

    public function get_action_url() {
        return new \moodle_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/config.php', [
            'tab' => 'webhook',
        ]);
    }

    protected function definition() {
        $retention_days = $this->model->add(new number(
            'webhook_log_retention_days',
            get_string('webhook_log_retention_days', 'contentmarketplace_goonetwo')
        ));
        $retention_days->set_attribute('required', true);
        $retention_days->set_attribute('min', self::RETENTION_DAYS_MIN);
        $retention_days->set_attribute('max', self::RETENTION_DAYS_MAX);
        $retention_days->add_help_button('webhook_log_retention_days', 'contentmarketplace_goonetwo');

        $this->model->add_action_buttons(false);
    }

}
