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
        ['path' => '/tickets/{ticket}/classify', 'method' => 'post', 'permission' => 'tickets-verify'],
        ['path' => '/tickets/{ticket}/reject', 'method' => 'post', 'permission' => 'tickets-reject'],
        ['path' => '/tickets/{ticket}/assignee-options', 'method' => 'get', 'permission' => 'tickets-assign'],
        ['path' => '/tickets/{ticket}/assign', 'method' => 'post', 'permission' => 'tickets-assign'],
        ['path' => '/tickets/{ticket}/handlings', 'method' => 'post', 'permission' => 'tickets-handle'],
    ];
}

test('the Helpdesk OpenAPI contract only documents implemented ticket operations', function (): void {
    $contract = Yaml::parseFile(base_path('openapi.yaml'));
    $expectedPaths = [
        '/tickets',
        '/tickets/classification-options',
        '/tickets/{ticket}',
        '/tickets/{ticket}/classify',
        '/tickets/{ticket}/reject',
        '/tickets/{ticket}/assignee-options',
        '/tickets/{ticket}/assign',
        '/tickets/{ticket}/handlings',
        '/tickets/{ticket}/verify',
    ];
    $documentedTicketPaths = array_values(array_filter(
        array_keys($contract['paths']),
        fn (string $path): bool => str_starts_with($path, '/tickets'),
    ));
    $listParameters = collect($contract['paths']['/tickets']['get']['parameters'])->keyBy('name');

    foreach (documentedTicketPermissions() as $operation) {
        $documentedOperation = $contract['paths'][$operation['path']][$operation['method']];

        expect($documentedOperation['x-required-permission'])->toBe($operation['permission'])
            ->and($documentedOperation['responses']['403']['$ref'])
            ->toBe('#/components/responses/TicketForbidden');
    }

    expect($contract['paths'])->toHaveKeys($expectedPaths)
        ->and($documentedTicketPaths)->toBe($expectedPaths)
        ->and($contract['paths']['/tickets'])->toHaveKeys(['get', 'post'])
        ->and($contract['paths']['/tickets']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketRequest')
        ->and($contract['components']['schemas']['CreateTicketRequest']['required'])
        ->toBe(['service', 'description'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties']['service']['enum'])
        ->toBe(['tik', 'sarpras'])
        ->and($contract['components']['schemas']['CreateTicketRequest']['properties'])
        ->toHaveKey('asset_id')
        ->and($contract['paths']['/tickets/{ticket}/classify']['post']['requestBody']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/ClassifyTicketRequest')
        ->and($contract['components']['schemas']['ClassifyTikTicketRequest']['required'])
        ->toBe(['quality_category_id', 'it_tag_id'])
        ->and($contract['components']['schemas']['ClassifySarprasTicketRequest']['required'])
        ->toBe(['sarpras_category_id'])
        ->and($contract['components']['schemas']['AssignTicketRequest']['required'])
        ->toBe(['assigned_officer_id'])
        ->and($contract['components']['schemas']['AssignTicketRequest']['properties']['priority']['enum'])
        ->toBe(['critical', 'high', 'medium', 'low'])
        ->and($listParameters['priority']['schema']['enum'])
        ->toBe(['critical', 'high', 'medium', 'low'])
        ->and($contract['paths']['/tickets/{ticket}/assignee-options']['get']['parameters'][1]['name'])
        ->toBe('search')
        ->and($contract['paths']['/tickets/{ticket}/assignee-options']['get']['responses']['200']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/AssigneeOptionCollectionResponse')
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'])
        ->toBe('#/components/schemas/CreateTicketHandlingRequest')
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['x-required-permission'])
        ->toBe('tickets-handle')
        ->and($contract['paths']['/tickets/{ticket}/handlings']['post']['responses']['403']['$ref'])
        ->toBe('#/components/responses/TicketForbidden')
        ->and($contract['paths']['/tickets/{ticket}/verify']['post']['operationId'])
        ->toBe('verifyTicketResolution')
        ->and($contract['paths']['/tickets/{ticket}/verify']['post'])
        ->not->toHaveKey('x-required-permission')
        ->and($contract['components']['schemas']['CreateTicketHandlingRequest']['required'])
        ->toBe(['notes', 'status', 'started_at', 'completed_at'])
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
                '/tickets/{ticket}/classify',
                '/tickets/{ticket}/reject',
                '/tickets/{ticket}/assignee-options',
                '/tickets/{ticket}/assign',
                '/tickets/{ticket}/handlings',
                '/tickets/{ticket}/verify',
            ])
            ->not->toHaveKey('/api/v1/tickets/{ticket}/handlings');

        $listOperation = $documentation['paths']['/tickets']['get'];
        $parameters = collect($listOperation['parameters'])->keyBy('name');

        expect($listOperation['security'])->toBe([['sanctum' => []]])
            ->and($parameters->keys()->all())->toBe([
                'page',
                'per_page',
                'service',
                'status',
                'priority',
                'date_from',
                'date_to',
                'search',
            ])
            ->and($parameters['service']['schema']['enum'])->toBe(['tik', 'sarpras'])
            ->and($parameters['status']['schema']['enum'])->toBe([
                'baru',
                'diklasifikasi',
                'diproses',
                'terselesaikan',
                'ditutup',
                'ditolak',
            ])
            ->and($parameters['priority']['schema']['enum'])->toBe([
                'critical',
                'high',
                'medium',
                'low',
            ])
            ->and($listOperation['responses']['200']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketCollectionResponse');

        $detailOperation = $documentation['paths']['/tickets/{ticket}']['get'];
        $createOperation = $documentation['paths']['/tickets']['post'];
        $classifyOperation = $documentation['paths']['/tickets/{ticket}/classify']['post'];
        $rejectOperation = $documentation['paths']['/tickets/{ticket}/reject']['post'];
        $assigneeOptionsOperation = $documentation['paths']['/tickets/{ticket}/assignee-options']['get'];
        $assignOperation = $documentation['paths']['/tickets/{ticket}/assign']['post'];
        $handlingOperation = $documentation['paths']['/tickets/{ticket}/handlings']['post'];
        $verifyOperation = $documentation['paths']['/tickets/{ticket}/verify']['post'];

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
            ->and($classifyOperation['requestBody']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/ClassifyTicketRequest')
            ->and($classifyOperation['responses']['409']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($classifyOperation['responses']['422']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/ValidationError')
            ->and($rejectOperation['requestBody']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/RejectTicketRequest')
            ->and($assignOperation['requestBody']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/AssignTicketRequest')
            ->and($documentation['components']['schemas']['AssignTicketRequest']['required'])
            ->toBe(['assigned_officer_id'])
            ->and($documentation['components']['schemas']['AssignTicketRequest']['properties']['priority']['enum'])
            ->toBe(['critical', 'high', 'medium', 'low'])
            ->and($assigneeOptionsOperation['parameters'][1]['name'])
            ->toBe('search')
            ->and($assigneeOptionsOperation['responses']['200']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/AssigneeOptionCollectionResponse')
            ->and($handlingOperation['requestBody']['content']['multipart/form-data']['schema']['$ref'])
            ->toBe('#/components/schemas/CreateTicketHandlingRequest')
            ->and($handlingOperation['responses']['403']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($handlingOperation['responses']['409']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($verifyOperation['responses']['200']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketActionResponse')
            ->and($verifyOperation['responses']['403']['content']['application/json']['schema']['$ref'])
            ->toBe('#/components/schemas/TicketReadError')
            ->and($documentation['components']['schemas']['CreateTicketHandlingRequest']['required'])
            ->toBe(['notes', 'status', 'started_at', 'completed_at'])
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
                'sla_started_at',
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
