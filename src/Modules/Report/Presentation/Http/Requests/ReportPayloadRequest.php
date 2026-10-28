<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use src\Modules\Identity\Domain\Enum\UserRoleEnum;
use src\Modules\Identity\Model\User;
use src\Modules\Report\Application\Catalog\Interfaces\Repository\ReportCatalogRepositoryInterface;
use src\Modules\Report\Application\Interfaces\Service\ReportAccessServiceInterface;
use src\Modules\Report\Domain\Catalog\ReportCatalog;
use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Validation\ReportImageValidation;
use Stringable;

/** @phpstan-import-type ReportPayload from \src\Modules\Report\Application\Interfaces\Adapter\ReportInputAdapterInterface */
abstract class ReportPayloadRequest extends FormRequest
{
    public function authorize(ReportAccessServiceInterface $access): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->role !== UserRoleEnum::VIEWER
            && ($this->route('id') === null || $access->canWrite($user->uuid, (string) $this->route('id')));
    }

    /** @return array<string, list<string|Stringable>> */
    protected function sectionRules(ReportCatalogRepositoryInterface $catalogs): array
    {
        $infrastructureFields = array_keys(ReportCatalog::INFRASTRUCTURE);
        $checklistFields = array_keys(ReportCatalog::CHECKLIST);

        $rules = [
            'created_by' => ['prohibited'],
            'status' => ['prohibited'],
            'revision_number' => ['prohibited'],
            'root_report_id' => ['prohibited'],
            'generated_document' => ['prohibited'],
            'report_type_id' => ['prohibited'],
            'coordination_id' => ['prohibited'],
            'cover' => ['sometimes', 'array:municipality_id,force,size,typology,sei_number'],
            'cover.municipality_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('municipalities', 'id')->where('state_code', 'BA')->where('is_active', true)],
            'cover.force' => ['sometimes', 'nullable', 'string', Rule::in($this->filled('cover.force') ? array_column($catalogs->forces(), 'key') : [])],
            'cover.size' => ['sometimes', 'nullable', 'string', Rule::in($this->filled('cover.size') ? array_column($catalogs->typologies(), 'key') : [])],
            'cover.typology' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cover.sei_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'general_information' => ['sometimes', 'array:inspection_date,collaborators,institutional_representatives'],
            'general_information.inspection_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'general_information.collaborators' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'general_information.institutional_representatives' => ['sometimes', 'array', 'list'],
            'general_information.institutional_representatives.*' => ['required', 'array:name,position'],
            'general_information.institutional_representatives.*.name' => ['required', 'string', 'max:255'],
            'general_information.institutional_representatives.*.position' => ['required', 'string', 'max:255'],
            'infrastructure' => ['sometimes', 'array:'.implode(',', $infrastructureFields)],
            'location' => ['sometimes', 'array:location_map,municipality_in_state_map'],
            'pre_implementation' => ['sometimes', 'array:image'],
            'photographic_documentation' => ['sometimes', 'array:images'],
            'photographic_documentation.images' => ['sometimes', 'array', 'list', 'max:20'],
            'attachments' => ['sometimes', 'array:checklist,municipality_location_map,topographic_plan,others'],
            'attachments.checklist' => ['sometimes', 'array:'.implode(',', $checklistFields)],
            'attachments.others' => ['sometimes', 'array', 'list', 'max:20'],
            'conclusion' => ['sometimes', 'array:content'],
            'conclusion.content' => ['sometimes', 'nullable', 'string'],
        ];

        foreach ($infrastructureFields as $field) {
            $rules['infrastructure.'.$field] = ['sometimes', 'nullable', Rule::enum(ChecklistAnswerEnum::class)];
        }
        foreach ($checklistFields as $field) {
            $rules['attachments.checklist.'.$field] = ['sometimes', 'nullable', Rule::enum(ChecklistAnswerEnum::class)];
        }
        foreach (['location.location_map', 'location.municipality_in_state_map', 'pre_implementation.image', 'photographic_documentation.images.*'] as $path) {
            $rules = [...$rules, ...$this->mediaRules($path, true)];
        }
        foreach (['attachments.municipality_location_map', 'attachments.topographic_plan', 'attachments.others.*'] as $path) {
            $rules = [...$rules, ...$this->mediaRules($path, false)];
        }

        return $rules;
    }

    /** @return array<string, list<string>> */
    private function mediaRules(string $path, bool $isImage): array
    {
        $rules = [
            $path => str_ends_with($path, '.*')
                ? ['required', 'array:'.($isImage ? 'id,file,order,caption' : 'id,file,description')]
                : ['sometimes', 'nullable', 'array:'.($isImage ? 'id,file,order,caption' : 'id,file,description')],
            $path.'.id' => ['sometimes', 'uuid'],
            $path.'.file' => ['required_with:'.$path, 'array:storage_identifier,file_name,mime_type,size_bytes,checksum'],
            $path.'.file.storage_identifier' => ['required_with:'.$path, 'string', 'max:255'],
            $path.'.file.file_name' => ['required_with:'.$path, 'string', 'max:255'],
            $path.'.file.mime_type' => ['required_with:'.$path, 'in:image/png,image/jpeg,application/pdf'],
            $path.'.file.size_bytes' => ['required_with:'.$path, 'integer', 'min:1', 'max:'.ReportImageValidation::MAXIMUM_SIZE_BYTES],
            $path.'.file.checksum' => ['required_with:'.$path, 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
        if ($isImage) {
            $rules[$path.'.order'] = ['required_with:'.$path, 'integer', 'min:1'];
            $rules[$path.'.caption'] = ['sometimes', 'nullable', 'string', 'max:500'];
        } else {
            $rules[$path.'.description'] = ['sometimes', 'nullable', 'string', 'max:500'];
        }

        return $rules;
    }
}
