@extends('layouts.app')

@section('title', $event->name)

@section('content')
    <div class="max-w-4xl mx-auto">
        <a href="{{ route('events.index') }}" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">← Back to all events</a>
        
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            @if($event->image)
                <img src="{{ str_starts_with($event->image, 'http') ? $event->image : asset('storage/' . $event->image) }}" alt="{{ $event->name }}" class="w-full h-96 object-cover">
            @endif
            
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-4">{{ $event->name }}</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Event Details</h3>
                        <div class="space-y-2 text-gray-600">
                            <p><strong>Start:</strong> {{ $event->start_time->format('F d, Y - g:i A') }}</p>
                            <p><strong>End:</strong> {{ $event->end_time->format('F d, Y - g:i A') }}</p>
                            <p><strong>Duration:</strong> {{ $event->start_time->diffForHumans($event->end_time, true) }}</p>
                            <p><strong>Cost:</strong> 
                                @if($event->cost > 0)
                                    ${{ number_format($event->cost, 2) }}
                                @else
                                    FREE
                                @endif
                            </p>
                            @if(!$event->unlimited_spots && $event->spots)
                                <p><strong>Available Spots:</strong> {{ $event->spots }}</p>
                            @endif
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Location</h3>
                        <div class="space-y-2 text-gray-600">
                            <p>{{ $event->address }}</p>
                            <p>{{ $event->city }}, {{ $event->state }} {{ $event->zip }}</p>
                            @if($event->latitude && $event->longitude)
                                <a href="https://maps.google.com/?q={{ $event->latitude }},{{ $event->longitude }}" 
                                   target="_blank" 
                                   class="text-blue-600 hover:text-blue-800 inline-flex items-center">
                                    View on Google Maps →
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                
                @if($event->description)
                    <div class="mb-6">
                        <h3 class="font-semibold text-gray-700 mb-2">Description</h3>
                        <div class="text-gray-600 prose max-w-none">
                            {!! nl2br(e($event->description)) !!}
                        </div>
                    </div>
                @endif
                
                <div class="border-t pt-6">
                    <p class="text-gray-600">
                        <strong>Hosted by:</strong> {{ $event->user->name }}
                    </p>
                    <p class="text-gray-500 text-sm mt-1">
                        Event created {{ $event->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection