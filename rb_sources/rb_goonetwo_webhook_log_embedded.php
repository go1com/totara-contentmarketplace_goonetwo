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
 * Embedded report of the Go1 webhook log.
 */
class rb_goonetwo_webhook_log_embedded extends rb_base_embedded {

    public function __construct() {
        $this->url = '/totara/contentmarketplace/contentmarketplaces/goonetwo/webhook_log.php';
        $this->source = 'goonetwo_webhook_log';
        $this->shortname = 'goonetwo_webhook_log';
        $this->fullname = get_string('webhook_log', 'contentmarketplace_goonetwo');
        $this->columns = [
            ['type' => 'webhook_log', 'value' => 'id', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'timecreated', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'result', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'message', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'enrollment_id', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'go1_userid', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'go1_username', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'go1_email', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'user', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'cm_ids', 'heading' => null],
            ['type' => 'webhook_log', 'value' => 'request', 'heading' => null],
        ];
        $this->filters = [
            ['type' => 'webhook_log', 'value' => 'go1_username', 'advanced' => 0],
            ['type' => 'webhook_log', 'value' => 'go1_email', 'advanced' => 0],
            ['type' => 'webhook_log', 'value' => 'timecreated', 'advanced' => 0],
            ['type' => 'webhook_log', 'value' => 'result', 'advanced' => 0],
        ];
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;
        $this->defaultsortcolumn = 'webhook_log_timecreated';
        $this->defaultsortorder = SORT_DESC;

        parent::__construct();
    }

    /**
     * Only users who can configure the content marketplaces can see the webhook log.
     *
     * @param int $reportfor
     * @param reportbuilder $report
     *
     * @return bool
     */
    public function is_capable($reportfor, $report) {
        return has_capability('totara/contentmarketplace:config', context_system::instance(), $reportfor);
    }
}
