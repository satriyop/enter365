<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;
use App\Models\Projects\Project;

/**
 * Work Order number generator with project context.
 */
class WorkOrderNumberGenerator
{
    public function __construct(
        private DocumentNumberGeneratorInterface $generator
    ) {}

    /**
     * Generate WO number with optional project prefix.
     */
    public function generate(?Project $project = null): string
    {
        if ($project) {
            $prefix = $project->project_number.'-WO-';
            $lastWo = Project::query()
                ->where('id', $project->id)
                ->first()
                ->workOrders()
                ->orderByDesc('id')
                ->first();

            if ($lastWo && preg_match('/-WO-(\d+)$/', $lastWo->wo_number, $matches)) {
                $sequence = (int) $matches[1] + 1;
            } else {
                $sequence = 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        }

        // Fallback to standard generator without project
        return $this->generator->generate('WO-'.now()->format('Ym').'-', 'work_orders', 'wo_number');
    }
}
