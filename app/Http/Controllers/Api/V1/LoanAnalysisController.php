<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\LoanServiceInterface;
use App\Models\Accounting\Loan;
use Illuminate\Http\JsonResponse;

class LoanAnalysisController extends Controller
{
    public function __construct(
        private LoanServiceInterface $loans,
    ) {}

    /**
     * Loans analysis (Odoo Review › Loans Analysis).
     */
    public function show(): JsonResponse
    {
        $this->authorize('viewAny', Loan::class);

        return $this->success($this->loans->analysis());
    }
}
