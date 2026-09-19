<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The historical project-location autocomplete (`location.service.js`): the
 * LocationIQ `autocomplete` API, restricted to German cities/towns/villages,
 * German-language results, five hits. Queried server-side so the API key is
 * never exposed to the browser (the historical client shipped it); the
 * request parameters and the result mapping are otherwise unchanged.
 *
 * `services.locationiq.url` exists so a self-hoster or the test stack can
 * point at a compatible endpoint; without a key configured no results are
 * returned (the field then cannot be completed for a fixed location — see
 * docs/deployment/README.md).
 */
class LocationSearch
{
    /**
     * @return array<int, array<string, mixed>>|null null when the provider could not be reached
     */
    public function find(string $term): ?array
    {
        $key = config('services.locationiq.key');
        $url = config('services.locationiq.url');

        if (! is_string($key) || $key === '' || ! is_string($url) || $url === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url, [
                'format' => 'json',
                'q' => $term,
                'limit' => 5,
                'countrycodes' => 'de',
                'accept-language' => 'de',
                'key' => $key,
                'tag' => 'place:city,place:town,place:village',
                'dedupe' => 1,
                'normalizecity' => 1,
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok() || ! is_array($response->json())) {
            return null;
        }

        return collect($response->json())
            ->filter(fn ($location): bool => is_array($location) && isset($location['place_id']))
            ->map(fn (array $location): array => [
                'key' => (string) $location['place_id'],
                'value' => ($location['display_place'] ?? '').', '.($location['display_address'] ?? ''),
                'postcode' => $location['address']['postcode'] ?? null,
                'city' => $location['display_place'] ?? '',
                'countryCode' => $location['address']['country_code'] ?? null,
                'geo' => ['lat' => (string) ($location['lat'] ?? ''), 'lon' => (string) ($location['lon'] ?? '')],
                'osm' => ['id' => (string) ($location['osm_id'] ?? ''), 'type' => $location['osm_type'] ?? ''],
            ])
            ->values()
            ->all();
    }

    /**
     * Reduces a client-supplied `location.data` to the documented shape
     * (docs/domain/entities.md, `Project.location`) — or `[]` when it is not a
     * usable selection. The OpenStreetMap link on the detail page is built
     * from `osm.type`/`osm.id`, so both are constrained to what OSM can
     * actually address.
     *
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $data): array
    {
        if (! is_array($data) || ! is_string($data['city'] ?? null) || trim($data['city']) === '') {
            return [];
        }

        $string = fn (mixed $value): string => is_scalar($value) ? mb_substr((string) $value, 0, 200) : '';

        $clean = [
            'key' => $string($data['key'] ?? ''),
            'postcode' => $string($data['postcode'] ?? ''),
            'city' => $string($data['city']),
            'countryCode' => $string($data['countryCode'] ?? ''),
            'geo' => [
                'lat' => $string($data['geo']['lat'] ?? ''),
                'lon' => $string($data['geo']['lon'] ?? ''),
            ],
        ];

        $osmType = $string($data['osm']['type'] ?? '');
        $osmId = $string($data['osm']['id'] ?? '');

        if (in_array($osmType, ['node', 'way', 'relation'], true) && ctype_digit($osmId)) {
            $clean['osm'] = ['id' => $osmId, 'type' => $osmType];
        }

        return $clean;
    }
}
