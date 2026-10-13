<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

class ReportPdfRequest extends ReportIdRequest
{
    /** @return ($key is null ? array{id: string, disposition?: string} : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...parent::rules(), 'disposition' => ['sometimes', 'string', 'in:inline,attachment']];
    }
}
