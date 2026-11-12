<?php

use App\Enums\DesignRequestStatus;
use App\Http\Requests\StoreDesignDraftRequest;
use App\Http\Requests\StoreDesignRevisionRequest;
use App\Models\DesignRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createTestDesignRequest(array $attributes = []): DesignRequest
{
    $requesterId = $attributes['requester_id'] ?? User::factory()->create(['unit_id' => Unit::factory()])->id;
    $unitId = $attributes['unit_id'] ?? User::find($requesterId)?->unit_id ?? Unit::factory()->create()->id;

    return DesignRequest::create(array_merge([
        'request_number' => 'DGR-'.uniqid(),
        'title' => 'Test Design',
        'design_type' => 'Poster',
        'description' => 'Deskripsi request',
        'needed_at' => now()->addDays(2),
        'requester_id' => $requesterId,
        'unit_id' => $unitId,
        'status' => DesignRequestStatus::Ditugaskan,
    ], $attributes));
}

test('route design workflow terdaftar dengan benar beserta middleware', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes());

    $draftRoute = $routes->first(fn ($r) => $r->uri() === 'api/v1/design-requests/{id}/drafts');
    expect($draftRoute)->not->toBeNull();
    expect($draftRoute->methods())->toContain('POST');
    expect($draftRoute->middleware())->toContain('permission:design-drafts-upload');

    $revisionRoute = $routes->first(fn ($r) => $r->uri() === 'api/v1/design-requests/{id}/revisions');
    expect($revisionRoute)->not->toBeNull();
    expect($revisionRoute->methods())->toContain('POST');
    expect($revisionRoute->middleware())->toContain('permission:design-requests-revise');

    $approvalRoute = $routes->first(fn ($r) => $r->uri() === 'api/v1/design-requests/{id}/approvals');
    expect($approvalRoute)->not->toBeNull();
    expect($approvalRoute->methods())->toContain('POST');
    expect($approvalRoute->middleware())->toContain('permission:design-requests-approve');

    $finalRoute = $routes->first(fn ($r) => $r->uri() === 'api/v1/design-requests/{id}/final-file');
    expect($finalRoute)->not->toBeNull();
    expect($finalRoute->methods())->toContain('GET');
});

test('store design draft request memvalidasi file dan notes', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
        'assigned_officer_id' => $officer->id,
    ]);

    $request = StoreDesignDraftRequest::create("/api/v1/design-requests/{$designRequest->id}/drafts", 'POST', [
        'notes' => 12345,
    ]);
    $request->setUserResolver(fn () => $officer);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/drafts', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('file'))->toBeTrue();
    expect($validator->errors()->has('notes'))->toBeTrue();
});

test('store design draft request menolak jika design request tidak ditemukan', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);

    $request = StoreDesignDraftRequest::create('/api/v1/design-requests/99999/drafts', 'POST', [
        'file' => UploadedFile::fake()->create('draft.pdf', 100),
    ]);
    $request->setUserResolver(fn () => $officer);
    $request->setRouteResolver(function () {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/drafts', []);
        $route->bind(request());
        $route->setParameter('id', '99999');

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('design_request'))->toBeTrue();
});

test('store design draft request menolak jika status bukan Ditugaskan atau PerluRevisi', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::Baru,
        'assigned_officer_id' => $officer->id,
    ]);

    $request = StoreDesignDraftRequest::create("/api/v1/design-requests/{$designRequest->id}/drafts", 'POST', [
        'file' => UploadedFile::fake()->create('draft.pdf', 100),
    ]);
    $request->setUserResolver(fn () => $officer);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/drafts', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('store design draft request menolak jika user bukan assigned officer', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
        'assigned_officer_id' => $officer->id,
    ]);

    $request = StoreDesignDraftRequest::create("/api/v1/design-requests/{$designRequest->id}/drafts", 'POST', [
        'file' => UploadedFile::fake()->create('draft.pdf', 100),
    ]);
    $request->setUserResolver(fn () => $otherUser);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/drafts', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('assigned_officer_id'))->toBeTrue();
});

test('store design draft request lolos validasi jika kondisi terpenuhi', function (): void {
    $officer = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::PerluRevisi,
        'assigned_officer_id' => $officer->id,
    ]);

    $request = StoreDesignDraftRequest::create("/api/v1/design-requests/{$designRequest->id}/drafts", 'POST', [
        'file' => UploadedFile::fake()->create('draft.png', 500),
        'notes' => 'Draft revisi pertama',
    ]);
    $request->setUserResolver(fn () => $officer);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/drafts', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

test('store design revision request memvalidasi revision_note wajib', function (): void {
    $requester = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
        'requester_id' => $requester->id,
    ]);

    $request = StoreDesignRevisionRequest::create("/api/v1/design-requests/{$designRequest->id}/revisions", 'POST', [
        'revision_note' => '',
    ]);
    $request->setUserResolver(fn () => $requester);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/revisions', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('revision_note'))->toBeTrue();
});

test('store design revision request menolak jika status bukan MenungguPersetujuan', function (): void {
    $requester = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::Ditugaskan,
        'requester_id' => $requester->id,
    ]);

    $request = StoreDesignRevisionRequest::create("/api/v1/design-requests/{$designRequest->id}/revisions", 'POST', [
        'revision_note' => 'Tolong ganti warna background',
    ]);
    $request->setUserResolver(fn () => $requester);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/revisions', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

test('store design revision request menolak jika user bukan requester', function (): void {
    $requester = User::factory()->create(['unit_id' => Unit::factory()]);
    $otherUser = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
        'requester_id' => $requester->id,
    ]);

    $request = StoreDesignRevisionRequest::create("/api/v1/design-requests/{$designRequest->id}/revisions", 'POST', [
        'revision_note' => 'Tolong perbaiki teks judul',
    ]);
    $request->setUserResolver(fn () => $otherUser);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/revisions', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('requester_id'))->toBeTrue();
});

test('store design revision request lolos validasi jika kondisi terpenuhi', function (): void {
    $requester = User::factory()->create(['unit_id' => Unit::factory()]);
    $designRequest = createTestDesignRequest([
        'status' => DesignRequestStatus::MenungguPersetujuan,
        'requester_id' => $requester->id,
    ]);

    $request = StoreDesignRevisionRequest::create("/api/v1/design-requests/{$designRequest->id}/revisions", 'POST', [
        'revision_note' => 'Tolong ubah ukuran logo menjadi lebih proporsional.',
    ]);
    $request->setUserResolver(fn () => $requester);
    $request->setRouteResolver(function () use ($designRequest) {
        $route = new Illuminate\Routing\Route('POST', 'api/v1/design-requests/{id}/revisions', []);
        $route->bind(request());
        $route->setParameter('id', (string) $designRequest->id);

        return $route;
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

test('endpoint drafts menolak user tanpa permission design-drafts-upload', function (): void {
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/design-requests/1/drafts', [])
        ->assertForbidden();
});

test('endpoint revisions menolak user tanpa permission design-requests-revise', function (): void {
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/design-requests/1/revisions', [])
        ->assertForbidden();
});

test('endpoint approvals menolak user tanpa permission design-requests-approve', function (): void {
    $user = User::factory()->create(['unit_id' => Unit::factory()]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/design-requests/1/approvals', [])
        ->assertForbidden();
});
