<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Enums;

enum WorkOrderType: string
{
    case Production = 'production';
    case Assembly = 'assembly';
    case Installation = 'installation';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Produksi',
            self::Assembly => 'Perakitan',
            self::Installation => 'Instalasi',
            self::Maintenance => 'Pemeliharaan',
        };
    }
}
