<?php

declare(strict_types=1);

namespace src\Modules\Report\Infra\Trait;

use src\Modules\Report\Domain\Enum\ChecklistAnswerEnum;
use src\Modules\Report\Domain\Enum\ForceEnum;
use src\Modules\Report\Domain\Enum\ReportSizeEnum;
use src\Modules\Report\Domain\ValueObject\MunicipalityValueObject;
use src\Modules\Report\Domain\ValueObject\ReportChecklistValueObject;
use src\Modules\Report\Domain\ValueObject\ReportCoverValueObject;
use src\Modules\Report\Domain\ValueObject\ReportInfrastructureValueObject;
use src\Modules\Report\Domain\ValueObject\SeiNumberValueObject;
use stdClass;
use UnexpectedValueException;

trait MapsLegacyReportSectionsTrait
{
    private function cover(stdClass $row): ?ReportCoverValueObject
    {
        if ($row->municipality_id === null && $row->force_id === null && $row->size_id === null
            && $row->typology === null && $row->sei_number === null) {
            return null;
        }

        return new ReportCoverValueObject(
            municipality: $row->municipality_id === null ? null : new MunicipalityValueObject(
                id: (int) $row->municipality_id,
                name: (string) $row->municipality_name,
                stateCode: (string) $row->municipality_state_code,
            ),
            force: $row->force_code === null ? null : ForceEnum::fromDatabase((string) $row->force_code),
            size: $row->size_name === null ? null : ReportSizeEnum::from(mb_strtoupper((string) $row->size_name)),
            typology: (string) ($row->typology ?? ''),
            seiNumber: $row->sei_number === null ? null : new SeiNumberValueObject((string) $row->sei_number),
        );
    }

    private function infrastructure(stdClass $row): ?ReportInfrastructureValueObject
    {
        $infrastructure = new ReportInfrastructureValueObject(
            waterNetwork: $this->answer($row->infrastructure_water_network),
            highVoltageNetwork: $this->answer($row->infrastructure_high_voltage_network),
            lowVoltageNetwork: $this->answer($row->infrastructure_low_voltage_network),
            sewageNetwork: $this->answer($row->infrastructure_sewage_network),
            telephony: $this->answer($row->infrastructure_telephony),
            publicLighting: $this->answer($row->infrastructure_public_lighting),
            internet: $this->answer($row->infrastructure_internet),
            wasteCollection: $this->answer($row->infrastructure_waste_collection),
            paving: $this->answer($row->infrastructure_paving),
            existingBuildings: $this->answer($row->infrastructure_existing_buildings),
        );

        return array_filter($infrastructure->answers()) === [] ? null : $infrastructure;
    }

    private function checklist(stdClass $row): ?ReportChecklistValueObject
    {
        $checklist = new ReportChecklistValueObject(
            seiConstructionRequest: $this->answer($row->checklist_sei_construction_request),
            seiLandAndTypologyIdentification: $this->answer($row->checklist_sei_land_and_typology_identification),
            stateOwnedLand: $this->answer($row->checklist_state_owned_land),
            simovLegalized: $this->answer($row->checklist_simov_legalized),
            compatibleDimensions: $this->answer($row->checklist_compatible_dimensions),
            slopeOrLevelRisk: $this->answer($row->checklist_slope_or_level_risk),
            stormwaterDrainage: $this->answer($row->checklist_stormwater_drainage),
            floodHistory: $this->answer($row->checklist_flood_history),
            electricitySupply: $this->answer($row->checklist_electricity_supply),
            waterSupply: $this->answer($row->checklist_water_supply),
            sewageSupply: $this->answer($row->checklist_sewage_supply),
            pavingAndSidewalk: $this->answer($row->checklist_paving_and_sidewalk),
            regularWasteCollection: $this->answer($row->checklist_regular_waste_collection),
            demolitionRequired: $this->answer($row->checklist_demolition_required),
            easyPublicAccess: $this->answer($row->checklist_easy_public_access),
            domainStripOrNonBuildableArea: $this->answer($row->checklist_domain_strip_or_non_buildable_area),
            technicalFeasibilityReport: $this->answer($row->checklist_technical_feasibility_report),
            reportAttachedToSei: $this->answer($row->checklist_report_attached_to_sei),
            worksDashboardUpdated: $this->answer($row->checklist_works_dashboard_updated),
            environmentalProtectionArea: $this->answer($row->checklist_environmental_protection_area),
        );

        return array_filter($checklist->answers()) === [] ? null : $checklist;
    }

    private function answer(mixed $value): ?ChecklistAnswerEnum
    {
        return match ($value) {
            null => null,
            0, '0' => ChecklistAnswerEnum::NO,
            1, '1' => ChecklistAnswerEnum::YES,
            2, '2' => ChecklistAnswerEnum::NOT_APPLICABLE,
            default => throw new UnexpectedValueException('O relatório legado possui uma resposta de checklist inválida.'),
        };
    }
}
