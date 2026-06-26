<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

enum TaxStatus: string
{
    case Monotributo = 'monotributo';
    case ResponsableInscripto = 'responsable_inscripto';
    case Exento = 'exento';
    case ConsumidorFinal = 'consumidor_final';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
