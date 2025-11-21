<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ParkUpFrontService;
use App\Services\GeocodingService;
use App\Models\Event;
use Carbon\Carbon;
use App\Mail\NewEventImported;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class ImportNextParkUpFrontEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:import-next';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the next 10 ParkUpFront IDs after the last known event';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for new ParkUpFront events...');

        $service = new ParkUpFrontService();
        $geocoder = new GeocodingService();
        $totalImported = 0;
        $totalUpdated = 0;
        $consecutiveEmpty = 0;
        $maxConsecutiveEmpty = 20; // Stop after 20 consecutive IDs with no events
        
        // Get the highest external_id from ParkUpFront events
        $lastKnownId = Event::where('imported_from', 'parkupfront')
            ->whereNotNull('external_id')
            ->max('external_id');
        
        // Start from last known ID + 1, or 9000 if no events imported yet
        $currentId = $lastKnownId ? $lastKnownId + 1 : 9000;
        
        $this->info("Starting from ID: {$currentId}");
        
        while ($consecutiveEmpty < $maxConsecutiveEmpty) {
            $eventData = $service->fetchEvent($currentId);
            
            if (!$eventData) {
                $consecutiveEmpty++;
                $currentId++;
                continue;
            }
            
            // Reset counter when we find an event
            $consecutiveEmpty = 0;
            
            // Get or create import user
            $importUser = User::firstOrCreate(
                ['email' => 'import@parkupfront.com'],
                ['name' => 'ParkUpFront Import', 'password' => bcrypt(uniqid())]
            );
            
            // Parse times in Central Time (Dallas timezone) and convert to UTC
            $startTime = Carbon::parse($eventData['start_time'], 'America/Chicago')->utc();
            $endTime = Carbon::parse($eventData['end_time'], 'America/Chicago')->utc();
            
            // Prepare event attributes
            $address = $eventData['address'] ?? 'TBD';
            $city = $service->getCityName($eventData['city_id']);
            $state = $service->getStateFromCity($eventData['city_id']);
            $zip = '75201'; // Default Dallas zip

            $eventAttributes = [
                'name' => $eventData['name'],
                'description' => $eventData['summary'] ?? null,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'cost' => $eventData['cost'] ?? 0,
                'spots' => $eventData['unlimited_spots'] ? null : $eventData['spots'],
                'unlimited_spots' => $eventData['unlimited_spots'],
                'image' => $eventData['default_image'],
                'imported_from' => 'parkupfront',
                'external_id' => $currentId,
                'user_id' => $importUser->id,
            ];

            // Geocode the address
            if ($address && $address !== 'TBD') {
                $coordinates = $geocoder->geocode($address, $city, $state, $zip);
                if ($coordinates) {
                    $eventAttributes['latitude'] = $coordinates['latitude'];
                    $eventAttributes['longitude'] = $coordinates['longitude'];
                }
                // Respect Nominatim rate limit
                usleep(1100000); // 1.1 seconds
            }
            
            // Check if event already exists
            $existingEvent = Event::where('external_id', $currentId)
                ->where('imported_from', 'parkupfront')
                ->first();
            
            if ($existingEvent) {
                $existingEvent->update($eventAttributes);
                $totalUpdated++;
                $this->info("Updated: {$eventData['name']} (ID: {$currentId})");
            } else {
                $newEvent = Event::create($eventAttributes);
                $totalImported++;
                $this->info("Imported: {$eventData['name']} (ID: {$currentId})");
                
                // Send email notification for new event
                try {
                    Mail::to('eric@ravenfab.com')->send(new NewEventImported($newEvent));
                    $this->info("Email notification sent for: {$eventData['name']}");
                } catch (\Exception $e) {
                    $this->warn("Failed to send email notification: " . $e->getMessage());
                }
            }
            
            $currentId++;
        }
        
        $checkedUpTo = $currentId - 1;
        $this->info("");
        $this->info("Checked up to ID: {$checkedUpTo}");
        
        if ($totalImported > 0 || $totalUpdated > 0) {
            $this->info("Total imported: {$totalImported} new events");
            $this->info("Total updated: {$totalUpdated} existing events");
        } else {
            $this->info("No new events found.");
        }
        
        return Command::SUCCESS;
    }
}