<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::with('user')->upcoming();

        // Handle date filters
        if ($request->has('date_filter')) {
            // Use Central Time for date filtering since all events are in Dallas
            $todayInCentral = now('America/Chicago')->startOfDay();
            $todayInUTC = $todayInCentral->copy()->utc();
            
            switch ($request->date_filter) {
                case 'today':
                    // Get start and end of today in Central Time, converted to UTC
                    $startOfDay = $todayInCentral->copy()->utc();
                    $endOfDay = $todayInCentral->copy()->endOfDay()->utc();
                    $query->whereBetween('start_time', [$startOfDay, $endOfDay]);
                    break;
                    
                case 'tomorrow':
                    // Get start and end of tomorrow in Central Time, converted to UTC
                    $startOfTomorrow = $todayInCentral->copy()->addDay()->utc();
                    $endOfTomorrow = $todayInCentral->copy()->addDay()->endOfDay()->utc();
                    $query->whereBetween('start_time', [$startOfTomorrow, $endOfTomorrow]);
                    break;
                    
                case 'this-week':
                    // From now until end of week
                    $startOfWeek = $todayInCentral->copy()->utc();
                    $endOfWeek = $todayInCentral->copy()->endOfWeek()->endOfDay()->utc();
                    $query->whereBetween('start_time', [$startOfWeek, $endOfWeek]);
                    break;
                    
                case 'this-month':
                    // From now until end of month
                    $startOfMonth = $todayInCentral->copy()->utc();
                    $endOfMonth = $todayInCentral->copy()->endOfMonth()->endOfDay()->utc();
                    $query->whereBetween('start_time', [$startOfMonth, $endOfMonth]);
                    break;
                    
                case 'fri-sat-sun':
                    // Get the next Friday (or today if it's Friday) in Central Time
                    if ($todayInCentral->isFriday()) {
                        $friday = $todayInCentral->copy()->startOfDay();
                    } else if ($todayInCentral->isSaturday() || $todayInCentral->isSunday()) {
                        // If today is weekend, get this Friday
                        $friday = $todayInCentral->copy()->previous('Friday')->startOfDay();
                    } else {
                        // Otherwise get next Friday
                        $friday = $todayInCentral->copy()->next('Friday')->startOfDay();
                    }
                    // Make sure we include all of Sunday
                    $sundayEnd = $friday->copy()->addDays(2)->endOfDay();
                    $query->whereBetween('start_time', [$friday->utc(), $sundayEnd->utc()]);
                    break;
                    
                case 'sat-sun':
                    // Get the next Saturday (or today if it's Saturday/Sunday) in Central Time
                    if ($todayInCentral->isSaturday()) {
                        $saturday = $todayInCentral->copy()->startOfDay();
                    } else if ($todayInCentral->isSunday()) {
                        // If today is Sunday, get yesterday (Saturday)
                        $saturday = $todayInCentral->copy()->previous('Saturday')->startOfDay();
                    } else {
                        $saturday = $todayInCentral->copy()->next('Saturday')->startOfDay();
                    }
                    // Make sure we include all of Sunday
                    $sundayEnd = $saturday->copy()->addDay()->endOfDay();
                    $query->whereBetween('start_time', [$saturday->utc(), $sundayEnd->utc()]);
                    break;
            }
        }
        
        // Handle custom date range
        if ($request->has('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        // Allow customizable page size, default to 50, max 100
        $perPage = min($request->get('per_page', 50), 100);
        $events = $query->paginate($perPage);

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'cost' => 'nullable|numeric|min:0',
            'spots' => 'nullable|integer|min:1',
            'unlimited_spots' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('events', 'public');
        }

        $event = $request->user()->events()->create($validated);

        return response()->json($event->load('user'), 201);
    }

    public function show(Event $event)
    {
        return response()->json($event->load('user'));
    }

    public function update(Request $request, Event $event)
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'state' => 'sometimes|required|string|size:2',
            'zip' => 'sometimes|required|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'cost' => 'nullable|numeric|min:0',
            'spots' => 'nullable|integer|min:1',
            'unlimited_spots' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('events', 'public');
        }

        $event->update($validated);

        return response()->json($event->load('user'));
    }

    public function destroy(Event $event)
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}
