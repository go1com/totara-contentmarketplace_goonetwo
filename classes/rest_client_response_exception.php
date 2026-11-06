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

namespace contentmarketplace_goonetwo;

defined('MOODLE_INTERNAL') || die();

/**
 * Go1 API response with an unexpected HTTP status.
 *
 * The exception message is for logs. get_go1_message() gives the error text from Go1, which can be shown to an admin.
 */
class rest_client_response_exception extends \Exception {

    /**
     * @param string $message Technical message for logs
     * @param int $http_code HTTP status of the response
     * @param string $go1_message Text of the "message" field of the response, empty when there is none
     */
    public function __construct(string $message, private int $http_code, private string $go1_message) {
        parent::__construct($message);
    }

    /**
     * @return int
     */
    public function get_http_code(): int {
        return $this->http_code;
    }

    /**
     * @return string Text of the "message" field of the Go1 response, empty when there is none
     */
    public function get_go1_message(): string {
        return $this->go1_message;
    }
}
