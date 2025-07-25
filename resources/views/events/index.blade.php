@extends('layouts.app')

@section('title', 'Upcoming Events')

@section('content')
    <h1 class="text-4xl font-bold mb-8">Upcoming Car Events</h1>

    @if($events->isEmpty())
        <div class="bg-gray-100 rounded-lg p-8 text-center">
            <p class="text-gray-600 text-lg">No upcoming events found.</p>
            <p class="text-gray-500 mt-2">Check back later for new events!</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($events as $event)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    @if($event->image)
                        <img src="{{ str_starts_with($event->image, 'http') ? $event->image : asset('storage/' . $event->image) }}" alt="{{ $event->name }}" class="w-full h-48 object-cover">
                    @else
                        <div class="w-full h-48 bg-gray-300 flex items-center justify-center">
                            <span class="text-gray-500">No image</span>
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-2">
                            <a href="{{ route('events.show', $event) }}" class="hover:text-blue-600">{{ $event->name }}</a>
                        </h2>
                        
                        <div class="text-gray-600 text-sm space-y-1 mb-4">
                            <p>📅 {{ $event->start_time->format('M d, Y - g:i A') }}</p>
                            <p>📍 {{ $event->city }}, {{ $event->state }}</p>
                            <p>👤 Hosted by {{ $event->user->name }}</p>
                        </div>
                        
                        @if($event->cost > 0)
                            <p class="text-green-600 font-semibold">${{ number_format($event->cost, 2) }}</p>
                        @else
                            <p class="text-green-600 font-semibold">FREE</p>
                        @endif
                        
                        @if(!$event->unlimited_spots && $event->spots)
                            <p class="text-sm text-gray-500 mt-2">{{ $event->spots }} spots available</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $events->links() }}
        </div>
    @endif
@endsection