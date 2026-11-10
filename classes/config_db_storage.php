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

final class config_db_storage extends config_storage {

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name) {
        return get_config('contentmarketplace_goonetwo', $name);
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value) {
        set_config($name, $value, 'contentmarketplace_goonetwo');
    }

    /**
     * Set a value that an admin changed, and log the change in config_log, the same as an admin setting.
     * Nothing is written when the value does not change.
     *
     * @param string $name
     * @param mixed $value
     */
    public function set_and_log(string $name, $value): void {
        $oldvalue = get_config('contentmarketplace_goonetwo', $name);
        $oldvalue = ($oldvalue === false) ? null : $oldvalue;
        $value = is_null($value) ? null : (string) $value;
        if ($oldvalue === $value) {
            return;
        }

        $this->set($name, $value);
        add_to_config_log($name, $oldvalue, $value, 'contentmarketplace_goonetwo');
    }

    /**
     * @param config_session_storage $config
     */
    public function copy($config) {
        foreach ($config->items() as $name => $value) {
            $this->set($name, $value);
        }
    }
}
