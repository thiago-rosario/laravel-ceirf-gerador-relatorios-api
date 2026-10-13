<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use src\Modules\Report\Application\DTO\ReportDataDTO;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;

class ReportResource extends JsonResource
{
    private bool $includeEditorialMedia = false;

    public function __construct(private readonly ReportDataDTO $report)
    {
        parent::__construct($report);
    }

    public static function forEditorial(ReportDataDTO $report): self
    {
        return self::withEditorialMedia($report, true);
    }

    public static function forReader(ReportDataDTO $report, string $userId, ReportAccessServiceInterface $access): self
    {
        return self::withEditorialMedia($report, $access->canWrite($userId, $report->id));
    }

    /**
     * @param  list<ReportDataDTO>  $reports
     * @return list<array<string, mixed>>
     */
    public static function collectionForReader(array $reports, string $userId, ReportAccessServiceInterface $access, Request $request): array
    {
        $editorialReportIds = array_fill_keys($access->editorialReportIds($userId,
            array_map(fn (ReportDataDTO $report): string => $report->id, $reports)), true);

        return array_map(fn (ReportDataDTO $report): array => self::withEditorialMedia($report, isset($editorialReportIds[$report->id]))->resolve($request), $reports);
    }

    private static function withEditorialMedia(ReportDataDTO $report, bool $includeEditorialMedia): self
    {
        $resource = new self($report);
        $resource->includeEditorialMedia = $includeEditorialMedia;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->serializeData($this->report);
        if (is_array($data['generated_document'] ?? null)) {
            unset($data['generated_document']['storage_identifier']);
        }

        if (! $this->includeEditorialMedia) {
            unset($data['uploaded_images']);
            $data = $this->withoutStorageIdentifiers($data);
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function withoutStorageIdentifiers(array $data): array
    {
        unset($data['storage_identifier']);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->withoutStorageIdentifiers($value);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeData(object $data): array
    {
        $result = [];
        foreach (get_object_vars($data) as $key => $value) {
            $result[Str::snake($key)] = $this->serializeValue($value);
        }

        return $result;
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            return array_map($this->serializeValue(...), $value);
        }

        return is_object($value) ? $this->serializeData($value) : $value;
    }
}
