<?php

use App\Domain\Shared\ValueObjects\CurrencyCode;

describe('CurrencyCode Value Object', function () {

    it('can create IDR currency', function () {
        $currency = CurrencyCode::IDR();

        expect($currency->code)->toBe('IDR');
        expect($currency->getName())->toBe('Indonesian Rupiah');
    });

    it('can create USD currency', function () {
        $currency = CurrencyCode::USD();

        expect($currency->code)->toBe('USD');
        expect($currency->getName())->toBe('US Dollar');
    });

    it('can create EUR currency', function () {
        $currency = CurrencyCode::EUR();

        expect($currency->code)->toBe('EUR');
        expect($currency->getName())->toBe('Euro');
    });

    it('can create currency from string', function () {
        $currency = CurrencyCode::fromString('IDR');

        expect($currency->code)->toBe('IDR');
    });

    it('normalizes currency code to uppercase', function () {
        $currency = CurrencyCode::fromString('idr');

        expect($currency->code)->toBe('IDR');
    });

    it('cannot create invalid currency code', function () {
        expect(fn () => CurrencyCode::fromString('XYZ'))
            ->toThrow(InvalidArgumentException::class, 'Invalid currency code');
    });

    it('can check if IDR', function () {
        $idr = CurrencyCode::IDR();
        $usd = CurrencyCode::USD();

        expect($idr->isIDR())->toBeTrue();
        expect($usd->isIDR())->toBeFalse();
    });

    it('can check if USD', function () {
        $usd = CurrencyCode::USD();

        expect($usd->isUSD())->toBeTrue();
    });

    it('can check if EUR', function () {
        $eur = CurrencyCode::EUR();

        expect($eur->isEUR())->toBeTrue();
    });

    it('can check equality', function () {
        $c1 = CurrencyCode::fromString('IDR');
        $c2 = CurrencyCode::fromString('IDR');
        $c3 = CurrencyCode::fromString('USD');

        expect($c1->isSameAs($c2))->toBeTrue();
        expect($c1->isSameAs($c3))->toBeFalse();
    });

    it('can get currency symbol', function () {
        expect(CurrencyCode::IDR()->getSymbol())->toBe('Rp');
        expect(CurrencyCode::USD()->getSymbol())->toBe('$');
        expect(CurrencyCode::fromString('EUR')->getSymbol())->toBe('€');
        expect(CurrencyCode::fromString('GBP')->getSymbol())->toBe('£');
        expect(CurrencyCode::fromString('JPY')->getSymbol())->toBe('¥');
    });

    it('can format money with symbol', function () {
        $currency = CurrencyCode::IDR();
        $formatted = $currency->formatMoney(1500000);

        expect($formatted)->toBe('Rp 1.500.000');
    });

    it('can convert currency amount', function () {
        $idr = CurrencyCode::IDR();
        $usd = CurrencyCode::USD();

        $converted = $idr->convertTo(100, $usd, 15000);

        expect($converted)->toBe(1500000); // 100 * 15000 = 1,500,000
    });

    it('returns same amount when converting to same currency', function () {
        $idr = CurrencyCode::IDR();

        $converted = $idr->convertTo(100000, $idr, 1);

        expect($converted)->toBe(100000);
    });

    it('cannot convert with invalid exchange rate for different currencies', function () {
        $usd = CurrencyCode::USD();
        $idr = CurrencyCode::IDR();

        expect(fn () => $usd->convertTo(100, $idr, 0))
            ->toThrow(InvalidArgumentException::class, 'greater than zero');
    });

    it('can convert to IDR', function () {
        $usd = CurrencyCode::USD();

        $idr = $usd->toIDR(15000);

        expect($idr->isIDR())->toBeTrue();
    });

    it('returns same if already IDR', function () {
        $idr = CurrencyCode::IDR();

        $result = $idr->toIDR(15000);

        expect($result->isSameAs($idr))->toBeTrue();
    });

    it('can validate currency code', function () {
        expect(CurrencyCode::isValid('IDR'))->toBeTrue();
        expect(CurrencyCode::isValid('USD'))->toBeTrue();
        expect(CurrencyCode::isValid('XYZ'))->toBeFalse();
    });

    it('can get all supported codes', function () {
        $codes = CurrencyCode::getSupportedCodes();

        expect($codes)->toBeArray();
        expect(isset($codes['IDR']))->toBeTrue();
        expect(isset($codes['USD']))->toBeTrue();
        expect(isset($codes['EUR']))->toBeTrue();
    });

    it('is always supported', function () {
        $currency = CurrencyCode::IDR();

        expect($currency->isSupported())->toBeTrue();
    });

    it('can convert to string', function () {
        $currency = CurrencyCode::IDR();

        expect((string) $currency)->toBe('IDR');
    });

    it('has many supported currencies', function () {
        $codes = CurrencyCode::getSupportedCodes();

        expect(count($codes))->toBeGreaterThan(10);
    });
});
