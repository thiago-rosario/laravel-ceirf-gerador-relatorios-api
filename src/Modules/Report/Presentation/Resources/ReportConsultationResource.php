<?php

declare(strict_types=1);

namespace src\Modules\Report\Presentation\Resources;

use Illuminate\Http\Request;

class ReportConsultationResource extends ReportResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_intersect_key(parent::toArray($request), array_flip([
            'id', 'root_report_id', 'parent_report_id', 'revision_number', 'status', 'revision_label',
            'is_generated', 'is_revision', 'created_at', 'updated_at', 'cover', 'general_information',
            'generated_document', 'coordination_id', 'report_type_id',
        ]));
    }
}
