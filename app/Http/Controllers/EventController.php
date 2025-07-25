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
            $today = now()->startOfDay();
            
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('start_time', $today);
                    break;
                    
                case 'tomorrow':
                    $query->whereDate('start_time', $today->copy()->addDay());
                    break;
                    
                case 'this-week':
                    $query->whereBetween('start_time', [
                        $today,
                        $today->copy()->endOfWeek()
                    ]);
                    break;
                    
                case 'this-month':
                    $query->whereBetween('start_time', [
                        $today,
                        $today->copy()->endOfMonth()
                    ]);
                    break;
                    
                case 'fri-sat-sun':
                    // Get the next Friday (or today if it's Friday)
                    if ($today->isFriday()) {
                        $friday = $today->copy();
                    } else if ($today->isSaturday() || $today->isSunday()) {
                        // If today is weekend, get this Friday
                        $friday = $today->copy()->previous('Friday');
                    } else {
                        // Otherwise get next Friday
                        $friday = $today->copy()->next('Friday');
                    }
                    $sunday = $friday->copy()->addDays(2)->endOfDay();
                    $query->whereBetween('start_time', [$friday, $sunday]);
                    break;
                    
                case 'sat-sun':
                    // Get the next Saturday (or today if it's Saturday/Sunday)
                    if ($today->isSaturday() || $today->isSunday()) {
                        $saturday = $today->copy()->startOfWeek()->addDays(5);
                    } else {
                        $saturday = $today->copy()->next('Saturday');
                    }
                    $sunday = $saturday->copy()->addDay()->endOfDay();
                    $query->whereBetween('start_time', [$saturday, $sunday]);
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

        $events = $query->paginate(20);

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
