<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('it generates the Helpdesk ticket Swagger contract', function (): void {
    $documentationPath = storage_path('framework/testing/swagger-docs');
    File::deleteDirectory($documentationPath);

    config()->set('l5-swagger.defaults.paths.docs', $documentationPath);

    try {
        expect(Artisan::call('l5-swagger:generate'))->toBe(0);

        $documentation = json_decode(
            File::get($documentationPath.'/api-docs.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        expect($documentation['paths'])
            ->toHaveKeys(['/tickets', '/tickets/{ticket}']);

        $listOperation = $documentation['paths']['/tickets']['get'];
        $parameters = collect($listOperation['parameters'])->keyBy('name');

        expect($listOperation['security'])->toBe([['sanctum' => []]])
            ->and($parameters->keys()->all())->toBe([
                'page',
                'per_page',
                'service',
                'status',
                'date_from',
                'date_to',
                'search',
            ])
            ->and($parameters['service']['schema']['enum'])->toBe(['tik', 'sarpras'])
            ->and($parameters['status']['schema']['enum'])->toBe([
                'baru',
                'terverifikasi',
                'diproses',
                'selesai',
                'ditolak',
            ])
            ->and($listOperation['responses']['200']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketCollectionResponse');

        $detailOperation = $documentation['paths']['/tickets/{ticket}']['get'];

        expect($detailOperation['responses']['200']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadResponse')
            ->and($detailOperation['responses']['404']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($documentation['components']['schemas']['TicketRead']['required'])
            ->toBe([
                'id',
                'ticket_number',
                'service',
                'category',
                'description',
                'status',
                'reporter',
                'unit',
                'created_at',
            ]);
    } finally {
        File::deleteDirectory($documentationPath);
    }
});

test('it serves the Swagger UI', function (): void {
    $this->get('/api/documentation')
        ->assertOk()
        ->assertSee('SwaggerUIBundle');
});
