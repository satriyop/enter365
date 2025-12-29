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
            font-size: 9pt;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
            padding: 0 15mm;
        }

        /* Page Setup */
        @page {
            margin: 18mm 8mm 22mm 8mm;
        }

        /* Page break controls */
        .page-break {
            page-break-after: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Header */
        .header {
            border-bottom: 2px solid #f97316;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header-table {
            width: 100%;
        }

        .header-table td {
            vertical-align: middle;
        }

        .company-name {
            font-size: 20pt;
            font-weight: bold;
            color: #f97316;
        }

        .company-tagline {
            font-size: 8pt;
            color: #64748b;
        }

        .proposal-badge {
            display: inline-block;
            background: #f97316;
            color: white;
            padding: 6px 12px;
            font-size: 10pt;
            font-weight: bold;
        }

        /* Title Section */
        .title-section {
            text-align: center;
            margin-bottom: 15px;
            padding: 15px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }

        .proposal-title {
            font-size: 16pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 3px;
        }

        .proposal-number {
            font-size: 12pt;
            color: #f97316;
            font-weight: bold;
        }

        .customer-name {
            font-size: 11pt;
            color: #475569;
            margin-top: 8px;
        }

        .validity-text {
            font-size: 9pt;
            color: #92400e;
            margin-top: 8px;
            font-weight: bold;
        }

        /* Section Styling */
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #f97316;
            border-bottom: 1px solid #fed7aa;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        /* Summary Grid */
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
        }

        .summary-table td {
            width: 25%;
            text-align: center;
            padding: 10px 5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .summary-value {
            font-size: 13pt;
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
            font-size: 7pt;
            color: #64748b;
            margin-top: 2px;
        }

        /* Info Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 9pt;
        }

        .info-table .label {
            width: 45%;
            color: #64748b;
        }

        .info-table .value {
            font-weight: 500;
            color: #1e293b;
        }

        /* Two Column Layout */
        .two-col-table {
            width: 100%;
        }

        .two-col-table > tbody > tr > td {
            width: 48%;
            vertical-align: top;
            padding: 0;
        }

        .two-col-table > tbody > tr > td:first-child {
            padding-right: 10px;
        }

        .two-col-table > tbody > tr > td:last-child {
            padding-left: 10px;
        }

        /* Bill Comparison */
        .bill-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .bill-table td {
            width: 33.33%;
            text-align: center;
            padding: 10px 5px;
            vertical-align: middle;
        }

        .bill-table .before {
            background: #fef9c3;
        }

        .bill-table .after {
            background: #dcfce7;
        }

        .bill-table .savings {
            background: #d1fae5;
        }

        .bill-label {
            font-size: 7pt;
            color: #64748b;
            margin-bottom: 3px;
        }

        .bill-value {
            font-size: 10pt;
            font-weight: bold;
        }

        .bill-value.before-val { color: #ca8a04; }
        .bill-value.after-val { color: #16a34a; }
        .bill-value.savings-val { color: #059669; }

        .bill-sublabel {
            font-size: 6pt;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* Map Section */
        .map-container {
            border: 1px solid #e2e8f0;
            text-align: center;
            margin-bottom: 10px;
        }

        .map-image {
            width: 100%;
            max-height: 150px;
        }

        .map-coords {
            font-size: 7pt;
            color: #64748b;
            padding: 5px;
            background: #f8fafc;
        }

        /* Bar Chart Styles */
        .chart-container {
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin: 10px 0;
        }

        .chart-title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .bar-chart {
            width: 100%;
            border-collapse: collapse;
        }

        .bar-chart td {
            vertical-align: bottom;
            text-align: center;
            padding: 0 2px;
        }

        .bar-cell {
            height: 80px;
            vertical-align: bottom;
        }

        .bar {
            width: 100%;
            min-height: 4px;
        }

        .bar-red {
            background: #ef4444;
        }

        .bar-green {
            background: #22c55e;
        }

        .bar-yellow {
            background: #fbbf24;
        }

        .bar-yellow-light {
            background: #fde047;
        }

        .chart-labels td {
            font-size: 6pt;
            color: #64748b;
            padding-top: 3px;
            text-align: center;
        }

        .chart-legend {
            text-align: center;
            margin-top: 8px;
            font-size: 7pt;
        }

        .legend-item {
            display: inline;
            margin: 0 8px;
        }

        .legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-right: 3px;
            vertical-align: middle;
        }

        /* Financial Table */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-top: 8px;
        }

        .financial-table th {
            background: #f97316;
            color: white;
            padding: 5px 3px;
            text-align: right;
            font-weight: bold;
        }

        .financial-table th:first-child {
            text-align: center;
            width: 30px;
        }

        .financial-table td {
            padding: 3px;
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

        /* Impact Grid */
        .impact-table {
            width: 100%;
        }

        .impact-table td {
            width: 25%;
            text-align: center;
            padding: 10px 5px;
        }

        .impact-icon {
            font-size: 18pt;
            margin-bottom: 3px;
        }

        .impact-value {
            font-size: 12pt;
            font-weight: bold;
            color: #16a34a;
        }

        .impact-label {
            font-size: 7pt;
            color: #64748b;
        }

        /* Terms Box */
        .terms-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px;
            margin-top: 12px;
        }

        .terms-title {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 6px;
            font-size: 9pt;
        }

        .terms-list {
            font-size: 8pt;
            color: #475569;
            padding-left: 15px;
        }

        .terms-list li {
            margin-bottom: 3px;
        }

        /* Acceptance Section */
        .acceptance-section {
            margin-top: 20px;
            padding: 15px;
            border: 2px solid #f97316;
            background: #fffbeb;
            page-break-inside: avoid;
        }

        .acceptance-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .signature-table {
            width: 100%;
            margin-top: 15px;
        }

        .signature-table td {
            width: 45%;
            padding: 8px;
            vertical-align: top;
        }

        .signature-line {
            border-bottom: 1px solid #1e293b;
            height: 35px;
            margin-bottom: 4px;
        }

        .signature-label {
            font-size: 8pt;
            color: #64748b;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
            padding: 8px 15mm;
            border-top: 1px solid #e2e8f0;
        }

        /* Total Savings Box */
        .total-savings-box {
            background: #dcfce7;
            padding: 12px;
            text-align: center;
        }

        .total-savings-label {
            font-size: 9pt;
            color: #166534;
        }

        .total-savings-value {
            font-size: 18pt;
            font-weight: bold;
            color: #16a34a;
        }

        /* Selected Package Box */
        .selected-package {
            background: #fff7ed;
            border: 2px solid #f97316;
            padding: 12px;
            margin-top: 10px;
        }

        .package-label {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 4px;
        }

        .package-name {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
        }

        .package-price {
            font-size: 14pt;
            font-weight: bold;
            color: #f97316;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- ==================== PAGE 1: Overview ==================== -->

    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 65%;">
                    <div class="company-name">Enter365</div>
                    <div class="company-tagline">Solar Energy Solutions</div>
                </td>
                <td style="width: 35%; text-align: right;">
                    <span class="proposal-badge">PROPOSAL</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Title Section -->
    <div class="title-section no-break">
        <div class="proposal-title">Proposal Sistem PLTS</div>
        <div class="proposal-number">{{ $proposal->proposal_number }}</div>
        <div class="customer-name">Untuk: {{ $proposal->contact->name ?? 'Customer' }}</div>
        <div class="validity-text">Berlaku hingga: {{ $proposal->valid_until?->format('d F Y') ?? '-' }}</div>
    </div>

    <!-- Executive Summary -->
    <div class="section no-break">
        <div class="section-title">Ringkasan Eksekutif</div>
        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-value">{{ number_format($proposal->system_capacity_kwp ?? 0, 1) }}</div>
                    <div class="summary-label">kWp Kapasitas</div>
                </td>
                <td>
                    <div class="summary-value highlight">Rp {{ number_format($proposal->getSystemCost() ?? 0, 0, ',', '.') }}</div>
                    <div class="summary-label">Investasi</div>
                </td>
                <td>
                    <div class="summary-value success">{{ number_format($proposal->getPaybackPeriod() ?? 0, 1) }}</div>
                    <div class="summary-label">Tahun Balik Modal</div>
                </td>
                <td>
                    <div class="summary-value success">{{ number_format($proposal->getRoi() ?? 0, 0) }}%</div>
                    <div class="summary-label">ROI 25 Tahun</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Monthly Bill Comparison -->
    @php
        $monthlyConsumption = $proposal->monthly_consumption_kwh ?? 0;
        $monthlyProduction = $proposal->getMonthlyProduction() ?? 0;
        $electricityRate = $proposal->electricity_rate ?? 0;

        // Bill calculations
        $billBefore = $monthlyConsumption * $electricityRate;
        $billAfter = max(0, $monthlyConsumption - $monthlyProduction) * $electricityRate;

        // For CHART: Savings = what was actually saved from the bill
        // Green + Yellow should equal Original Bill (billBefore)
        $savingsForChart = $billBefore - $billAfter;

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $maxBillHeight = 70;

        // Max value for scaling is billBefore (since green + yellow = billBefore)
        $maxBillValue = max($billBefore, 1);

        // Calculate bar heights
        $beforeBarH = max(4, round(($billBefore / $maxBillValue) * $maxBillHeight));
        $afterBarH = $billAfter > 0 ? max(4, round(($billAfter / $maxBillValue) * $maxBillHeight)) : 0;
        $savingsBarH = $savingsForChart > 0 ? max(4, round(($savingsForChart / $maxBillValue) * $maxBillHeight)) : 0;
    @endphp
    <div class="section no-break">
        <div class="section-title">Perbandingan Tagihan Bulanan</div>

        <!-- Monthly Bar Chart - Grouped Bars -->
        <div class="chart-container" style="position: relative;">
            {{-- Trend line simulation using positioned dots --}}
            <div style="position: absolute; top: 0; left: 0; right: 0; height: {{ $maxBillHeight }}px; pointer-events: none;">
                @php
                    // Calculate trend line Y positions (from top)
                    $trendBeforeY = $maxBillHeight - $beforeBarH + 5; // 5px padding from top of chart
                    $trendAfterY = $maxBillHeight - $afterBarH + 5;
                @endphp
                {{-- Dotted trend line spans across the chart --}}
                <div style="position: absolute; top: {{ $trendBeforeY }}px; left: 2%; width: 46%; border-top: 2px dashed #0ea5e9;"></div>
                <div style="position: absolute; top: {{ ($trendBeforeY + $trendAfterY) / 2 }}px; left: 48%; width: 4%; border-top: 2px dashed #0ea5e9; transform: rotate(-15deg);"></div>
                <div style="position: absolute; top: {{ $trendAfterY }}px; left: 52%; width: 46%; border-top: 2px dashed #0ea5e9;"></div>
            </div>

            <table class="bar-chart" style="position: relative; z-index: 1;">
                <tr>
                    @foreach($months as $idx => $month)
                    <td style="width: 8.33%; vertical-align: bottom; height: {{ $maxBillHeight }}px;">
                        @if($idx < 6)
                            {{-- Before Solar: Single yellow bar with blue border --}}
                            <table style="width: 100%; height: {{ $maxBillHeight }}px; border-collapse: collapse;">
                                <tr><td style="vertical-align: bottom; padding: 0;">
                                    <div style="height: {{ $beforeBarH }}px; background: #fde047; border: 2px solid #0ea5e9; margin: 0 auto; width: 80%;"></div>
                                </td></tr>
                            </table>
                        @else
                            {{-- After Solar: TWO GROUPED bars side by side --}}
                            <table style="width: 100%; height: {{ $maxBillHeight }}px; border-collapse: collapse;">
                                <tr><td style="vertical-align: bottom; padding: 0;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            {{-- Green bar (Savings) --}}
                                            <td style="width: 50%; vertical-align: bottom; padding: 0 1px;">
                                                @if($savingsBarH > 0)
                                                <div style="height: {{ $savingsBarH }}px; background: #22c55e; width: 100%;"></div>
                                                @endif
                                            </td>
                                            {{-- Yellow bar (Remaining Bill) --}}
                                            <td style="width: 50%; vertical-align: bottom; padding: 0 1px;">
                                                @if($afterBarH > 0)
                                                <div style="height: {{ $afterBarH }}px; background: #fde047; border: 2px solid #0ea5e9; width: 100%;"></div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td></tr>
                            </table>
                        @endif
                    </td>
                    @endforeach
                </tr>
            </table>

            <!-- Month labels -->
            <table class="bar-chart chart-labels" style="border-top: 1px solid #e2e8f0;">
                <tr>
                    @foreach($months as $month)
                    <td style="width: 8.33%;">{{ $month }}</td>
                    @endforeach
                </tr>
            </table>

            <!-- Legend -->
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot" style="background: #22c55e;"></span> Hemat dari Solar</span>
                <span class="legend-item"><span class="legend-dot" style="background: #fde047; border: 1px solid #0ea5e9;"></span> Tagihan PLN</span>
                <span class="legend-item" style="color: #0ea5e9;">┈┈ Tren Tagihan</span>
            </div>
        </div>

        <!-- Summary boxes -->
        <table class="bill-table" style="margin-top: 10px;">
            <tr>
                <td class="before">
                    <div class="bill-label">Sebelum Solar</div>
                    <div class="bill-value before-val">Rp {{ number_format($billBefore, 0, ',', '.') }}</div>
                    <div class="bill-sublabel">{{ number_format($monthlyConsumption, 0, ',', '.') }} kWh</div>
                </td>
                <td class="after">
                    <div class="bill-label">Setelah Solar</div>
                    <div class="bill-value after-val">Rp {{ number_format($billAfter, 0, ',', '.') }}</div>
                    <div class="bill-sublabel">{{ number_format(max(0, $monthlyConsumption - $monthlyProduction), 0, ',', '.') }} kWh</div>
                </td>
                <td class="savings">
                    <div class="bill-label">Hemat/Bulan</div>
                    <div class="bill-value savings-val">Rp {{ number_format($savingsForChart, 0, ',', '.') }}</div>
                    <div class="bill-sublabel">{{ number_format(min($monthlyProduction, $monthlyConsumption), 0, ',', '.') }} kWh</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Location Map -->
    @if($proposal->latitude && $proposal->longitude)
    <div class="section no-break">
        <div class="section-title">Lokasi Instalasi</div>
        <div class="map-container">
            @php
                $lat = $proposal->latitude;
                $lng = $proposal->longitude;
                $mapUrl = "https://staticmap.openstreetmap.de/staticmap.php?center={$lat},{$lng}&zoom=17&size=600x150&markers={$lat},{$lng},red";
            @endphp
            <img src="{{ $mapUrl }}" alt="Lokasi" class="map-image" onerror="this.style.display='none'">
            <div class="map-coords">
                {{ $proposal->site_address }} | Koordinat: {{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}
                @if($proposal->roof_area_m2) | Luas Atap: {{ number_format($proposal->roof_area_m2, 0) }} m² @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Two Column: Site Info & Electricity Profile -->
    <div class="section no-break">
        <table class="two-col-table">
            <tr>
                <td>
                    <div class="section-title">Informasi Lokasi</div>
                    <table class="info-table">
                        <tr><td class="label">Nama Site</td><td class="value">{{ $proposal->site_name }}</td></tr>
                        <tr><td class="label">Alamat</td><td class="value">{{ $proposal->site_address }}</td></tr>
                        <tr><td class="label">Kota/Provinsi</td><td class="value">{{ $proposal->city }}, {{ $proposal->province }}</td></tr>
                        <tr><td class="label">Tipe Atap</td><td class="value">{{ $proposal->getRoofTypeLabel() }}</td></tr>
                        <tr><td class="label">Orientasi</td><td class="value">{{ $proposal->getOrientationLabel() }}</td></tr>
                    </table>
                </td>
                <td>
                    <div class="section-title">Profil Listrik</div>
                    <table class="info-table">
                        <tr><td class="label">Konsumsi Bulanan</td><td class="value">{{ number_format($proposal->monthly_consumption_kwh ?? 0, 0, ',', '.') }} kWh</td></tr>
                        <tr><td class="label">Tarif PLN</td><td class="value">{{ $proposal->pln_tariff_category }}</td></tr>
                        <tr><td class="label">Harga Listrik</td><td class="value">Rp {{ number_format($proposal->electricity_rate ?? 0, 0, ',', '.') }}/kWh</td></tr>
                        <tr><td class="label">Peak Sun Hours</td><td class="value">{{ number_format($proposal->peak_sun_hours ?? 0, 1) }} jam/hari</td></tr>
                        <tr><td class="label">Performance Ratio</td><td class="value">{{ number_format(($proposal->performance_ratio ?? 0) * 100, 0) }}%</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- System Specifications -->
    <div class="section no-break">
        <div class="section-title">Spesifikasi Sistem</div>
        <table class="two-col-table">
            <tr>
                <td>
                    <table class="info-table">
                        <tr><td class="label">Kapasitas Sistem</td><td class="value">{{ number_format($proposal->system_capacity_kwp ?? 0, 1) }} kWp</td></tr>
                        <tr><td class="label">Produksi Tahunan</td><td class="value">{{ number_format($proposal->annual_production_kwh ?? 0, 0, ',', '.') }} kWh</td></tr>
                        <tr><td class="label">Produksi Bulanan</td><td class="value">{{ number_format($proposal->getMonthlyProduction() ?? 0, 0, ',', '.') }} kWh</td></tr>
                    </table>
                </td>
                <td>
                    <table class="info-table">
                        <tr><td class="label">Offset Listrik</td><td class="value">{{ number_format($proposal->getSolarOffsetPercent() ?? 0, 1) }}%</td></tr>
                        <tr><td class="label">Kenaikan Tarif/Tahun</td><td class="value">{{ $proposal->tariff_escalation_percent ?? 0 }}%</td></tr>
                        <tr><td class="label">Luas Atap</td><td class="value">{{ number_format($proposal->roof_area_m2 ?? 0, 0) }} m²</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($proposal->selectedBom)
        <div class="selected-package">
            <div class="package-label">Paket yang Dipilih</div>
            <div class="package-name">{{ $proposal->selectedBom->name }}</div>
            <div class="package-price">Rp {{ number_format($proposal->getSystemCost() ?? 0, 0, ',', '.') }}</div>
        </div>
        @endif
    </div>

    <!-- ==================== PAGE 2: Financial Analysis ==================== -->
    <div class="page-break"></div>

    <!-- Header Page 2 -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 65%;">
                    <div class="company-name">Enter365</div>
                    <div class="company-tagline">Solar Energy Solutions</div>
                </td>
                <td style="width: 35%; text-align: right;">
                    <span style="font-size: 9pt; color: #64748b;">{{ $proposal->proposal_number }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Financial Summary -->
    <div class="section no-break">
        <div class="section-title">Analisis Finansial</div>
        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-value success">Rp {{ number_format($proposal->getFirstYearSavings() ?? 0, 0, ',', '.') }}</div>
                    <div class="summary-label">Hemat Tahun Pertama</div>
                </td>
                <td>
                    <div class="summary-value">{{ number_format($proposal->getPaybackPeriod() ?? 0, 1) }} thn</div>
                    <div class="summary-label">Balik Modal</div>
                </td>
                <td>
                    <div class="summary-value highlight">Rp {{ number_format($proposal->getNpv() ?? 0, 0, ',', '.') }}</div>
                    <div class="summary-label">NPV</div>
                </td>
                <td>
                    <div class="summary-value success">{{ number_format($proposal->getIrr() ?? 0, 1) }}%</div>
                    <div class="summary-label">IRR</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Payback Period Bar Chart -->
    @if($proposal->financial_analysis && isset($proposal->financial_analysis['yearly_projections']))
    @php
        $projections = $proposal->financial_analysis['yearly_projections'];
        $systemCost = $proposal->getSystemCost() ?? 0;
        $cumulative = -$systemCost;
        $cashFlows = [];

        // Calculate cumulative cash flows for first 10 years
        foreach (array_slice($projections, 0, 10) as $year) {
            $cumulative += ($year['savings'] ?? 0);
            $cashFlows[] = $cumulative;
        }

        // Find max absolute value for scaling
        $maxAbsValue = max(array_map('abs', $cashFlows));
        $maxAbsValue = max($maxAbsValue, 1); // Prevent division by zero

        // Max bar height in pixels
        $maxBarHeight = 70;

        // Find payback year
        $paybackYearIdx = null;
        foreach ($cashFlows as $idx => $val) {
            if ($val >= 0) {
                $paybackYearIdx = $idx;
                break;
            }
        }
    @endphp
    <div class="section no-break">
        <div class="chart-container">
            <div class="chart-title">Periode Balik Modal - {{ number_format($proposal->getPaybackPeriod() ?? 0, 1) }} Tahun</div>

            <!-- Bar Chart using nested tables for DomPDF compatibility -->
            <table class="bar-chart">
                <tr>
                    @foreach($cashFlows as $idx => $value)
                    <td style="width: 10%; vertical-align: bottom; height: {{ $maxBarHeight }}px;">
                        @php
                            $barHeight = max(4, round((abs($value) / $maxAbsValue) * $maxBarHeight));
                            $isPositive = $value >= 0;
                            $isPayback = $paybackYearIdx !== null && $idx === $paybackYearIdx;
                            $barColor = $isPositive ? '#22c55e' : '#ef4444';
                            $borderStyle = $isPayback ? 'border: 2px solid #f59e0b;' : '';
                        @endphp
                        <table style="width: 100%; height: {{ $maxBarHeight }}px; border-collapse: collapse;">
                            <tr><td style="vertical-align: bottom; padding: 0;">
                                <div style="height: {{ $barHeight }}px; background: {{ $barColor }}; {{ $borderStyle }} margin: 0 auto; width: 80%;"></div>
                            </td></tr>
                        </table>
                    </td>
                    @endforeach
                </tr>
            </table>

            <!-- X-axis labels -->
            <table class="bar-chart chart-labels" style="border-top: 1px solid #e2e8f0;">
                <tr>
                    @for($i = 1; $i <= 10; $i++)
                    <td style="width: 10%;">Y{{ $i }}</td>
                    @endfor
                </tr>
            </table>

            <!-- Legend -->
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot" style="background: #ef4444;"></span> Recovery</span>
                <span class="legend-item"><span class="legend-dot" style="background: #22c55e;"></span> Profit</span>
                @if($paybackYearIdx !== null)
                <span class="legend-item" style="color: #f59e0b; font-weight: bold;">■ Payback: Tahun {{ $paybackYearIdx + 1 }}</span>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- 25-Year Projection Table -->
    @if($proposal->financial_analysis && isset($proposal->financial_analysis['yearly_projections']))
    <div class="section">
        <div class="section-title">Proyeksi 25 Tahun</div>
        <table class="financial-table">
            <thead>
                <tr>
                    <th>Thn</th>
                    <th>Tarif/kWh</th>
                    <th>Produksi</th>
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
        <div style="margin-top: 6px; font-size: 7pt; color: #64748b;">* Baris kuning menunjukkan tahun balik modal</div>
    </div>
    @endif

    <!-- Total Savings -->
    <div class="section no-break">
        <div class="total-savings-box">
            <div class="total-savings-label">Total Penghematan 25 Tahun</div>
            <div class="total-savings-value">Rp {{ number_format($proposal->getTotalLifetimeSavings() ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Environmental Impact -->
    <div class="section no-break">
        <div class="section-title">Dampak Lingkungan</div>
        <table class="impact-table">
            <tr>
                <td>
                    <div class="impact-icon">🌱</div>
                    <div class="impact-value">{{ number_format($proposal->getCo2OffsetTons() ?? 0, 1) }}</div>
                    <div class="impact-label">Ton CO2/Tahun</div>
                </td>
                <td>
                    <div class="impact-icon">🌳</div>
                    <div class="impact-value">{{ number_format($proposal->getTreesEquivalent() ?? 0, 0, ',', '.') }}</div>
                    <div class="impact-label">Pohon Setara</div>
                </td>
                <td>
                    <div class="impact-icon">🚗</div>
                    <div class="impact-value">{{ number_format($proposal->getCarsEquivalent() ?? 0, 1) }}</div>
                    <div class="impact-label">Mobil dari Jalan</div>
                </td>
                <td>
                    <div class="impact-icon">🌍</div>
                    <div class="impact-value">{{ number_format(($proposal->getCo2OffsetTons() ?? 0) * 25, 0, ',', '.') }}</div>
                    <div class="impact-label">Ton CO2 Lifetime</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Terms & Conditions -->
    <div class="terms-box no-break">
        <div class="terms-title">Syarat & Ketentuan</div>
        <ul class="terms-list">
            <li>Harga belum termasuk PPN 11%</li>
            <li>Garansi panel surya: 25 tahun performa, 12 tahun produk</li>
            <li>Garansi inverter: 5-10 tahun (tergantung merek)</li>
            <li>Estimasi produksi berdasarkan data iradiasi lokasi dan asumsi performance ratio {{ ($proposal->performance_ratio ?? 0) * 100 }}%</li>
            <li>Penghematan aktual dapat bervariasi tergantung kondisi cuaca dan pola konsumsi</li>
        </ul>
    </div>

    <!-- Acceptance Section -->
    <div class="acceptance-section">
        <div class="acceptance-title">Persetujuan Proposal</div>
        <p style="font-size: 8pt; color: #475569; margin-bottom: 10px;">
            Dengan menandatangani di bawah ini, saya menyetujui proposal sistem PLTS ini dan bersedia melanjutkan ke tahap berikutnya.
        </p>

        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-label">Tanda Tangan Customer</div>
                    <div style="margin-top: 8px;"><div class="signature-label">Nama: {{ $proposal->contact->name ?? '________________' }}</div></div>
                    <div style="margin-top: 4px;"><div class="signature-label">Tanggal: ____________________</div></div>
                </td>
                <td></td>
                <td>
                    <div class="signature-line"></div>
                    <div class="signature-label">Tanda Tangan Enter365</div>
                    <div style="margin-top: 8px;"><div class="signature-label">Nama: ____________________</div></div>
                    <div style="margin-top: 4px;"><div class="signature-label">Tanggal: ____________________</div></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        Enter365 - Solar Energy Solutions | {{ $proposal->proposal_number }} | Generated: {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
