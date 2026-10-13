<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListReportsRequest extends FormRequest
{
    /** @return ($key is null ? array{user_id?: string, coordination_id?: int|string, municipality_id?: int|string, typology?: string, page?: int|string, per_page?: int|string} : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('typology') && is_string($this->input('typology'))) {
            $this->merge(['typology' => trim($this->input('typology'))]);
        }
        if ($this->hasAny(['page', 'per_page'])) {
            $this->merge(['page' => $this->input('page', 1), 'per_page' => $this->input('per_page', 10)]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'uuid'],
            'coordination_id' => ['sometimes', 'integer', 'min:1'],
            'municipality_id' => ['sometimes', 'integer', 'min:1'],
            'typology' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
