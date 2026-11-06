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

use core_phpunit\testcase;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the install schema.
 */
#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_install_test extends testcase {

    public function test_user_map_userid_is_unique(): void {
        global $DB;

        $user = self::getDataGenerator()->create_user();
        $DB->insert_record('marketplace_goonetwo_user_map', (object) ['userid' => $user->id, 'goone_userid' => 1]);

        $this->expectException(dml_write_exception::class);
        $DB->insert_record('marketplace_goonetwo_user_map', (object) ['userid' => $user->id, 'goone_userid' => 2]);
    }
}
