<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
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
 * @author Michael Dunstan <michael.dunstan@androgogic.com>
 * @package contentmarketplace_goonetwo
 */

namespace contentmarketplace_goonetwo;

defined('MOODLE_INTERNAL') || die();

final class config_session_storage extends config_storage {

    public function get($name) {
        global $SESSION;
        return isset($SESSION->contentmarketplace_goonetwo[$name]) ? $SESSION->contentmarketplace_goonetwo[$name] : null;
    }

    public function set($name, $value) {
        global $SESSION;
        if (empty($SESSION->contentmarketplace_goonetwo) || !is_array($SESSION->contentmarketplace_goonetwo)) {
            $SESSION->contentmarketplace_goonetwo = [];
        }
        $SESSION->contentmarketplace_goonetwo[$name] = $value;
    }

    public function items() {
        global $SESSION;
        return $SESSION->contentmarketplace_goonetwo;
    }

    public function clear() {
        global $SESSION;
        unset($SESSION->contentmarketplace_goonetwo);
    }

    public function exists() {
        global $SESSION;
        return isset($SESSION->contentmarketplace_goonetwo);
    }
}
