<?php

namespace Database\Seeders\Locations;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        // Fonte: Governo da Bahia (SEPLAN/SECULT), territórios de identidade.
        // Municípios organizados por território, usando o nome cadastrado no banco.
        $municipalitiesByTerritory = [
            'Bacia do Jacuípe' => [
                'Baixa Grande', 'Capela do Alto Alegre', 'Capim Grosso', 'Gavião', 'Ipirá', 'Mairi',
                'Nova Fátima', 'Pé de Serra', 'Pintadas', 'Quixabeira', 'Riachão do Jacuípe',
                'São José do Jacuípe', 'Serra Preta', 'Várzea da Roça', 'Várzea do Poço',
            ],
            'Bacia do Paramirim' => [
                'Boquira', 'Botuporã', 'Caturama', 'Érico Cardoso', 'Ibipitanga', 'Macaúbas', 'Paramirim',
                'Rio do Pires',
            ],
            'Bacia do Rio Corrente' => [
                'Brejolândia', 'Canápolis', 'Cocos', 'Coribe', 'Correntina', 'Jaborandi',
                'Santa Maria da Vitória', 'Santana', 'São Félix do Coribe', 'Serra Dourada',
                'Tabocas do Brejo Velho',
            ],
            'Bacia do Rio Grande' => [
                'Angical', 'Baianópolis', 'Barreiras', 'Buritirama', 'Catolândia', 'Cotegipe', 'Cristópolis',
                'Formosa do Rio Preto', 'Luís Eduardo Magalhães', 'Mansidão', 'Riachão das Neves',
                'Santa Rita de Cássia', 'São Desidério', 'Wanderley',
            ],
            'Baixo Sul' => [
                'Aratuípe', 'Cairu', 'Camamu', 'Gandu', 'Ibirapitanga', 'Igrapiúna', 'Ituberá', 'Jaguaripe',
                'Nilo Peçanha', 'Piraí do Norte', 'Presidente Tancredo Neves', 'Taperoá', 'Teolândia', 'Valença',
                'Wenceslau Guimarães',
            ],
            'Chapada Diamantina' => [
                'Abaíra', 'Andaraí', 'Barra da Estiva', 'Boninal', 'Bonito', 'Ibicoara', 'Ibitiara', 'Iramaia',
                'Iraquara', 'Itaetê', 'Jussiape', 'Lençóis', 'Marcionílio Souza', 'Morro do Chapéu', 'Mucugê',
                'Nova Redenção', 'Novo Horizonte', 'Palmeiras', 'Piatã', 'Rio de Contas', 'Seabra',
                'Souto Soares', 'Utinga', 'Wagner',
            ],
            'Costa do Descobrimento' => [
                'Belmonte', 'Eunápolis', 'Guaratinga', 'Itabela', 'Itagimirim', 'Itapebi', 'Porto Seguro',
                'Santa Cruz Cabrália',
            ],
            'Extremo Sul' => [
                'Alcobaça', 'Caravelas', 'Ibirapuã', 'Itamaraju', 'Itanhém', 'Jucuruçu', 'Lajedão',
                'Medeiros Neto', 'Mucuri', 'Nova Viçosa', 'Prado', 'Teixeira de Freitas', 'Vereda',
            ],
            'Irecê' => [
                'América Dourada', 'Barra do Mendes', 'Barro Alto', 'Cafarnaum', 'Canarana', 'Central',
                'Gentio do Ouro', 'Ibipeba', 'Ibititá', 'Ipupiara', 'Irecê', 'Itaguaçu da Bahia', 'João Dourado',
                'Jussara', 'Lapão', 'Mulungu do Morro', 'Presidente Dutra', 'São Gabriel', 'Uibaí', 'Xique-Xique',
            ],
            'Itaparica' => [
                'Abaré', 'Chorrochó', 'Glória', 'Macururé', 'Paulo Afonso', 'Rodelas',
            ],
            'Litoral Norte e Agreste Baiano' => [
                'Acajutiba', 'Alagoinhas', 'Aporá', 'Araçás', 'Aramari', 'Cardeal da Silva', 'Catu', 'Conde',
                'Crisópolis', 'Entre Rios', 'Esplanada', 'Inhambupe', 'Itanagra', 'Itapicuru', 'Jandaíra',
                'Olindina', 'Ouriçangas', 'Pedrão', 'Rio Real', 'Sátiro Dias',
            ],
            'Litoral Sul' => [
                'Almadina', 'Arataca', 'Aurelino Leal', 'Barro Preto', 'Buerarema', 'Camacan', 'Canavieiras',
                'Coaraci', 'Floresta Azul', 'Ibicaraí', 'Ilhéus', 'Itabuna', 'Itacaré', 'Itaju do Colônia',
                'Itajuípe', 'Itapé', 'Itapitanga', 'Jussari', 'Maraú', 'Mascote', 'Pau Brasil', 'Santa Luzia',
                'São José da Vitória', 'Ubaitaba', 'Una', 'Uruçuca',
            ],
            'Médio Rio de Contas' => [
                'Aiquara', 'Apuarema', 'Barra do Rocha', 'Boa Nova', 'Dário Meira', 'Gongogi', 'Ibirataia',
                'Ipiaú', 'Itagi', 'Itagibá', 'Itamari', 'Jequié', 'Jitaúna', 'Manoel Vitorino', 'Nova Ibiá',
                'Ubatã',
            ],
            'Médio Sudoeste da Bahia' => [
                'Caatiba', 'Firmino Alves', 'Ibicuí', 'Iguaí', 'Itambé', 'Itapetinga', 'Itarantim', 'Itororó',
                'Macarani', 'Maiquinique', 'Nova Canaã', 'Potiraguá', 'Santa Cruz da Vitória',
            ],
            'Metropolitano de Salvador' => [
                'Camaçari', 'Candeias', 'Dias d\'Ávila', 'Itaparica', 'Lauro de Freitas', 'Madre de Deus',
                'Mata de São João', 'Pojuca', 'Salvador', 'São Francisco do Conde', 'São Sebastião do Passé',
                'Simões Filho', 'Vera Cruz',
            ],
            'Piemonte da Diamantina' => [
                'Caém', 'Jacobina', 'Miguel Calmon', 'Mirangaba', 'Ourolândia', 'Saúde', 'Serrolândia',
                'Umburanas', 'Várzea Nova',
            ],
            'Piemonte do Paraguaçu' => [
                'Boa Vista do Tupim', 'Iaçu', 'Ibiquera', 'Itaberaba', 'Itatim', 'Lajedinho', 'Macajuba',
                'Mundo Novo', 'Piritiba', 'Rafael Jambeiro', 'Ruy Barbosa', 'Santa Terezinha', 'Tapiramutá',
            ],
            'Piemonte Norte do Itapicuru' => [
                'Andorinha', 'Antônio Gonçalves', 'Caldeirão Grande', 'Campo Formoso', 'Filadélfia', 'Jaguarari',
                'Pindobaçu', 'Ponto Novo', 'Senhor do Bonfim',
            ],
            'Portal do Sertão' => [
                'Água Fria', 'Amélia Rodrigues', 'Anguera', 'Antônio Cardoso', 'Conceição da Feira',
                'Conceição do Jacuípe', 'Coração de Maria', 'Feira de Santana', 'Ipecaetá', 'Irará',
                'Santa Bárbara', 'Santanópolis', 'Santo Estêvão', 'São Gonçalo dos Campos', 'Tanquinho',
                'Teodoro Sampaio', 'Terra Nova',
            ],
            'Recôncavo' => [
                'Cabaceiras do Paraguaçu', 'Cachoeira', 'Castro Alves', 'Conceição do Almeida', 'Cruz das Almas',
                'Dom Macedo Costa', 'Governador Mangabeira', 'Maragogipe', 'Muniz Ferreira', 'Muritiba', 'Nazaré',
                'Salinas da Margarida', 'Santo Amaro', 'Santo Antônio de Jesus', 'São Felipe', 'São Félix',
                'Sapeaçu', 'Saubara', 'Varzedo',
            ],
            'Semiárido Nordeste II' => [
                'Adustina', 'Antas', 'Banzaê', 'Cícero Dantas', 'Cipó', 'Coronel João Sá', 'Euclides da Cunha',
                'Fátima', 'Heliópolis', 'Jeremoabo', 'Nova Soure', 'Novo Triunfo', 'Paripiranga',
                'Pedro Alexandre', 'Ribeira do Amparo', 'Ribeira do Pombal', 'Santa Brígida', 'Sítio do Quinto',
            ],
            'Sertão do São Francisco' => [
                'Campo Alegre de Lourdes', 'Canudos', 'Casa Nova', 'Curaçá', 'Juazeiro', 'Pilão Arcado',
                'Remanso', 'Sento Sé', 'Sobradinho', 'Uauá',
            ],
            'Sertão Produtivo' => [
                'Brumado', 'Caculé', 'Caetité', 'Candiba', 'Contendas do Sincorá', 'Dom Basílio', 'Guanambi',
                'Ibiassucê', 'Ituaçu', 'Iuiu', 'Lagoa Real', 'Livramento de Nossa Senhora', 'Malhada de Pedras',
                'Palmas de Monte Alto', 'Pindaí', 'Rio do Antônio', 'Sebastião Laranjeiras', 'Tanhaçu',
                'Tanque Novo', 'Urandi',
            ],
            'Sisal' => [
                'Araci', 'Barrocas', 'Biritinga', 'Candeal', 'Cansanção', 'Conceição do Coité', 'Ichu', 'Itiúba',
                'Lamarão', 'Monte Santo', 'Nordestina', 'Queimadas', 'Quijingue', 'Retirolândia', 'Santaluz',
                'São Domingos', 'Serrinha', 'Teofilândia', 'Tucano', 'Valente',
            ],
            'Sudoeste Baiano' => [
                'Anagé', 'Aracatu', 'Barra do Choça', 'Belo Campo', 'Bom Jesus da Serra', 'Caetanos',
                'Cândido Sales', 'Caraíbas', 'Condeúba', 'Cordeiros', 'Encruzilhada', 'Guajeru', 'Jacaraci',
                'Licínio de Almeida', 'Maetinga', 'Mirante', 'Mortugaba', 'Piripá', 'Planalto', 'Poções',
                'Presidente Jânio Quadros', 'Ribeirão do Largo', 'Tremedal', 'Vitória da Conquista',
            ],
            'Vale do Jiquiriçá' => [
                'Amargosa', 'Brejões', 'Cravolândia', 'Elísio Medrado', 'Irajuba', 'Itaquara', 'Itiruçu',
                'Jaguaquara', 'Jiquiriçá', 'Lafaiete Coutinho', 'Laje', 'Lajedo do Tabocal', 'Maracás',
                'Milagres', 'Mutuípe', 'Nova Itarana', 'Planaltino', 'Santa Inês', 'São Miguel das Matas',
                'Ubaíra',
            ],
            'Velho Chico' => [
                'Barra', 'Bom Jesus da Lapa', 'Brotas de Macaúbas', 'Carinhanha', 'Feira da Mata', 'Ibotirama',
                'Igaporã', 'Malhada', 'Matina', 'Morpará', 'Muquém do São Francisco', 'Oliveira dos Brejinhos',
                'Paratinga', 'Riacho de Santana', 'Serra do Ramalho', 'Sítio do Mato',
            ],
        ];

        $territoryIds = DB::table('identity_territories')->pluck('id', 'name');

        foreach (array_keys($municipalitiesByTerritory) as $territoryName) {
            if (! $territoryIds->has($territoryName)) {
                throw new RuntimeException(
                    "Território de identidade não cadastrado: {$territoryName}"
                );
            }
        }

        DB::transaction(function () use ($municipalitiesByTerritory, $territoryIds): void {
            foreach ($municipalitiesByTerritory as $territoryName => $municipalityNames) {
                $territoryId = $territoryIds->get($territoryName);

                foreach ($municipalityNames as $municipalityName) {
                    DB::table('municipalities')->updateOrInsert(
                        [
                            'name' => $municipalityName,
                            'state_code' => 'BA',
                        ],
                        [
                            'identity_territory_id' => $territoryId,
                            'is_active' => true,
                        ]
                    );
                }
            }
        });
    }
}
