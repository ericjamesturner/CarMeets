<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Services\ParkUpFrontService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportParkUpFrontEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:import-parkupfront 
                            {--start-id=9000 : Starting event ID to scan}
                            {--end-id=10000 : Ending event ID to scan}
                            {--skip-past : Skip events that have already ended}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import events from ParkUpFront GraphQL API';

    private ParkUpFrontService $service;
    private User $importUser;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->service = new ParkUpFrontService();
        
        // Get or create a system user for imported events
        $this->importUser = User::firstOrCreate(
            ['email' => 'import@carmeets.system'],
            [
                'name' => 'ParkUpFront Import',
                'password' => bcrypt(Str::random(32)),
            ]
        );

        $startId = (int) $this->option('start-id');
        $endId = (int) $this->option('end-id');
        $skipPast = $this->option('skip-past');

        $this->info("Starting import from ID {$startId} to {$endId}");

        $events = $this->service->scanForEvents($startId, $endId);
        
        $this->info("Found " . count($events) . " events from ParkUpFront");

        $imported = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($events as $eventData) {
            if (!$eventData['active']) {
                $skipped++;
                $this->line("Skipping inactive event: {$eventData['name']}");
                continue;
            }

            // Parse times in Central Time (Dallas timezone)
            $startTime = Carbon::parse($eventData['start_time'], 'America/Chicago');
            $endTime = Carbon::parse($eventData['end_time'], 'America/Chicago');

            if ($skipPast && $endTime->isPast()) {
                $skipped++;
                $this->line("Skipping past event: {$eventData['name']}");
                continue;
            }

            // Check if event already exists
            $existingEvent = Event::where('imported_from', 'parkupfront')
                ->where('external_id', $eventData['venue_event_id'])
                ->first();

            $eventAttributes = [
                'name' => $eventData['name'],
                'description' => $eventData['summary'] ?? null,
                'address' => $eventData['address'] ?? 'TBD',
                'city' => $this->service->getCityName($eventData['city_id']),
                'state' => $this->service->getStateFromCity($eventData['city_id']),
                'zip' => '75201', // Default Dallas zip since API doesn't provide
                'start_time' => $startTime,
                'end_time' => $endTime,
                'cost' => $eventData['cost'] ?? 0,
                'spots' => $eventData['unlimited_spots'] ? null : $eventData['spots'],
                'unlimited_spots' => $eventData['unlimited_spots'],
                'image' => $eventData['default_image'],
                'imported_from' => 'parkupfront',
                'external_id' => $eventData['venue_event_id'],
                'user_id' => $this->importUser->id,
            ];

            if ($existingEvent) {
                $existingEvent->update($eventAttributes);
                $updated++;
                $this->info("Updated event: {$eventData['name']}");
            } else {
                Event::create($eventAttributes);
                $imported++;
                $this->info("Imported event: {$eventData['name']}");
            }
        }

        $this->newLine();
        $this->info("Import complete!");
        $this->info("Imported: {$imported} new events");
        $this->info("Updated: {$updated} existing events");
        $this->info("Skipped: {$skipped} events");

        return Command::SUCCESS;
    }
}
