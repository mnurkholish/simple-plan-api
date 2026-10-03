<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('completed tickets are closed after two days without reporter verification', function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');

    try {
        $reporter = User::factory()->for(Unit::factory())->create();
        $reporter->assignRole('user');

        $atBoundary = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Terselesaikan,
            'completed_at' => '2026-09-29 10:00:00',
        ]);
        $older = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Terselesaikan,
            'completed_at' => '2026-09-28 09:00:00',
        ]);

        $this->artisan('tickets:auto-close-unverified')
            ->expectsOutput('2 tiket ditutup otomatis.')
            ->assertSuccessful();

        foreach ([$atBoundary, $older] as $ticket) {
            expect($ticket->refresh()->status)->toBe(TicketStatus::Ditutup)
                ->and($ticket->closed_at?->format('Y-m-d H:i:s'))->toBe('2026-10-01 10:00:00');

            $this->assertDatabaseHas('ticket_status_histories', [
                'ticket_id' => $ticket->id,
                'from_status' => TicketStatus::Terselesaikan->value,
                'to_status' => TicketStatus::Ditutup->value,
                'changed_by_id' => null,
                'notes' => 'Ditutup otomatis setelah 2 hari tanpa verifikasi reporter.',
            ]);
        }
    } finally {
        Carbon::setTestNow();
    }
});

test('auto close leaves ineligible tickets unchanged and is idempotent', function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');

    try {
        $reporter = User::factory()->for(Unit::factory())->create();
        $reporter->assignRole('user');

        $recent = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Terselesaikan,
            'completed_at' => '2026-09-29 10:00:01',
        ]);
        $withoutCompletionTime = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Terselesaikan,
            'completed_at' => null,
        ]);
        $returnedToHandling = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Diproses,
            'completed_at' => '2026-09-28 10:00:00',
        ]);
        $eligible = Ticket::factory()->reportedBy($reporter)->create([
            'status' => TicketStatus::Terselesaikan,
            'completed_at' => '2026-09-28 10:00:00',
        ]);

        $this->artisan('tickets:auto-close-unverified')->assertSuccessful();
        $this->artisan('tickets:auto-close-unverified')
            ->expectsOutput('0 tiket ditutup otomatis.')
            ->assertSuccessful();

        expect($recent->refresh()->status)->toBe(TicketStatus::Terselesaikan)
            ->and($withoutCompletionTime->refresh()->status)->toBe(TicketStatus::Terselesaikan)
            ->and($returnedToHandling->refresh()->status)->toBe(TicketStatus::Diproses)
            ->and($eligible->refresh()->status)->toBe(TicketStatus::Ditutup)
            ->and($eligible->statusHistories()->count())->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('the automatic close command is scheduled every minute', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'tickets:auto-close-unverified'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
