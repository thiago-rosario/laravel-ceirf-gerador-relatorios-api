<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use Illuminate\Validation\Validator;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportForcesUsecaseInterface;
use src\Modules\Report\Application\Catalog\Interfaces\Usecase\ListReportTypologiesUsecaseInterface;
use Stringable;

/** @phpstan-import-type ReportUpdatePayload from \src\Modules\Report\Application\Interfaces\Adapter\ReportInputAdapterInterface */
class UpdateReportRequest extends ReportPayloadRequest
{
    /** @return ($key is null ? ReportUpdatePayload : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    /** @return array<string, list<string|Stringable>> */
    public function rules(ListReportForcesUsecaseInterface $forces, ListReportTypologiesUsecaseInterface $typologies): array
    {
        return ['id' => ['required', 'uuid'], ...$this->sectionRules($forces, $typologies)];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['cover', 'general_information', 'location', 'infrastructure', 'pre_implementation', 'photographic_documentation', 'attachments', 'conclusion'])) {
                    $validator->errors()->add('report', 'Informe ao menos uma seção do relatório.');
                }
            },
        ];
    }
}
