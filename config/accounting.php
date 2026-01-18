<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Basic company information for documents and reports.
    |
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'PT Contoh Indonesia'),
        'npwp' => env('COMPANY_NPWP', ''),
        'address' => env('COMPANY_ADDRESS', ''),
        'city' => env('COMPANY_CITY', 'Jakarta'),
        'phone' => env('COMPANY_PHONE', ''),
        'email' => env('COMPANY_EMAIL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The primary currency for this application. Indonesian Rupiah by default.
    |
    */
    'default_currency' => env('DEFAULT_CURRENCY', 'IDR'),

    /*
    |--------------------------------------------------------------------------
    | Tax Settings (PPN - Pajak Pertambahan Nilai)
    |--------------------------------------------------------------------------
    |
    | Indonesian VAT settings. As of 2024, standard PPN rate is 11%.
    |
    */
    'tax' => [
        'default_rate' => env('TAX_DEFAULT_RATE', 11.00),
        'name' => 'PPN',
        'registration_number_label' => 'NPWP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Terms
    |--------------------------------------------------------------------------
    |
    | Default payment terms in days.
    |
    */
    'payment' => [
        'default_term_days' => env('PAYMENT_DEFAULT_TERM_DAYS', 30),
        'available_terms' => [0, 7, 14, 30, 45, 60, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Early Payment Discount
    |--------------------------------------------------------------------------
    |
    | Settings for early payment discounts (potongan pembayaran awal).
    |
    */
    'early_payment_discount' => [
        'enabled' => env('EARLY_PAYMENT_DISCOUNT_ENABLED', true),
        'default_discount_percent' => 2.00,
        'default_discount_days' => 10, // Pay within X days to get discount
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Limit Settings
    |--------------------------------------------------------------------------
    |
    | Default credit limit settings for customers.
    |
    */
    'credit_limit' => [
        'enabled' => env('CREDIT_LIMIT_ENABLED', true),
        'default_limit' => 0, // 0 = no limit by default
        'warn_at_percent' => 80, // Warn when 80% of limit is used
        'block_at_percent' => 100, // Block new invoices at 100%
    ],

    /*
    |--------------------------------------------------------------------------
    | Overdue Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for overdue invoice/bill detection.
    |
    */
    'overdue' => [
        'check_daily' => true,
        'grace_period_days' => 0, // Days after due date before marking overdue
        'reminder_intervals' => [1, 7, 14, 30], // Days after due date to send reminders
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Documents
    |--------------------------------------------------------------------------
    |
    | Settings for recurring invoices and bills.
    |
    */
    'recurring' => [
        'enabled' => env('RECURRING_ENABLED', true),
        'frequencies' => [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'quarterly' => 'Triwulan',
            'yearly' => 'Tahunan',
        ],
        'auto_post' => false, // Auto-post generated documents
        'generate_days_before' => 3, // Generate X days before due
    ],

    /*
    |--------------------------------------------------------------------------
    | Aging Report Buckets
    |--------------------------------------------------------------------------
    |
    | Age buckets for AR/AP aging reports.
    |
    */
    'aging_buckets' => [
        ['min' => 0, 'max' => 0, 'label' => 'Belum Jatuh Tempo'],
        ['min' => 1, 'max' => 30, 'label' => '1-30 Hari'],
        ['min' => 31, 'max' => 60, 'label' => '31-60 Hari'],
        ['min' => 61, 'max' => 90, 'label' => '61-90 Hari'],
        ['min' => 91, 'max' => null, 'label' => '> 90 Hari'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Number Formats
    |--------------------------------------------------------------------------
    |
    | Formats for auto-generated document numbers.
    | Available placeholders: {PREFIX}, {YEAR}, {MONTH}, {SEQ}
    |
    */
    'document_formats' => [
        'quotation' => 'QUO-{YEAR}{MONTH}-{SEQ}',
        'invoice' => 'INV-{YEAR}{MONTH}-{SEQ}',
        'bill' => 'BILL-{YEAR}{MONTH}-{SEQ}',
        'payment_receive' => 'RCV-{YEAR}{MONTH}-{SEQ}',
        'payment_send' => 'PAY-{YEAR}{MONTH}-{SEQ}',
        'journal_entry' => 'JE-{YEAR}{MONTH}-{SEQ}',
        'purchase_order' => 'PO-{YEAR}{MONTH}-{SEQ}',
        'delivery_order' => 'DO-{YEAR}{MONTH}-{SEQ}',
        'down_payment' => 'DP-{YEAR}{MONTH}-{SEQ}',
        'sales_return' => 'SR-{YEAR}{MONTH}-{SEQ}',
        'purchase_return' => 'PR-{YEAR}{MONTH}-{SEQ}',
        'credit_note' => 'CN-{YEAR}{MONTH}-{SEQ}',
        'debit_note' => 'DN-{YEAR}{MONTH}-{SEQ}',
        'project' => 'PRJ-{YEAR}{MONTH}-{SEQ}',
        'bom' => 'BOM-{SEQ}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotation Settings (Penawaran)
    |--------------------------------------------------------------------------
    |
    | Settings for quotation/penawaran documents.
    |
    */
    'quotation' => [
        'default_validity_days' => 30,
        'terms_conditions' => [
            'id' => "SYARAT DAN KETENTUAN:\n1. Harga berlaku selama masa penawaran.\n2. Pembayaran: 50% DP, 50% sebelum pengiriman.\n3. Waktu pengerjaan dihitung setelah DP diterima.\n4. Harga belum termasuk PPN 11%.\n5. Penawaran ini berlaku selama {validity_days} hari.",
            'en' => "TERMS AND CONDITIONS:\n1. Prices are valid during the quotation period.\n2. Payment: 50% down payment, 50% before delivery.\n3. Lead time starts after down payment is received.\n4. Prices exclude 11% VAT.\n5. This quotation is valid for {validity_days} days.",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year
    |--------------------------------------------------------------------------
    |
    | Fiscal year settings. Most Indonesian companies use calendar year.
    |
    */
    'fiscal_year' => [
        'start_month' => 1, // January
        'start_day' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Reconciliation
    |--------------------------------------------------------------------------
    |
    | Settings for bank reconciliation feature.
    |
    */
    'bank_reconciliation' => [
        'enabled' => env('BANK_RECONCILIATION_ENABLED', true),
        'tolerance_amount' => 100, // IDR tolerance for matching
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Currency
    |--------------------------------------------------------------------------
    |
    | Multi-currency support settings.
    |
    */
    'multi_currency' => [
        'enabled' => env('MULTI_CURRENCY_ENABLED', false),
        'supported_currencies' => ['IDR', 'USD', 'EUR', 'SGD', 'JPY', 'CNY'],
        'exchange_rate_source' => 'manual', // manual, api
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Settings for audit trail / activity logging.
    |
    */
    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'log_reads' => false, // Log read operations
        'retention_days' => 365, // Keep logs for X days
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Notification settings.
    |
    */
    'notifications' => [
        'payment_reminder' => [
            'enabled' => env('NOTIFICATION_PAYMENT_REMINDER', true),
            'channels' => ['database', 'mail'],
        ],
        'overdue_alert' => [
            'enabled' => env('NOTIFICATION_OVERDUE_ALERT', true),
            'channels' => ['database', 'mail'],
        ],
        'credit_limit_warning' => [
            'enabled' => env('NOTIFICATION_CREDIT_LIMIT', true),
            'channels' => ['database'],
        ],
        'recurring_generated' => [
            'enabled' => env('NOTIFICATION_RECURRING', true),
            'channels' => ['database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Accounts
    |--------------------------------------------------------------------------
    |
    | Default account codes for automatic journal entries.
    |
    */
    'default_accounts' => [
        'cash' => '1-1001',
        'bank' => '1-1002',
        'accounts_receivable' => '1-1100',
        'accounts_payable' => '2-1100',
        'sales_revenue' => '4-1001',
        'purchase_expense' => '5-1002',
        'tax_payable' => '2-1200', // PPN Keluaran
        'tax_receivable' => '1-1300', // PPN Masukan
        'early_payment_discount_expense' => '5-3001',
        'early_payment_discount_income' => '4-2001',
        'foreign_exchange_gain' => '4-3001',
        'foreign_exchange_loss' => '5-4001',
        'inventory' => '1-1400', // Persediaan
        'cogs' => '5-1001', // Harga Pokok Penjualan
        'sales_returns' => '4-1004', // Retur Penjualan
        'purchase_returns' => '5-1004', // Retur Pembelian
        'dp_receivable' => '2-2100', // Uang Muka Penjualan (liability)
        'dp_payable' => '1-1500', // Uang Muka Pembelian (asset)
    ],

    /*
    |--------------------------------------------------------------------------
    | Accounting Policies (Strategy Pattern)
    |--------------------------------------------------------------------------
    |
    | Configure how the system handles accounting operations.
    | These settings determine which strategy classes are used.
    |
    */
    'policies' => [
        /*
        | Inventory Accounting Method
        |
        | Options:
        | - 'perpetual': Journal on every inventory movement (GRN, DO)
        | - 'periodic': No auto journals, adjust at period end
        | - 'hybrid': Journal only on specific events (recommended for EPC)
        */
        'inventory_method' => env('ACCOUNTING_INVENTORY_METHOD', 'hybrid'),

        /*
        | COGS Recognition Timing
        |
        | Options:
        | - 'on_invoice': COGS when invoice is posted (matches revenue)
        | - 'on_delivery': COGS when goods are shipped
        | - 'manual': No auto COGS, manual journal entry required
        */
        'cogs_recognition' => env('ACCOUNTING_COGS_RECOGNITION', 'on_invoice'),

        /*
        | Return Accounting Method
        |
        | Options:
        | - 'full_journal': Create reversal journals (AP/AR + Tax)
        | - 'inventory_only': Only update inventory, no journal
        */
        'return_accounting' => env('ACCOUNTING_RETURN_METHOD', 'full_journal'),

        /*
        | Manufacturing Costing Method
        |
        | Options:
        | - 'project_based': Costs flow to projects (recommended for EPC)
        | - 'job_costing': Costs accumulate per work order
        | - 'wip_accounting': Full WIP journal entries
        */
        'manufacturing_costing' => env('ACCOUNTING_MFG_METHOD', 'project_based'),
    ],
];
