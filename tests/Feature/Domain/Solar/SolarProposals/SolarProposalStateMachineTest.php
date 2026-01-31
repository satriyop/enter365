<?php

declare(strict_types=1);

use App\Domain\Solar\Proposals\Events\SolarProposalStatusChanged;
use App\Domain\Solar\Proposals\SolarProposalStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Solar\SolarProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Create a variant group to satisfy FK constraints for readyToSend proposals
    $this->variantGroup = BomVariantGroup::factory()->create();
});

/*
|--------------------------------------------------------------------------
| SolarProposalStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the solar proposal state machine including transitions, guards,
| actions, event dispatching, and business rule helpers.
|
*/

describe('SolarProposalStateMachine transitions', function () {
    it('allows transition from draft to sent when has variant_group_id and financial_analysis', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('does not block transition in canTransitionTo when missing variant_group_id', function () {
        // Note: SolarProposalStateMachine uses beforeTransition hook instead of guards,
        // so canTransitionTo returns true, but actual transition will throw exception
        $proposal = SolarProposal::factory()
            ->calculated()
            ->draft()
            ->create(['variant_group_id' => null]);

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('does not block transition in canTransitionTo when missing financial_analysis', function () {
        // Note: SolarProposalStateMachine uses beforeTransition hook instead of guards,
        // so canTransitionTo returns true, but actual transition will throw exception
        $proposal = SolarProposal::factory()
            ->draft()
            ->create([
                'variant_group_id' => null,
                'selected_bom_id' => null,
                'system_capacity_kwp' => 5.0,
                'annual_production_kwh' => 6570,
                'financial_analysis' => null,
            ]);

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('throws exception when sending without variant_group_id', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->calculated()
            ->draft()
            ->create(['variant_group_id' => null]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Variant group harus dipilih sebelum mengirim proposal.');

    it('throws exception when sending without financial_analysis', function () {
        $user = User::factory()->create();

        // Create a variant group to satisfy the first check
        $variantGroup = \App\Models\Manufacturing\BomVariantGroup::factory()->create();

        $proposal = SolarProposal::factory()
            ->draft()
            ->create([
                'variant_group_id' => $variantGroup->id,
                'selected_bom_id' => null,
                'system_capacity_kwp' => 5.0,
                'annual_production_kwh' => 6570,
                'financial_analysis' => null,
            ]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Kalkulasi harus diselesaikan sebelum mengirim proposal.');

    it('allows transition from sent to accepted when not expired', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Accepted))->toBeTrue();
    });

    it('uses beforeTransition to block accepting expired proposal', function () {
        // Note: SolarProposalStateMachine uses beforeTransition hook for expiry check,
        // so canTransitionTo returns true, but actual transition will throw exception
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create(['valid_until' => now()->subDays(5)]);

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Accepted))->toBeTrue();
    });

    it('throws exception when accepting expired proposal', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create(['valid_until' => now()->subDays(5)]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Accepted, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Proposal sudah kedaluwarsa.');

    it('allows transition from sent to rejected when not expired', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('uses beforeTransition to block rejecting expired proposal', function () {
        // Note: SolarProposalStateMachine uses beforeTransition hook for expiry check,
        // so canTransitionTo returns true, but actual transition will throw exception
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create(['valid_until' => now()->subDays(5)]);

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('allows transition from sent to expired', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        expect($stateMachine->canTransitionTo(DocumentStatus::Expired))->toBeTrue();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        expect($proposal->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('throws exception on invalid transition', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        $stateMachine->transitionTo(DocumentStatus::Accepted);
    })->throws(StateTransitionException::class);
});

describe('SolarProposalStateMachine actions', function () {
    it('updates sent_at and generates public_token when sending', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create(['valid_until' => now()->addDays(30)]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        $proposal->refresh();
        expect($proposal->sent_at)->not->toBeNull();
        expect($proposal->public_token)->not->toBeNull();
        expect($proposal->public_token_expires_at)->not->toBeNull();
    });

    it('updates accepted_at when accepting', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Accepted, ['user_id' => $user->id]);

        $proposal->refresh();
        expect($proposal->accepted_at)->not->toBeNull();
    });

    it('updates rejected_at and rejection_reason when rejecting', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => 'Harga terlalu tinggi',
        ]);

        $proposal->refresh();
        expect($proposal->rejected_at)->not->toBeNull();
        expect($proposal->rejection_reason)->toBe('Harga terlalu tinggi');
    });
});

describe('SolarProposalStateMachine event dispatching', function () {
    it('dispatches SolarProposalStatusChanged event on every transition', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(SolarProposalStatusChanged::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SolarProposalStatusChanged::class);
        expect($event->proposalId)->toBe($proposal->id);
        expect($event->fromStatus)->toBe(DocumentStatus::Draft);
        expect($event->toStatus)->toBe(DocumentStatus::Sent);
    });

    it('dispatches event when accepting proposal', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Accepted, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(SolarProposalStatusChanged::class))->toBe(1);
    });

    it('dispatches event when rejecting proposal', function () {
        $user = User::factory()->create();
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => 'Budget tidak sesuai',
        ]);

        expect($eventDispatcher->dispatchCount(SolarProposalStatusChanged::class))->toBe(1);
    });
});

describe('SolarProposalStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft proposal', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Sent->value);
    });

    it('provides correct workflow metadata for sent proposal', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Sent->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Accepted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Rejected->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Expired->value);
    });

    it('provides empty transitions for accepted proposal', function () {
        $proposal = SolarProposal::factory()->accepted()->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Accepted->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('provides empty transitions for rejected proposal', function () {
        $proposal = SolarProposal::factory()->rejected()->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Rejected->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('provides empty transitions for expired proposal', function () {
        $proposal = SolarProposal::factory()->expired()->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Expired->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata)->toHaveKey('current_status');
        expect($metadata)->toHaveKey('next_statuses');
        expect($metadata)->toHaveKey('all_statuses');
        expect($metadata)->toHaveKey('transitions');
        expect($metadata['current_status'])->toHaveKey('value');
        expect($metadata['current_status'])->toHaveKey('label');
        expect($metadata['current_status'])->toHaveKey('color');
    });
});

describe('SolarProposalStateMachine business helpers', function () {
    it('reports canSend correctly', function () {
        $readyToSend = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();
        $missingVariant = SolarProposal::factory()
            ->calculated()
            ->draft()
            ->create(['variant_group_id' => null]);
        $missingAnalysis = SolarProposal::factory()
            ->draft()
            ->create([
                'variant_group_id' => $this->variantGroup->id,
                'financial_analysis' => null,
            ]);
        $alreadySent = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create();

        expect(SolarProposalStateMachine::fromSolarProposal($readyToSend)->canSend())->toBeTrue();
        expect(SolarProposalStateMachine::fromSolarProposal($missingVariant)->canSend())->toBeFalse();
        expect(SolarProposalStateMachine::fromSolarProposal($missingAnalysis)->canSend())->toBeFalse();
        expect(SolarProposalStateMachine::fromSolarProposal($alreadySent)->canSend())->toBeFalse();
    });

    it('reports canAccept correctly', function () {
        $validProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();
        $expiredProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create(['valid_until' => now()->subDays(5)]);
        $draftProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        expect(SolarProposalStateMachine::fromSolarProposal($validProposal)->canAccept())->toBeTrue();
        expect(SolarProposalStateMachine::fromSolarProposal($expiredProposal)->canAccept())->toBeFalse();
        expect(SolarProposalStateMachine::fromSolarProposal($draftProposal)->canAccept())->toBeFalse();
    });

    it('reports canReject correctly', function () {
        $validProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->validFor(30)
            ->create();
        $expiredProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create(['valid_until' => now()->subDays(5)]);
        $draftProposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();

        expect(SolarProposalStateMachine::fromSolarProposal($validProposal)->canReject())->toBeTrue();
        expect(SolarProposalStateMachine::fromSolarProposal($expiredProposal)->canReject())->toBeFalse();
        expect(SolarProposalStateMachine::fromSolarProposal($draftProposal)->canReject())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draft = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();
        $sent = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create();

        expect(SolarProposalStateMachine::fromSolarProposal($draft)->canEdit())->toBeTrue();
        expect(SolarProposalStateMachine::fromSolarProposal($sent)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draft = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create();
        $sent = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->create();

        expect(SolarProposalStateMachine::fromSolarProposal($draft)->canDelete())->toBeTrue();
        expect(SolarProposalStateMachine::fromSolarProposal($sent)->canDelete())->toBeFalse();
    });
});

describe('SolarProposalStateMachine edge cases', function () {
    it('handles proposal expiring soon', function () {
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->sent()
            ->expiringSoon()
            ->create();

        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal);

        // Should still be able to accept if not yet expired
        expect($stateMachine->canAccept())->toBeTrue();
        expect($stateMachine->canReject())->toBeTrue();
    });

    it('handles proposal valid_until matching public_token_expires_at', function () {
        $user = User::factory()->create();
        $validUntil = now()->addDays(30);
        $proposal = SolarProposal::factory()
            ->readyToSend()->withVariantGroup($this->variantGroup)
            ->draft()
            ->create(['valid_until' => $validUntil]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SolarProposalStateMachine::fromSolarProposal($proposal, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        $proposal->refresh();
        expect($proposal->public_token_expires_at->toDateString())->toBe($validUntil->toDateString());
    });
});
