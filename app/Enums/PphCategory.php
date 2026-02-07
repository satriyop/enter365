<?php

declare(strict_types=1);

namespace App\Enums;

enum PphCategory: string
{
    case Pph23Jasa = 'pph23_jasa';
    case Pph23Sewa = 'pph23_sewa';
    case Pph23Bunga = 'pph23_bunga';
    case Pph23Royalti = 'pph23_royalti';
    case Pph4ayat2Konstruksi = 'pph4_2_konstruksi';
    case Pph4ayat2Sewa = 'pph4_2_sewa';
    case Pph26 = 'pph26';

    public function label(): string
    {
        return match ($this) {
            self::Pph23Jasa => 'PPh 23 - Jasa (2%)',
            self::Pph23Sewa => 'PPh 23 - Sewa Peralatan (2%)',
            self::Pph23Bunga => 'PPh 23 - Bunga (15%)',
            self::Pph23Royalti => 'PPh 23 - Royalti (15%)',
            self::Pph4ayat2Konstruksi => 'PPh 4(2) - Konstruksi (2-6%)',
            self::Pph4ayat2Sewa => 'PPh 4(2) - Sewa Tanah/Bangunan (10%)',
            self::Pph26 => 'PPh 26 - Badan Asing (20%)',
        };
    }

    public function defaultRate(): float
    {
        return match ($this) {
            self::Pph23Jasa, self::Pph23Sewa => 2.00,
            self::Pph23Bunga, self::Pph23Royalti => 15.00,
            self::Pph4ayat2Konstruksi => 3.00,
            self::Pph4ayat2Sewa => 10.00,
            self::Pph26 => 20.00,
        };
    }

    /**
     * Get the payable account code for this PPh category.
     */
    public function accountCode(): string
    {
        return match ($this) {
            self::Pph23Jasa, self::Pph23Sewa, self::Pph23Bunga, self::Pph23Royalti => '2-1302',
            self::Pph4ayat2Konstruksi, self::Pph4ayat2Sewa => '2-1305',
            self::Pph26 => '2-1306',
        };
    }

    /**
     * Whether this is a final tax (PPh Final / PPh 4(2)).
     * Final taxes cannot be credited against annual income tax.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Pph4ayat2Konstruksi, self::Pph4ayat2Sewa => true,
            default => false,
        };
    }

    /**
     * Get the config key for rate lookup.
     */
    public function configRateKey(): string
    {
        return "accounting.pph.rates.{$this->value}";
    }

    /**
     * Get the effective rate from config (falls back to default).
     */
    public function effectiveRate(): float
    {
        return (float) config($this->configRateKey(), $this->defaultRate());
    }
}
