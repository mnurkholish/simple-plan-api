<?php

use App\Models\AssetMaintenance;
use App\Models\User;
use App\Notifications\AssetMaintenanceOverdueNotification;
use App\Notifications\AssetMaintenanceReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('pengingat dan keterlambatan maintenance menyimpan tujuan detail aset', function (): void {
    $user = User::factory()->create();
    $maintenance = new AssetMaintenance([
        'maintenance_type' => 'Pembersihan',
        'next_scheduled_date' => '2026-11-27',
    ]);
    $maintenance->id = 123;

    $user->notify(new AssetMaintenanceReminderNotification($maintenance, 'h-1'));
    $user->notify(new AssetMaintenanceOverdueNotification($maintenance));

    $payloads = $user->notifications()->get()->pluck('data');
    expect($payloads->pluck('title')->all())
        ->toContain('Pengingat Pemeliharaan Aset', 'Jadwal Pemeliharaan Terlambat');
    foreach ($payloads as $data) {
        expect($data['maintenance_id'])->toBe(123)
            ->and($data['target']['feature'])->toBe('inventory');
    }

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.maintenance_id', 123)
        ->assertJsonMissingPath('data.0.ticket_id');
});
