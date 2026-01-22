<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Exception for invalid workflow state transitions.
 *
 * Use when:
 * - Document status change is not allowed
 * - Workflow action is invalid for current state
 * - State machine transition is blocked
 */
class StateTransitionException extends DomainException
{
    protected string $errorCode = 'INVALID_STATE_TRANSITION';

    protected int $statusCode = 422;

    /**
     * Create exception for invalid transition attempt.
     */
    public static function invalidTransition(
        string $documentType,
        string $currentState,
        string $targetState
    ): self {
        return new self(
            "Tidak dapat mengubah status {$documentType} dari '{$currentState}' ke '{$targetState}'.",
            [
                'document_type' => $documentType,
                'current_state' => $currentState,
                'target_state' => $targetState,
            ]
        );
    }

    /**
     * Create exception when action is not available for current state.
     */
    public static function actionNotAvailable(string $action, string $currentState, ?string $reason = null): self
    {
        $message = $reason ?? "Aksi '{$action}' tidak tersedia untuk status '{$currentState}'.";

        return new self(
            $message,
            [
                'action' => $action,
                'current_state' => $currentState,
            ]
        );
    }

    /**
     * Create exception for already processed document.
     */
    public static function alreadyProcessed(string $documentType, string $action): self
    {
        return new self(
            "{$documentType} sudah di-{$action}.",
            [
                'document_type' => $documentType,
                'action' => $action,
            ]
        );
    }
}
