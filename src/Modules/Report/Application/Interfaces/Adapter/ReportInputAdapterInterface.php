<?php

declare(strict_types=1);

namespace src\Modules\Report\Application\Interfaces\Adapter;

use src\Modules\Report\Application\DTO\CreateReportInputDTO;
use src\Modules\Report\Application\DTO\UpdateReportInputDTO;

/**
 * @phpstan-type FileInput array{storage_identifier: string, file_name: string, mime_type: string, size_bytes: int|string, checksum: string}
 * @phpstan-type ImageInput array{id?: string, file: FileInput, order: int|string, caption?: string|null}
 * @phpstan-type AttachmentInput array{id?: string, file: FileInput, description?: string|null}
 * @phpstan-type CoverInput array{municipality_id?: int|string|null, force?: string|null, size?: string|null, typology?: string|null, sei_number?: string|null}
 * @phpstan-type AttachmentsInput array{checklist?: array<string, string|null>, municipality_location_map?: AttachmentInput|null, topographic_plan?: AttachmentInput|null, others?: list<AttachmentInput>}
 * @phpstan-type ReportPayload array{
 *     coordination_id?: int|string,
 *     report_type_id?: int|string,
 *     cover?: CoverInput,
 *     general_information?: array{inspection_date?: string|null, collaborators?: string|null},
 *     location?: array{location_map?: ImageInput|null, municipality_in_state_map?: ImageInput|null},
 *     infrastructure?: array<string, string|null>,
 *     pre_implementation?: array{image?: ImageInput|null},
 *     photographic_documentation?: array{images?: list<ImageInput>},
 *     attachments?: AttachmentsInput,
 *     conclusion?: array{content?: string|null}
 * }
 * @phpstan-type ReportUpdatePayload array{
 *     id: string,
 *     cover?: CoverInput,
 *     general_information?: array{inspection_date?: string|null, collaborators?: string|null},
 *     location?: array{location_map?: ImageInput|null, municipality_in_state_map?: ImageInput|null},
 *     infrastructure?: array<string, string|null>,
 *     pre_implementation?: array{image?: ImageInput|null},
 *     photographic_documentation?: array{images?: list<ImageInput>},
 *     attachments?: AttachmentsInput,
 *     conclusion?: array{content?: string|null}
 * }
 */
interface ReportInputAdapterInterface
{
    /** @param ReportPayload $data */
    public function create(array $data, string $createdBy): CreateReportInputDTO;

    /** @param ReportUpdatePayload $data */
    public function update(array $data): UpdateReportInputDTO;
}
