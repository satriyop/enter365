<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateAccountingPoliciesRequest;
use App\Http\Resources\Api\V1\AccountingPolicyResource;
use App\Models\Accounting\AccountingPolicy;
use App\Services\Accounting\AccountingPolicyManager;
use Illuminate\Support\Facades\Gate;

class AccountingPolicyController extends Controller
{
    public function __construct(
        private AccountingPolicyManager $policyManager
    ) {}

    /** GET /api/v1/accounting-policies */
    public function show(): array
    {
        Gate::authorize('settings.manage_accounting');

        return [
            'data' => new AccountingPolicyResource(AccountingPolicy::current()),
            'meta' => [
                'available_options' => $this->policyManager->getAvailableOptions(),
                'descriptions' => $this->getPolicyDescriptions(),
            ],
        ];
    }

    /** PUT /api/v1/accounting-policies */
    public function update(UpdateAccountingPoliciesRequest $request): AccountingPolicyResource
    {
        Gate::authorize('settings.manage_accounting');

        $policy = AccountingPolicy::current();
        $policy->update($request->validated());

        // Clear cached singleton so the response reflects new values
        app()->forgetInstance(AccountingPolicyManager::class);

        return new AccountingPolicyResource($policy->fresh());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getPolicyDescriptions(): array
    {
        return [
            'inventory_method' => [
                'perpetual' => 'Jurnal otomatis setiap pergerakan stok (GRN, DO)',
                'periodic' => 'Tanpa jurnal otomatis, penyesuaian di akhir periode',
                'hybrid' => 'Jurnal hanya pada event tertentu (rekomendasi EPC)',
            ],
            'cogs_recognition' => [
                'on_invoice' => 'HPP diakui saat invoice diposting (matching revenue)',
                'on_delivery' => 'HPP diakui saat barang dikirim',
                'manual' => 'Tanpa HPP otomatis, jurnal manual',
            ],
            'return_accounting' => [
                'full_journal' => 'Buat jurnal pembalikan lengkap (AP/AR + Pajak)',
                'inventory_only' => 'Update stok saja, tanpa jurnal',
            ],
            'manufacturing_costing' => [
                'project_based' => 'Biaya mengalir ke proyek (rekomendasi EPC)',
                'job_costing' => 'Biaya terakumulasi per work order',
                'wip_accounting' => 'Jurnal WIP lengkap',
            ],
            'closing_strategy' => [
                'direct' => 'Tutup langsung ke retained earnings (lebih sederhana)',
                'income_summary' => 'Melalui akun income summary (audit trail lebih jelas)',
            ],
        ];
    }
}
