<?php

declare(strict_types=1);

use src\Modules\Report\Application\Catalog\DTO\ListReportChecklistOutputDTO;
use src\Modules\Report\Infra\Catalog\Adapter\ListReportChecklistDataAdapter;

test('maps optional input fields into the catalog input DTO', function (array $data, array $expected): void {
    $adapter = new ListReportChecklistDataAdapter;

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
    $adapter = new ListReportChecklistDataAdapter;
    $output = new ListReportChecklistOutputDTO(items: [
        ['code' => 'state_owned_land', 'name' => 'Terreno pertence ao Estado.'],
    ]);

    $response = $adapter->toArray($output);

    expect($response)->toBe(['checklist' => [
        ['key' => 'state_owned_land', 'label' => 'Terreno pertence ao Estado.'],
    ]]);
});
