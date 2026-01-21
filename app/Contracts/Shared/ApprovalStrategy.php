<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use Illuminate\Database\Eloquent\Model;

/**
 * Strategy interface for document approval workflows.
 *
 * Different approval strategies can be configured:
 * - Amount-based: Approval required above certain thresholds
 * - Role-based: Specific roles must approve
 * - Auto-approve: No approval needed
 */
interface ApprovalStrategy
{
    /**
     * Check if document requires approval.
     */
    public function requiresApproval(Model $document): bool;

    /**
     * Get user IDs of required approvers.
     *
     * @return array<int> User IDs
     */
    public function getApprovers(Model $document): array;

    /**
     * Check if a specific user can approve the document.
     */
    public function canApprove(Model $document, int $userId): bool;

    /**
     * Get strategy name for configuration.
     */
    public function getName(): string;
}
