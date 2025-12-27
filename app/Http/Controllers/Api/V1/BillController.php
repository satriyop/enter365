<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreBillRequest;
use App\Http\Requests\Api\V1\UpdateBillRequest;
use App\Http\Resources\Api\V1\BillResource;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillController extends Controller
{
    public function __construct(
        private JournalService $journalService,
        private RecurringService $recurringService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Bill::query()->with(['contact', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('start_date')) {
            $query->where('bill_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('bill_date', '<=', $request->input('end_date'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(bill_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(vendor_invoice_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $bills = $query->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return BillResource::collection($bills);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $bill = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $items = $data['items'];
            unset($data['items']);

            // Calculate subtotal from items
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (int) round($item['quantity'] * $item['unit_price']);
            }

            $taxRate = $data['tax_rate'] ?? 11.00;
            $taxAmount = (int) round($subtotal * ($taxRate / 100));
            $discountAmount = $data['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;

            $bill = Bill::create([
                ...$data,
                'bill_number' => Bill::generateBillNumber(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'status' => Bill::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $amount = (int) round($item['quantity'] * $item['unit_price']);
                BillItem::create([
                    'bill_id' => $bill->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? 'unit',
                    'unit_price' => $item['unit_price'],
                    'amount' => $amount,
                    'expense_account_id' => $item['expense_account_id'] ?? null,
                ]);
            }

            return $bill->load(['contact', 'items']);
        });

        return (new BillResource($bill))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Bill $bill): BillResource
    {
        return new BillResource(
            $bill->load(['contact', 'items.expenseAccount', 'journalEntry.lines.account', 'payments'])
        );
    }

    public function update(UpdateBillRequest $request, Bill $bill): BillResource
    {
        if ($bill->status !== Bill::STATUS_DRAFT) {
            abort(422, 'Hanya tagihan draft yang bisa diubah.');
        }

        return DB::transaction(function () use ($request, $bill) {
            $data = $request->validated();

            if (isset($data['items'])) {
                $items = $data['items'];
                unset($data['items']);

                // Delete existing items and recreate
                $bill->items()->delete();

                $subtotal = 0;
                foreach ($items as $item) {
                    $amount = (int) round($item['quantity'] * $item['unit_price']);
                    $subtotal += $amount;

                    BillItem::create([
                        'bill_id' => $bill->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? 'unit',
                        'unit_price' => $item['unit_price'],
                        'amount' => $amount,
                        'expense_account_id' => $item['expense_account_id'] ?? null,
                    ]);
                }

                $taxRate = $data['tax_rate'] ?? $bill->tax_rate;
                $taxAmount = (int) round($subtotal * ($taxRate / 100));
                $discountAmount = $data['discount_amount'] ?? $bill->discount_amount;
                $totalAmount = $subtotal + $taxAmount - $discountAmount;

                $data['subtotal'] = $subtotal;
                $data['tax_amount'] = $taxAmount;
                $data['total_amount'] = $totalAmount;
            }

            $bill->update($data);

            return new BillResource($bill->fresh(['contact', 'items']));
        });
    }

    public function destroy(Bill $bill): JsonResponse
    {
        if ($bill->status !== Bill::STATUS_DRAFT) {
            abort(422, 'Hanya tagihan draft yang bisa dihapus.');
        }

        if ($bill->payments()->exists()) {
            abort(422, 'Tidak bisa menghapus tagihan yang sudah memiliki pembayaran.');
        }

        $bill->delete();

        return response()->json(['message' => 'Tagihan berhasil dihapus.']);
    }

    public function post(Bill $bill): BillResource|JsonResponse
    {
        if ($bill->status !== Bill::STATUS_DRAFT) {
            abort(422, 'Tagihan sudah diposting.');
        }

        try {
            $this->journalService->postBill($bill);

            return new BillResource($bill->fresh(['contact', 'items', 'journalEntry.lines.account']));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function makeRecurring(MakeRecurringRequest $request, Bill $bill): JsonResponse
    {
        $bill->load('items');

        $template = $this->recurringService->createTemplateFromBill($bill, $request->validated());

        return response()->json([
            'message' => 'Template recurring berhasil dibuat dari tagihan.',
            'data' => new RecurringTemplateResource($template->load('contact')),
        ], 201);
    }
}
