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

use contentmarketplace_goonetwo\mock_curl;
use contentmarketplace_goonetwo\testing\generator;
use contentmarketplace_goonetwo\webhook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/contentmarketplace/contentmarketplaces/goonetwo/tests/fixtures/mock_curl.php');

/**
 * Test webhook signature verification.
 */
#[CoversClass(webhook::class)]
#[Group('totara_contentmarketplace')]
class contentmarketplace_goonetwo_webhook_test extends \core_phpunit\testcase {

    /**
     * Build a valid signature header for the given body, secret and timestamp.
     *
     * @param string $body
     * @param string $secret
     * @param int $timestamp
     * @return string
     */
    private function make_header(string $body, string $secret, int $timestamp): string {
        $v1 = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        return "t={$timestamp},v1={$v1}";
    }

    public function test_valid_signature_is_accepted(): void {
        $body = '{"event_type":"enrollment.complete"}';
        $secret = 'shhh-secret';
        $header = $this->make_header($body, $secret, time());

        self::assertSame('', webhook::verify_signature($body, $header, $secret));
    }

    public function test_valid_millisecond_timestamp_is_accepted(): void {
        $body = '{"event_type":"enrollment.complete"}';
        $secret = 'shhh-secret';
        // Go1 may send the timestamp in milliseconds rather than seconds.
        $header = $this->make_header($body, $secret, time() * 1000);

        self::assertSame('', webhook::verify_signature($body, $header, $secret));
    }

    public function test_wrong_secret_is_rejected(): void {
        $body = '{"event_type":"enrollment.complete"}';
        $header = $this->make_header($body, 'the-real-secret', time());

        self::assertSame('Failed to verify signature.', webhook::verify_signature($body, $header, 'a-different-secret'));
    }

    public function test_tampered_body_is_rejected(): void {
        $secret = 'shhh-secret';
        $header = $this->make_header('{"lo_id":1}', $secret, time());

        // Signature was generated for a different body.
        self::assertSame('Failed to verify signature.', webhook::verify_signature('{"lo_id":999}', $header, $secret));
    }

    public function test_missing_header_is_rejected(): void {
        self::assertSame('Missing Go1 signature header.', webhook::verify_signature('{}', '', 'shhh-secret'));
    }

    public function test_malformed_header_is_rejected(): void {
        // No "=" segments at all, so neither timestamp nor signature can be extracted.
        self::assertSame('Malformed Go1 signature header.', webhook::verify_signature('{}', 'not-a-signature', 'shhh-secret'));
    }

    public function test_stale_timestamp_is_rejected(): void {
        $body = '{"event_type":"enrollment.complete"}';
        $secret = 'shhh-secret';
        // Correctly signed, but far outside the tolerance window (replay).
        $stale = time() - (webhook::WEBHOOK_TOLERANCE_SECONDS + 60);
        $header = $this->make_header($body, $secret, $stale);

        self::assertSame('Go1 signature timestamp outside the accepted tolerance.', webhook::verify_signature($body, $header, $secret));
    }

    public function test_stale_millisecond_timestamp_is_rejected(): void {
        $body = '{"event_type":"enrollment.complete"}';
        $secret = 'shhh-secret';
        // Correctly signed, but far outside the tolerance window (replay), in milliseconds.
        $stale = (time() - (webhook::WEBHOOK_TOLERANCE_SECONDS + 60)) * 1000;
        $header = $this->make_header($body, $secret, $stale);

        self::assertSame('Go1 signature timestamp outside the accepted tolerance.', webhook::verify_signature($body, $header, $secret));
    }

    public function test_non_numeric_timestamp_is_rejected(): void {
        self::assertSame('Go1 signature timestamp outside the accepted tolerance.', webhook::verify_signature('{}', 't=notanumber,v1=abc', 'shhh-secret'));
    }

    public function test_process_request_with_bad_signature_logs_message_only(): void {
        global $DB;

        $_SERVER['HTTP_GO1_SIGNATURE'] = 't=' . time() . ',v1=not-the-signature';
        try {
            webhook::process_request();
        } finally {
            unset($_SERVER['HTTP_GO1_SIGNATURE']);
        }

        $log = $DB->get_record('marketplace_goonetwo_webhook_log', [], '*', MUST_EXIST);
        self::assertEquals(0, $log->result);
        self::assertSame('Failed to verify signature.', $log->message);
        self::assertNull($log->request);
    }

    public function test_process_payload_logs_malformed_payloads(): void {
        global $DB;

        webhook::process_payload('this is not json');
        webhook::process_payload('{"something": "without an event type"}');

        $logs = $DB->get_records('marketplace_goonetwo_webhook_log');
        self::assertCount(2, $logs);
        foreach ($logs as $log) {
            self::assertStringContainsString('Malformed webhook payload.', $log->message);
        }
    }

    public function test_process_payload_logs_unhandled_event_types(): void {
        global $DB;

        webhook::process_payload('{"event_type": "user.updated"}');

        $log = $DB->get_record('marketplace_goonetwo_webhook_log', []);
        self::assertNotEmpty($log);
        self::assertStringContainsString('Unhandled webhook event type: user.updated', $log->message);
    }

    public function test_generate_webhook_secret_key(): void {
        $key = webhook::generate_webhook_secret_key();
        self::assertSame(20, strlen($key));

        $key = webhook::generate_webhook_secret_key(40);
        self::assertSame(40, strlen($key));

        // Two generated keys are (overwhelmingly likely to be) different.
        self::assertNotSame(webhook::generate_webhook_secret_key(), webhook::generate_webhook_secret_key());
    }

    public function test_get_webhook_secret_key_is_generated_once_and_persisted(): void {
        self::assertFalse(get_config('contentmarketplace_goonetwo', 'webhook_secret_key'));

        $key = webhook::get_webhook_secret_key();
        self::assertNotEmpty($key);
        self::assertSame($key, get_config('contentmarketplace_goonetwo', 'webhook_secret_key'));

        // Subsequent calls return the stored key rather than generating a new one.
        self::assertSame($key, webhook::get_webhook_secret_key());
    }

    public function test_get_webhook_params(): void {
        global $CFG;

        $params = webhook::get_webhook_params();

        self::assertSame(
            $CFG->wwwroot . '/totara/contentmarketplace/contentmarketplaces/goonetwo/payload.php',
            $params->url
        );
        self::assertSame(['enrollment.complete'], $params->event_types);
        self::assertSame(webhook::get_webhook_secret_key(), $params->secret_key);
    }

    /**
     * Build a webhook that matches this site's settings.
     *
     * @return \stdClass
     */
    private function make_matching_webhook(): \stdClass {
        $params = webhook::get_webhook_params();

        $webhook = new \stdClass();
        $webhook->id = '123';
        $webhook->url = $params->url;
        $webhook->secret_key = $params->secret_key;
        $webhook->event_types = $params->event_types;
        $webhook->status = 'active';

        return $webhook;
    }

    public function test_check_webhook_with_matching_webhook(): void {
        self::assertSame([], webhook::check_webhook($this->make_matching_webhook()));
    }

    public function test_check_webhook_with_empty_secret_key(): void {
        $webhook = $this->make_matching_webhook();
        unset_config('webhook_secret_key', 'contentmarketplace_goonetwo');

        self::assertSame(['secret_key'], webhook::check_webhook($webhook));
    }

    public function test_check_webhook_with_mismatched_secret_key(): void {
        $webhook = $this->make_matching_webhook();
        $webhook->secret_key = 'some-other-secret';

        self::assertSame(['secret_key'], webhook::check_webhook($webhook));
    }

    public function test_check_webhook_with_inactive_webhook(): void {
        $webhook = $this->make_matching_webhook();
        $webhook->status = 'inactive';

        self::assertSame(['inactive'], webhook::check_webhook($webhook));
    }

    public function test_get_webhook_returns_only_the_webhook_of_this_site(): void {
        global $CFG;

        $api = generator::instance()->get_mock_api();

        // The GET /webhooks fixture has a webhook for this site and one for a staging site.
        self::assertSame('site-webhook', webhook::get_webhook($api)->id);

        $CFG->wwwroot = 'https://other.example.com';
        self::assertFalse(webhook::get_webhook($api));
    }

    public function test_update_webhook_sets_status_to_active(): void {
        $api = generator::instance()->get_mock_api();
        mock_curl::$requests = [];

        webhook::update_webhook('site-webhook', $api);

        self::assertCount(1, mock_curl::$requests);
        self::assertSame('PATCH', mock_curl::$requests[0]['method']);
        self::assertStringEndsWith('/webhooks/site-webhook', mock_curl::$requests[0]['url']);
        $body = json_decode(mock_curl::$requests[0]['body'], true);
        self::assertSame('active', $body['status']);
        self::assertSame(
            'https://www.example.com/moodle/totara/contentmarketplace/contentmarketplaces/goonetwo/payload.php',
            $body['url']
        );
    }

    public function test_check_webhook_with_missing_event_type(): void {
        $webhook = $this->make_matching_webhook();
        $webhook->event_types = ['enrollment.create'];

        self::assertSame(['event_types'], webhook::check_webhook($webhook));
    }

    public function test_check_webhook_with_additional_event_type(): void {
        $webhook = $this->make_matching_webhook();
        $webhook->event_types = ['enrollment.create', 'enrollment.complete'];

        self::assertSame([], webhook::check_webhook($webhook));
    }

    public function test_check_webhook_with_missing_fields(): void {
        self::assertSame(['inactive', 'secret_key', 'event_types'], webhook::check_webhook(new \stdClass()));
    }

    public function test_deactivate_site_webhook_changes_only_this_site(): void {
        set_config('oauth_client_id', 'client', 'contentmarketplace_goonetwo');
        $api = generator::instance()->get_mock_api();
        mock_curl::$requests = [];

        // The webhook URL of the site in the GET /webhooks fixture.
        self::assertSame(
            'https://www.example.com/moodle/totara/contentmarketplace/contentmarketplaces/goonetwo/payload.php',
            webhook::get_webhook_url()
        );
        self::assertTrue(webhook::deactivate_site_webhook($api));

        $patches = array_values(array_filter(mock_curl::$requests, fn(array $request) => $request['method'] === 'PATCH'));
        self::assertCount(1, $patches);
        self::assertStringEndsWith('/webhooks/site-webhook', $patches[0]['url']);
        self::assertSame(['status' => 'inactive'], json_decode($patches[0]['body'], true));
    }

    public function test_deactivate_site_webhook_without_oauth_config(): void {
        $api = generator::instance()->get_mock_api();
        mock_curl::$requests = [];

        self::assertFalse(webhook::deactivate_site_webhook($api));
        self::assertSame([], mock_curl::$requests);
    }

    public function test_deactivate_site_webhook_continues_on_error(): void {
        global $CFG;

        set_config('oauth_client_id', 'client', 'contentmarketplace_goonetwo');
        $api = generator::instance()->get_mock_api();
        // The site webhook is then other-site-webhook, which has no PATCH fixture, thus the request fails.
        $CFG->wwwroot = 'https://staging.example.com';

        self::assertFalse(webhook::deactivate_site_webhook($api));
        $this->assertDebuggingCalled();
    }

    protected function tearDown(): void {
        mock_curl::$requests = [];
        parent::tearDown();
    }
}
