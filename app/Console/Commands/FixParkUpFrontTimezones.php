<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixParkUpFrontTimezones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:fix-parkupfront-timezones 
                            {--dry-run : Preview changes without applying them}
                            {--force : Skip confirmation prompt}
                            {--event-id= : Fix only a specific event by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix timezone conversion for ParkUpFront imported events (one-time fix for production)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificEventId = $this->option('event-id');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }
        
        $this->info('Fixing timezones for ParkUpFront imported events...');
        $this->info('This will convert times from UTC (incorrectly stored) to Central Time and back to UTC correctly.');
        $this->newLine();
        
        // Get events to fix
        $query = Event::where('imported_from', 'parkupfront');
        
        if ($specificEventId) {
            $query->where('id', $specificEventId);
        }
        
        $events = $query->get();
        
        if ($events->isEmpty()) {
            $this->warn('No ParkUpFront events found to fix.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$events->count()} ParkUpFront events to check.");
        
        if (!$dryRun && !$specificEventId && !$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with fixing these events?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }
        
        $fixed = 0;
        $skipped = 0;
        $errors = 0;
        
        // Use a transaction for safety
        DB::beginTransaction();
        
        try {
            foreach ($events as $event) {
                try {
                    // Get the current times
                    $currentStart = $event->start_time;
                    $currentEnd = $event->end_time;
                    
                    // Check if this looks like it needs fixing
                    // If the hour is between 0-5 UTC, it's likely a morning event that was stored wrong
                    $startHour = (int) $currentStart->format('H');
                    $needsFix = false;
                    
                    // Most car events are between 6 AM and 11 PM local time
                    // In UTC, Dallas morning events (6-11 AM CDT) would be 11 AM - 4 PM UTC
                    // If we see times like 6-11 AM UTC, they're probably wrong
                    if ($startHour >= 6 && $startHour <= 11) {
                        $needsFix = true;
                    }
                    
                    if (!$needsFix && !$specificEventId) {
                        $this->line("⏭️  Skipped: {$event->name} - appears to be correct");
                        $this->line("   Currently: " . $currentStart->copy()->setTimezone('America/Chicago')->format('g:i A T'));
                        $skipped++;
                        continue;
                    }
                    
                    // These times are stored as if they were UTC, but they're actually Central Time values
                    // Example: "09:00:00" is stored as 09:00 UTC but should be 09:00 CDT
                    $startTimeString = $currentStart->format('Y-m-d H:i:s');
                    $endTimeString = $currentEnd->format('Y-m-d H:i:s');
                    
                    // Parse as Central Time
                    $startTimeInCentral = Carbon::createFromFormat('Y-m-d H:i:s', $startTimeString, 'America/Chicago');
                    $endTimeInCentral = Carbon::createFromFormat('Y-m-d H:i:s', $endTimeString, 'America/Chicago');
                    
                    // Convert to UTC for storage
                    $newStartUTC = $startTimeInCentral->copy()->utc();
                    $newEndUTC = $endTimeInCentral->copy()->utc();
                    
                    $this->info("🔧 {$event->name} (ID: {$event->id})");
                    $this->line("   Was stored as: {$currentStart} UTC");
                    $this->line("   Displayed as:  " . $currentStart->copy()->setTimezone('America/Chicago')->format('g:i A T'));
                    $this->line("   Should be:     " . $startTimeInCentral->format('g:i A T'));
                    $this->line("   New UTC:       {$newStartUTC} UTC");
                    
                    if (!$dryRun) {
                        $event->update([
                            'start_time' => $newStartUTC,
                            'end_time' => $newEndUTC,
                        ]);
                        $this->line("   ✅ Fixed!");
                    } else {
                        $this->line("   🔍 Would be fixed (dry run)");
                    }
                    
                    $fixed++;
                    $this->newLine();
                    
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("❌ Error fixing event {$event->id} ({$event->name}): " . $e->getMessage());
                }
            }
            
            if (!$dryRun && $errors === 0) {
                DB::commit();
                $this->newLine();
                $this->info("✅ Successfully fixed {$fixed} events!");
            } else if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info("🔍 Dry run complete. Would fix {$fixed} events.");
            } else {
                DB::rollBack();
                $this->error("❌ Rolled back due to errors.");
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Transaction failed: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->info("Summary:");
        $this->info("- Checked: {$events->count()} events");
        $this->info("- Fixed: {$fixed} events");
        $this->info("- Skipped: {$skipped} events");
        $this->info("- Errors: {$errors} events");
        
        return Command::SUCCESS;
    }
}