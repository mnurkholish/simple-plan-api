<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketHandling;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketHandling>
 */
class TicketHandlingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'handled_by_id' => User::factory(),
            'notes' => fake()->sentence(),
            'started_at' => fake()->optional()->dateTimeBetween('-1 week'),
            'completed_at' => null,
        ];
    }
}
