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

namespace contentmarketplace_goonetwo\rb\display;

use totara_reportbuilder\rb\display\base;

/**
 * Display the completed activities of a webhook log entry, one activity on each line.
 *
 * The value is JSON: a list of ['courseid' => courseid, 'cmid' => cmid] pairs.
 * In HTML, the activity names are links, and the names are shortened to 40 characters at a word boundary.
 * When the activity does not exist, the line shows the course module ID.
 */
class webhook_cm_ids extends base {

    /**
     * Maximum length of an activity name in HTML.
     */
    const NAME_LENGTH = 40;

    /**
     * @param mixed $value
     * @param string $format
     * @param \stdClass $row
     * @param \rb_column $column
     * @param \reportbuilder $report
     *
     * @return string
     */
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        global $DB;

        $pairs = json_decode((string) $value, true);
        if (!is_array($pairs) || empty($pairs)) {
            return '';
        }

        $cmids = [];
        foreach ($pairs as $pair) {
            if (isset($pair['cmid'])) {
                $cmids[] = (int) $pair['cmid'];
            }
        }

        // Activity names, kept for all rows of the report.
        $cache = self::cache($report);
        $cache->activities ??= [];
        $missing = array_diff($cmids, array_keys($cache->activities));
        if ($missing) {
            [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED);
            $sql = "SELECT cm.id, s.name
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                      JOIN {scorm} s ON s.id = cm.instance
                     WHERE cm.id {$insql}";
            $records = $DB->get_records_sql($sql, $params);
            foreach ($missing as $cmid) {
                $cache->activities[$cmid] = $records[$cmid] ?? false;
            }
        }

        $isexport = ($format !== 'html');
        $lines = [];
        foreach ($cmids as $cmid) {
            $activity = $cache->activities[$cmid] ?? false;
            if (!$activity) {
                $lines[] = (string) $cmid;
                continue;
            }

            $name = format_string($activity->name, true, ['context' => \context_module::instance($cmid)]);

            if ($isexport) {
                $lines[] = \core_text::entities_to_utf8($name);
                continue;
            }

            $lines[] = \html_writer::link(
                new \moodle_url('/mod/scorm/view.php', ['id' => $cmid]),
                shorten_text($name, self::NAME_LENGTH)
            );
        }

        return implode($isexport ? "\n" : '<br />', $lines);
    }

    /**
     * @param \rb_column $column
     * @param \rb_column_option $option
     * @param \reportbuilder $report
     *
     * @return bool
     */
    public static function is_graphable(\rb_column $column, \rb_column_option $option, \reportbuilder $report) {
        return false;
    }
}
