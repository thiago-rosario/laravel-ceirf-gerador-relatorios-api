<?php

namespace Database\Seeders\Force;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use src\Modules\Report\Domain\Enum\ForceEnum;

class ForceSeeder extends Seeder
{
    public function run(): void
    {
        $codes = DB::table('forces')->pluck('code')->all();

        foreach (ForceEnum::cases() as $force) {
            if (! in_array($force->databaseCode(), $codes, true) && ! in_array($force->value, $codes, true)) {
                DB::table('forces')->insertOrIgnore([
                    'code' => $force->databaseCode(), 'name' => $force->name(), 'is_active' => true,
                ]);
            }
        }
    }
}
