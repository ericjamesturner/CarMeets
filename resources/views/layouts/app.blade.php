<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CarMeets - @yield('title', 'Local Car Events')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <nav class="bg-gray-800 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="{{ route('events.index') }}" class="text-2xl font-bold">CarMeets</a>
            <div class="space-x-4">
                <a href="{{ route('events.index') }}" class="hover:text-gray-300">All Events</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        @yield('content')
    </main>

    <footer class="bg-gray-800 text-white p-4 mt-8">
        <div class="container mx-auto text-center">
            <p>&copy; {{ date('Y') }} CarMeets. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>