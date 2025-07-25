<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ParkUpFrontService
{
    private Client $client;
    private string $endpoint = 'https://gqlv2.parkupfrontpartners.com/';

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false, // Disable SSL verification as per scope
            'timeout' => 30,
        ]);
    }

    public function fetchEvent(int $eventId): ?array
    {
        $query = 'query anonymusEvent($venue_event_id: Int!) {
            anonymusEvent(venue_event_id: $venue_event_id) {
                venue_event_id
                name
                default_image
                address
                cost
                city_id
                official
                public
                public_cc_event
                private_cc_event
                available_spots
                spots
                unlimited_spots
                start_time
                end_time
                allowed_classes
                allowed_sub_classes
                restrict_makes
                code
                active
                car_club {
                    name
                    car_club_id
                    owner {
                        user_id
                        driver {
                            driver_id
                            username
                        }
                    }
                }
                active_reservations_count
                requires_signup_code
                requires_payment
                max_driver_guests
                guests
                guest_price
                allow_guests
                web_page
                sign_up_page
                summary
                payment_type {
                    payment_type_id
                    type
                    description
                }
                payment_type_id
                unlimited_guests
                owner_role {
                    name
                    car_club_id
                }
                sponsors
            }
        }';

        try {
            $response = $this->client->post($this->endpoint, [
                'json' => [
                    'operationName' => 'anonymusEvent',
                    'variables' => ['venue_event_id' => $eventId],
                    'query' => $query,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['data']['anonymusEvent'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch ParkUpFront event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    public function scanForEvents(int $startId, int $endId): array
    {
        $events = [];
        $consecutiveNulls = 0;

        for ($id = $startId; $id <= $endId; $id++) {
            $event = $this->fetchEvent($id);
            
            if ($event === null) {
                $consecutiveNulls++;
                if ($consecutiveNulls >= 10) {
                    Log::info("Stopping scan after 10 consecutive null events at ID {$id}");
                    break;
                }
            } else {
                $consecutiveNulls = 0;
                $events[] = $event;
                Log::info("Found event ID {$id}: {$event['name']}");
            }

            // Small delay to be respectful to their API
            usleep(500000); // 0.5 seconds
        }

        return $events;
    }

    public function getCityName(int $cityId): string
    {
        // Based on scope.txt, city ID 3 is Dallas-Fort Worth
        $cities = [
            3 => 'Dallas',
        ];

        return $cities[$cityId] ?? 'Unknown';
    }

    public function getStateFromCity(int $cityId): string
    {
        $states = [
            3 => 'TX', // Dallas-Fort Worth
        ];

        return $states[$cityId] ?? 'TX';
    }
}