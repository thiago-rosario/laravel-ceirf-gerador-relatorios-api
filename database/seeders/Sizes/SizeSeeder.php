<?php

namespace Database\Seeders\Sizes;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $names = DB::table('sizes')->pluck('name')->map(fn (string $name): string => mb_strtoupper($name))->all();

        foreach (ReportSizeEnum::cases() as $size) {
            if (! in_array($size->value, $names, true)) {
                DB::table('sizes')->insertOrIgnore(['name' => $size->value]);
            }
        }
    }
}
