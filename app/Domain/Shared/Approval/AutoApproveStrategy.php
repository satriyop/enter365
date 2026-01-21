<?php

declare(strict_types=1);

namespace App\Domain\Shared\Approval;

use App\Contracts\Shared\ApprovalStrategy;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-approve strategy: No approval required.
 *
 * Documents are automatically approved without
 * requiring explicit approval from anyone.
 */
class AutoApproveStrategy implements ApprovalStrategy
{
    /**
     * Never requires approval.
     */
    public function requiresApproval(Model $document): bool
    {
        return false;
    }

    /**
     * No approvers needed.
     */
    public function getApprovers(Model $document): array
    {
        return [];
    }

    /**
     * Anyone can approve (irrelevant since no approval needed).
     */
    public function canApprove(Model $document, int $userId): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'auto_approve';
    }
}
