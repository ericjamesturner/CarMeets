<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeEvents extends Command
{
    protected $signature = 'app:geocode-events {--force : Geocode all events, even those with existing coordinates}';

    protected $description = 'Geocode event addresses to populate latitude/longitude';

    public function handle()
    {
        $query = Event::query();

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('latitude')
                  ->orWhereNull('longitude');
            });
        }

        $events = $query->whereNotNull('address')
                        ->where('address', '!=', '')
                        ->where('start_time', '>=', now())
                        ->get();

        if ($events->isEmpty()) {
            $this->info('No events to geocode.');
            return 0;
        }

        $this->info("Found {$events->count()} events to geocode.");
        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($events as $event) {
            $coordinates = $this->geocode($event);

            if ($coordinates) {
                $event->update([
                    'latitude' => $coordinates['lat'],
                    'longitude' => $coordinates['lng'],
                ]);
                $success++;
            } else {
                $failed++;
                $this->newLine();
                $this->warn("  Failed to geocode: {$event->name}");
            }

            $bar->advance();

            // Nominatim requires 1 second delay between requests
            sleep(1);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Geocoding complete: {$success} successful, {$failed} failed.");

        return 0;
    }

    private function geocode(Event $event): ?array
    {
        // Build address string
        $addressParts = [];

        // Clean up the address - take first line if multiline
        $address = $event->address;
        if (str_contains($address, "\n")) {
            $lines = explode("\n", $address);
            $address = $lines[0];
        }

        $addressParts[] = $address;

        if ($event->city && $event->city !== 'Unknown') {
            $addressParts[] = $event->city;
        }

        if ($event->state) {
            $addressParts[] = $event->state;
        }

        if ($event->zip) {
            $addressParts[] = $event->zip;
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
                    'lat' => $result['lat'],
                    'lng' => $result['lon'],
                ];
            }

            // Try with just the first part of address if full address fails
            if ($event->city && $event->city !== 'Unknown') {
                $response = Http::withHeaders([
                    'User-Agent' => 'CarMeets/1.0 (contact@carmeets.com)',
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address . ', ' . $event->city . ', TX',
                    'format' => 'json',
                    'limit' => 1,
                ]);

                if ($response->successful() && !empty($response->json())) {
                    $result = $response->json()[0];
                    return [
                        'lat' => $result['lat'],
                        'lng' => $result['lon'],
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->error("Error geocoding {$event->name}: {$e->getMessage()}");
        }

        return null;
    }
}
