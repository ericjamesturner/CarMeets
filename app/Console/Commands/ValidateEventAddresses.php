<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ValidateEventAddresses extends Command
{
    protected $signature = 'events:validate-addresses
                            {--fix : Actually fix the addresses, not just report}
                            {--future-only : Only process future events}';

    protected $description = 'Validate and fix event addresses by geocoding and correcting city names';

    public function handle()
    {
        $query = Event::where(function ($q) {
            $q->whereNull('latitude')
              ->orWhereNull('longitude');
        });

        if ($this->option('future-only')) {
            $query->where('start_time', '>=', now());
        }

        $events = $query->whereNotNull('address')
            ->where('address', '!=', '')
            ->where('address', '!=', 'TBD')
            ->get();

        if ($events->isEmpty()) {
            $this->info('No events need address validation.');
            return 0;
        }

        $this->info("Found {$events->count()} events to validate.");
        $fix = $this->option('fix');

        if (!$fix) {
            $this->warn('Running in dry-run mode. Use --fix to actually update records.');
        }

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        $fixed = 0;
        $cityFixed = 0;
        $failed = 0;

        foreach ($events as $event) {
            $result = $this->validateAndGeocode($event);

            if ($result) {
                $updates = [
                    'latitude' => $result['latitude'],
                    'longitude' => $result['longitude'],
                ];

                // Check if city needs correction
                $cityChanged = false;
                if ($result['city'] && $result['city'] !== $event->city) {
                    $updates['city'] = $result['city'];
                    $cityChanged = true;
                }

                // Update address with formatted version
                $addressChanged = false;
                if ($result['formatted_address'] && $result['formatted_address'] !== $event->address) {
                    $updates['address'] = $result['formatted_address'];
                    $addressChanged = true;
                }

                // Update zip if we got one
                if ($result['zip'] && $result['zip'] !== $event->zip) {
                    $updates['zip'] = $result['zip'];
                }

                if ($fix) {
                    $event->update($updates);
                }

                $fixed++;
                if ($cityChanged || $addressChanged) {
                    $this->newLine();
                    if ($addressChanged) {
                        $this->info("  {$event->name}:");
                        $this->line("    Address: {$event->address}");
                        $this->line("         -> {$result['formatted_address']}");
                    }
                    if ($cityChanged) {
                        $cityFixed++;
                        if (!$addressChanged) {
                            $this->info("  {$event->name}: City '{$event->city}' -> '{$result['city']}'");
                        }
                    }
                }
            } else {
                $failed++;
                $this->newLine();
                $this->warn("  Failed: {$event->name} - Address: {$event->address}");
            }

            $bar->advance();

            // Respect Nominatim rate limit (1 request per second)
            // We might make 2 requests per event (geocode + reverse), so 2 seconds
            sleep(2);
        }

        $bar->finish();
        $this->newLine(2);

        $action = $fix ? 'Fixed' : 'Would fix';
        $this->info("Validation complete:");
        $this->info("  {$action}: {$fixed} addresses");
        $this->info("  City corrections: {$cityFixed}");
        $this->info("  Failed: {$failed}");

        return 0;
    }

    private function validateAndGeocode(Event $event): ?array
    {
        // Clean up address - take first line if multiline
        $address = $event->address;
        if (str_contains($address, "\n")) {
            $lines = explode("\n", $address);
            $address = trim($lines[0]);
        }

        // Skip vague addresses
        if (strlen($address) < 5 || preg_match('/^(tbd|tba|coming soon)/i', $address)) {
            return null;
        }

        // Try multiple geocoding strategies
        $strategies = [
            // Strategy 1: Full address with city, state, zip
            "{$address}, {$event->city}, {$event->state} {$event->zip}",
            // Strategy 2: Address with state only (handles wrong city)
            "{$address}, {$event->state}",
            // Strategy 3: Just the address (let Nominatim figure it out)
            $address,
        ];

        foreach ($strategies as $query) {
            $result = $this->geocodeQuery($query);
            if ($result) {
                // Get the actual city and formatted address from the coordinates
                $locationInfo = $this->reverseGeocode($result['lat'], $result['lon']);

                return [
                    'latitude' => $result['lat'],
                    'longitude' => $result['lon'],
                    'city' => $locationInfo['city'] ?? $event->city,
                    'formatted_address' => $locationInfo['formatted_address'] ?? null,
                    'zip' => $locationInfo['zip'] ?? null,
                ];
            }
            // Small delay between strategy attempts
            usleep(500000);
        }

        return null;
    }

    private function geocodeQuery(string $query): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CarMeets/1.0 (contact@carmeets.com)',
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'us',
            ]);

            if ($response->successful() && !empty($response->json())) {
                $result = $response->json()[0];
                return [
                    'lat' => $result['lat'],
                    'lon' => $result['lon'],
                ];
            }
        } catch (\Exception $e) {
            // Silently fail, we'll try next strategy
        }

        return null;
    }

    private function reverseGeocode(string $lat, string $lon): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CarMeets/1.0 (contact@carmeets.com)',
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $lat,
                'lon' => $lon,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $address = $data['address'] ?? [];

                // Try to get city from various fields Nominatim uses
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['municipality']
                    ?? $address['village']
                    ?? $address['suburb']
                    ?? null;

                // Build a clean formatted address
                $formattedParts = [];

                // Street address
                if (!empty($address['house_number']) && !empty($address['road'])) {
                    $formattedParts[] = $address['house_number'] . ' ' . $address['road'];
                } elseif (!empty($address['road'])) {
                    $formattedParts[] = $address['road'];
                }

                $formattedAddress = !empty($formattedParts) ? implode(', ', $formattedParts) : null;

                return [
                    'city' => $city,
                    'formatted_address' => $formattedAddress,
                    'zip' => $address['postcode'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }
}
