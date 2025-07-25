<x-mail::message>
# New Event Imported from ParkUpFront

A new event has been imported to CarMeets:

**{{ $event->name }}**

<x-mail::panel>
**Date:** {{ \Carbon\Carbon::parse($event->start_time)->format('l, F j, Y') }}  
**Time:** {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::parse($event->end_time)->format('g:i A') }}  
**Location:** {{ $event->city }}, {{ $event->state }}  
**Cost:** {{ $event->cost > 0 ? '$' . number_format($event->cost, 2) : 'FREE' }}
</x-mail::panel>

@if($event->description)
**Description:**  
{{ Str::limit($event->description, 200) }}
@endif

<x-mail::button :url="url('/events/' . $event->id)">
View Event Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>