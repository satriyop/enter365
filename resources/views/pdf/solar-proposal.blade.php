<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Solar Proposal - {{ $proposal->proposal_number }}</title>
    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1e293b;
            background: #fff;
        }

        /* Page Setup */
        @page {
            margin: 15mm 15mm 20mm 15mm;
        }

        .page-break {
            page-break-after: always;
        }

        /* Header */
        .header {
            border-bottom: 3px solid #f97316;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 40%;
        }

        .company-name {
            font-size: 24pt;
            font-weight: bold;
            color: #f97316;
        }

        .company-tagline {
            font-size: 9pt;
            color: #64748b;
        }

        .proposal-badge {
            display: inline-block;
            background: #f97316;
            color: white;
            padding: 8px 15px;
            font-size: 11pt;
            font-weight: bold;
            border-radius: 5px;
        }

        /* Title Section */
        .title-section {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            border-radius: 10px;
        }

        .proposal-title {
            font-size: 18pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .proposal-number {
            font-size: 14pt;
            color: #f97316;
            font-weight: bold;
        }

        .customer-name {
            font-size: 12pt;
            color: #475569;
            margin-top: 10px;
        }

        /* Section Styling */
        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #f97316;
            border-bottom: 2px solid #fed7aa;
            padding-bottom: 5px;
            margin-bottom: 12px;
        }

        /* Executive Summary */
        .summary-grid {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
        }

        .summary-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 12px 8px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .summary-value {
            font-size: 16pt;
            font-weight: bold;
            color: #1e293b;
        }

        .summary-value.highlight {
            color: #f97316;
        }

        .summary-value.success {
            color: #16a34a;
        }

        .summary-label {
            font-size: 8pt;
            color: #64748b;
            margin-top: 3px;
        }

        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .info-table .label {
            width: 35%;
            color: #64748b;
            font-size: 9pt;
        }

        .info-table .value {
            font-weight: 500;
            color: #1e293b;
        }

        /* Two Column Layout */
        .two-column {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .column {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }

        .column:first-child {
            padding-right: 15px;
        }

        .column:last-child {
            padding-left: 15px;
        }

        /* Financial Table */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-top: 10px;
        }

        .financial-table th {
            background: #f97316;
            color: white;
            padding: 8px 5px;
            text-align: right;
            font-weight: bold;
        }

        .financial-table th:first-child {
            text-align: center;
            width: 40px;
        }

        .financial-table td {
            padding: 5px;
            text-align: right;
            border-bottom: 1px solid #e2e8f0;
        }

        .financial-table td:first-child {
            text-align: center;
            font-weight: bold;
            color: #64748b;
        }

        .financial-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .financial-table tr.highlight {
            background: #fef3c7;
            font-weight: bold;
        }

        /* Environmental Impact */
        .impact-grid {
            display: table;
            width: 100%;
        }

        .impact-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 15px 10px;
        }

        .impact-icon {
            font-size: 24pt;
            margin-bottom: 5px;
        }

        .impact-value {
            font-size: 14pt;
            font-weight: bold;
            color: #16a34a;
        }

        .impact-label {
            font-size: 8pt;
            color: #64748b;
        }

        /* Terms Box */
        .terms-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .terms-title {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .terms-list {
            font-size: 9pt;
            color: #475569;
        }

        .terms-list li {
            margin-bottom: 4px;
        }

        /* Acceptance Section */
        .acceptance-section {
            margin-top: 30px;
            padding: 20px;
            border: 2px solid #f97316;
            border-radius: 10px;
            background: #fffbeb;
        }

        .acceptance-title {
            font-size: 12pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 15px;
        }

        .signature-grid {
            display: table;
            width: 100%;
            margin-top: 20px;
        }

        .signature-box {
            display: table-cell;
            width: 45%;
            padding: 10px;
        }

        .signature-line {
            border-bottom: 1px solid #1e293b;
            height: 40px;
            margin-bottom: 5px;
        }

        .signature-label {
            font-size: 9pt;
            color: #64748b;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #94a3b8;
            padding: 10px 0;
            border-top: 1px solid #e2e8f0;
        }

        /* Validity Banner */
        .validity-banner {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 5px;
            padding: 10px 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .validity-text {
            color: #92400e;
            font-weight: bold;
        }

        /* BOM Options */
        .bom-option {
            display: table;
            width: 100%;
            padding: 10px;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            background: #f8fafc;
        }

        .bom-option.selected {
            border-color: #f97316;
            background: #fff7ed;
        }

        .bom-name {
            font-weight: bold;
            color: #1e293b;
        }

        .bom-price {
            text-align: right;
            font-weight: bold;
            color: #f97316;
        }

        .selected-badge {
            display: inline-block;
            background: #f97316;
            color: white;
            font-size: 7pt;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="company-name">Enter365</div>
                <div class="company-tagline">Solar Energy Solutions</div>
            </div>
            <div class="header-right">
                <div class="proposal-badge">PROPOSAL</div>
            </div>
        </div>
    </div>

    <!-- Title Section -->
    <div class="title-section">
        <div class="proposal-title">Proposal Sistem PLTS</div>
        <div class="proposal-number">{{ $proposal->proposal_number }}</div>
        <div class="customer-name">Untuk: {{ $proposal->contact->name ?? 'Customer' }}</div>
    </div>

    <!-- Validity Banner -->
    <div class="validity-banner">
        <span class="validity-text">
            Proposal berlaku hingga: {{ $proposal->valid_until?->format('d F Y') ?? '-' }}
        </span>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <div class="section-title">Ringkasan Eksekutif</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">{{ number_format($proposal->system_capacity_kwp ?? 0, 1) }}</div>
                <div class="summary-label">kWp Kapasitas</div>
            </div>
            <div class="summary-item">
                <div class="summary-value highlight">Rp {{ number_format($proposal->getSystemCost() ?? 0, 0, ',', '.') }}</div>
                <div class="summary-label">Investasi</div>
            </div>
            <div class="summary-item">
                <div class="summary-value success">{{ number_format($proposal->getPaybackPeriod() ?? 0, 1) }}</div>
                <div class="summary-label">Tahun Balik Modal</div>
            </div>
            <div class="summary-item">
                <div class="summary-value success">{{ number_format($proposal->getRoi() ?? 0, 0) }}%</div>
                <div class="summary-label">ROI 25 Tahun</div>
            </div>
        </div>
    </div>

    <!-- Two Column: Site Info & Electricity Profile -->
    <div class="two-column">
        <div class="column">
            <div class="section">
                <div class="section-title">Informasi Lokasi</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Nama Site</td>
                        <td class="value">{{ $proposal->site_name }}</td>
                    </tr>
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="value">{{ $proposal->site_address }}</td>
                    </tr>
                    <tr>
                        <td class="label">Kota/Provinsi</td>
                        <td class="value">{{ $proposal->city }}, {{ $proposal->province }}</td>
                    </tr>
                    <tr>
                        <td class="label">Luas Atap</td>
                        <td class="value">{{ number_format($proposal->roof_area_m2 ?? 0, 0) }} m²</td>
                    </tr>
                    <tr>
                        <td class="label">Tipe Atap</td>
                        <td class="value">{{ $proposal->getRoofTypeLabel() }}</td>
                    </tr>
                    <tr>
                        <td class="label">Orientasi</td>
                        <td class="value">{{ $proposal->getOrientationLabel() }}</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="column">
            <div class="section">
                <div class="section-title">Profil Listrik</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Konsumsi Bulanan</td>
                        <td class="value">{{ number_format($proposal->monthly_consumption_kwh ?? 0, 0, ',', '.') }} kWh</td>
                    </tr>
                    <tr>
                        <td class="label">Tarif PLN</td>
                        <td class="value">{{ $proposal->pln_tariff_category }}</td>
                    </tr>
                    <tr>
                        <td class="label">Harga Listrik</td>
                        <td class="value">Rp {{ number_format($proposal->electricity_rate ?? 0, 0, ',', '.') }}/kWh</td>
                    </tr>
                    <tr>
                        <td class="label">Kenaikan Tarif/Tahun</td>
                        <td class="value">{{ $proposal->tariff_escalation_percent ?? 0 }}%</td>
                    </tr>
                    <tr>
                        <td class="label">Peak Sun Hours</td>
                        <td class="value">{{ number_format($proposal->peak_sun_hours ?? 0, 1) }} jam/hari</td>
                    </tr>
                    <tr>
                        <td class="label">Performance Ratio</td>
                        <td class="value">{{ number_format(($proposal->performance_ratio ?? 0) * 100, 0) }}%</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- System Specifications -->
    <div class="section">
        <div class="section-title">Spesifikasi Sistem</div>
        <div class="two-column">
            <div class="column">
                <table class="info-table">
                    <tr>
                        <td class="label">Kapasitas Sistem</td>
                        <td class="value">{{ number_format($proposal->system_capacity_kwp ?? 0, 1) }} kWp</td>
                    </tr>
                    <tr>
                        <td class="label">Produksi Tahunan</td>
                        <td class="value">{{ number_format($proposal->annual_production_kwh ?? 0, 0, ',', '.') }} kWh</td>
                    </tr>
                    <tr>
                        <td class="label">Produksi Bulanan</td>
                        <td class="value">{{ number_format($proposal->getMonthlyProduction() ?? 0, 0, ',', '.') }} kWh</td>
                    </tr>
                </table>
            </div>
            <div class="column">
                <table class="info-table">
                    <tr>
                        <td class="label">Offset Listrik</td>
                        <td class="value">{{ number_format($proposal->getSolarOffsetPercent() ?? 0, 1) }}%</td>
                    </tr>
                    <tr>
                        <td class="label">Biaya Sistem</td>
                        <td class="value">Rp {{ number_format($proposal->getSystemCost() ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Paket Dipilih</td>
                        <td class="value">{{ $proposal->selectedBom->name ?? 'Auto-select' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    @if($proposal->variantGroup && $proposal->variantGroup->activeBoms->count() > 1)
    <!-- BOM Options -->
    <div class="section">
        <div class="section-title">Pilihan Paket Tersedia</div>
        @foreach($proposal->variantGroup->activeBoms as $bom)
        <div class="bom-option {{ $proposal->selected_bom_id === $bom->id ? 'selected' : '' }}">
            <table style="width: 100%">
                <tr>
                    <td class="bom-name">
                        {{ $bom->name }}
                        @if($proposal->selected_bom_id === $bom->id)
                        <span class="selected-badge">DIPILIH</span>
                        @endif
                    </td>
                    <td class="bom-price">Rp {{ number_format($bom->total_cost, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
        @endforeach
    </div>
    @endif

    <div class="page-break"></div>

    <!-- Page 2: Financial Analysis -->
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <div class="company-name">Enter365</div>
                <div class="company-tagline">Solar Energy Solutions</div>
            </div>
            <div class="header-right">
                <div style="font-size: 10pt; color: #64748b;">{{ $proposal->proposal_number }}</div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="section">
        <div class="section-title">Analisis Finansial</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value success">Rp {{ number_format($proposal->getFirstYearSavings() ?? 0, 0, ',', '.') }}</div>
                <div class="summary-label">Hemat Tahun Pertama</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">{{ number_format($proposal->getPaybackPeriod() ?? 0, 1) }} thn</div>
                <div class="summary-label">Balik Modal</div>
            </div>
            <div class="summary-item">
                <div class="summary-value highlight">Rp {{ number_format($proposal->getNpv() ?? 0, 0, ',', '.') }}</div>
                <div class="summary-label">NPV</div>
            </div>
            <div class="summary-item">
                <div class="summary-value success">{{ number_format($proposal->getIrr() ?? 0, 1) }}%</div>
                <div class="summary-label">IRR</div>
            </div>
        </div>
    </div>

    <!-- 25-Year Projection Table -->
    @if($proposal->financial_analysis && isset($proposal->financial_analysis['yearly_projections']))
    <div class="section">
        <div class="section-title">Proyeksi 25 Tahun</div>
        <table class="financial-table">
            <thead>
                <tr>
                    <th>Thn</th>
                    <th>Tarif/kWh</th>
                    <th>Produksi (kWh)</th>
                    <th>Hemat/Tahun</th>
                    <th>Kumulatif</th>
                </tr>
            </thead>
            <tbody>
                @php $paybackYear = ceil($proposal->getPaybackPeriod() ?? 0); @endphp
                @foreach($proposal->financial_analysis['yearly_projections'] as $index => $year)
                <tr class="{{ ($index + 1) == $paybackYear ? 'highlight' : '' }}">
                    <td>{{ $year['year'] ?? ($index + 1) }}</td>
                    <td>Rp {{ number_format($year['electricity_rate'] ?? 0, 0, ',', '.') }}</td>
                    <td>{{ number_format($year['production'] ?? $proposal->annual_production_kwh ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($year['savings'] ?? 0, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($year['cumulative_savings'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top: 8px; font-size: 8pt; color: #64748b;">
            * Baris kuning menunjukkan tahun balik modal
        </div>
    </div>
    @endif

    <!-- Total Savings -->
    <div class="section">
        <div style="background: #dcfce7; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 10pt; color: #166534;">Total Penghematan 25 Tahun</div>
            <div style="font-size: 20pt; font-weight: bold; color: #16a34a;">
                Rp {{ number_format($proposal->getTotalLifetimeSavings() ?? 0, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Environmental Impact -->
    <div class="section">
        <div class="section-title">Dampak Lingkungan</div>
        <div class="impact-grid">
            <div class="impact-item">
                <div class="impact-icon">🌱</div>
                <div class="impact-value">{{ number_format($proposal->getCo2OffsetTons() ?? 0, 1) }}</div>
                <div class="impact-label">Ton CO2/Tahun</div>
            </div>
            <div class="impact-item">
                <div class="impact-icon">🌳</div>
                <div class="impact-value">{{ number_format($proposal->getTreesEquivalent() ?? 0, 0, ',', '.') }}</div>
                <div class="impact-label">Pohon Setara</div>
            </div>
            <div class="impact-item">
                <div class="impact-icon">🚗</div>
                <div class="impact-value">{{ number_format($proposal->getCarsEquivalent() ?? 0, 1) }}</div>
                <div class="impact-label">Mobil dari Jalan</div>
            </div>
            <div class="impact-item">
                <div class="impact-icon">🌍</div>
                <div class="impact-value">{{ number_format(($proposal->getCo2OffsetTons() ?? 0) * 25, 0, ',', '.') }}</div>
                <div class="impact-label">Ton CO2 Lifetime</div>
            </div>
        </div>
    </div>

    <!-- Terms & Conditions -->
    <div class="terms-box">
        <div class="terms-title">Syarat & Ketentuan</div>
        <ul class="terms-list">
            <li>Harga belum termasuk PPN 11%</li>
            <li>Garansi panel surya: 25 tahun performa, 12 tahun produk</li>
            <li>Garansi inverter: 5-10 tahun (tergantung merek)</li>
            <li>Estimasi produksi berdasarkan data iradiasi lokasi dan asumsi performance ratio {{ ($proposal->performance_ratio ?? 0) * 100 }}%</li>
            <li>Penghematan aktual dapat bervariasi tergantung kondisi cuaca dan pola konsumsi</li>
            <li>Proposal ini berlaku hingga {{ $proposal->valid_until?->format('d F Y') ?? '-' }}</li>
        </ul>
    </div>

    <!-- Acceptance Section -->
    <div class="acceptance-section">
        <div class="acceptance-title">Persetujuan Proposal</div>
        <p style="font-size: 9pt; color: #475569; margin-bottom: 15px;">
            Dengan menandatangani di bawah ini, saya menyetujui proposal sistem PLTS ini dan bersedia melanjutkan ke tahap berikutnya.
        </p>

        <div class="signature-grid">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Tanda Tangan Customer</div>
                <div style="margin-top: 10px;">
                    <div class="signature-line" style="height: 20px;"></div>
                    <div class="signature-label">Nama: {{ $proposal->contact->name ?? '________________' }}</div>
                </div>
                <div style="margin-top: 5px;">
                    <div class="signature-label">Tanggal: ____________________</div>
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Tanda Tangan Enter365</div>
                <div style="margin-top: 10px;">
                    <div class="signature-line" style="height: 20px;"></div>
                    <div class="signature-label">Nama: ____________________</div>
                </div>
                <div style="margin-top: 5px;">
                    <div class="signature-label">Tanggal: ____________________</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Enter365 - Solar Energy Solutions | {{ $proposal->proposal_number }} | Generated: {{ now()->format('d M Y H:i') }}</p>
    </div>
</body>
</html>
