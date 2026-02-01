<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for financial and operational reports.
 *
 * Registered as Gates (not model-based) since reports
 * don't correspond to Eloquent models.
 */
class ReportPolicy
{
    public function viewFinancial(User $user): bool
    {
        return $user->hasPermission('reports.financial');
    }

    public function viewTax(User $user): bool
    {
        return $user->hasPermission('reports.tax');
    }

    public function viewAging(User $user): bool
    {
        return $user->hasPermission('reports.aging');
    }

    public function viewCashFlow(User $user): bool
    {
        return $user->hasPermission('reports.cash_flow');
    }

    public function viewProject(User $user): bool
    {
        return $user->hasPermission('reports.project');
    }

    public function viewManufacturing(User $user): bool
    {
        return $user->hasPermission('reports.manufacturing');
    }

    public function viewCogs(User $user): bool
    {
        return $user->hasPermission('reports.cogs');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }
}
