<?php

declare(strict_types=1);

namespace App\Domain\Shared\Approval;

use App\Contracts\Shared\ApprovalStrategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Amount-based approval strategy.
 *
 * Approval is required based on document amount thresholds:
 * - Below 10M: No approval needed
 * - 10M-50M: Manager approval
 * - 50M-100M: Director approval
 * - Above 100M: CEO approval
 *
 * Thresholds are configurable via approval.thresholds config.
 */
class AmountBasedApprovalStrategy implements ApprovalStrategy
{
    /** @var array<int, array{threshold: int, role: string}> */
    private array $thresholds;

    public function __construct()
    {
        // Default thresholds (can be configured)
        $this->thresholds = config('approval.thresholds', [
            ['threshold' => 10_000_000, 'role' => 'manager'],       // > 10M needs manager
            ['threshold' => 50_000_000, 'role' => 'director'],      // > 50M needs director
            ['threshold' => 100_000_000, 'role' => 'ceo'],          // > 100M needs CEO
        ]);

        // Ensure thresholds are sorted by amount descending
        usort($this->thresholds, fn ($a, $b) => $b['threshold'] <=> $a['threshold']);
    }

    /**
     * Check if document amount exceeds any threshold.
     */
    public function requiresApproval(Model $document): bool
    {
        $amount = $this->getDocumentAmount($document);

        foreach ($this->thresholds as $tier) {
            if ($amount > $tier['threshold']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get approvers based on document amount.
     */
    public function getApprovers(Model $document): array
    {
        $amount = $this->getDocumentAmount($document);
        $requiredRole = null;

        // Find the highest applicable threshold
        foreach ($this->thresholds as $tier) {
            if ($amount > $tier['threshold']) {
                $requiredRole = $tier['role'];
                break;
            }
        }

        if (! $requiredRole) {
            return [];
        }

        // Get users with the required role
        return User::role($requiredRole)->pluck('id')->toArray();
    }

    /**
     * Check if user is in the list of approvers.
     */
    public function canApprove(Model $document, int $userId): bool
    {
        $approvers = $this->getApprovers($document);

        return in_array($userId, $approvers, true);
    }

    public function getName(): string
    {
        return 'amount_based';
    }

    /**
     * Extract amount from document model.
     */
    private function getDocumentAmount(Model $document): int
    {
        // Try common amount field names
        if (isset($document->total_amount)) {
            return (int) $document->total_amount;
        }

        if (isset($document->grand_total)) {
            return (int) $document->grand_total;
        }

        if (isset($document->amount)) {
            return (int) $document->amount;
        }

        return 0;
    }
}
