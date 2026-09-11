<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Accounting\AccountReconcileServiceInterface;
use App\Http\Requests\Api\V1\StoreAccountReconcileRequest;
use App\Http\Resources\Api\V1\AccountReconciliationResource;
use App\Http\Resources\Api\V1\ReconcileLineResource;
use App\Models\Accounting\AccountReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountReconcileController extends Controller
{
    public function __construct(
        private AccountReconcileServiceInterface $reconcile,
    ) {}

    /**
     * List past general reconciliations (not bank-statement matching).
     *
     * @queryParam account_id int Filter by reconcilable account. Example: 12
     * @queryParam per_page int Items per page. Default: 50. Example: 25
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AccountReconciliation::class);

        return AccountReconciliationResource::collection(
            $this->reconcile->history([
                'account_id' => $request->integer('account_id') ?: null,
                'per_page' => $request->integer('per_page', 50),
            ])
        );
    }

    /**
     * Reconcilable accounts with outstanding residual totals.
     */
    public function accounts(): JsonResponse
    {
        $this->authorize('viewAny', AccountReconciliation::class);

        return $this->success($this->reconcile->accounts()->all());
    }

    /**
     * Unreconciled posted journal lines for an account.
     *
     * @queryParam account_id int required Account to reconcile. Example: 12
     * @queryParam partner_id int Optional partner filter. Example: 4
     */
    public function lines(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReconciliation::class);

        $lines = $this->reconcile->lines([
            'account_id' => $request->integer('account_id'),
            'partner_id' => $request->integer('partner_id') ?: null,
        ]);

        return $this->success(ReconcileLineResource::collection($lines)->resolve());
    }

    public function store(StoreAccountReconcileRequest $request): JsonResponse
    {
        $this->authorize('create', AccountReconciliation::class);

        $reconciliation = $this->reconcile->reconcile($request->validated());

        return (new AccountReconciliationResource($reconciliation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AccountReconciliation $accountReconciliation): AccountReconciliationResource
    {
        $this->authorize('view', $accountReconciliation);

        return new AccountReconciliationResource(
            $accountReconciliation->load(['account', 'partner', 'items.journalEntryLine.journalEntry'])
        );
    }

    /**
     * Undo a general reconciliation and restore line residuals.
     */
    public function unreconcile(AccountReconciliation $accountReconciliation): JsonResponse
    {
        $this->authorize('update', $accountReconciliation);

        $this->reconcile->unreconcile($accountReconciliation);

        return $this->success(null, 'Rekonsiliasi berhasil dibatalkan.');
    }
}
