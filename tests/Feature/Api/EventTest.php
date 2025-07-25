<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateUser($user = null)
    {
        $user = $user ?: User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function test_anyone_can_list_events()
    {
        Event::factory()->count(5)->create();
        Event::factory()->past()->count(2)->create();

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'address',
                        'city',
                        'state',
                        'start_time',
                        'end_time',
                        'cost',
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                ],
                'current_page',
                'per_page',
                'total',
            ]);
    }

    public function test_anyone_can_view_single_event()
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $event->id,
                'name' => $event->name,
                'user' => [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                ],
            ]);
    }

    public function test_authenticated_user_can_create_event()
    {
        Storage::fake('public');
        $auth = $this->authenticateUser();

        $eventData = [
            'name' => 'Test Car Meet',
            'description' => 'A test car meet event',
            'address' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75201',
            'latitude' => 32.7767,
            'longitude' => -96.7970,
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(7)->addHours(3)->format('Y-m-d H:i:s'),
            'cost' => 0,
            'unlimited_spots' => true,
            'image' => UploadedFile::fake()->image('event.jpg'),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->postJson('/api/events', $eventData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'image',
                'user',
            ]);

        $this->assertDatabaseHas('events', [
            'name' => 'Test Car Meet',
            'user_id' => $auth['user']->id,
        ]);

        Storage::disk('public')->assertExists('events/'.basename($response->json('image')));
    }

    public function test_unauthenticated_user_cannot_create_event()
    {
        $eventData = [
            'name' => 'Test Car Meet',
            'start_time' => now()->addDays(7),
            'end_time' => now()->addDays(7)->addHours(3),
        ];

        $response = $this->postJson('/api/events', $eventData);

        $response->assertStatus(401);
    }

    public function test_event_creation_validates_required_fields()
    {
        $auth = $this->authenticateUser();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->postJson('/api/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'address',
                'city',
                'state',
                'zip',
                'start_time',
                'end_time',
            ]);
    }

    public function test_event_creation_validates_dates()
    {
        $auth = $this->authenticateUser();

        $eventData = [
            'name' => 'Test Event',
            'address' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75201',
            'start_time' => now()->subDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->subDay()->addHour()->format('Y-m-d H:i:s'),
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->postJson('/api/events', $eventData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_event_owner_can_update_their_event()
    {
        $auth = $this->authenticateUser();
        $event = Event::factory()->create(['user_id' => $auth['user']->id]);

        $updateData = [
            'name' => 'Updated Event Name',
            'description' => 'Updated description',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->putJson("/api/events/{$event->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $event->id,
                'name' => 'Updated Event Name',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Updated Event Name',
        ]);
    }

    public function test_user_cannot_update_others_event()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $auth = $this->authenticateUser($otherUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->putJson("/api/events/{$event->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_event_owner_can_delete_their_event()
    {
        $auth = $this->authenticateUser();
        $event = Event::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Event deleted successfully']);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_user_cannot_delete_others_event()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $auth = $this->authenticateUser($otherUser);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
        ])->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_unauthenticated_user_cannot_update_event()
    {
        $event = Event::factory()->create();

        $response = $this->putJson("/api/events/{$event->id}", ['name' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_delete_event()
    {
        $event = Event::factory()->create();

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_events_are_paginated()
    {
        Event::factory()->count(25)->create();

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('per_page', 20);
    }

    public function test_only_upcoming_events_are_shown()
    {
        Event::factory()->count(3)->create();
        Event::factory()->past()->count(2)->create();

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
}
