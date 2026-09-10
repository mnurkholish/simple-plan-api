<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('guest cannot create a ticket', function (): void {
    $unit = Unit::factory()->create();

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => $unit->id,
        'description' => 'Komputer tidak dapat menyala.',
    ])->assertUnauthorized();
});

test('authenticated user can create a TIK ticket', function (): void {
    $reporter = User::factory()->create();
    $unit = Unit::factory()->create();
    Sanctum::actingAs($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => $unit->id,
        'category' => 'Perangkat Komputer',
        'description' => 'Komputer tidak dapat menyala.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Tiket berhasil dibuat.')
        ->assertJsonPath('data.service', 'tik')
        ->assertJsonPath('data.category', 'Perangkat Komputer')
        ->assertJsonPath('data.description', 'Komputer tidak dapat menyala.')
        ->assertJsonPath('data.status', 'baru')
        ->assertJsonPath('data.reporter.id', $reporter->id)
        ->assertJsonPath('data.unit.id', $unit->id)
        ->assertJsonPath('data.initial_evidence', null);

    expect($response->json('data.ticket_number'))
        ->toMatch('/^TIK-\d{4}-\d{4,}$/');

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'reporter_id' => $reporter->id,
        'unit_id' => $unit->id,
        'service' => 'tik',
        'status' => TicketStatus::Baru->value,
    ]);
});

test('authenticated user can create a Sarpras ticket without a category', function (): void {
    $reporter = User::factory()->create();
    $unit = Unit::factory()->create();
    Sanctum::actingAs($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'sarpras',
        'unit_id' => $unit->id,
        'description' => 'Lampu ruang pemeriksaan mati.',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.service', 'sarpras')
        ->assertJsonPath('data.category', null)
        ->assertJsonPath('data.status', 'baru');

    expect($response->json('data.ticket_number'))
        ->toMatch('/^SARPRAS-\d{4}-\d{4,}$/');
});

test('ticket creation requires its documented fields', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tickets')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service', 'unit_id', 'description']);
});

test('ticket creation rejects an unknown service', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tickets', [
        'service' => 'umum',
        'unit_id' => Unit::factory()->create()->id,
        'description' => 'Kerusakan perlu ditangani.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service');
});

test('ticket creation rejects an unavailable unit', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => 999999,
        'description' => 'Komputer tidak dapat menyala.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');
});

test('ticket creation keeps server controlled fields authoritative', function (): void {
    $reporter = User::factory()->create();
    $otherUser = User::factory()->create();
    $unit = Unit::factory()->create();
    Sanctum::actingAs($reporter);

    $response = $this->postJson('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => $unit->id,
        'description' => 'Komputer tidak dapat menyala.',
        'reporter_id' => $otherUser->id,
        'status' => TicketStatus::Selesai->value,
        'ticket_number' => 'CLIENT-CONTROLLED',
    ])->assertCreated();

    $ticket = Ticket::findOrFail($response->json('data.id'));

    expect($ticket->reporter_id)->toBe($reporter->id)
        ->and($ticket->status)->toBe(TicketStatus::Baru)
        ->and($ticket->ticket_number)->not->toBe('CLIENT-CONTROLLED');
});

test('generated ticket numbers are unique', function (): void {
    $reporter = User::factory()->create();
    $unit = Unit::factory()->create();
    Sanctum::actingAs($reporter);

    $payload = [
        'service' => 'tik',
        'unit_id' => $unit->id,
        'description' => 'Komputer tidak dapat menyala.',
    ];

    $firstNumber = $this->postJson('/api/v1/tickets', $payload)
        ->assertCreated()
        ->json('data.ticket_number');
    $secondNumber = $this->postJson('/api/v1/tickets', $payload)
        ->assertCreated()
        ->json('data.ticket_number');

    expect($firstNumber)->not->toBe($secondNumber);
});

test('initial evidence image is stored with portable metadata', function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');

    $reporter = User::factory()->create();
    $unit = Unit::factory()->create();
    Sanctum::actingAs($reporter);

    $response = $this->post('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => $unit->id,
        'description' => 'Monitor menampilkan garis.',
        'initial_evidence' => UploadedFile::fake()->image('monitor-rusak.jpg')->size(512),
    ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonPath('data.initial_evidence.original_name', 'monitor-rusak.jpg')
        ->assertJsonPath('data.initial_evidence.mime_type', 'image/jpeg');

    $objectKey = $response->json('data.initial_evidence.object_key');

    expect($objectKey)->toStartWith('helpdesk/evidence/');
    Storage::disk('local')->assertExists($objectKey);

    $this->assertDatabaseHas('tickets', [
        'id' => $response->json('data.id'),
        'initial_evidence_object_key' => $objectKey,
        'initial_evidence_original_name' => 'monitor-rusak.jpg',
        'initial_evidence_mime_type' => 'image/jpeg',
    ]);
});

test('initial evidence must be an image no larger than two megabytes', function (UploadedFile $file): void {
    Storage::fake('local');
    Sanctum::actingAs(User::factory()->create());

    $this->post('/api/v1/tickets', [
        'service' => 'tik',
        'unit_id' => Unit::factory()->create()->id,
        'description' => 'Monitor menampilkan garis.',
        'initial_evidence' => $file,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('initial_evidence');
})->with([
    'non image file' => fn (): UploadedFile => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
    'image above size limit' => fn (): UploadedFile => UploadedFile::fake()->image('large.jpg')->size(2049),
]);
