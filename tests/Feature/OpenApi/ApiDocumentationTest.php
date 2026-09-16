<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

test('the Helpdesk OpenAPI contract only documents implemented ticket operations', function (): void {
    $contract = Yaml::parseFile(base_path('openapi.yaml'));
    $expectedPaths = [
        '/tickets',
        '/tickets/{ticket}',
        '/tickets/{ticket}/verify',
        '/tickets/{ticket}/reject',
        '/tickets/{ticket}/assign',
        '/tickets/{ticket}/handlings',
    ];

    expect($contract['paths'])->toHaveKeys($expectedPaths)
        ->and(array_keys($contract['paths']))->toBe($expectedPaths)
        ->and($contract['paths']['/tickets'])->toHaveKeys(['get', 'post'])
        ->and($contract['paths']['/tickets']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketRequest')
        ->and($contract['components']['schemas']['CreateTicketRequest']['required'])
        ->toBe(['service', 'unit_id', 'description'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties']['service']['enum'])
        ->toBe(['tik', 'sarpras'])
        ->and($contract['components']['schemas']['AssignTicketRequest']['required'])
        ->toBe(['priority', 'officer_id'])
        ->and($contract['components']['schemas']['AssignTicketRequest']['properties']['priority']['enum'])
        ->toBe(['critical', 'high', 'medium', 'low'])
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketHandlingRequest')
        ->and($contract['components']['schemas']['CreateTicketHandlingRequest']['required'])
        ->toBe(['notes', 'status'])
        ->and($contract['components']['schemas']['CreateTicketHandlingRequest']['properties']['status']['enum'])
        ->toBe(['diproses', 'selesai']);
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
            ->toHaveKeys([
                '/tickets',
                '/tickets/{ticket}',
                '/tickets/{ticket}/verify',
                '/tickets/{ticket}/reject',
                '/tickets/{ticket}/assign',
                '/tickets/{ticket}/handlings',
            ]);

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
        $verifyOperation = $documentation['paths']['/tickets/{ticket}/verify']['post'];
        $rejectOperation = $documentation['paths']['/tickets/{ticket}/reject']['post'];
        $assignOperation = $documentation['paths']['/tickets/{ticket}/assign']['post'];
        $handlingOperation = $documentation['paths']['/tickets/{ticket}/handlings']['post'];

        expect($createOperation['security'])->toBe([['sanctum' => []]])
            ->and($createOperation['requestBody']['content']['multipart/form-data']['schema']['$ref'])
            ->toBe('#/components/schemas/CreateTicketRequest')
            ->and($createOperation['responses']['201']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketCreateResponse')
            ->and($documentation['components']['schemas']['CreateTicketRequest']['required'])
            ->toBe(['service', 'unit_id', 'description'])
            ->and($verifyOperation['responses']['409']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($rejectOperation['requestBody']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/RejectTicketRequest')
            ->and($assignOperation['requestBody']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/AssignTicketRequest')
            ->and($documentation['components']['schemas']['AssignTicketRequest']['required'])
            ->toBe(['priority', 'officer_id'])
            ->and($documentation['components']['schemas']['AssignTicketRequest']['properties']['priority']['enum'])
            ->toBe(['critical', 'high', 'medium', 'low'])
            ->and($handlingOperation['requestBody']['content']['multipart/form-data']['schema']['$ref'])
            ->toBe('#/components/schemas/CreateTicketHandlingRequest')
            ->and($handlingOperation['responses']['409']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($documentation['components']['schemas']['CreateTicketHandlingRequest']['required'])
            ->toBe(['notes', 'status'])
            ->and($documentation['components']['schemas']['CreateTicketHandlingRequest']['properties']['status']['enum'])
            ->toBe(['diproses', 'selesai'])
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
                'rejection_reason',
                'priority',
                'reporter',
                'unit',
                'assigned_officer',
                'initial_evidence',
                'completed_at',
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
