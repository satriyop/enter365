<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MakeRecurringRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Http\Resources\Api\V1\RecurringTemplateResource;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\RecurringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function __construct(
        private JournalService $journalService,
        private RecurringService $recurringService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::query()->with(['contact', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }

        if ($request->has('start_date')) {
            $query->where('invoice_date', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('invoice_date', '<=', $request->input('end_date'));
        }

        if ($request->has('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(invoice_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('contact', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $invoices = $query->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 25));

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = DB::transaction(function () use ($request) {
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

            $invoice = Invoice::create([
                ...$data,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'status' => Invoice::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $amount = (int) round($item['quantity'] * $item['unit_price']);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? 'unit',
                    'unit_price' => $item['unit_price'],
                    'amount' => $amount,
                    'revenue_account_id' => $item['revenue_account_id'] ?? null,
                ]);
            }

            return $invoice->load(['contact', 'items']);
        });

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource(
            $invoice->load(['contact', 'items.revenueAccount', 'journalEntry.lines.account', 'payments'])
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            abort(422, 'Hanya faktur draft yang bisa diubah.');
        }

        return DB::transaction(function () use ($request, $invoice) {
            $data = $request->validated();
            
            if (isset($data['items'])) {
                $items = $data['items'];
                unset($data['items']);

                // Delete existing items and recreate
                $invoice->items()->delete();

                $subtotal = 0;
                foreach ($items as $item) {
                    $amount = (int) round($item['quantity'] * $item['unit_price']);
                    $subtotal += $amount;
                    
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? 'unit',
                        'unit_price' => $item['unit_price'],
                        'amount' => $amount,
                        'revenue_account_id' => $item['revenue_account_id'] ?? null,
                    ]);
                }

                $taxRate = $data['tax_rate'] ?? $invoice->tax_rate;
                $taxAmount = (int) round($subtotal * ($taxRate / 100));
                $discountAmount = $data['discount_amount'] ?? $invoice->discount_amount;
                $totalAmount = $subtotal + $taxAmount - $discountAmount;

                $data['subtotal'] = $subtotal;
                $data['tax_amount'] = $taxAmount;
                $data['total_amount'] = $totalAmount;
            }

            $invoice->update($data);

            return new InvoiceResource($invoice->fresh(['contact', 'items']));
        });
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            abort(422, 'Hanya faktur draft yang bisa dihapus.');
        }

        if ($invoice->payments()->exists()) {
            abort(422, 'Tidak bisa menghapus faktur yang sudah memiliki pembayaran.');
        }

        $invoice->delete();

        return response()->json(['message' => 'Faktur berhasil dihapus.']);
    }

    public function post(Invoice $invoice): InvoiceResource|JsonResponse
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            abort(422, 'Faktur sudah diposting.');
        }

        try {
            $this->journalService->postInvoice($invoice);

            return new InvoiceResource($invoice->fresh(['contact', 'items', 'journalEntry.lines.account']));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function makeRecurring(MakeRecurringRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->load('items');

        $template = $this->recurringService->createTemplateFromInvoice($invoice, $request->validated());

        return response()->json([
            'message' => 'Template recurring berhasil dibuat dari faktur.',
            'data' => new RecurringTemplateResource($template->load('contact')),
        ], 201);
    }
}
