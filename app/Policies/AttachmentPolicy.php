<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shared\Attachment;
use App\Models\User;

/**
 * Authorization for attachment operations.
 *
 * Attachments are cross-domain (attached to invoices, bills, etc.),
 * so rather than a permission group, we use simple rules:
 * - Any authenticated user can view and create attachments
 * - Only the uploader or an admin can delete an attachment
 */
class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->id === $attachment->uploaded_by
            || $user->hasPermission('settings.company_profile');
    }
}
