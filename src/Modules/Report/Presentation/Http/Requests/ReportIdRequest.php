<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;

class ReportIdRequest extends FormRequest
{
    public function authorize(ReportAccessServiceInterface $access): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $access->canRead($user->uuid, (string) $this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    /** @return ($key is null ? array{id: string} : mixed) */
    public function validated(mixed $key = null, mixed $default = null): mixed
    {
        return parent::validated($key, $default);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['id' => ['required', 'uuid']];
    }
}
