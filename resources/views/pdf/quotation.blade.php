<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Penawaran - {{ $quotation->quotation_number }}</title>
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
            padding: 0 10mm;
        }

        /* Page Setup */
        @page {
            margin: 15mm 10mm 20mm 10mm;
        }

        .page-break {
            page-break-after: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Header */
        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }

        .header-table {
            width: 100%;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 20pt;
            font-weight: bold;
            color: #2563eb;
        }

        .company-tagline {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }

        .document-badge {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 8px 16px;
            font-size: 12pt;
            font-weight: bold;
        }

        /* Document Info */
        .doc-info {
            margin-bottom: 20px;
        }

        .doc-info-table {
            width: 100%;
        }

        .doc-info-table td {
            vertical-align: top;
            padding: 0;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px;
        }

        .info-box-title {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .info-box-content {
            font-size: 10pt;
            color: #1e293b;
        }

        .info-box-content strong {
            font-size: 11pt;
        }

        .quotation-number {
            font-size: 14pt;
            font-weight: bold;
            color: #2563eb;
        }

        /* Subject Section */
        .subject-section {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px;
            margin-bottom: 20px;
        }

        .subject-label {
            font-size: 8pt;
            color: #64748b;
            margin-bottom: 4px;
        }

        .subject-text {
            font-size: 11pt;
            font-weight: bold;
            color: #1e293b;
        }

        /* Items Table */
        .items-section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #2563eb;
            border-bottom: 2px solid #bfdbfe;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #2563eb;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 8pt;
            font-weight: bold;
        }

        .items-table th.right {
            text-align: right;
        }

        .items-table th.center {
            text-align: center;
        }

        .items-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9pt;
            vertical-align: top;
        }

        .items-table td.right {
            text-align: right;
        }

        .items-table td.center {
            text-align: center;
        }

        .items-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .item-name {
            font-weight: 500;
        }

        .item-desc {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }

        /* Totals Section */
        .totals-section {
            margin-bottom: 20px;
        }

        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 8px;
            font-size: 9pt;
        }

        .totals-table td.label {
            text-align: right;
            color: #64748b;
        }

        .totals-table td.value {
            text-align: right;
            font-weight: 500;
        }

        .totals-table tr.subtotal {
            border-top: 1px solid #e2e8f0;
        }

        .totals-table tr.discount td {
            color: #dc2626;
        }

        .totals-table tr.total {
            background: #2563eb;
            color: white;
        }

        .totals-table tr.total td {
            font-size: 11pt;
            font-weight: bold;
            padding: 10px 8px;
        }

        /* Terms Section */
        .terms-section {
            margin-bottom: 20px;
        }

        .terms-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px;
        }

        .terms-title {
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 10pt;
        }

        .terms-list {
            font-size: 8pt;
            color: #475569;
            padding-left: 16px;
        }

        .terms-list li {
            margin-bottom: 4px;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
        }

        .signature-table td {
            width: 45%;
            padding: 10px;
            vertical-align: top;
        }

        .signature-box {
            border: 1px solid #e2e8f0;
            padding: 15px;
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #1e293b;
            height: 50px;
            margin-bottom: 8px;
        }

        .signature-label {
            font-size: 8pt;
            color: #64748b;
        }

        .signature-name {
            font-size: 9pt;
            font-weight: bold;
            margin-top: 4px;
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
            padding: 8px 10mm;
            border-top: 1px solid #e2e8f0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 8pt;
            font-weight: bold;
            border-radius: 4px;
        }

        .status-draft { background: #fef3c7; color: #92400e; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-expired { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="company-name">{{ config('app.company_name', 'Enter365') }}</div>
                    <div class="company-tagline">{{ config('app.company_tagline', 'Your Trusted Business Partner') }}</div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <span class="document-badge">PENAWARAN</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Document Info -->
    <div class="doc-info">
        <table class="doc-info-table">
            <tr>
                <td style="width: 48%; padding-right: 10px;">
                    <div class="info-box">
                        <div class="info-box-title">Kepada</div>
                        <div class="info-box-content">
                            <strong>{{ $quotation->contact->name ?? '-' }}</strong><br>
                            @if($quotation->contact->address)
                                {{ $quotation->contact->address }}<br>
                            @endif
                            @if($quotation->contact->city || $quotation->contact->province)
                                {{ $quotation->contact->city }}{{ $quotation->contact->city && $quotation->contact->province ? ', ' : '' }}{{ $quotation->contact->province }}<br>
                            @endif
                            @if($quotation->contact->phone)
                                Telp: {{ $quotation->contact->phone }}<br>
                            @endif
                            @if($quotation->contact->email)
                                Email: {{ $quotation->contact->email }}
                            @endif
                        </div>
                    </div>
                </td>
                <td style="width: 48%; padding-left: 10px;">
                    <div class="info-box">
                        <div class="info-box-title">Detail Penawaran</div>
                        <div class="info-box-content">
                            <div class="quotation-number">{{ $quotation->quotation_number }}</div>
                            @if($quotation->revision > 0)
                                <span style="font-size: 8pt; color: #64748b;">(Revisi {{ $quotation->revision }})</span>
                            @endif
                            <table style="margin-top: 8px; font-size: 9pt;">
                                <tr>
                                    <td style="color: #64748b; padding-right: 10px;">Tanggal:</td>
                                    <td>{{ $quotation->quotation_date?->format('d M Y') ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding-right: 10px;">Berlaku s/d:</td>
                                    <td>{{ $quotation->valid_until?->format('d M Y') ?? '-' }}</td>
                                </tr>
                                @if($quotation->reference)
                                <tr>
                                    <td style="color: #64748b; padding-right: 10px;">Referensi:</td>
                                    <td>{{ $quotation->reference }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Subject -->
    @if($quotation->subject)
    <div class="subject-section no-break">
        <div class="subject-label">Perihal</div>
        <div class="subject-text">{{ $quotation->subject }}</div>
    </div>
    @endif

    <!-- Items -->
    <div class="items-section">
        <div class="section-title">Detail Penawaran</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;" class="center">No</th>
                    <th style="width: 35%;">Produk/Jasa</th>
                    <th style="width: 10%;" class="center">Qty</th>
                    <th style="width: 10%;" class="center">Satuan</th>
                    <th style="width: 18%;" class="right">Harga Satuan</th>
                    <th style="width: 18%;" class="right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <div class="item-name">{{ $item->product->name ?? $item->description ?? '-' }}</div>
                        @if($item->description && $item->product)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="center">{{ $item->unit ?? $item->product->unit ?? '-' }}</td>
                    <td class="right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="totals-section no-break">
        <table class="totals-table">
            <tr class="subtotal">
                <td class="label">Subtotal</td>
                <td class="value">Rp {{ number_format($quotation->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if($quotation->discount_amount > 0)
            <tr class="discount">
                <td class="label">
                    Diskon
                    @if($quotation->discount_type === 'percentage')
                        ({{ number_format($quotation->discount_value, 0) }}%)
                    @endif
                </td>
                <td class="value">- Rp {{ number_format($quotation->discount_amount, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if($quotation->tax_amount > 0)
            <tr>
                <td class="label">PPN ({{ number_format($quotation->tax_rate, 0) }}%)</td>
                <td class="value">Rp {{ number_format($quotation->tax_amount, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total">
                <td class="label">TOTAL</td>
                <td class="value">Rp {{ number_format($quotation->total_amount, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Terms & Conditions -->
    <div class="terms-section no-break">
        <div class="terms-box">
            <div class="terms-title">Syarat & Ketentuan</div>
            <ul class="terms-list">
                <li>Harga sudah termasuk PPN {{ number_format($quotation->tax_rate ?? 11, 0) }}% (kecuali disebutkan lain)</li>
                <li>Penawaran ini berlaku sampai dengan {{ $quotation->valid_until?->format('d M Y') ?? '-' }}</li>
                <li>Pembayaran dilakukan sesuai kesepakatan</li>
                <li>Harga dapat berubah tanpa pemberitahuan terlebih dahulu setelah masa berlaku penawaran</li>
            </ul>
        </div>
    </div>

    <!-- Signature -->
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <div style="font-size: 9pt; margin-bottom: 8px;">Hormat Kami,</div>
                        <div class="signature-line"></div>
                        <div class="signature-label">{{ config('app.company_name', 'Enter365') }}</div>
                    </div>
                </td>
                <td style="width: 10%;"></td>
                <td>
                    <div class="signature-box">
                        <div style="font-size: 9pt; margin-bottom: 8px;">Menyetujui,</div>
                        <div class="signature-line"></div>
                        <div class="signature-label">{{ $quotation->contact->name ?? 'Customer' }}</div>
                        <div style="font-size: 8pt; color: #94a3b8; margin-top: 4px;">Tanggal: ________________</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        {{ config('app.company_name', 'Enter365') }} | {{ $quotation->quotation_number }} | Dibuat: {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
