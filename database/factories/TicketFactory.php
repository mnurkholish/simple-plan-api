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
                '%s-%s-%06d',
                $service->ticketNumberPrefix(),
                now()->format('Y'),
                fake()->unique()->numberBetween(1, 999999),
            ),
            'service' => $service,
            'reporter_id' => User::factory(),
            'unit_id' => Unit::factory(),
            'description' => fake()->sentence(),
            'status' => TicketStatus::Baru,
        ];
    }
}
