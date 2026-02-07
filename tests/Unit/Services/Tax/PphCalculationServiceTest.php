<?php

declare(strict_types=1);

use App\Enums\PphCategory;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Services\Tax\PphCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['accounting.pph.enabled' => true]);
    $this->service = new PphCalculationService;
});

describe('PphCalculationService', function () {

    it('returns null when PPh is disabled', function () {
        config(['accounting.pph.enabled' => false]);

        $bill = Bill::factory()
            ->for(Contact::factory()->pphSubject())
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->toBeNull();
    });

    it('returns null when contact is not PPh subject', function () {
        $bill = Bill::factory()
            ->for(Contact::factory()->supplier())
            ->hasItems(1)
            ->create(['total_amount' => 11_100_000]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->toBeNull();
    });

    it('calculates PPh 23 Jasa at 2%', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->not->toBeNull()
            ->and($result->category)->toBe(PphCategory::Pph23Jasa)
            ->and($result->rate)->toBe(2.00)
            ->and($result->pphAmount)->toBe(200_000)
            ->and($result->cashAmount)->toBe(10_900_000)
            ->and($result->accountCode)->toBe('2-1302');
    });

    it('calculates PPh 23 Bunga at 15%', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Bunga)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Bunga,
                'pph_rate' => 15.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 1_500_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->not->toBeNull()
            ->and($result->rate)->toBe(15.00)
            ->and($result->pphAmount)->toBe(1_500_000)
            ->and($result->cashAmount)->toBe(9_600_000);
    });

    it('calculates PPh 4(2) Sewa at 10%', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph4ayat2Sewa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 5_000_000,
                'discount_amount' => 0,
                'total_amount' => 5_550_000,
                'pph_category' => PphCategory::Pph4ayat2Sewa,
                'pph_rate' => 10.00,
                'pph_base_amount' => 5_000_000,
                'pph_amount' => 500_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 5_550_000);

        expect($result)->not->toBeNull()
            ->and($result->category)->toBe(PphCategory::Pph4ayat2Sewa)
            ->and($result->pphAmount)->toBe(500_000)
            ->and($result->accountCode)->toBe('2-1305');
    });

    it('calculates PPh 26 for foreign entity at 20%', function () {
        $contact = Contact::factory()->foreignEntity()->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph26,
                'pph_rate' => 20.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 2_000_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->not->toBeNull()
            ->and($result->category)->toBe(PphCategory::Pph26)
            ->and($result->rate)->toBe(20.00)
            ->and($result->pphAmount)->toBe(2_000_000)
            ->and($result->accountCode)->toBe('2-1306');
    });

    it('applies 200% surcharge for no-NPWP on PPh 23', function () {
        $contact = Contact::factory()->pphSubjectNoNpwp(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 4.00, // 2% * 200% = 4%
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 400_000,
            ]);

        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->not->toBeNull()
            ->and($result->rate)->toBe(4.00)
            ->and($result->pphAmount)->toBe(400_000)
            ->and($result->noNpwpSurcharge)->toBeFalse(); // rate already baked in from bill
    });

    it('proportionally calculates PPh for partial payments', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'pph_withheld_amount' => 0,
            ]);

        // Pay half
        $result = $this->service->calculateForBillPayment($bill, 5_550_000);

        expect($result)->not->toBeNull()
            ->and($result->pphAmount)->toBe(100_000) // half of 200K
            ->and($result->cashAmount)->toBe(5_450_000);
    });

    it('caps PPh at remaining withholding', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph23Jasa)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph23Jasa,
                'pph_rate' => 2.00,
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 200_000,
                'pph_withheld_amount' => 150_000, // already withheld 150K
            ]);

        // Remaining PPh = 50K, but proportional for full payment would be 200K
        $result = $this->service->calculateForBillPayment($bill, 11_100_000);

        expect($result)->not->toBeNull()
            ->and($result->pphAmount)->toBe(50_000); // capped at remaining
    });

    it('accepts custom rate override', function () {
        $contact = Contact::factory()->pphSubject(PphCategory::Pph4ayat2Konstruksi)->create();
        $bill = Bill::factory()
            ->for($contact)
            ->hasItems(1)
            ->create([
                'subtotal' => 10_000_000,
                'discount_amount' => 0,
                'total_amount' => 11_100_000,
                'pph_category' => PphCategory::Pph4ayat2Konstruksi,
                'pph_rate' => 0, // will use override
                'pph_base_amount' => 10_000_000,
                'pph_amount' => 0,
            ]);

        // Override rate to 6% (highest construction tier)
        $result = $this->service->calculateForBillPayment($bill, 11_100_000, rateOverride: 6.0);

        expect($result)->not->toBeNull()
            ->and($result->rate)->toBe(6.0)
            ->and($result->pphAmount)->toBe(600_000);
    });

});
