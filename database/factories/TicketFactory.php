<?php

namespace Database\Factories;

use App\Enums\TicketService;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;
use RuntimeException;

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
            'reporter_id' => function (): int {
                $reporterId = User::query()
                    ->whereNotNull('unit_id')
                    ->where('status', 'active')
                    ->where('status_user', 'Aktif')
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                        'super-admin',
                        'koordinator-sarpras',
                        'petugas-tik',
                        'petugas-sarpras',
                        'user',
                        'management',
                    ]))
                    ->inRandomOrder()
                    ->value('id');

                return $reporterId ?? throw new RuntimeException(
                    'Tidak ada user aktif dengan unit dan role yang dapat digunakan sebagai reporter ticket.'
                );
            },
            'unit_id' => function (array $attributes): int {
                $unitId = User::query()->findOrFail($attributes['reporter_id'])->unit_id;

                return $unitId ?? throw new RuntimeException(
                    'Reporter ticket harus memiliki unit.'
                );
            },
            'description' => fake()->sentence(),
            'status' => TicketStatus::Baru,
        ];
    }

    public function reportedBy(User $reporter): static
    {
        if (! $reporter->exists) {
            throw new InvalidArgumentException(
                'Reporter harus sudah tersimpan di database.'
            );
        }

        if ($reporter->unit_id === null) {
            throw new InvalidArgumentException(
                'Reporter harus memiliki unit sebelum membuat ticket.'
            );
        }

        return $this->state([
            'reporter_id' => $reporter->id,
            'unit_id' => $reporter->unit_id,
        ]);
    }
}
