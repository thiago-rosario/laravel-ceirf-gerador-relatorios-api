<?php

declare(strict_types=1);

namespace src\Modules\Report\Domain\Enum;

enum ForceEnum: string
{
    case PM = 'PM';

    case PC = 'PC';

    case CBM = 'CBM';

    case DPT = 'DPT';

    case PC_PM = 'PC-PM';

    case SSP = 'SSP';

    public function databaseCode(): string
    {
        return $this === self::CBM ? 'BM' : $this->value;
    }

    public static function fromDatabase(string $code): self
    {
        return self::from($code === 'BM' ? self::CBM->value : $code);
    }

    public function name(): string
    {
        return match ($this) {
            self::PM => 'Policia Militar',
            self::PC => 'Policia Civil',
            self::CBM => 'Bombeiro militar',
            self::DPT => 'Departamento de policia tecnica',
            self::PC_PM => 'Conjugada',
            self::SSP => 'Secretaria da Segurança Pública',
        };
    }
}
