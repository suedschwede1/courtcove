<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RestrictApiByIp;
use Tests\TestCase;

final class ApiIpAllowlistTest extends TestCase
{
    private function fromIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_api_paths_are_refused_for_addresses_not_on_the_list(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10', '203.0.113.11']]);

        $this->fromIp('198.51.100.7')->getJson('/api/v1/anything')->assertForbidden();
        $this->fromIp('198.51.100.7')->get('/api')->assertForbidden();
        $this->fromIp('198.51.100.7')->postJson('/api/v1/anything')->assertForbidden();
    }

    public function test_api_paths_pass_through_for_listed_addresses(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10', '203.0.113.11']]);

        $this->assertNotSame(403, $this->fromIp('203.0.113.10')->getJson('/api/v1/anything')->getStatusCode());
        $this->assertNotSame(403, $this->fromIp('203.0.113.11')->getJson('/api/v1/anything')->getStatusCode());
    }

    public function test_an_empty_list_refuses_everyone_on_api_paths(): void
    {
        config(['booking.api.allowed_ips' => []]);

        $this->fromIp('203.0.113.10')->getJson('/api/v1/anything')->assertForbidden();
    }

    public function test_forwarded_headers_cannot_spoof_an_allowed_address(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10']]);

        $this->fromIp('198.51.100.7')
            ->withHeaders(['X-Forwarded-For' => '203.0.113.10', 'X-Real-IP' => '203.0.113.10'])
            ->getJson('/api/v1/anything')
            ->assertForbidden();
    }

    public function test_everything_outside_api_stays_open_for_any_address(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10']]);

        $this->fromIp('198.51.100.7')->get('/login')->assertOk();
        $this->assertNotSame(403, $this->fromIp('198.51.100.7')->get('/calendar')->getStatusCode());
        // look-alike prefixes are not the API
        $this->assertNotSame(403, $this->fromIp('198.51.100.7')->get('/apiary')->getStatusCode());
        $this->assertNotSame(403, $this->fromIp('198.51.100.7')->get('/calendar/api')->getStatusCode());
    }

    public function test_webhooks_are_not_affected(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10']]);

        // Refused by the signature check (403 body of the webhook controller), not by the allowlist: the response
        // must not carry the allowlist marker header.
        $response = $this->fromIp('198.51.100.7')->post('/webhooks/whatsapp/status');
        $this->assertNull($response->headers->get('X-Api-Restricted'));
    }

    public function test_the_refusal_is_marked_and_names_no_address(): void
    {
        config(['booking.api.allowed_ips' => ['203.0.113.10']]);

        $response = $this->fromIp('198.51.100.7')->getJson('/api/v1/anything');
        $response->assertForbidden();
        $this->assertSame('1', $response->headers->get('X-Api-Restricted'));
        $this->assertStringNotContainsString('203.0.113.10', $response->getContent());
        $this->assertStringNotContainsString('198.51.100.7', $response->getContent());
    }

    public function test_list_is_read_from_a_comma_separated_env_value(): void
    {
        $this->assertSame(['203.0.113.10', '203.0.113.11'], RestrictApiByIp::parse(' 203.0.113.10 , ,203.0.113.11,203.0.113.10 '));
        $this->assertSame([], RestrictApiByIp::parse(null));
    }
}
