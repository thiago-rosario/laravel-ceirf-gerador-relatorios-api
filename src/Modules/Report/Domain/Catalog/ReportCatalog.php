<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Catalog;

class ReportCatalog
{
    public const array INFRASTRUCTURE = [
        'water_network' => 'Rede de Água',
        'high_voltage_network' => 'Rede Elétrica de Alta',
        'low_voltage_network' => 'Rede Elétrica de Baixa',
        'sewage_network' => 'Rede de Esgoto',
        'telephony' => 'Telefonia',
        'public_lighting' => 'Iluminação Pública',
        'internet' => 'Internet',
        'waste_collection' => 'Coleta de Lixo',
        'paving' => 'Pavimentação',
        'existing_buildings' => 'Edificações existente',
    ];

    public const array CHECKLIST = [
        'sei_construction_request' => 'Solicitação via Sistema Eletrônico de Informação – SEI para construção da unidade.',
        'sei_land_and_typology_identification' => 'Indicação no processo SEI do terreno e da tipologia da unidade a ser construída.',
        'state_owned_land' => 'Terreno escriturado em nome do Estado.',
        'simov_legalized' => 'Terreno possui código SIMOV com o status de legalizado.',
        'compatible_dimensions' => 'Dimensões do Terreno compatíveis com a tipologia da unidade a ser construída.',
        'slope_or_level_risk' => 'Terreno próximo a encostas ou com cota de implantação inferior a edificações vizinhas ou nível da rua que gerem vulnerabilidade da unidade.',
        'stormwater_drainage' => 'Existência de sistema de drenagem pluvial no acesso à unidade.',
        'flood_history' => 'Terreno em área com histórico de alagamentos.',
        'electricity_supply' => 'Existência de sistema de fornecimento de energia elétrica.',
        'water_supply' => 'Existência de sistema de fornecimento de água potável.',
        'sewage_supply' => 'Existência de sistema de esgotamento sanitário.',
        'paving_and_sidewalk' => 'Existência de pavimentação e passeio no acesso à unidade.',
        'regular_waste_collection' => 'Existência de serviço de coleta regular de resíduos sólidos.',
        'demolition_required' => 'Necessidade de demolição de edificação existente no terreno.',
        'easy_public_access' => 'Localização de fácil acesso ao cidadão.',
        'domain_strip_or_non_buildable_area' => 'Terreno em faixa de domínio ou em área não edificante.',
        'technical_feasibility_report' => 'Elaboração de relatório técnico conclusivo sobre a viabilidade do terreno.',
        'report_attached_to_sei' => 'Encarte do relatório no processo SEI, para conhecimento das Forças e Gabinete do Secretário.',
        'works_dashboard_updated' => 'Atualização do Painel de Obras.',
        'environmental_protection_area' => 'Verificar se o terreno se encontra em área de preservação ambiental.',
    ];
}
