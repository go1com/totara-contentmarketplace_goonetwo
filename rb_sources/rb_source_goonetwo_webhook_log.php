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
 * Report source for the Go1 webhook log.
 */
class rb_source_goonetwo_webhook_log extends rb_base_source {

    /**
     * @param mixed $groupid
     * @param rb_global_restriction_set|null $globalrestrictionset
     */
    public function __construct($groupid, ?rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        $this->globalrestrictionset = $globalrestrictionset;

        $this->usedcomponents[] = 'contentmarketplace_goonetwo';
        $this->base = '{marketplace_goonetwo_webhook_log}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = [];
        $this->paramoptions = [];
        $this->defaultcolumns = [
            ['type' => 'webhook_log', 'value' => 'timecreated'],
            ['type' => 'webhook_log', 'value' => 'result'],
            ['type' => 'webhook_log', 'value' => 'message'],
            ['type' => 'webhook_log', 'value' => 'user'],
            ['type' => 'webhook_log', 'value' => 'cm_ids'],
        ];
        $this->defaultfilters = [
            ['type' => 'webhook_log', 'value' => 'result', 'advanced' => 0],
            ['type' => 'webhook_log', 'value' => 'timecreated', 'advanced' => 0],
        ];
        $this->requiredcolumns = [];
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_goonetwo_webhook_log');
        $this->sourcesummary = get_string('sourcesummary', 'rb_source_goonetwo_webhook_log');
        $this->sourcelabel = get_string('sourcelabel', 'rb_source_goonetwo_webhook_log');

        parent::__construct();
    }

    /**
     * @return bool
     */
    public function global_restrictions_supported() {
        return false;
    }

    /**
     * @return rb_join[]
     */
    protected function define_joinlist() {
        $joinlist = [];
        $this->add_core_user_tables($joinlist, 'base', 'user_id');
        return $joinlist;
    }

    /**
     * @return rb_column_option[]
     */
    protected function define_columnoptions() {
        global $DB;

        $usernamefields = totara_get_all_user_name_fields_join('auser');

        return [
            new rb_column_option(
                'webhook_log',
                'id',
                get_string('id', 'rb_source_goonetwo_webhook_log'),
                'base.id',
                ['displayfunc' => 'integer', 'dbdatatype' => 'integer']
            ),
            new rb_column_option(
                'webhook_log',
                'timecreated',
                get_string('timecreated', 'rb_source_goonetwo_webhook_log'),
                'base.timecreated',
                ['displayfunc' => 'nice_datetime', 'dbdatatype' => 'timestamp']
            ),
            new rb_column_option(
                'webhook_log',
                'result',
                get_string('result', 'rb_source_goonetwo_webhook_log'),
                'base.result',
                ['displayfunc' => 'webhook_result', 'dbdatatype' => 'integer']
            ),
            new rb_column_option(
                'webhook_log',
                'message',
                get_string('message', 'rb_source_goonetwo_webhook_log'),
                'base.message',
                ['displayfunc' => 'plaintext', 'dbdatatype' => 'text', 'outputformat' => 'text', 'nosort' => true]
            ),
            new rb_column_option(
                'webhook_log',
                'request',
                get_string('request', 'rb_source_goonetwo_webhook_log'),
                'base.request',
                ['displayfunc' => 'plaintext', 'dbdatatype' => 'text', 'outputformat' => 'text', 'nosort' => true]
            ),
            new rb_column_option(
                'webhook_log',
                'enrollment_id',
                get_string('enrollment_id', 'rb_source_goonetwo_webhook_log'),
                'base.enrollment_id',
                ['displayfunc' => 'integer', 'dbdatatype' => 'integer']
            ),
            new rb_column_option(
                'webhook_log',
                'go1_userid',
                get_string('go1_userid', 'rb_source_goonetwo_webhook_log'),
                'base.go1_userid',
                ['displayfunc' => 'integer', 'dbdatatype' => 'integer']
            ),
            new rb_column_option(
                'webhook_log',
                'go1_username',
                get_string('go1_username', 'rb_source_goonetwo_webhook_log'),
                'base.go1_username',
                ['displayfunc' => 'plaintext', 'dbdatatype' => 'char', 'outputformat' => 'text']
            ),
            new rb_column_option(
                'webhook_log',
                'go1_email',
                get_string('go1_email', 'rb_source_goonetwo_webhook_log'),
                'base.go1_email',
                ['displayfunc' => 'plaintext', 'dbdatatype' => 'char', 'outputformat' => 'text']
            ),
            new rb_column_option(
                'webhook_log',
                'user',
                get_string('user', 'rb_source_goonetwo_webhook_log'),
                $DB->sql_concat_join("' '", $usernamefields),
                [
                    'joins' => 'auser',
                    'displayfunc' => 'user_link',
                    'extrafields' => array_merge(['id' => 'auser.id', 'deleted' => 'auser.deleted'], $usernamefields),
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                ]
            ),
            new rb_column_option(
                'webhook_log',
                'cm_ids',
                get_string('cm_ids', 'rb_source_goonetwo_webhook_log'),
                'base.cm_ids',
                ['displayfunc' => 'webhook_cm_ids', 'dbdatatype' => 'text', 'nosort' => true]
            ),
        ];
    }

    /**
     * @return rb_filter_option[]
     */
    protected function define_filteroptions() {
        return [
            new rb_filter_option(
                'webhook_log',
                'timecreated',
                get_string('timecreated', 'rb_source_goonetwo_webhook_log'),
                'date',
                ['includetime' => true]
            ),
            new rb_filter_option(
                'webhook_log',
                'result',
                get_string('result', 'rb_source_goonetwo_webhook_log'),
                'select',
                [
                    'selectchoices' => [
                        1 => get_string('success', 'rb_source_goonetwo_webhook_log'),
                        0 => get_string('error', 'rb_source_goonetwo_webhook_log'),
                    ],
                    'simplemode' => true,
                ]
            ),
            new rb_filter_option(
                'webhook_log',
                'enrollment_id',
                get_string('enrollment_id', 'rb_source_goonetwo_webhook_log'),
                'number'
            ),
            new rb_filter_option(
                'webhook_log',
                'go1_userid',
                get_string('go1_userid', 'rb_source_goonetwo_webhook_log'),
                'number'
            ),
            new rb_filter_option(
                'webhook_log',
                'go1_username',
                get_string('go1_username', 'rb_source_goonetwo_webhook_log'),
                'text'
            ),
            new rb_filter_option(
                'webhook_log',
                'go1_email',
                get_string('go1_email', 'rb_source_goonetwo_webhook_log'),
                'text'
            ),
        ];
    }

    /**
     * Returns expected result for column_test.
     *
     * @param rb_column_option $columnoption
     * @return int
     */
    public function phpunit_column_test_expected_count($columnoption) {
        if (!PHPUNIT_TEST) {
            throw new coding_exception('phpunit_column_test_expected_count() cannot be used outside of unit tests');
        }
        return 0;
    }
}
