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
        'invoice' => 'INV-{YEAR}{MONTH}-{SEQ}',
        'bill' => 'BILL-{YEAR}{MONTH}-{SEQ}',
        'payment_receive' => 'RCV-{YEAR}{MONTH}-{SEQ}',
        'payment_send' => 'PAY-{YEAR}{MONTH}-{SEQ}',
        'journal_entry' => 'JE-{YEAR}{MONTH}-{SEQ}',
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
    ],
];
