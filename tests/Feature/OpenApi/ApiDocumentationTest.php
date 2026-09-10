<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

test('the Helpdesk OpenAPI contract only documents implemented ticket operations', function (): void {
    $contract = Yaml::parseFile(base_path('openapi.yaml'));

    expect($contract['paths'])->toHaveKeys(['/tickets', '/tickets/{ticket}'])
        ->and(array_keys($contract['paths']))->toBe(['/tickets', '/tickets/{ticket}'])
        ->and($contract['paths']['/tickets'])->toHaveKeys(['get', 'post'])
        ->and($contract['paths']['/tickets']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketRequest')
        ->and($contract['components']['schemas']['CreateTicketRequest']['required'])
        ->toBe(['service', 'unit_id', 'description'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties']['service']['enum'])
        ->toBe(['tik', 'sarpras']);
});

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
        $createOperation = $documentation['paths']['/tickets']['post'];

        expect($createOperation['security'])->toBe([['sanctum' => []]])
            ->and($createOperation['requestBody']['content']['multipart/form-data']['schema']['$ref'])
            ->toBe('#/components/schemas/CreateTicketRequest')
            ->and($createOperation['responses']['201']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketCreateResponse')
            ->and($documentation['components']['schemas']['CreateTicketRequest']['required'])
            ->toBe(['service', 'unit_id', 'description'])
            ->and($detailOperation['responses']['200']['content']['application/json']['schema']['$ref'])
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
                'initial_evidence',
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
