<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function geocode(string $address, ?string $city = null, ?string $state = null, ?string $zip = null): ?array
    {
        // Build address string
        $addressParts = [];

        // Clean up the address - take first line if multiline
        if (str_contains($address, "\n")) {
            $lines = explode("\n", $address);
            $address = $lines[0];
        }

        $addressParts[] = $address;

        if ($city && $city !== 'Unknown') {
            $addressParts[] = $city;
        }

        if ($state) {
            $addressParts[] = $state;
        }

        if ($zip) {
            $addressParts[] = $zip;
        }

        $fullAddress = implode(', ', $addressParts);

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CarMeets/1.0 (contact@carmeets.com)',
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => $fullAddress,
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($response->successful() && !empty($response->json())) {
                $result = $response->json()[0];
                return [
                    'latitude' => $result['lat'],
                    'longitude' => $result['lon'],
                ];
            }

            // Try with just address and state (no city) - helps when city is wrong
            if ($state) {
                $response = Http::withHeaders([
                    'User-Agent' => 'CarMeets/1.0 (contact@carmeets.com)',
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address . ', ' . $state,
                    'format' => 'json',
                    'limit' => 1,
                ]);

                if ($response->successful() && !empty($response->json())) {
                    $result = $response->json()[0];
                    return [
                        'latitude' => $result['lat'],
                        'longitude' => $result['lon'],
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning("Geocoding failed for address: {$fullAddress}", [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
