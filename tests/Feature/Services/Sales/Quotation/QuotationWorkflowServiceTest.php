<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use App\Services\Sales\Quotation\QuotationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('QuotationWorkflowService', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->customer()->create();
        $this->service = app(QuotationWorkflowService::class);

        $this->actingAs($this->user);
    });

    describe('submit', function () {
        it('submits a draft quotation successfully', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->createdBy($this->user)
                ->create();

            // Quotation needs items to be submitted
            QuotationItem::factory()->forQuotation($quotation)->create();

            $result = $this->service->submit($quotation);

            expect($result->status)->toBe(DocumentStatus::Submitted)
                ->and($result->submitted_at)->not->toBeNull()
                ->and($result->submitted_by)->toBe($this->user->id)
                ->and($result->relationLoaded('items'))->toBeTrue()
                ->and($result->relationLoaded('contact'))->toBeTrue();
        });

        it('submits with custom user id', function () {
            $otherUser = User::factory()->create();
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->createdBy($this->user)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $result = $this->service->submit($quotation, $otherUser->id);

            expect($result->submitted_by)->toBe($otherUser->id);
        });

        it('throws exception when quotation is not in draft status', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->submit($quotation);
        })->throws(StateTransitionException::class);

        it('throws exception when quotation has no items', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->createdBy($this->user)
                ->create();

            // No items created

            $this->service->submit($quotation);
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is already approved', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->submit($quotation);
        })->throws(StateTransitionException::class);
    });

    describe('approve', function () {
        it('approves a submitted quotation successfully', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $result = $this->service->approve($quotation);

            expect($result->status)->toBe(DocumentStatus::Approved)
                ->and($result->approved_at)->not->toBeNull()
                ->and($result->approved_by)->toBe($this->user->id)
                ->and($result->relationLoaded('items'))->toBeTrue()
                ->and($result->relationLoaded('contact'))->toBeTrue();
        });

        it('approves with custom user id', function () {
            $approver = User::factory()->create();
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $result = $this->service->approve($quotation, $approver->id);

            expect($result->approved_by)->toBe($approver->id);
        });

        it('throws exception when quotation is in draft status', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create();

            QuotationItem::factory()->forQuotation($quotation)->create();

            $this->service->approve($quotation);
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is already approved', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $this->service->approve($quotation);
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is rejected', function () {
            $quotation = Quotation::factory()
                ->rejected()
                ->forContact($this->contact)
                ->create();

            $this->service->approve($quotation);
        })->throws(StateTransitionException::class);
    });

    describe('reject', function () {
        it('rejects a submitted quotation with reason', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            $reason = 'Harga tidak sesuai budget';

            $result = $this->service->reject($quotation, $reason);

            expect($result->status)->toBe(DocumentStatus::Rejected)
                ->and($result->rejected_at)->not->toBeNull()
                ->and($result->rejected_by)->toBe($this->user->id)
                ->and($result->rejection_reason)->toBe($reason)
                ->and($result->relationLoaded('items'))->toBeTrue()
                ->and($result->relationLoaded('contact'))->toBeTrue();
        });

        it('rejects with custom user id', function () {
            $rejecter = User::factory()->create();
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->reject($quotation, 'Alasan penolakan', $rejecter->id);

            expect($result->rejected_by)->toBe($rejecter->id);
        });

        it('throws exception when reason is empty', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            $this->service->reject($quotation, '');
        })->throws(BusinessRuleException::class);

        it('throws exception when quotation is in draft status', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create();

            $this->service->reject($quotation, 'Reason');
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is already approved', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $this->service->reject($quotation, 'Reason');
        })->throws(StateTransitionException::class);
    });

    describe('cancel', function () {
        it('cancels a draft quotation', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->withFollowUp(3)
                ->create();

            $reason = 'Customer tidak jadi order';

            $result = $this->service->cancel($quotation, $reason);

            expect($result->status)->toBe(DocumentStatus::Cancelled)
                ->and($result->next_follow_up_at)->toBeNull()
                ->and($result->relationLoaded('items'))->toBeTrue()
                ->and($result->relationLoaded('contact'))->toBeTrue();
        });

        it('cancels a submitted quotation', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->cancel($quotation, 'Dibatalkan karena duplikat');

            expect($result->status)->toBe(DocumentStatus::Cancelled);
        });

        it('cancels an approved quotation', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->cancel($quotation, 'Customer mundur');

            expect($result->status)->toBe(DocumentStatus::Cancelled);
        });

        it('cancels without reason', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->cancel($quotation);

            expect($result->status)->toBe(DocumentStatus::Cancelled);
        });

        it('cancels with custom user id', function () {
            $canceller = User::factory()->create();
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->cancel($quotation, 'Reason', $canceller->id);

            expect($result->status)->toBe(DocumentStatus::Cancelled);
        });

        it('clears follow-up schedule when cancelling', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->withFollowUp(5)
                ->create();

            expect($quotation->next_follow_up_at)->not->toBeNull();

            $result = $this->service->cancel($quotation);

            expect($result->next_follow_up_at)->toBeNull();
        });

        it('throws exception when quotation is rejected', function () {
            $quotation = Quotation::factory()
                ->rejected()
                ->forContact($this->contact)
                ->create();

            $this->service->cancel($quotation, 'Reason');
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is converted', function () {
            $quotation = Quotation::factory()
                ->converted()
                ->forContact($this->contact)
                ->create();

            $this->service->cancel($quotation, 'Reason');
        })->throws(StateTransitionException::class);
    });

    describe('markAsSent', function () {
        it('marks approved quotation as sent with email', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $email = 'customer@example.com';
            $via = 'email';

            $result = $this->service->markAsSent($quotation, $email, $via);

            expect($result->sent_at)->not->toBeNull()
                ->and($result->sent_by)->toBe($this->user->id)
                ->and($result->sent_to_email)->toBe($email)
                ->and($result->sent_via)->toBe($via)
                ->and($result->relationLoaded('items'))->toBeTrue()
                ->and($result->relationLoaded('contact'))->toBeTrue();
        });

        it('uses contact email when email not provided', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->markAsSent($quotation, null, 'email');

            expect($result->sent_to_email)->toBe($this->contact->email);
        });

        it('defaults via to email when not provided', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->markAsSent($quotation, 'test@example.com');

            expect($result->sent_via)->toBe('email');
        });

        it('can mark as sent via whatsapp', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            $result = $this->service->markAsSent($quotation, 'customer@example.com', 'whatsapp');

            expect($result->sent_via)->toBe('whatsapp');
        });

        it('throws exception when quotation is not approved', function () {
            $quotation = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create();

            $this->service->markAsSent($quotation, 'email@example.com');
        })->throws(StateTransitionException::class);

        it('throws exception when quotation is already sent', function () {
            $quotation = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create();

            // Mark as sent first time
            $quotation->update([
                'sent_at' => now(),
                'sent_by' => $this->user->id,
                'sent_to_email' => 'first@example.com',
                'sent_via' => 'email',
            ]);

            // Try to mark as sent again
            $this->service->markAsSent($quotation, 'second@example.com');
        })->throws(BusinessRuleException::class);

        it('throws exception when quotation is in draft status', function () {
            $quotation = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create();

            $this->service->markAsSent($quotation, 'email@example.com');
        })->throws(StateTransitionException::class);
    });

    describe('markExpired', function () {
        it('marks draft quotations as expired when valid_until is past', function () {
            // Expired draft quotations
            $expiredDraft1 = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDays(1)]);

            $expiredDraft2 = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDays(5)]);

            // Should not expire - still valid
            $validDraft = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->addDays(5)]);

            $count = $this->service->markExpired();

            expect($count)->toBe(2);

            $expiredDraft1->refresh();
            $expiredDraft2->refresh();
            $validDraft->refresh();

            expect($expiredDraft1->status)->toBe(DocumentStatus::Expired)
                ->and($expiredDraft2->status)->toBe(DocumentStatus::Expired)
                ->and($validDraft->status)->toBe(DocumentStatus::Draft);
        });

        it('marks submitted quotations as expired when valid_until is past', function () {
            $expiredSubmitted = Quotation::factory()
                ->submitted()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDays(1)]);

            $count = $this->service->markExpired();

            expect($count)->toBe(1);

            $expiredSubmitted->refresh();
            expect($expiredSubmitted->status)->toBe(DocumentStatus::Expired);
        });

        it('marks approved unsent quotations as expired when valid_until is past', function () {
            // Approved but not sent - should expire
            $expiredApprovedUnsent = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'valid_until' => now()->subDays(1),
                    'sent_at' => null,
                ]);

            $count = $this->service->markExpired();

            expect($count)->toBe(1);

            $expiredApprovedUnsent->refresh();
            expect($expiredApprovedUnsent->status)->toBe(DocumentStatus::Expired);
        });

        it('does not mark approved sent quotations as expired', function () {
            // Approved and sent - should NOT expire
            $approvedSent = Quotation::factory()
                ->approved()
                ->forContact($this->contact)
                ->create([
                    'valid_until' => now()->subDays(1),
                    'sent_at' => now()->subDays(1),
                    'sent_by' => $this->user->id,
                ]);

            $count = $this->service->markExpired();

            expect($count)->toBe(0);

            $approvedSent->refresh();
            expect($approvedSent->status)->toBe(DocumentStatus::Approved);
        });

        it('does not mark rejected quotations as expired', function () {
            $rejected = Quotation::factory()
                ->rejected()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDays(1)]);

            $count = $this->service->markExpired();

            expect($count)->toBe(0);

            $rejected->refresh();
            expect($rejected->status)->toBe(DocumentStatus::Rejected);
        });

        it('does not mark cancelled quotations as expired', function () {
            $cancelled = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create([
                    'valid_until' => now()->subDays(1),
                    'status' => DocumentStatus::Cancelled,
                ]);

            $count = $this->service->markExpired();

            expect($count)->toBe(0);

            $cancelled->refresh();
            expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
        });

        it('does not mark converted quotations as expired', function () {
            $converted = Quotation::factory()
                ->converted()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDays(1)]);

            $count = $this->service->markExpired();

            expect($count)->toBe(0);

            $converted->refresh();
            expect($converted->status)->toBe(DocumentStatus::Converted);
        });

        it('processes large number of quotations using chunks', function () {
            // Create 250 expired draft quotations
            $quotations = [];
            for ($i = 0; $i < 250; $i++) {
                $quotations[] = Quotation::factory()
                    ->draft()
                    ->forContact($this->contact)
                    ->create(['valid_until' => now()->subDays(1)]);
            }

            $count = $this->service->markExpired();

            expect($count)->toBe(250);

            // Verify all are expired
            $expiredCount = Quotation::where('status', DocumentStatus::Expired)->count();
            expect($expiredCount)->toBe(250);
        });

        it('returns zero when no quotations to expire', function () {
            // All quotations are still valid
            Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->addDays(10)]);

            $count = $this->service->markExpired();

            expect($count)->toBe(0);
        });

        it('only expires quotations with valid_until before today start of day', function () {
            // Today's date - should NOT expire
            $validToday = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->startOfDay()]);

            // Yesterday - should expire
            $expiredYesterday = Quotation::factory()
                ->draft()
                ->forContact($this->contact)
                ->create(['valid_until' => now()->subDay()->startOfDay()]);

            $count = $this->service->markExpired();

            expect($count)->toBe(1);

            $validToday->refresh();
            $expiredYesterday->refresh();

            expect($validToday->status)->toBe(DocumentStatus::Draft)
                ->and($expiredYesterday->status)->toBe(DocumentStatus::Expired);
        });
    });
});
