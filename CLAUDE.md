# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CarMeets is a Laravel-based platform for car enthusiasts to discover, create, and share automotive events. The project is in its initial development phase with a Laravel 12 skeleton application using Tailwind CSS via Vite.

## Development Commands

### Laravel/PHP Commands
- `php artisan serve` - Start development server
- `php artisan test` - Run all tests
- `php artisan test --filter TestName` - Run specific test
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh` - Drop all tables and re-run migrations
- `php artisan tinker` - Interactive PHP shell
- `php artisan queue:listen --tries=1` - Run queue worker
- `php artisan pail --timeout=0` - View logs in real-time
- `composer test` - Run tests with config clearing

### Frontend Commands
- `npm run dev` - Start Vite development server
- `npm run build` - Build production assets
- `composer dev` - Run all development services concurrently (server, queue, logs, vite)

### Code Quality
- `php artisan pint` - Format PHP code (Laravel's code style fixer)

## Architecture Overview

### Current Structure
- **Laravel 12** with PHP 8.2+ requirement
- **Database**: SQLite for development (configured for PostgreSQL with PostGIS in production per scope)
- **Frontend**: Tailwind CSS v4 with Vite bundler
- **Testing**: PHPUnit 11 for backend tests

### Key Directories
- `app/Models/` - Eloquent models (currently only User model)
- `app/Http/Controllers/` - HTTP controllers
- `database/migrations/` - Database schema migrations
- `routes/web.php` - Web routes (currently only welcome page)
- `resources/views/` - Blade templates
- `resources/js/` and `resources/css/` - Frontend assets

### Planned Architecture (from scope.txt)
The project aims to be API-first with potential frontend options:
1. Separate React SPA with Laravel API backend
2. Next.js with Laravel API
3. React Native mobile apps sharing the same API

Key planned services:
- PostgreSQL with PostGIS for geolocation features
- Redis for caching and queues
- WebSockets (Pusher/Soketi) for real-time features
- S3/Cloudflare R2 for image storage
- Algolia/Meilisearch for search functionality

### ParkUpFront Data Import
The platform will import historical event data from ParkUpFront's GraphQL API:
- Endpoint: `https://gqlv2.parkupfrontpartners.com/`
- Requires SSL verification disabled
- No authentication needed
- City ID 3 = Dallas-Fort Worth area
- See scope.txt lines 171-274 for complete API details and query structure

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage (if configured)
php artisan test --coverage
```

## Database Setup

The project uses SQLite for local development:
```bash
# Create database file (done automatically on project creation)
touch database/database.sqlite

# Run migrations
php artisan migrate
```

For production, configure PostgreSQL with PostGIS extension in `.env` file.

## Development Guidance

### Testing Strategy
- Write tests using the http calls for all api routes when you make them