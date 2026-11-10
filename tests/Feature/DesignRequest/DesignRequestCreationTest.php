<?php

use App\Enums\DesignRequestStatus;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
});

function actingAsDesignRequestCreator(): User
{
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    $permission = Permission::firstOrCreate([
        'name' => 'design-requests-create',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
    Sanctum::actingAs($user);

    return $user;
}

function validDesignRequestData(): array
{
    return [
        'title' => 'Poster edukasi pasien',
        'design_type' => 'Poster edukasi untuk ruang tunggu',
        'description' => 'Buat poster tentang persiapan pemeriksaan.',
        'needed_at' => today()->addDays(3)->toDateString(),
    ];
}

test('pengguna yang diizinkan dapat membuat request desain dengan status baru dan identitasnya sendiri', function (): void {
    $requester = actingAsDesignRequestCreator();
    $data = validDesignRequestData();

    $response = $this->postJson('/api/v1/design-requests', $data);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Request desain berhasil dibuat.')
        ->assertJsonPath('data.title', $data['title'])
        ->assertJsonPath('data.design_type', $data['design_type'])
        ->assertJsonPath('data.status', DesignRequestStatus::Baru->value)
        ->assertJsonPath('data.requester.id', $requester->id)
        ->assertJsonPath('data.unit.id', $requester->unit_id)
        ->assertJsonPath('data.reference_file', null);

    expect($response->json('data.request_number'))
        ->toMatch('/^DGR-'.now()->format('Y').'-\d{6}$/');

    $this->assertDatabaseHas('design_requests', [
        'id' => $response->json('data.id'),
        'request_number' => $response->json('data.request_number'),
        'title' => $data['title'],
        'design_type' => $data['design_type'],
        'description' => $data['description'],
        'requester_id' => $requester->id,
        'unit_id' => $requester->unit_id,
        'status' => DesignRequestStatus::Baru->value,
    ]);
});

test('nomor request desain yang dibuat berulang tetap unik', function (): void {
    actingAsDesignRequestCreator();

    $first = $this->postJson('/api/v1/design-requests', validDesignRequestData())->assertCreated();
    $second = $this->postJson('/api/v1/design-requests', validDesignRequestData())->assertCreated();

    expect($second->json('data.request_number'))->not->toBe($first->json('data.request_number'));
    expect(DesignRequest::query()->count())->toBe(2);
});

test('field wajib request desain yang kosong ditolak', function (): void {
    actingAsDesignRequestCreator();

    $this->postJson('/api/v1/design-requests', [
        'title' => '',
        'design_type' => '',
        'description' => '',
        'needed_at' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'design_type', 'description', 'needed_at']);

    expect(DesignRequest::query()->count())->toBe(0);
});

test('tanggal kebutuhan sebelum hari ini ditolak', function (): void {
    actingAsDesignRequestCreator();

    $this->postJson('/api/v1/design-requests', [
        ...validDesignRequestData(),
        'needed_at' => today()->subDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('needed_at');

    expect(DesignRequest::query()->count())->toBe(0);
});

test('file referensi disimpan pada storage beserta metadata', function (): void {
    actingAsDesignRequestCreator();
    $file = UploadedFile::fake()->create('contoh-referensi.pdf', 512, 'application/pdf');

    $response = $this->post('/api/v1/design-requests', [
        ...validDesignRequestData(),
        'reference_file' => $file,
    ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonPath('data.reference_file.original_name', 'contoh-referensi.pdf')
        ->assertJsonPath('data.reference_file.mime_type', 'application/pdf')
        ->assertJsonPath('data.reference_file.size', $file->getSize())
        ->assertJsonMissingPath('data.reference_file.object_key')
        ->assertJsonMissingPath('data.reference_file.url');

    $designRequest = DesignRequest::query()->findOrFail($response->json('data.id'));

    expect($designRequest->reference_file_object_key)->toStartWith('design-requests/references/');
    Storage::disk('local')->assertExists($designRequest->reference_file_object_key);

    $this->assertDatabaseHas('design_requests', [
        'id' => $designRequest->id,
        'reference_file_object_key' => $designRequest->reference_file_object_key,
        'reference_file_original_name' => 'contoh-referensi.pdf',
        'reference_file_mime_type' => 'application/pdf',
        'reference_file_size' => $file->getSize(),
    ]);
});

test('file referensi yang tidak sesuai format atau lebih besar dari 5 MB ditolak', function (mixed $file): void {
    actingAsDesignRequestCreator();

    $this->post('/api/v1/design-requests', [
        ...validDesignRequestData(),
        'reference_file' => $file,
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reference_file');

    expect(DesignRequest::query()->count())->toBe(0);
})->with([
    'bukan file' => 'teks biasa',
    'file melebihi batas ukuran' => fn (): UploadedFile => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
]);

test('pengguna tanpa permission design-requests-create ditolak', function (): void {
    Sanctum::actingAs(User::factory()->create(['unit_id' => Unit::factory()]));

    $this->postJson('/api/v1/design-requests', validDesignRequestData())
        ->assertForbidden();

    expect(DesignRequest::query()->count())->toBe(0);
});
