<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;

readonly class FollowUpManager
{
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    public function scheduleFollowUp(Quotation $quotation, int $daysFromNow = 3): void
    {
        $quotation->next_follow_up_at = now()->addDays($daysFromNow);
        $quotation->save();
    }

    public function recordContact(Quotation $quotation): void
    {
        $quotation->last_contacted_at = now();
        $quotation->follow_up_count = ($quotation->follow_up_count ?? 0) + 1;
        $quotation->save();
    }

    public function calculateAutoFollowUpDate(Quotation $quotation): ?\DateTimeInterface
    {
        if ($quotation->outcome !== null) {
            return null;
        }

        return match ($quotation->status) {
            DocumentStatus::Submitted => now()->addDays(3),
            DocumentStatus::Approved => now()->addDays(7),
            default => null,
        };
    }

    public function needsFollowUp(Quotation $quotation): bool
    {
        if ($quotation->next_follow_up_at === null) {
            return false;
        }

        return $quotation->next_follow_up_at->isPast() && $quotation->outcome === null;
    }

    public function getDaysSinceLastContact(Quotation $quotation): ?int
    {
        if ($quotation->last_contacted_at === null) {
            return null;
        }

        return (int) $quotation->last_contacted_at->diffInDays(now());
    }

    public function isOverdue(Quotation $quotation): bool
    {
        if ($quotation->next_follow_up_at === null) {
            return false;
        }

        return $quotation->next_follow_up_at->isPast();
    }

    public function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_LOW => 'Rendah',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'Tinggi',
            self::PRIORITY_URGENT => 'Mendesak',
            default => 'Normal',
        };
    }

    public function isHighPriority(string $priority): bool
    {
        return in_array($priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT], true);
    }
}
