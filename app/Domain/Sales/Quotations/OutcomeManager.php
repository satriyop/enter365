<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Models\Sales\Quotation;

readonly class OutcomeManager
{
    public const OUTCOME_WON = 'won';

    public const OUTCOME_LOST = 'lost';

    public const OUTCOME_CANCELLED = 'cancelled';

    public const WON_REASONS = [
        'harga_kompetitif' => 'Harga Kompetitif',
        'kualitas_produk' => 'Kualitas Produk',
        'layanan_baik' => 'Layanan yang Baik',
        'waktu_pengiriman' => 'Waktu Pengiriman Cepat',
        'hubungan_baik' => 'Hubungan Baik dengan Pelanggan',
        'spesifikasi_sesuai' => 'Spesifikasi Sesuai Kebutuhan',
        'rekomendasi' => 'Rekomendasi dari Pelanggan Lain',
        'lainnya' => 'Lainnya',
    ];

    public const LOST_REASONS = [
        'harga_tinggi' => 'Harga Terlalu Tinggi',
        'kalah_kompetitor' => 'Kalah dari Kompetitor',
        'spesifikasi_tidak_sesuai' => 'Spesifikasi Tidak Sesuai',
        'waktu_pengiriman_lama' => 'Waktu Pengiriman Terlalu Lama',
        'proyek_dibatalkan' => 'Proyek Dibatalkan',
        'tidak_ada_budget' => 'Tidak Ada Budget',
        'tidak_ada_respon' => 'Tidak Ada Respon dari Pelanggan',
        'lainnya' => 'Lainnya',
    ];

    public function markAsWon(Quotation $quotation, array $data = []): void
    {
        $quotation->outcome = self::OUTCOME_WON;
        $quotation->won_reason = $data['won_reason'] ?? null;
        $quotation->outcome_notes = $data['outcome_notes'] ?? null;
        $quotation->outcome_at = now();
        $quotation->next_follow_up_at = null;
        $quotation->save();
    }

    public function markAsLost(Quotation $quotation, array $data = []): void
    {
        $quotation->outcome = self::OUTCOME_LOST;
        $quotation->lost_reason = $data['lost_reason'] ?? null;
        $quotation->lost_to_competitor = $data['lost_to_competitor'] ?? null;
        $quotation->outcome_notes = $data['outcome_notes'] ?? null;
        $quotation->outcome_at = now();
        $quotation->next_follow_up_at = null;
        $quotation->save();
    }

    public function getOutcomeLabel(?string $outcome): ?string
    {
        return match ($outcome) {
            self::OUTCOME_WON => 'Menang',
            self::OUTCOME_LOST => 'Kalah',
            self::OUTCOME_CANCELLED => 'Dibatalkan',
            default => null,
        };
    }

    public function canMarkAsWon(Quotation $quotation): bool
    {
        return in_array($quotation->status->value, ['submitted', 'approved'], true);
    }

    public function canMarkAsLost(Quotation $quotation): bool
    {
        return $this->canMarkAsWon($quotation);
    }

    public function hasOutcome(Quotation $quotation): bool
    {
        return $quotation->outcome !== null;
    }
}
