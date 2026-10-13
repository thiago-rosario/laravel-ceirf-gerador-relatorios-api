<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use Illuminate\Validation\Validator;
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
    public function rules(): array
    {
        return ['id' => ['required', 'uuid'], ...$this->sectionRules()];
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
