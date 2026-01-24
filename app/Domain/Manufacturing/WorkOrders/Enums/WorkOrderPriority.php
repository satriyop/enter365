<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Enums;

enum WorkOrderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Normal => 'Normal',
            self::High => 'Tinggi',
            self::Urgent => 'Mendesak',
        };
    }
}
