# Phase 6: Add "Sent to Customer" Status

> **Type**: 📋 NEW FEATURE
> **Status**: Documented for Future Development
> **Priority**: Low
> **Effort**: Large (1-2 weeks)
> **Dependencies**: Email integration, PDF generation

## Problem Statement

There's no tracking of when a quotation was actually **sent to the customer**:

- `submitted_at` = submitted for **internal** approval
- No field for = sent to **customer**

### Current Gap

```
Internal Workflow:    Draft → Submitted → Approved
Customer Workflow:    ??? (when did customer receive it?)
```

### Business Impact

1. **No visibility** into customer communication timeline
2. **Sales metrics incomplete** - Can't measure "time to send" or "send to close"
3. **Follow-up confusion** - When to follow up if don't know when sent?
4. **Audit gap** - No proof quotation was delivered to customer

## Proposed Solution

### Enhanced Workflow

```
┌────────┐   ┌───────────┐   ┌──────────┐   ┌──────┐   ┌───────────┐
│ DRAFT  │──▶│ SUBMITTED │──▶│ APPROVED │──▶│ SENT │──▶│ CONVERTED │
└────────┘   └───────────┘   └──────────┘   └──────┘   └───────────┘
                                               │
                                               ▼
                                           DECLINED
```

### New Status: `Sent`

| Attribute | Value |
|-----------|-------|
| Status Name | `Sent` |
| Enum Value | `sent` |
| Can Edit | No |
| Can Delete | No |
| Can Cancel | Yes |
| Next States | Converted, Declined, Cancelled, Expired |

## Detailed Implementation

### 1. Database Migration

```php
// database/migrations/xxxx_add_sent_tracking_to_quotations.php

public function up(): void
{
    Schema::table('quotations', function (Blueprint $table) {
        $table->timestamp('sent_at')->nullable()->after('approved_at');
        $table->foreignId('sent_by')->nullable()->after('sent_at')
            ->constrained('users')->nullOnDelete();
        $table->string('sent_via')->nullable()->after('sent_by'); // email, portal, manual, print
        $table->string('sent_to_email')->nullable()->after('sent_via');
        $table->timestamp('customer_viewed_at')->nullable()->after('sent_to_email');
        $table->integer('view_count')->default(0)->after('customer_viewed_at');
    });
}
```

### 2. Enum Update

```php
// app/Enums/DocumentStatus.php

enum DocumentStatus: string
{
    // ... existing statuses
    case Sent = 'sent';
    case Declined = 'declined'; // Optional: customer explicitly declined
}
```

### 3. State Machine Updates

```php
// app/Domain/Sales/Quotations/QuotationStateMachine.php

protected function defineTransitions(): array
{
    return [
        DocumentStatus::Draft->value => [
            DocumentStatus::Submitted,
            DocumentStatus::Cancelled,
            DocumentStatus::Expired,
        ],
        DocumentStatus::Submitted->value => [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Cancelled,
            DocumentStatus::Expired,
        ],
        DocumentStatus::Approved->value => [
            DocumentStatus::Sent,        // NEW
            DocumentStatus::Cancelled,
            DocumentStatus::Expired,
        ],
        DocumentStatus::Sent->value => [  // NEW
            DocumentStatus::Converted,
            DocumentStatus::Declined,    // Customer explicitly declined
            DocumentStatus::Cancelled,
            DocumentStatus::Expired,
        ],
        DocumentStatus::Rejected->value => [
            DocumentStatus::Draft, // Revise
        ],
        DocumentStatus::Declined->value => [ // NEW
            DocumentStatus::Draft, // Revise
        ],
        // Terminal states
        DocumentStatus::Converted->value => [],
        DocumentStatus::Expired->value => [
            DocumentStatus::Draft, // Revise
        ],
        DocumentStatus::Cancelled->value => [
            DocumentStatus::Draft, // Revise
        ],
    ];
}

public function canSend(): bool
{
    return $this->currentStatus === DocumentStatus::Approved;
}

public function canDecline(): bool
{
    return $this->currentStatus === DocumentStatus::Sent;
}
```

### 4. Service Layer

```php
// app/Services/Sales/QuotationService.php

public function send(Quotation $quotation, array $options = []): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canSend()) {
        return ServiceResult::failure('Quotation must be approved before sending.');
    }

    return DB::transaction(function () use ($quotation, $options) {
        $sendVia = $options['via'] ?? 'manual';
        $recipientEmail = $options['email'] ?? $quotation->contact->email;

        // Generate PDF if not exists
        if (!$quotation->pdf_path) {
            $quotation->pdf_path = $this->pdfService->generate($quotation);
        }

        // Send based on method
        if ($sendVia === 'email') {
            $this->sendViaEmail($quotation, $recipientEmail, $options);
        }

        // Update tracking
        $quotation->sent_at = now();
        $quotation->sent_by = auth()->id();
        $quotation->sent_via = $sendVia;
        $quotation->sent_to_email = $recipientEmail;

        // Transition status
        $quotation->transitionTo(DocumentStatus::Sent);

        // Schedule auto follow-up
        $quotation->next_follow_up_at = now()->addDays(
            config('quotation.default_follow_up_days', 3)
        );
        $quotation->save();

        event(new QuotationSent($quotation, $sendVia, $recipientEmail));

        return ServiceResult::success($quotation, 'Quotation sent successfully.');
    });
}

public function markDeclined(Quotation $quotation, array $data = []): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canDecline()) {
        return ServiceResult::failure('Only sent quotations can be declined.');
    }

    return DB::transaction(function () use ($quotation, $data) {
        $quotation->outcome = 'lost';
        $quotation->lost_reason = $data['reason'] ?? 'customer_declined';
        $quotation->outcome_notes = $data['notes'] ?? null;
        $quotation->outcome_at = now();

        $quotation->transitionTo(DocumentStatus::Declined);

        event(new QuotationDeclined($quotation, $data['reason'] ?? null));

        return ServiceResult::success($quotation);
    });
}

private function sendViaEmail(Quotation $quotation, string $email, array $options): void
{
    $viewLink = $options['include_portal_link'] ?? true
        ? $this->generatePortalLink($quotation)
        : null;

    Mail::to($email)->send(new QuotationMail(
        quotation: $quotation,
        customMessage: $options['message'] ?? null,
        portalLink: $viewLink,
    ));
}

private function generatePortalLink(Quotation $quotation): string
{
    // Generate secure, time-limited link for customer portal
    return URL::signedRoute('quotation.portal.view', [
        'quotation' => $quotation->uuid,
    ], now()->addDays(30));
}
```

### 5. Controller Updates

```php
// app/Http/Controllers/Api/V1/QuotationController.php

public function send(SendQuotationRequest $request, Quotation $quotation)
{
    $result = $this->quotationService->send($quotation, [
        'via' => $request->input('via', 'email'),
        'email' => $request->input('email'),
        'message' => $request->input('message'),
        'include_portal_link' => $request->boolean('include_portal_link', true),
    ]);

    if ($result->failed()) {
        return response()->json(['message' => $result->message], 422);
    }

    return new QuotationResource($result->data);
}

public function markDeclined(DeclineQuotationRequest $request, Quotation $quotation)
{
    $result = $this->quotationService->markDeclined($quotation, [
        'reason' => $request->input('reason'),
        'notes' => $request->input('notes'),
    ]);

    if ($result->failed()) {
        return response()->json(['message' => $result->message], 422);
    }

    return new QuotationResource($result->data);
}
```

### 6. API Endpoints

```php
// routes/api.php

Route::prefix('quotations/{quotation}')->group(function () {
    // ... existing routes
    Route::post('send', [QuotationController::class, 'send']);
    Route::post('decline', [QuotationController::class, 'markDeclined']);
});
```

### 7. Events

```php
// app/Domain/Sales/Quotations/Events/QuotationSent.php

class QuotationSent extends QuotationStatusChanged
{
    public function __construct(
        Quotation $quotation,
        public readonly string $sentVia,
        public readonly ?string $recipientEmail,
    ) {
        parent::__construct($quotation, DocumentStatus::Approved, DocumentStatus::Sent);
    }
}

// app/Domain/Sales/Quotations/Events/QuotationDeclined.php

class QuotationDeclined extends QuotationStatusChanged
{
    public function __construct(
        Quotation $quotation,
        public readonly ?string $declineReason,
    ) {
        parent::__construct($quotation, DocumentStatus::Sent, DocumentStatus::Declined);
    }
}
```

### 8. Email Template

```php
// app/Mail/QuotationMail.php

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public ?string $customMessage = null,
        public ?string $portalLink = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quotation {$this->quotation->quotation_number} from " . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.quotations.sent',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->quotation->pdf_path)
                ->as("{$this->quotation->quotation_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
```

### 9. Customer Portal (Optional)

```php
// app/Http/Controllers/Portal/QuotationPortalController.php

class QuotationPortalController extends Controller
{
    public function view(Request $request, string $uuid)
    {
        $quotation = Quotation::where('uuid', $uuid)->firstOrFail();

        // Track view
        $quotation->increment('view_count');
        if (!$quotation->customer_viewed_at) {
            $quotation->update(['customer_viewed_at' => now()]);
        }

        return view('portal.quotation', compact('quotation'));
    }

    public function accept(Request $request, string $uuid)
    {
        $quotation = Quotation::where('uuid', $uuid)->firstOrFail();

        // Customer accepts via portal
        // Could trigger conversion or just mark as Won
    }

    public function decline(Request $request, string $uuid)
    {
        $quotation = Quotation::where('uuid', $uuid)->firstOrFail();

        $this->quotationService->markDeclined($quotation, [
            'reason' => $request->input('reason'),
            'notes' => $request->input('notes'),
        ]);

        return view('portal.quotation-declined');
    }
}
```

## Testing Requirements

```php
it('can send approved quotation via email', function () {
    Mail::fake();

    $quotation = Quotation::factory()->approved()->create();

    $result = $this->quotationService->send($quotation, [
        'via' => 'email',
        'email' => 'customer@example.com',
    ]);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Sent);
    expect($quotation->fresh()->sent_via)->toBe('email');

    Mail::assertSent(QuotationMail::class);
});

it('cannot send draft quotation', function () {
    $quotation = Quotation::factory()->create(); // Draft

    $result = $this->quotationService->send($quotation);

    expect($result->failed())->toBeTrue();
});

it('can decline sent quotation', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Sent,
    ]);

    $result = $this->quotationService->markDeclined($quotation, [
        'reason' => 'price_too_high',
    ]);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Declined);
    expect($quotation->fresh()->outcome)->toBe('lost');
});

it('tracks customer view', function () {
    $quotation = Quotation::factory()->create([
        'status' => DocumentStatus::Sent,
        'uuid' => Str::uuid(),
    ]);

    $this->get(route('quotation.portal.view', $quotation->uuid));

    expect($quotation->fresh()->view_count)->toBe(1);
    expect($quotation->fresh()->customer_viewed_at)->not->toBeNull();
});
```

## UI/UX Requirements

### Send Quotation Modal

```
┌─────────────────────────────────────────┐
│ Send Quotation QUO-2026-001             │
├─────────────────────────────────────────┤
│                                         │
│ Send to: [customer@example.com    ]     │
│                                         │
│ Send via:                               │
│ (•) Email with PDF attachment           │
│ ( ) Customer Portal link only           │
│ ( ) Mark as manually sent (no email)    │
│                                         │
│ ☑ Include customer portal link          │
│                                         │
│ Custom message (optional):              │
│ ┌─────────────────────────────────────┐ │
│ │                                     │ │
│ │                                     │ │
│ └─────────────────────────────────────┘ │
│                                         │
│              [Cancel] [Send Quotation]  │
└─────────────────────────────────────────┘
```

### Status Timeline

```
📝 Created           Jan 15, 2026  9:00 AM
   ↓
📤 Submitted         Jan 15, 2026  10:30 AM
   ↓
✅ Approved          Jan 15, 2026  2:00 PM
   ↓
📧 Sent to Customer  Jan 15, 2026  3:00 PM
   │  via: Email
   │  to: customer@example.com
   │
👁️ Viewed by Customer Jan 16, 2026  9:15 AM
   │  views: 3
   ↓
💰 Converted         Jan 18, 2026  11:00 AM
   Invoice: INV-2026-001
```

## Dependencies

1. **PDF Generation Service** - Must be implemented first
2. **Email Configuration** - SMTP/Mailgun setup
3. **UUID on Quotations** - For secure portal links
4. **Customer Portal** (optional) - Separate frontend work

## Rollout Plan

1. **Phase 1**: Add database fields, keep current workflow
2. **Phase 2**: Add manual "Mark as Sent" (no email)
3. **Phase 3**: Implement email sending
4. **Phase 4**: Add customer portal (optional)
5. **Phase 5**: Add view tracking

## Decision Log

| Date | Decision | Rationale | Decided By |
|------|----------|-----------|------------|
| TBD | Include Portal? | TBD | TBD |
| TBD | Require email to send? | TBD | TBD |

---

## Related Documents

- `00-master-plan.md` - Overall quotation enhancement plan
- `07-accepted-status.md` - Customer acceptance tracking
