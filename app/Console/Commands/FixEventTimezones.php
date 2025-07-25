<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Carbon\Carbon;

class FixEventTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:fix-timezones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix timezone conversion for all ParkUpFront imported events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing timezones for ParkUpFront imported events...');
        
        $events = Event::where('imported_from', 'parkupfront')->get();
        $fixed = 0;
        
        foreach ($events as $event) {
            // Get the current start/end times
            $currentStart = $event->start_time;
            $currentEnd = $event->end_time;
            
            // These are currently stored as if they were UTC, but they're actually Central Time
            // So we need to reinterpret them as Central Time and convert to UTC
            $startTimeString = $currentStart->format('Y-m-d H:i:s');
            $endTimeString = $currentEnd->format('Y-m-d H:i:s');
            
            // Parse as Central Time
            $startTimeInCentral = Carbon::parse($startTimeString, 'America/Chicago');
            $endTimeInCentral = Carbon::parse($endTimeString, 'America/Chicago');
            
            // Convert to UTC for storage
            $startTimeUTC = $startTimeInCentral->copy()->utc();
            $endTimeUTC = $endTimeInCentral->copy()->utc();
            
            // Only update if times are different
            if (!$currentStart->eq($startTimeUTC) || !$currentEnd->eq($endTimeUTC)) {
                $event->update([
                    'start_time' => $startTimeUTC,
                    'end_time' => $endTimeUTC,
                ]);
                
                $this->info("Fixed: {$event->name}");
                $this->line("  Was: {$currentStart} UTC (displayed as " . $currentStart->copy()->setTimezone('America/Chicago')->format('g:i A T') . ")");
                $this->line("  Now: {$startTimeUTC} UTC (displays as " . $startTimeUTC->copy()->setTimezone('America/Chicago')->format('g:i A T') . ")");
                
                $fixed++;
            }
        }
        
        $this->info("\nFixed {$fixed} events out of {$events->count()} total ParkUpFront events.");
        
        return Command::SUCCESS;
    }
}