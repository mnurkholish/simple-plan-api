<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * @return list<array{path: string, method: string, permission: string}>
 */
function documentedTicketPermissions(): array
{
    return [
        ['path' => '/tickets', 'method' => 'get', 'permission' => 'tickets-access'],
        ['path' => '/tickets', 'method' => 'post', 'permission' => 'tickets-create'],
        ['path' => '/tickets/{ticket}', 'method' => 'get', 'permission' => 'tickets-access'],
        ['path' => '/tickets/{ticket}/verify', 'method' => 'post', 'permission' => 'tickets-verify'],
        ['path' => '/tickets/{ticket}/reject', 'method' => 'post', 'permission' => 'tickets-reject'],
        ['path' => '/tickets/{ticket}/assign', 'method' => 'post', 'permission' => 'tickets-assign'],
        ['path' => '/tickets/{ticket}/handlings', 'method' => 'post', 'permission' => 'tickets-handle'],
    ];
}

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

    foreach (documentedTicketPermissions() as $operation) {
        $documentedOperation = $contract['paths'][$operation['path']][$operation['method']];

        expect($documentedOperation['x-required-permission'])->toBe($operation['permission'])
            ->and($documentedOperation['responses']['403']['$ref'])
            ->toBe('#/components/responses/TicketForbidden');
    }

    expect($contract['paths'])->toHaveKeys($expectedPaths)
        ->and(array_keys($contract['paths']))->toBe($expectedPaths)
        ->and($contract['paths']['/tickets'])->toHaveKeys(['get', 'post'])
        ->and($contract['paths']['/tickets']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketRequest')
        ->and($contract['components']['schemas']['CreateTicketRequest']['required'])
        ->toBe(['service', 'description'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties']['service']['enum'])
        ->toBe(['tik', 'sarpras'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties'])
        ->toHaveKey('asset_id')
        ->and($contract['components']['schemas']['AssignTicketRequest']['required'])
        ->toBe(['priority', 'officer_id'])
        ->and($contract['components']['schemas']['AssignTicketRequest']['properties']['priority']['enum'])
        ->toBe(['critical', 'high', 'medium', 'low'])
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketHandlingRequest')
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['x-required-permission'])
        ->toBe('tickets-handle')
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['responses']['403']['$ref'])
        ->toBe('#/components/responses/TicketForbidden')
        ->and($contract['components']['schemas']['CreateTicketHandlingRequest']['required'])
        ->toBe(['notes', 'status'])
        ->and($contract['components']['schemas']['CreateTicketHandlingRequest']['properties']['status']['enum'])
        ->toBe(['diproses', 'terselesaikan']);
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
                'diklasifikasi',
                'ditugaskan',
                'diproses',
                'eskalasi',
                'terselesaikan',
                'terverifikasi',
                'ditutup',
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

        foreach (documentedTicketPermissions() as $operation) {
            $documentedOperation = $documentation['paths'][$operation['path']][$operation['method']];

            expect($documentedOperation['description'])->toContain($operation['permission'])
                ->and($documentedOperation['responses']['403']['content']['application/json']['schema']['$ref'])
                ->toBe('#/components/schemas/TicketReadError');
        }

        expect($createOperation['security'])->toBe([['sanctum' => []]])
            ->and($createOperation['requestBody']['content']['multipart/form-data']['schema']['$ref'])
            ->toBe('#/components/schemas/CreateTicketRequest')
            ->and($createOperation['responses']['201']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketCreateResponse')
            ->and($documentation['components']['schemas']['CreateTicketRequest']['required'])
            ->toBe(['service', 'description'])
            ->and($documentation['components']['schemas']['CreateTicketRequest']['properties'])
            ->toHaveKey('asset_id')
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
            ->and($handlingOperation['responses']['403']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($handlingOperation['responses']['409']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($documentation['components']['schemas']['CreateTicketHandlingRequest']['required'])
            ->toBe(['notes', 'status'])
            ->and($documentation['components']['schemas']['CreateTicketHandlingRequest']['properties']['status']['enum'])
            ->toBe(['diproses', 'terselesaikan'])
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
                'tik_detail',
                'sarpras_detail',
                'description',
                'status',
                'rejection_reason',
                'priority',
                'asset',
                'reporter',
                'unit',
                'assigned_officer',
                'classified_by',
                'initial_evidence',
                'classified_at',
                'assigned_at',
                'sla_deadline',
                'completed_at',
                'closed_at',
                'created_at',
                'updated_at',
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
