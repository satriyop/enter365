<?php

namespace Database\Seeders\Demo\Nex;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationItem;
use App\Models\Accounting\QuotationVariantOption;
use App\Models\User;
use Illuminate\Database\Seeder;

class NexTransactionSeeder extends Seeder
{
    private static int $quotationSeq = 100; // Start at 100 to avoid conflicts with Vahana

    /**
     * Seed transactions for PT NEX - Solar EPC business cycle demo.
     */
    public function run(): void
    {
        $this->createMultiOptionQuotation();
        $this->createRegularQuotations();
        $this->command->info('Created NEX quotations including multi-option quotation');
    }

    /**
     * Create a multi-option quotation showcasing Budget/Standard/Premium variants.
     */
    private function createMultiOptionQuotation(): void
    {
        $salesUser = User::where('email', 'sales@demo.com')->first();
        $customer = Contact::where('type', Contact::TYPE_CUSTOMER)
            ->where('code', 'C-PIK')
            ->first();

        if (! $customer) {
            $customer = Contact::where('type', Contact::TYPE_CUSTOMER)->first();
        }

        // Find the PLTS 50 kWp variant group
        $variantGroup = BomVariantGroup::where('name', 'PLTS 50 kWp Material Options')->first();
        if (! $variantGroup) {
            $this->command->warn('BomVariantGroup not found, skipping multi-option quotation');

            return;
        }

        // Get the BOMs in this variant group
        $boms = Bom::where('variant_group_id', $variantGroup->id)
            ->orderBy('variant_sort_order')
            ->get();

        if ($boms->count() < 2) {
            $this->command->warn('Not enough BOMs in variant group, skipping multi-option quotation');

            return;
        }

        self::$quotationSeq++;
        $quotationNumber = 'QUO-'.now()->format('Ym').'-'.str_pad(self::$quotationSeq, 4, '0', STR_PAD_LEFT);

        // Create the multi-option quotation
        $quotation = Quotation::updateOrCreate(
            ['quotation_number' => $quotationNumber, 'revision' => 0],
            [
                'revision' => 0,
                'contact_id' => $customer->id,
                'quotation_date' => now()->subDays(5),
                'valid_until' => now()->addDays(30),
                'subject' => 'PLTS Rooftop 50 kWp - Pilihan Konfigurasi Material',
                'reference' => 'RFQ-PIK-2024-001',
                'quotation_type' => Quotation::TYPE_MULTI_OPTION,
                'variant_group_id' => $variantGroup->id,
                'status' => Quotation::STATUS_SUBMITTED,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'subtotal' => 0, // Will be calculated based on selected variant
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_rate' => 11.0,
                'tax_amount' => 0,
                'total' => 0,
                'base_currency_total' => 0,
                'notes' => 'Penawaran dengan 3 pilihan konfigurasi material. Silakan pilih sesuai dengan kebutuhan dan budget Anda.',
                'terms_conditions' => Quotation::getDefaultTermsConditions(),
                'created_by' => $salesUser?->id,
                'assigned_to' => $salesUser?->id,
                'submitted_at' => now()->subDays(3),
                'submitted_by' => $salesUser?->id,
                'priority' => 'high',
                'next_follow_up_at' => now()->addDays(2),
            ]
        );

        // Delete existing variant options and recreate
        QuotationVariantOption::where('quotation_id', $quotation->id)->delete();

        // Create variant options for each BOM
        $variantConfigs = [
            'Budget' => [
                'display_name' => 'Paket Hemat',
                'tagline' => 'Solusi ekonomis untuk memulai energi hijau',
                'is_recommended' => false,
                'margin_percent' => 15,
                'features' => [
                    'Garansi Inverter 5 Tahun',
                    'Garansi Panel 10 Tahun',
                    'Instalasi Standar',
                    'Support Teknis 1 Tahun',
                ],
                'specifications' => [
                    'inverter' => 'Growatt 10kW x 5 unit',
                    'panel' => 'NUSA Poly 550Wp x 91 unit',
                    'efficiency' => '~17%',
                    'warranty' => '5 Tahun Inverter, 10 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 10 tahun, inverter 5 tahun',
            ],
            'Standard' => [
                'display_name' => 'Paket Standar',
                'tagline' => 'Keseimbangan performa dan nilai investasi',
                'is_recommended' => true,
                'margin_percent' => 20,
                'features' => [
                    'Garansi Inverter 10 Tahun',
                    'Garansi Panel 12 Tahun',
                    'Instalasi Premium',
                    'Support Teknis 2 Tahun',
                    'Monitoring System',
                ],
                'specifications' => [
                    'inverter' => 'Huawei SUN2000 10kW x 5 unit',
                    'panel' => 'NUSA Mono 550Wp x 91 unit',
                    'efficiency' => '~19%',
                    'warranty' => '10 Tahun Inverter, 12 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 12 tahun, inverter 10 tahun, performance warranty 25 tahun',
            ],
            'Premium' => [
                'display_name' => 'Paket Premium',
                'tagline' => 'Performa maksimal untuk hasil optimal',
                'is_recommended' => false,
                'margin_percent' => 25,
                'features' => [
                    'Garansi Inverter 15 Tahun',
                    'Garansi Panel 15 Tahun',
                    'Instalasi Premium Plus',
                    'Support Teknis 3 Tahun',
                    'Monitoring System Advanced',
                    'Maintenance Gratis 1 Tahun',
                    'Performance Guarantee 90%',
                ],
                'specifications' => [
                    'inverter' => 'SMA Sunny Tripower 10kW x 5 unit',
                    'panel' => 'LONGi Bifacial 550Wp x 91 unit',
                    'efficiency' => '~21%',
                    'warranty' => '15 Tahun Inverter, 15 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 15 tahun, inverter 15 tahun, performance warranty 30 tahun (90%)',
            ],
        ];

        $sortOrder = 0;
        foreach ($boms as $bom) {
            $variantName = $bom->variant_name;
            $config = $variantConfigs[$variantName] ?? null;

            if (! $config) {
                continue;
            }

            // Calculate selling price based on BOM cost + margin
            $bomCost = $bom->total_cost > 0 ? $bom->total_cost : ($sortOrder + 1) * 350000000; // Default if no cost
            $margin = $config['margin_percent'] / 100;
            $sellingPrice = (int) round($bomCost * (1 + $margin));

            QuotationVariantOption::create([
                'quotation_id' => $quotation->id,
                'bom_id' => $bom->id,
                'display_name' => $config['display_name'],
                'tagline' => $config['tagline'],
                'is_recommended' => $config['is_recommended'],
                'selling_price' => $sellingPrice,
                'features' => $config['features'],
                'specifications' => $config['specifications'],
                'warranty_terms' => $config['warranty_terms'],
                'sort_order' => $sortOrder++,
            ]);
        }

        $this->command->info("  → Created multi-option quotation: {$quotationNumber} with {$boms->count()} variants");
    }

    /**
     * Create regular single quotations for other NEX customers.
     */
    private function createRegularQuotations(): void
    {
        $salesUser = User::where('email', 'sales@demo.com')->first();
        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();

        // Find some NEX products
        $plts30kwp = Bom::where('bom_number', 'BOM-PLTS-30KWP')->first();
        $plts20kwp = Bom::where('bom_number', 'BOM-PLTS-20KWP')->first();

        // Create a regular quotation for comparison
        $farmindo = $customers->firstWhere('code', 'C-FAR');
        if ($farmindo && $plts30kwp) {
            self::$quotationSeq++;
            $quotationNumber = 'QUO-'.now()->format('Ym').'-'.str_pad(self::$quotationSeq, 4, '0', STR_PAD_LEFT);

            $sellingPrice = $plts30kwp->total_cost > 0 ? (int) ($plts30kwp->total_cost * 1.2) : 450000000;
            $taxAmount = (int) ($sellingPrice * 0.11);

            $quotation = Quotation::updateOrCreate(
                ['quotation_number' => $quotationNumber, 'revision' => 0],
                [
                    'revision' => 0,
                    'contact_id' => $farmindo->id,
                    'quotation_date' => now()->subDays(10),
                    'valid_until' => now()->addDays(30),
                    'subject' => 'PLTS Rooftop 30 kWp untuk Cold Storage',
                    'quotation_type' => Quotation::TYPE_SINGLE,
                    'status' => Quotation::STATUS_APPROVED,
                    'currency' => 'IDR',
                    'exchange_rate' => 1,
                    'subtotal' => $sellingPrice,
                    'tax_rate' => 11.0,
                    'tax_amount' => $taxAmount,
                    'total' => $sellingPrice + $taxAmount,
                    'base_currency_total' => $sellingPrice + $taxAmount,
                    'terms_conditions' => Quotation::getDefaultTermsConditions(),
                    'created_by' => $salesUser?->id,
                    'assigned_to' => $salesUser?->id,
                    'submitted_at' => now()->subDays(8),
                    'submitted_by' => $salesUser?->id,
                    'approved_at' => now()->subDays(5),
                    'approved_by' => User::where('email', 'admin@demo.com')->first()?->id,
                    'priority' => 'normal',
                    'outcome' => Quotation::OUTCOME_WON,
                    'won_reason' => 'kecepatan_respons',
                    'outcome_at' => now()->subDays(3),
                ]
            );

            // Add quotation items
            QuotationItem::where('quotation_id', $quotation->id)->delete();
            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $plts30kwp->product_id,
                'description' => 'Sistem PLTS Rooftop 30 kWp Turnkey',
                'quantity' => 1,
                'unit' => 'system',
                'unit_price' => $sellingPrice,
                'subtotal' => $sellingPrice,
                'sort_order' => 0,
            ]);
        }
    }
}
