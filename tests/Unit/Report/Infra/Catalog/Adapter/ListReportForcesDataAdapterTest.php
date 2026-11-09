<?php

declare(strict_types=1);

use src\Modules\Report\Application\Catalog\DTO\ListReportForcesOutputDTO;
use src\Modules\Report\Infra\Catalog\Adapter\ListReportForcesDataAdapter;

test('maps optional input fields into the force input DTO', function (array $data, array $expected): void {
    $adapter = new ListReportForcesDataAdapter;

    $input = $adapter->fromArray($data);

    expect([$input->id, $input->code, $input->name])->toBe($expected);
})->with([
    'listing without filters' => [[], [null, null, null]],
    'all future filter fields' => [
        ['id' => '10', 'code' => 'PM', 'name' => 'Polícia Militar'],
        ['10', 'PM', 'Polícia Militar'],
    ],
    'only a name provided' => [['name' => 'Polícia Civil'], [null, null, 'Polícia Civil']],
]);

test('transforms normalized force output into the public response fields', function (): void {
    $adapter = new ListReportForcesDataAdapter;
    $output = new ListReportForcesOutputDTO(items: [
        ['id' => 20, 'code' => 'CBM', 'name' => 'Bombeiro cadastrado'],
    ]);

    $response = $adapter->toArray($output);

    expect($response)->toBe(['forces' => [
        ['key' => 'CBM', 'label' => 'CBM', 'name' => 'Bombeiro cadastrado'],
    ]]);
});
