<?php

namespace Database\Factories;

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = fake()->randomElement(TicketService::cases());

        return [
            'ticket_number' => sprintf(
                '%s-%s-%04d',
                strtoupper($service->value),
                now()->format('Y'),
                fake()->unique()->numberBetween(1, 9999),
            ),
            'service' => $service,
            'reporter_id' => User::factory(),
            'unit_id' => Unit::factory(),
            'category' => fake()->optional()->words(2, true),
            'description' => fake()->sentence(),
            'status' => TicketStatus::Baru,
        ];
    }
}
