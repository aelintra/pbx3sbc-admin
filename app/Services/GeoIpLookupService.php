<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * On-demand IP geolocation for SBC admin View pages (door-knock, etc.).
 *
 * Uses free ip-api.com JSON (HTTP-only, 45 req/min, non-commercial). Server-side
 * HTTP avoids mixed-content under HTTPS admin. Swap provider later if fleet needs
 * commercial/HTTPS; keep this cache + result shape stable.
 */
class GeoIpLookupService
{
    public const CACHE_TTL_SECONDS = 604800; // 7 days

    public const CACHE_KEY_PREFIX = 'geoip:v1:';

    /**
     * @return array{
     *     status: string,
     *     city: ?string,
     *     region: ?string,
     *     country: ?string,
     *     countryCode: ?string,
     *     isp: ?string,
     *     org: ?string,
     *     lat: ?float,
     *     lon: ?float,
     *     message: ?string,
     * }|null
     *   null = soft failure (network/429/invalid); View stays usable without geo.
     */
    public function lookup(string $ip): ?array
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return [
                'status' => 'fail',
                'city' => null,
                'region' => null,
                'country' => null,
                'countryCode' => null,
                'isp' => null,
                'org' => null,
                'lat' => null,
                'lon' => null,
                'message' => 'Invalid IP address',
            ];
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$ip;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetchFromApi($ip);
        // Cache success and hard-fail (private/reserved); do not cache soft null (429/network).
        if ($result !== null) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);
        }

        return $result;
    }

    /**
     * Square OSM embed URL pinned at lat/lon.
     * (staticmap.openstreetmap.de is dead DNS; PHP GD not available on lab SBC for tile compositing.)
     */
    public function embedMapUrl(float $lat, float $lon, float $padDegrees = 2.5): string
    {
        $minLon = $lon - $padDegrees;
        $maxLon = $lon + $padDegrees;
        $minLat = $lat - $padDegrees;
        $maxLat = $lat + $padDegrees;

        return sprintf(
            'https://www.openstreetmap.org/export/embed.html?bbox=%.6f%%2C%.6f%%2C%.6f%%2C%.6f&layer=mapnik&marker=%.6f%%2C%.6f',
            $minLon,
            $minLat,
            $maxLon,
            $maxLat,
            $lat,
            $lon
        );
    }

    /**
     * Outbound link to OpenStreetMap centered on the point.
     */
    public function openStreetMapUrl(float $lat, float $lon, int $zoom = 5): string
    {
        return sprintf(
            'https://www.openstreetmap.org/?mlat=%.6f&mlon=%.6f#map=%d/%.6f/%.6f',
            $lat,
            $lon,
            $zoom,
            $lat,
            $lon
        );
    }

    /**
     * @return array{
     *     status: string,
     *     city: ?string,
     *     region: ?string,
     *     country: ?string,
     *     countryCode: ?string,
     *     isp: ?string,
     *     org: ?string,
     *     lat: ?float,
     *     lon: ?float,
     *     message: ?string,
     * }|null
     */
    private function fetchFromApi(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)
                ->acceptJson()
                ->get('http://ip-api.com/json/'.$ip, [
                    'fields' => 'status,message,country,countryCode,regionName,city,lat,lon,isp,org',
                ]);

            if ($response->status() === 429) {
                Log::warning('GeoIpLookupService: ip-api rate limited', ['ip' => $ip]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('GeoIpLookupService: ip-api HTTP error', [
                    'ip' => $ip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data)) {
                return null;
            }

            $status = (string) ($data['status'] ?? 'fail');

            return [
                'status' => $status,
                'city' => isset($data['city']) ? (string) $data['city'] : null,
                'region' => isset($data['regionName']) ? (string) $data['regionName'] : null,
                'country' => isset($data['country']) ? (string) $data['country'] : null,
                'countryCode' => isset($data['countryCode']) ? (string) $data['countryCode'] : null,
                'isp' => isset($data['isp']) ? (string) $data['isp'] : null,
                'org' => isset($data['org']) ? (string) $data['org'] : null,
                'lat' => isset($data['lat']) && is_numeric($data['lat']) ? (float) $data['lat'] : null,
                'lon' => isset($data['lon']) && is_numeric($data['lon']) ? (float) $data['lon'] : null,
                'message' => isset($data['message']) ? (string) $data['message'] : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('GeoIpLookupService: lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
