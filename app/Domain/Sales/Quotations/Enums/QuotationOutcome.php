<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations\Enums;

enum QuotationOutcome: string
{
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Won => 'Menang',
            self::Lost => 'Kalah',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public const WON_REASONS = [
        'harga_kompetitif' => 'Harga Kompetitif',
        'kualitas_produk' => 'Kualitas Produk',
        'layanan_baik' => 'Layanan yang Baik',
        'waktu_pengiriman' => 'Waktu Pengiriman Cepat',
        'hubungan_baik' => 'Hubungan Baik dengan Pelanggan',
        'spesifikasi_sesuai' => 'Spesifikasi Sesuai Kebutuhan',
        'rekomendasi' => 'Rekomendasi dari Pelanggan Lain',
        'converted_to_invoice' => 'Dikonversi ke Faktur',
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

    public function wonReasons(): array
    {
        return self::WON_REASONS;
    }

    public function lostReasons(): array
    {
        return self::LOST_REASONS;
    }
}
