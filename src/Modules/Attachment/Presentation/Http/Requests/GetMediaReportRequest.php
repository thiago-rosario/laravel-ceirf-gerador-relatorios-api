<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Presentation\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use src\Modules\Report\Presentation\Http\Requests\ReportIdRequest;
use src\Modules\Shared\Helper\ResponseJsend;

class GetMediaReportRequest extends ReportIdRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id'), 'mediaId' => $this->route('mediaId')]);
    }

    /** @return ($key is null ? array{id: string, mediaId: string, disposition?: string} : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'mediaId' => ['required', 'uuid'],
            'disposition' => ['sometimes', 'string', 'in:inline,attachment'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException((new ResponseJsend(
            data: ['errors' => $validator->errors()->toArray()],
            status: ResponseJsend::STATUS_FAIL,
            message: 'Os dados informados são inválidos.',
            code: 422,
        ))->toJsonResponse(422));
    }
}
