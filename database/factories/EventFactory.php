<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+1 month');
        $endTime = clone $startTime;
        $endTime->modify('+' . rand(1, 4) . ' hours');

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3) . ' Car Meet',
            'description' => $this->faker->paragraphs(2, true),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(25, 49),
            'longitude' => $this->faker->longitude(-125, -66),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'cost' => $this->faker->randomElement([0, 0, 0, 5, 10, 15, 20]),
            'spots' => $this->faker->randomElement([null, 20, 50, 100]),
            'unlimited_spots' => function (array $attributes) {
                return $attributes['spots'] === null;
            },
            'image' => null,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => 0,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => $this->faker->randomFloat(2, 5, 50),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
            'end_time' => $this->faker->dateTimeBetween('-1 month', '-1 hour'),
        ]);
    }
}
