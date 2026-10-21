<?php

declare(strict_types=1);

namespace src\Modules\Attachment\Presentation\Http\Requests;

use Illuminate\Http\UploadedFile;
use src\Modules\Report\Domain\Validation\ReportImageValidation;
use src\Modules\Report\Presentation\Http\Requests\WriteReportIdRequest;

class UploadReportMediaRequest extends WriteReportIdRequest
{
    /** @return ($key is null ? array{id: string, file: UploadedFile, checksum?: string, replaces_id?: string} : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg,application/pdf', 'mimes:png,jpg,jpeg,pdf', 'extensions:png,jpg,jpeg,pdf', 'max:'.intdiv(ReportImageValidation::MAXIMUM_SIZE_BYTES, 1024)],
            'checksum' => ['sometimes', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'replaces_id' => ['sometimes', 'uuid'],
        ];
    }
}
