<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for dashboard access.
 *
 * Registered as a Gate (not model-based) since dashboards
 * don't correspond to Eloquent models.
 */
class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('dashboard.view');
    }

    public function viewFinancials(User $user): bool
    {
        return $user->hasPermission('dashboard.financials');
    }

    public function viewKpis(User $user): bool
    {
        return $user->hasPermission('dashboard.kpis');
    }
}
