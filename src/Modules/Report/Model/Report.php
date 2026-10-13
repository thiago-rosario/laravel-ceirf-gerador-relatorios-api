<?php

declare(strict_types=1);

namespace src\Modules\Report\Model;

use Carbon\CarbonImmutable;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use src\Modules\Report\Domain\Enum\ReportStatusEnum;

/**
 * @property int $id
 * @property string $uuid
 * @property int $report_series_id
 * @property int|null $report_type_id
 * @property int|null $coordination_id
 * @property int|null $previous_report_id
 * @property int $revision_number
 * @property int $created_by
 * @property int|null $municipality_id
 * @property ReportStatusEnum $status
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => ReportStatusEnum::class,
            'revision_number' => 'integer',
            'coordination_id' => 'integer',
            'report_type_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
    }
}
