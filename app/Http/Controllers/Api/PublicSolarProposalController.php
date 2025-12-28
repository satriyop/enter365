<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SolarProposalResource;
use App\Models\Accounting\SolarProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSolarProposalController extends Controller
{
    /**
     * Show a solar proposal by public token.
     *
     * @operationId getPublicProposal
     */
    public function show(string $token): JsonResponse
    {
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

        if (! $proposal->canAccept()) {
            return response()->json([
                'message' => 'Proposal tidak dapat diterima.',
                'reason' => match ($proposal->status) {
                    SolarProposal::STATUS_ACCEPTED => 'Proposal sudah diterima sebelumnya.',
                    SolarProposal::STATUS_REJECTED => 'Proposal sudah ditolak.',
                    SolarProposal::STATUS_DRAFT => 'Proposal belum dikirim.',
                    SolarProposal::STATUS_EXPIRED => 'Proposal sudah kedaluwarsa.',
                    default => 'Status proposal tidak valid.',
                },
            ], 422);
        }

        $proposal->update([
            'status' => SolarProposal::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

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

        if (! $proposal->canReject()) {
            return response()->json([
                'message' => 'Proposal tidak dapat ditolak.',
                'reason' => match ($proposal->status) {
                    SolarProposal::STATUS_ACCEPTED => 'Proposal sudah diterima.',
                    SolarProposal::STATUS_REJECTED => 'Proposal sudah ditolak sebelumnya.',
                    SolarProposal::STATUS_DRAFT => 'Proposal belum dikirim.',
                    SolarProposal::STATUS_EXPIRED => 'Proposal sudah kedaluwarsa.',
                    default => 'Status proposal tidak valid.',
                },
            ], 422);
        }

        $proposal->update([
            'status' => SolarProposal::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $request->input('reason'),
        ]);

        return response()->json([
            'message' => 'Proposal berhasil ditolak.',
            'data' => new SolarProposalResource($proposal),
        ]);
    }
}
