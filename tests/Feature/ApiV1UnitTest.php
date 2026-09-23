<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an authorized user can replace a unit membership list', function (): void {
    $this->seed();

    $admin = User::query()->where('email', 'juniyasyos@gmail.com')->firstOrFail();
    $token = $admin->createToken('feature-test')->plainTextToken;
    $unit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $retainedUser = User::factory()->create(['unit_id' => $unit->id]);
    $removedUser = User::factory()->create(['unit_id' => $unit->id]);
    $reassignedUser = User::factory()->create(['unit_id' => $otherUnit->id]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/units/{$unit->id}/users", [
            'user_ids' => [$retainedUser->id, $reassignedUser->id],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('user_ids', [$retainedUser->id, $reassignedUser->id]);

    expect($retainedUser->fresh()->unit_id)->toBe($unit->id)
        ->and($removedUser->fresh()->unit_id)->toBeNull()
        ->and($reassignedUser->fresh()->unit_id)->toBe($unit->id);
});

test('an authorized user can remove every member from a unit', function (): void {
    $this->seed();

    $admin = User::query()->where('email', 'juniyasyos@gmail.com')->firstOrFail();
    $token = $admin->createToken('feature-test')->plainTextToken;
    $unit = Unit::factory()->create();
    $member = User::factory()->create(['unit_id' => $unit->id]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/units/{$unit->id}/users", [
            'user_ids' => [],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('user_ids', []);

    expect($member->fresh()->unit_id)->toBeNull();
});
