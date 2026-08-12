<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaymentReminderRequest;
use App\Http\Resources\Api\V1\PaymentReminderResource;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\PaymentReminder;
use App\Services\Sales\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentReminderController extends Controller
{
    public function __construct(
        private ReminderService $reminderService
    ) {}

    /**
     * List all payment reminders with filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PaymentReminder::query()->with(['remindable', 'contact']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('remindable_type')) {
            $morphClass = match ($request->input('remindable_type')) {
                'invoice' => Invoice::class,
                'bill' => Bill::class,
                default => null,
            };
            if ($morphClass) {
                $query->where('remindable_type', $morphClass);
            }
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }

        $query->orderBy(
            $request->input('sort_by', 'scheduled_date'),
            $request->input('sort_dir', 'asc')
        );

        $reminders = $query->paginate($request->input('per_page', 25));

        return PaymentReminderResource::collection($reminders);
    }

    /**
     * Get summary counts for the reminder dashboard.
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'pending' => PaymentReminder::where('status', PaymentReminder::STATUS_PENDING)->count(),
            'due_today' => PaymentReminder::where('status', PaymentReminder::STATUS_PENDING)
                ->whereDate('scheduled_date', '<=', today())->count(),
            'sent_this_week' => PaymentReminder::where('status', PaymentReminder::STATUS_SENT)
                ->whereDate('sent_date', '>=', now()->startOfWeek())->count(),
            'failed' => PaymentReminder::where('status', PaymentReminder::STATUS_FAILED)->count(),
            'total_overdue_invoices' => Invoice::where('status', DocumentStatus::Sent)
                ->whereDate('due_date', '<', today())->count(),
        ]);
    }

    /**
     * Show a single payment reminder.
     */
    public function show(PaymentReminder $paymentReminder): PaymentReminderResource
    {
        $paymentReminder->load(['remindable', 'contact', 'creator']);

        return new PaymentReminderResource($paymentReminder);
    }

    /**
     * List reminders for a specific invoice.
     */
    public function forInvoice(Request $request, Invoice $invoice): AnonymousResourceCollection
    {
        $reminders = PaymentReminder::query()
            ->where('remindable_type', Invoice::class)
            ->where('remindable_id', $invoice->id)
            ->with('contact')
            ->orderBy('scheduled_date')
            ->get();

        return PaymentReminderResource::collection($reminders);
    }

    /**
     * Manually schedule a custom reminder for an invoice.
     */
    public function store(StorePaymentReminderRequest $request, Invoice $invoice): JsonResponse
    {
        $reminder = $this->reminderService->scheduleManualInvoiceReminder(
            $invoice,
            $request->validated(),
            $request->user()?->id
        );

        return (new PaymentReminderResource($reminder->load('contact')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Manually send a pending reminder.
     */
    public function send(PaymentReminder $paymentReminder): PaymentReminderResource|JsonResponse
    {
        if (! $paymentReminder->isPending()) {
            return response()->json([
                'message' => 'Hanya pengingat dengan status pending yang dapat dikirim.',
            ], 422);
        }

        $success = $this->reminderService->sendReminder($paymentReminder);

        if (! $success) {
            return response()->json([
                'message' => 'Gagal mengirim pengingat. Dokumen mungkin sudah dibayar atau dibatalkan.',
            ], 422);
        }

        return new PaymentReminderResource($paymentReminder->fresh(['remindable', 'contact']));
    }

    /**
     * Cancel a pending reminder.
     */
    public function cancel(PaymentReminder $paymentReminder): PaymentReminderResource|JsonResponse
    {
        try {
            $reminder = $this->reminderService->cancelReminder($paymentReminder);
        } catch (\App\Exceptions\Domain\BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return new PaymentReminderResource($reminder);
    }

    /**
     * Quick action: create and send an immediate reminder for an invoice.
     */
    public function sendImmediate(Request $request, Invoice $invoice): PaymentReminderResource|JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'channel' => ['nullable', 'string', 'in:email,whatsapp'],
        ]);

        $reminder = $this->reminderService->createAndSendImmediateInvoiceReminder(
            $invoice,
            $validated,
            $request->user()?->id
        );

        $reminder = $reminder->fresh(['remindable', 'contact']) ?? $reminder;

        if (! $reminder->wasSent()) {
            return response()->json([
                'message' => 'Gagal mengirim pengingat.',
            ], 422);
        }

        return new PaymentReminderResource($reminder);
    }
}
