# Phase 7: Add "Accepted" Status

> **Type**: 📋 NEW FEATURE
> **Status**: Documented for Future Development
> **Priority**: Low
> **Effort**: Large (1-2 weeks)
> **Dependencies**: Phase 6 (Sent Status), Customer Portal

## Problem Statement

Currently, there's no distinction between:
- **Quotation sent** to customer
- **Customer accepted** the quotation
- **Invoice created** (converted)

The flow jumps directly from Approved (or Sent) to Converted, missing the explicit customer acceptance step.

### Business Impact

1. **No PO capture** - Cannot require customer PO before invoicing
2. **No acceptance tracking** - Don't know when/how customer accepted
3. **Terms not acknowledged** - No record that customer agreed to terms
4. **Compliance gap** - Some industries require signed quotation acceptance

## Proposed Solution

### Enhanced Workflow (with Phase 6)

```
┌────────┐   ┌───────────┐   ┌──────────┐   ┌──────┐   ┌──────────┐   ┌───────────┐
│ DRAFT  │──▶│ SUBMITTED │──▶│ APPROVED │──▶│ SENT │──▶│ ACCEPTED │──▶│ CONVERTED │
└────────┘   └───────────┘   └──────────┘   └──────┘   └──────────┘   └───────────┘
                                               │            │
                                               ▼            ▼
                                           DECLINED       LOST
```

### New Status: `Accepted`

| Attribute | Value |
|-----------|-------|
| Status Name | `Accepted` |
| Enum Value | `accepted` |
| Can Edit | No |
| Can Delete | No |
| Can Cancel | Yes (with reason) |
| Next States | Converted, Cancelled, Lost |

### What Happens at Acceptance

1. Customer explicitly accepts quotation
2. (Optional) Customer provides PO number
3. (Optional) Customer signs/acknowledges terms
4. Quotation locked for conversion
5. Sales team notified to create invoice

## Detailed Implementation

### 1. Database Migration

```php
// database/migrations/xxxx_add_acceptance_tracking_to_quotations.php

public function up(): void
{
    Schema::table('quotations', function (Blueprint $table) {
        // Acceptance tracking
        $table->timestamp('accepted_at')->nullable()->after('sent_at');
        $table->string('accepted_via')->nullable()->after('accepted_at'); // portal, email, phone, in_person
        $table->string('accepted_by_name')->nullable()->after('accepted_via');
        $table->string('accepted_by_email')->nullable()->after('accepted_by_name');

        // Customer PO
        $table->string('customer_po_number')->nullable()->after('accepted_by_email');
        $table->date('customer_po_date')->nullable()->after('customer_po_number');

        // Terms acceptance
        $table->boolean('terms_accepted')->default(false)->after('customer_po_date');
        $table->timestamp('terms_accepted_at')->nullable()->after('terms_accepted');
        $table->ipAddress('terms_accepted_ip')->nullable()->after('terms_accepted_at');

        // Digital signature (optional)
        $table->text('signature_data')->nullable()->after('terms_accepted_ip'); // Base64 or reference
        $table->string('signature_type')->nullable()->after('signature_data'); // drawn, typed, uploaded
    });
}
```

### 2. State Machine Updates

```php
// app/Domain/Sales/Quotations/QuotationStateMachine.php

protected function defineTransitions(): array
{
    return [
        // ... earlier states
        DocumentStatus::Sent->value => [
            DocumentStatus::Accepted,    // Customer accepts
            DocumentStatus::Declined,    // Customer declines
            DocumentStatus::Cancelled,
            DocumentStatus::Expired,
        ],
        DocumentStatus::Accepted->value => [  // NEW
            DocumentStatus::Converted,   // Create invoice
            DocumentStatus::Cancelled,   // Cancelled after acceptance (rare)
        ],
        // ... rest
    ];
}

public function canAccept(): bool
{
    return $this->currentStatus === DocumentStatus::Sent;
}

public function canConvert(): bool
{
    // Updated: Can now only convert from Accepted (not Approved)
    // If Phase 6 not implemented, keep converting from Approved
    $allowedStatuses = [DocumentStatus::Accepted];

    // Fallback for Phase 6 not implemented
    if (!$this->hasSentStatus()) {
        $allowedStatuses[] = DocumentStatus::Approved;
    }

    if (!in_array($this->currentStatus, $allowedStatuses)) {
        return false;
    }

    // ... existing checks (not already converted, not lost, etc.)
}
```

### 3. Service Layer

```php
// app/Services/Sales/QuotationService.php

public function accept(Quotation $quotation, array $data): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canAccept()) {
        return ServiceResult::failure('Only sent quotations can be accepted.');
    }

    return DB::transaction(function () use ($quotation, $data) {
        // Acceptance details
        $quotation->accepted_at = now();
        $quotation->accepted_via = $data['via'] ?? 'manual';
        $quotation->accepted_by_name = $data['accepted_by_name'] ?? null;
        $quotation->accepted_by_email = $data['accepted_by_email'] ?? null;

        // Customer PO (optional but encouraged)
        if (isset($data['po_number'])) {
            $quotation->customer_po_number = $data['po_number'];
            $quotation->customer_po_date = $data['po_date'] ?? now();
        }

        // Terms acceptance
        if ($data['terms_accepted'] ?? false) {
            $quotation->terms_accepted = true;
            $quotation->terms_accepted_at = now();
            $quotation->terms_accepted_ip = request()->ip();
        }

        // Digital signature (if provided)
        if (isset($data['signature'])) {
            $quotation->signature_data = $data['signature'];
            $quotation->signature_type = $data['signature_type'] ?? 'drawn';
        }

        // Transition status
        $quotation->transitionTo(DocumentStatus::Accepted);

        // Mark outcome as Won (automatically)
        $quotation->outcome = 'won';
        $quotation->won_reason = 'customer_accepted';
        $quotation->outcome_at = now();

        // Clear follow-ups
        $quotation->next_follow_up_at = null;

        $quotation->save();

        event(new QuotationAccepted($quotation, $data));

        return ServiceResult::success($quotation, 'Quotation accepted successfully.');
    });
}

/**
 * Accept and immediately convert to invoice
 */
public function acceptAndConvert(Quotation $quotation, array $data): ServiceResult
{
    $acceptResult = $this->accept($quotation, $data);

    if ($acceptResult->failed()) {
        return $acceptResult;
    }

    return $this->convertToInvoice($quotation->fresh());
}
```

### 4. Controller Updates

```php
// app/Http/Controllers/Api/V1/QuotationController.php

public function accept(AcceptQuotationRequest $request, Quotation $quotation)
{
    $result = $this->quotationService->accept($quotation, $request->validated());

    if ($result->failed()) {
        return response()->json(['message' => $result->message], 422);
    }

    return new QuotationResource($result->data);
}

public function acceptAndConvert(AcceptQuotationRequest $request, Quotation $quotation)
{
    $result = $this->quotationService->acceptAndConvert(
        $quotation,
        $request->validated()
    );

    if ($result->failed()) {
        return response()->json(['message' => $result->message], 422);
    }

    return response()->json([
        'quotation' => new QuotationResource($quotation->fresh()),
        'invoice' => new InvoiceResource($quotation->convertedToInvoice),
    ]);
}
```

### 5. Request Validation

```php
// app/Http/Requests/Api/V1/AcceptQuotationRequest.php

class AcceptQuotationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'via' => ['sometimes', 'string', 'in:portal,email,phone,in_person,manual'],
            'accepted_by_name' => ['sometimes', 'string', 'max:255'],
            'accepted_by_email' => ['sometimes', 'email', 'max:255'],

            // PO
            'po_number' => ['sometimes', 'string', 'max:100'],
            'po_date' => ['sometimes', 'date'],

            // Terms
            'terms_accepted' => ['sometimes', 'boolean'],

            // Signature
            'signature' => ['sometimes', 'string'], // Base64
            'signature_type' => ['sometimes', 'string', 'in:drawn,typed,uploaded'],
        ];
    }
}
```

### 6. API Endpoints

```php
// routes/api.php

Route::prefix('quotations/{quotation}')->group(function () {
    // ... existing routes
    Route::post('accept', [QuotationController::class, 'accept']);
    Route::post('accept-and-convert', [QuotationController::class, 'acceptAndConvert']);
});
```

### 7. Events

```php
// app/Domain/Sales/Quotations/Events/QuotationAccepted.php

class QuotationAccepted extends QuotationStatusChanged
{
    public function __construct(
        Quotation $quotation,
        public readonly array $acceptanceData,
    ) {
        parent::__construct($quotation, DocumentStatus::Sent, DocumentStatus::Accepted);
    }

    public function getCustomerPo(): ?string
    {
        return $this->acceptanceData['po_number'] ?? null;
    }

    public function hasSignature(): bool
    {
        return isset($this->acceptanceData['signature']);
    }
}
```

### 8. Customer Portal Acceptance Page

```php
// app/Http/Controllers/Portal/QuotationPortalController.php

public function showAcceptForm(string $uuid)
{
    $quotation = Quotation::where('uuid', $uuid)
        ->where('status', DocumentStatus::Sent)
        ->firstOrFail();

    return view('portal.quotation-accept', [
        'quotation' => $quotation,
        'termsUrl' => route('terms'),
    ]);
}

public function processAcceptance(Request $request, string $uuid)
{
    $quotation = Quotation::where('uuid', $uuid)->firstOrFail();

    $validated = $request->validate([
        'accepted_by_name' => ['required', 'string', 'max:255'],
        'accepted_by_email' => ['required', 'email'],
        'po_number' => ['nullable', 'string', 'max:100'],
        'terms_accepted' => ['required', 'accepted'],
        'signature' => ['required_if:signature_required,true', 'string'],
    ]);

    $result = $this->quotationService->accept($quotation, [
        ...$validated,
        'via' => 'portal',
    ]);

    if ($result->failed()) {
        return back()->withErrors(['acceptance' => $result->message]);
    }

    return view('portal.quotation-accepted', compact('quotation'));
}
```

### 9. Customer Portal View

```blade
{{-- resources/views/portal/quotation-accept.blade.php --}}

<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">
        Accept Quotation {{ $quotation->quotation_number }}
    </h1>

    <div class="bg-gray-50 p-4 rounded mb-6">
        <p class="text-lg font-semibold">Total: {{ $quotation->formatted_total }}</p>
        <p class="text-sm text-gray-600">Valid until: {{ $quotation->valid_until->format('M d, Y') }}</p>
    </div>

    <form action="{{ route('quotation.portal.accept', $quotation->uuid) }}" method="POST">
        @csrf

        <div class="mb-4">
            <label class="block font-medium">Your Name *</label>
            <input type="text" name="accepted_by_name" required class="w-full border rounded p-2">
        </div>

        <div class="mb-4">
            <label class="block font-medium">Your Email *</label>
            <input type="email" name="accepted_by_email" required class="w-full border rounded p-2">
        </div>

        <div class="mb-4">
            <label class="block font-medium">PO Number (optional)</label>
            <input type="text" name="po_number" class="w-full border rounded p-2">
        </div>

        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="terms_accepted" value="1" required class="mr-2">
                I accept the <a href="{{ $termsUrl }}" target="_blank" class="text-blue-600 underline ml-1">Terms & Conditions</a>
            </label>
        </div>

        @if($quotation->requires_signature)
        <div class="mb-4">
            <label class="block font-medium">Signature *</label>
            <div id="signature-pad" class="border rounded h-32"></div>
            <input type="hidden" name="signature" id="signature-input">
        </div>
        @endif

        <button type="submit" class="w-full bg-green-600 text-white py-3 rounded font-semibold">
            Accept Quotation
        </button>
    </form>
</div>
```

## Configuration Options

```php
// config/quotation.php

return [
    'acceptance' => [
        // Require PO number for acceptance
        'require_po' => env('QUOTATION_REQUIRE_PO', false),

        // Require terms acceptance
        'require_terms' => env('QUOTATION_REQUIRE_TERMS', true),

        // Require digital signature
        'require_signature' => env('QUOTATION_REQUIRE_SIGNATURE', false),

        // Auto-convert to invoice on acceptance
        'auto_convert' => env('QUOTATION_AUTO_CONVERT', false),

        // Notify sales team on acceptance
        'notify_sales' => env('QUOTATION_NOTIFY_ON_ACCEPT', true),
    ],
];
```

## Testing Requirements

```php
it('can accept sent quotation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Sent,
    ]);

    $result = $this->quotationService->accept($quotation, [
        'via' => 'phone',
        'accepted_by_name' => 'John Customer',
        'po_number' => 'PO-12345',
    ]);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh())
        ->status->toBe(DocumentStatus::Accepted)
        ->customer_po_number->toBe('PO-12345')
        ->outcome->toBe('won');
});

it('cannot accept draft quotation', function () {
    $quotation = Quotation::factory()->create(); // Draft

    $result = $this->quotationService->accept($quotation, []);

    expect($result->failed())->toBeTrue();
});

it('can convert accepted quotation', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->create(['status' => DocumentStatus::Accepted]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
});

it('cannot convert sent quotation directly when accepted status exists', function () {
    // If Accepted status is implemented, Sent cannot convert directly
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->create(['status' => DocumentStatus::Sent]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->failed())->toBeTrue();
    expect($result->message)->toContain('accepted');
});

it('accept and convert in one step', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->create(['status' => DocumentStatus::Sent]);

    $result = $this->quotationService->acceptAndConvert($quotation, [
        'accepted_by_name' => 'John',
        'terms_accepted' => true,
    ]);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
});

it('customer can accept via portal', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Sent,
        'uuid' => Str::uuid(),
    ]);

    $this->post(route('quotation.portal.accept', $quotation->uuid), [
        'accepted_by_name' => 'Jane Customer',
        'accepted_by_email' => 'jane@example.com',
        'terms_accepted' => true,
    ])->assertRedirect();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Accepted);
});
```

## UI/UX Requirements

### Acceptance Confirmation (Internal)

```
┌─────────────────────────────────────────────────┐
│ Record Customer Acceptance                       │
├─────────────────────────────────────────────────┤
│                                                 │
│ Quotation: QUO-2026-001                         │
│ Customer: PT Example Corp                       │
│ Total: Rp 150.000.000                           │
│                                                 │
│ ─────────────────────────────────────────────── │
│                                                 │
│ Accepted via:                                   │
│ ( ) Customer Portal                             │
│ (•) Phone Call                                  │
│ ( ) Email                                       │
│ ( ) In Person                                   │
│                                                 │
│ Accepted by: [John Smith          ]             │
│ Email:       [john@customer.com   ]             │
│                                                 │
│ Customer PO#: [PO-2026-0045       ] (optional)  │
│                                                 │
│ ☑ Customer accepted terms & conditions          │
│                                                 │
│        [Cancel] [Record Acceptance]             │
│                                                 │
│ ☑ Create invoice immediately after acceptance   │
│                                                 │
└─────────────────────────────────────────────────┘
```

### Status Timeline Update

```
📝 Created           Jan 15, 2026  9:00 AM
   ↓
📤 Submitted         Jan 15, 2026  10:30 AM
   ↓
✅ Approved          Jan 15, 2026  2:00 PM
   ↓
📧 Sent              Jan 15, 2026  3:00 PM
   │  to: john@customer.com
   ↓
✍️ Accepted          Jan 18, 2026  10:00 AM
   │  by: John Smith
   │  via: Customer Portal
   │  PO#: PO-2026-0045
   │  Terms: Accepted
   ↓
💰 Converted         Jan 18, 2026  10:01 AM
   Invoice: INV-2026-001
```

## Dependencies

1. **Phase 6** - Sent Status (recommended but not required)
2. **Customer Portal** - For self-service acceptance
3. **Digital Signature Library** (optional) - signature_pad.js or similar
4. **Terms & Conditions Page** - For linking in acceptance form

## Alternative: Simplified Implementation

If full acceptance tracking is overkill, consider:

### Option A: Just Add PO Field to Conversion

```php
public function convertToInvoice(Quotation $quotation, ?string $customerPo = null): ServiceResult
{
    // ... existing logic

    $invoice->reference = $customerPo ?? $quotation->quotation_number;

    // ...
}
```

### Option B: Acceptance as Optional Step

Make Accepted status optional - allow conversion from both Approved/Sent AND Accepted:

```php
public function canConvert(): bool
{
    return in_array($this->currentStatus, [
        DocumentStatus::Approved,
        DocumentStatus::Sent,
        DocumentStatus::Accepted,
    ]);
}
```

## Decision Log

| Date | Decision | Rationale | Decided By |
|------|----------|-----------|------------|
| TBD | Require PO? | TBD | TBD |
| TBD | Require Signature? | TBD | TBD |
| TBD | Auto-convert? | TBD | TBD |

---

## Related Documents

- `00-master-plan.md` - Overall quotation enhancement plan
- `06-sent-status.md` - Prerequisite: Sent status implementation
- `05-outcome-integration.md` - How acceptance relates to Won outcome
