<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
        .detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .detail-table td { padding: 8px 0; }
        .detail-table td:first-child { font-weight: bold; color: #6b7280; width: 40%; }
        .footer { text-align: center; padding: 20px; color: #9ca3af; font-size: 12px; }
        .badge { display: inline-block; background-color: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">{{ $company_name }}</h1>
            <p style="margin: 5px 0 0;">Konfirmasi Pembayaran</p>
        </div>

        <div class="content">
            <p>Yth. <strong>{{ $customer_name }}</strong>,</p>

            <p>Kami mengkonfirmasi bahwa pembayaran untuk faktur berikut telah kami terima:</p>

            <table class="detail-table">
                <tr>
                    <td>Nomor Faktur</td>
                    <td>{{ $invoice_number }}</td>
                </tr>
                <tr>
                    <td>Tanggal Faktur</td>
                    <td>{{ $invoice_date }}</td>
                </tr>
                <tr>
                    <td>Jumlah Dibayar</td>
                    <td><strong>{{ $amount_paid }}</strong></td>
                </tr>
                <tr>
                    <td>Tanggal Pembayaran</td>
                    <td>{{ $payment_date }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span class="badge">Lunas</span></td>
                </tr>
            </table>

            <p>Terima kasih atas pembayaran Anda.</p>

            <p>Hormat kami,<br><strong>{{ $company_name }}</strong></p>
        </div>

        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh {{ $company_name }}.</p>
        </div>
    </div>
</body>
</html>
