<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Catalog\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class ListReportTypologiesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }
}
