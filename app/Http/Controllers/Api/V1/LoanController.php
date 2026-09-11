<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\LoanServiceInterface;
use App\Http\Requests\Api\V1\StoreLoanRequest;
use App\Http\Requests\Api\V1\UpdateLoanRequest;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Accounting\Loan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanController extends Controller
{
    public function __construct(
        private LoanServiceInterface $loans,
    ) {}

    /**
     * List the loan register (Odoo Accounting › Loans).
     *
     * @queryParam search string Search by code or name. Example: BCA
     * @queryParam status string Filter by draft, running, or closed. Example: running
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Loan::class);

        $query = Loan::query()->with('contact')->orderByDesc('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($inner) use ($search) {
                $inner->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return LoanResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $this->authorize('create', Loan::class);

        $loan = $this->loans->create($request->validated());

        return (new LoanResource($loan->load(['contact', 'lines'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        return new LoanResource($loan->load(['contact', 'lines']));
    }

    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        return new LoanResource($this->loans->update($loan, $request->validated()));
    }

    public function destroy(Loan $loan): JsonResponse
    {
        $this->authorize('delete', $loan);

        $this->loans->delete($loan);

        return $this->deleted('Pinjaman berhasil dihapus.');
    }

    /**
     * Confirm a draft loan: generate the amortization board and post disbursement.
     */
    public function confirm(Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        return new LoanResource($this->loans->confirm($loan));
    }

    /**
     * Post the next installment (principal + interest) to the journal.
     */
    public function postInstallment(Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        return new LoanResource($this->loans->postNextInstallment($loan));
    }
}
