<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\Reports\AccountingReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountingReviewController extends Controller
{
    public function __construct(
        private AccountingReviewService $review,
    ) {}

    /**
     * Line-level journal items (Odoo Review › Journal Items).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     * @queryParam journal_id int Filter by journal. Example: 1
     * @queryParam account_id int Filter by account. Example: 12
     * @queryParam partner_id int Filter by partner. Example: 4
     * @queryParam is_posted bool Posted only. Example: 1
     * @queryParam search string Search number, description, account. Example: 1-1100
     * @queryParam per_page int Default 50. Example: 25
     */
    public function journalItems(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        $page = $this->review->journalItems($request->only([
            'from', 'to', 'journal_id', 'account_id', 'partner_id', 'is_posted', 'search', 'per_page',
        ]));

        $rows = [];
        foreach ($page->items() as $row) {
            $date = data_get($row, 'entry_date');
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d');
            }

            $rows[] = [
                'id' => (int) data_get($row, 'id'),
                'journal_entry_id' => (int) data_get($row, 'journal_entry_id'),
                'entry_number' => data_get($row, 'entry_number'),
                'entry_date' => $date,
                'journal_id' => data_get($row, 'journal_id') ? (int) data_get($row, 'journal_id') : null,
                'journal_name' => data_get($row, 'journal_name'),
                'journal_type' => data_get($row, 'journal_type'),
                'account_id' => (int) data_get($row, 'account_id'),
                'account_code' => data_get($row, 'account_code'),
                'account_name' => data_get($row, 'account_name'),
                'partner_id' => data_get($row, 'partner_id') ? (int) data_get($row, 'partner_id') : null,
                'partner_name' => data_get($row, 'partner_name'),
                'description' => data_get($row, 'description'),
                'debit' => (int) data_get($row, 'debit'),
                'credit' => (int) data_get($row, 'credit'),
                'reconciled_amount' => (int) data_get($row, 'reconciled_amount'),
                'residual' => (int) data_get($row, 'residual'),
                'is_posted' => (bool) data_get($row, 'is_posted'),
            ];
        }

        return $this->success([
            'report_name' => 'Journal Items',
            'rows' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Posted journal register by journal (Odoo Review › Journal Audit).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     * @queryParam journal_id int Filter by journal. Example: 1
     */
    public function journalAudit(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        return $this->success($this->review->journalAudit($request->only(['from', 'to', 'journal_id'])));
    }

    /**
     * Unposted journals and draft invoices/bills (Odoo Review › Working Files).
     */
    public function workingFiles(): JsonResponse
    {
        $this->authorize('viewAny', JournalEntry::class);

        return $this->success($this->review->workingFiles());
    }

    /**
     * Accounting document change log (Odoo Review › Audit Trail).
     *
     * @queryParam from date Inclusive start. Example: 2026-01-01
     * @queryParam to date Inclusive end. Example: 2026-03-31
     * @queryParam action string created, updated, posted, voided, … Example: posted
     * @queryParam search string Label, user, or notes. Example: INV
     * @queryParam per_page int Default 50. Example: 25
     */
    public function auditTrail(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JournalEntry::class);

        return AuditLogResource::collection(
            $this->review->auditTrail($request->only(['from', 'to', 'action', 'auditable_type', 'search', 'per_page']))
        );
    }
}
