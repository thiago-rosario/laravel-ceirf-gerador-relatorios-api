<?php

declare(strict_types=1);

use src\Modules\Report\Application\Catalog\DTO\ListReportInfrastructureOutputDTO;
use src\Modules\Report\Infra\Catalog\Adapter\ListReportInfrastructureDataAdapter;

test('maps optional input fields into the catalog input DTO', function (array $data, array $expected): void {
    $adapter = new ListReportInfrastructureDataAdapter;

    $input = $adapter->fromArray($data);

    expect([$input->id, $input->code, $input->name])->toBe($expected);
})->with([
    'listing without filters' => [[], [null, null, null]],
    'all future filter fields' => [
        ['id' => '10', 'code' => 'item-code', 'name' => 'Descrição futura'],
        ['10', 'item-code', 'Descrição futura'],
    ],
    'only a name provided' => [['name' => 'Descrição futura'], [null, null, 'Descrição futura']],
]);

test('transforms normalized catalog output into the public response fields', function (): void {
    $adapter = new ListReportInfrastructureDataAdapter;
    $output = new ListReportInfrastructureOutputDTO(items: [
        ['code' => 'water_network', 'name' => 'Rede de Água'],
    ]);

    $response = $adapter->toArray($output);

    expect($response)->toBe(['infrastructure' => [
        ['key' => 'water_network', 'label' => 'Rede de Água'],
    ]]);
});
