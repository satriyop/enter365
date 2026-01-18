<?php

declare(strict_types=1);

namespace App\Contracts\Solar;

use App\Models\Sales\Quotation;
use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\SolarProposal;

/**
 * Interface for Solar Proposal service operations.
 */
interface SolarProposalServiceInterface
{
    /**
     * Create a new solar proposal.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SolarProposal;

    /**
     * Update a solar proposal.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SolarProposal $proposal, array $data): SolarProposal;

    /**
     * Delete a solar proposal.
     */
    public function delete(SolarProposal $proposal): bool;

    /**
     * Calculate solar proposal projections.
     */
    public function calculateProposal(SolarProposal $proposal): SolarProposal;

    /**
     * Attach a BOM variant group to the proposal.
     */
    public function attachVariantGroup(SolarProposal $proposal, int $variantGroupId): SolarProposal;

    /**
     * Select a BOM for the proposal.
     */
    public function selectBom(SolarProposal $proposal, int $bomId): SolarProposal;

    /**
     * Send proposal to customer.
     */
    public function send(SolarProposal $proposal): SolarProposal;

    /**
     * Mark proposal as accepted.
     */
    public function accept(SolarProposal $proposal, ?int $selectedBomId = null): SolarProposal;

    /**
     * Mark proposal as rejected.
     */
    public function reject(SolarProposal $proposal, ?string $reason = null): SolarProposal;

    /**
     * Convert accepted proposal to quotation.
     */
    public function convertToQuotation(SolarProposal $proposal): Quotation;

    /**
     * Lookup solar data by province and city.
     */
    public function lookupSolarData(string $province, string $city): ?IndonesiaSolarData;

    /**
     * Lookup solar data by GPS coordinates.
     */
    public function lookupSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData;
}
