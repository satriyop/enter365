<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SolarProposalResource;
use App\Models\Solar\SolarProposal;
use App\Services\Solar\SolarProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSolarProposalController extends Controller
{
    public function __construct(
        private SolarProposalService $proposalService
    ) {}

    /**
     * Show a solar proposal by public token.
     *
     * @operationId getPublicProposal
     */
    public function show(string $token): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        $proposal = SolarProposal::findByPublicToken($token);

        if (! $proposal) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        if (! $proposal->hasValidPublicToken()) {
            return response()->json([
                'message' => 'Link proposal sudah kedaluwarsa.',
            ], 410); // Gone
        }

        // Load relationships for the proposal
        $proposal->load(['contact', 'selectedBom', 'variantGroup']);

        return response()->json([
            'data' => new SolarProposalResource($proposal),
        ]);
    }

    /**
     * Accept a solar proposal by public token.
     *
     * @operationId acceptPublicProposal
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        $proposal = SolarProposal::findByPublicToken($token);

        if (! $proposal) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        if (! $proposal->hasValidPublicToken()) {
            return response()->json([
                'message' => 'Link proposal sudah kedaluwarsa.',
            ], 410);
        }

        if (! $proposal->stateMachine()->canAccept()) {
            return response()->json([
                'message' => 'Proposal tidak dapat diterima.',
                'reason' => match ($proposal->status) {
                    DocumentStatus::Accepted => 'Proposal sudah diterima sebelumnya.',
                    DocumentStatus::Rejected => 'Proposal sudah ditolak.',
                    DocumentStatus::Draft => 'Proposal belum dikirim.',
                    DocumentStatus::Expired => 'Proposal sudah kedaluwarsa.',
                    default => 'Status proposal tidak valid.',
                },
            ], 422);
        }

        // Use service for proper state machine handling
        $proposal = $this->proposalService->accept($proposal);

        return response()->json([
            'message' => 'Proposal berhasil diterima.',
            'data' => new SolarProposalResource($proposal),
        ]);
    }

    /**
     * Reject a solar proposal by public token.
     *
     * @operationId rejectPublicProposal
     */
    public function reject(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        $proposal = SolarProposal::findByPublicToken($token);

        if (! $proposal) {
            return response()->json([
                'message' => 'Proposal tidak ditemukan.',
            ], 404);
        }

        if (! $proposal->hasValidPublicToken()) {
            return response()->json([
                'message' => 'Link proposal sudah kedaluwarsa.',
            ], 410);
        }

        if (! $proposal->stateMachine()->canReject()) {
            return response()->json([
                'message' => 'Proposal tidak dapat ditolak.',
                'reason' => match ($proposal->status) {
                    DocumentStatus::Accepted => 'Proposal sudah diterima.',
                    DocumentStatus::Rejected => 'Proposal sudah ditolak sebelumnya.',
                    DocumentStatus::Draft => 'Proposal belum dikirim.',
                    DocumentStatus::Expired => 'Proposal sudah kedaluwarsa.',
                    default => 'Status proposal tidak valid.',
                },
            ], 422);
        }

        // Use service for proper state machine handling
        $proposal = $this->proposalService->reject($proposal, $request->input('reason'));

        return response()->json([
            'message' => 'Proposal berhasil ditolak.',
            'data' => new SolarProposalResource($proposal),
        ]);
    }
}
