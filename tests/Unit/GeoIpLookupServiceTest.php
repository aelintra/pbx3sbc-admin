<?php

namespace Tests\Unit;

use App\Services\GeoIpLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoIpLookupServiceTest extends TestCase
{
    private GeoIpLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new GeoIpLookupService;
    }

    public function test_success_lookup_normalizes_fields(): void
    {
        Http::fake([
            'ip-api.com/json/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'California',
                'city' => 'Mountain View',
                'lat' => 37.4192,
                'lon' => -122.0574,
                'isp' => 'Google LLC',
                'org' => 'Google Public DNS',
            ], 200),
        ]);

        $result = $this->service->lookup('8.8.8.8');

        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);
        $this->assertSame('Mountain View', $result['city']);
        $this->assertSame('California', $result['region']);
        $this->assertSame('United States', $result['country']);
        $this->assertSame('US', $result['countryCode']);
        $this->assertSame('Google LLC', $result['isp']);
        $this->assertSame('Google Public DNS', $result['org']);
        $this->assertSame(37.4192, $result['lat']);
        $this->assertSame(-122.0574, $result['lon']);
        $this->assertNull($result['message']);

        Http::assertSentCount(1);
    }

    public function test_second_lookup_uses_cache_without_http(): void
    {
        Http::fake([
            'ip-api.com/json/*' => Http::response([
                'status' => 'success',
                'country' => 'United States',
                'countryCode' => 'US',
                'regionName' => 'California',
                'city' => 'Mountain View',
                'lat' => 37.4192,
                'lon' => -122.0574,
                'isp' => 'Google LLC',
                'org' => 'Google Public DNS',
            ], 200),
        ]);

        $first = $this->service->lookup('8.8.8.8');
        $second = $this->service->lookup('8.8.8.8');

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
        $this->assertTrue(Cache::has(GeoIpLookupService::CACHE_KEY_PREFIX.'8.8.8.8'));
    }

    public function test_private_range_fail_is_cached(): void
    {
        Http::fake([
            'ip-api.com/json/*' => Http::response([
                'status' => 'fail',
                'message' => 'private range',
            ], 200),
        ]);

        $result = $this->service->lookup('10.0.0.1');
        $this->assertSame('fail', $result['status']);
        $this->assertSame('private range', $result['message']);
        $this->assertNull($result['lat']);

        $this->service->lookup('10.0.0.1');
        Http::assertSentCount(1);
    }

    public function test_invalid_ip_does_not_call_api(): void
    {
        Http::fake();

        $result = $this->service->lookup('not-an-ip');

        $this->assertSame('fail', $result['status']);
        $this->assertSame('Invalid IP address', $result['message']);
        Http::assertNothingSent();
    }

    public function test_empty_ip_does_not_call_api(): void
    {
        Http::fake();

        $result = $this->service->lookup('  ');

        $this->assertSame('fail', $result['status']);
        $this->assertSame('Invalid IP address', $result['message']);
        Http::assertNothingSent();
    }

    public function test_rate_limit_returns_null_and_is_not_cached(): void
    {
        Http::fake([
            'ip-api.com/json/*' => Http::response('Too Many Requests', 429),
        ]);

        $result = $this->service->lookup('1.2.3.4');

        $this->assertNull($result);
        $this->assertFalse(Cache::has(GeoIpLookupService::CACHE_KEY_PREFIX.'1.2.3.4'));
    }

    public function test_network_error_returns_null_and_is_not_cached(): void
    {
        Http::fake([
            'ip-api.com/json/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->lookup('1.2.3.4');

        $this->assertNull($result);
        $this->assertFalse(Cache::has(GeoIpLookupService::CACHE_KEY_PREFIX.'1.2.3.4'));
    }

    public function test_embed_map_and_osm_urls(): void
    {
        $map = $this->service->embedMapUrl(37.4192, -122.0574);
        $this->assertStringContainsString('openstreetmap.org/export/embed.html', $map);
        $this->assertStringContainsString('marker=37.419200', $map);
        $this->assertStringContainsString('-122.057400', $map);

        $osm = $this->service->openStreetMapUrl(37.4192, -122.0574);
        $this->assertStringContainsString('openstreetmap.org', $osm);
        $this->assertStringContainsString('mlat=37.419200', $osm);
        $this->assertStringContainsString('mlon=-122.057400', $osm);
    }
}
