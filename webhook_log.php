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
 * Go1 webhook log report.
 *
 * @package    contentmarketplace_goonetwo
 * @copyright  2026 onwards Androgogic Pty Ltd
 * @author     Kirill Astashov <kirill.astashov@androgogic.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

$sid = optional_param('sid', 0, PARAM_INT);
$debug = optional_param('debug', 0, PARAM_INT);
$format = optional_param('format', '', PARAM_TEXT);

require_login();
$context = context_system::instance();
require_capability('totara/contentmarketplace:config', $context);

$PAGE->set_context($context);
$PAGE->set_url('/totara/contentmarketplace/contentmarketplaces/goonetwo/webhook_log.php');
$PAGE->set_pagelayout('noblocks');

$config = (new rb_config())->set_sid($sid);
$report = reportbuilder::create_embedded('goonetwo_webhook_log', $config);

if ($format !== '') {
    $report->export_data($format);
    die;
}

$PAGE->set_title($report->fullname);
$PAGE->set_heading($report->fullname);

/** @var totara_reportbuilder_renderer $output */
$output = $PAGE->get_renderer('totara_reportbuilder');
echo $OUTPUT->header();
$report->include_js();
[$reporthtml, $debughtml] = $output->report_html($report, $debug);
echo $debughtml;
echo $OUTPUT->heading(get_string('webhook_logs', 'contentmarketplace_goonetwo'));
echo $output->print_description($report->description, $report->_id);
$report->display_saved_search_options();
$report->display_search();
$report->display_sidebar_search();
echo $output->result_count_heading($report);
echo $reporthtml;
$output->export_select($report, $sid);
echo $OUTPUT->footer();
