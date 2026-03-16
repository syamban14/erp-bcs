<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Loan Interest Rate
    |--------------------------------------------------------------------------
    |
    | Flat interest rate per month (in percent)
    | Default: 1% per month
    |
    */
    'interest_rate_percent' => env('LOAN_INTEREST_RATE', 1.0),
    
    /*
    |--------------------------------------------------------------------------
    | Admin Fee
    |--------------------------------------------------------------------------
    |
    | One-time admin fee deducted from disbursement amount
    | Default: Rp 25,000
    |
    */
    'admin_fee' => env('LOAN_ADMIN_FEE', 25000),
    
    /*
    |--------------------------------------------------------------------------
    | Tenor Options
    |--------------------------------------------------------------------------
    |
    | Available loan tenor options in months
    |
    */
    'tenor_options' => [3, 6, 9, 12],
    
    /*
    |--------------------------------------------------------------------------
    | Max Limit Multiplier
    |--------------------------------------------------------------------------
    |
    | Maximum loan amount = basic_salary × multiplier
    | Default: 3x basic salary
    |
    */
    'max_limit_multiplier' => env('LOAN_MAX_LIMIT_MULTIPLIER', 3),
    
    /*
    |--------------------------------------------------------------------------
    | Auto Deduct
    |--------------------------------------------------------------------------
    |
    | Automatically deduct loan installments from salary slips
    | Default: true
    |
    */
    'auto_deduct' => env('LOAN_AUTO_DEDUCT', true),
    
    /*
    |--------------------------------------------------------------------------
    | Loan Reasons
    |--------------------------------------------------------------------------
    |
    | Valid loan request reasons
    |
    */
    'reasons' => [
        'health' => 'Keperluan Darurat Medis',
        'education' => 'Pendidikan',
        'disaster' => 'Bencana',
        'other' => 'Lainnya',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Loan Statuses
    |--------------------------------------------------------------------------
    |
    | Valid loan statuses
    |
    */
    'statuses' => [
        'pending_approval' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'active' => 'Aktif',
        'rejected' => 'Ditolak',
        'paid_off' => 'Lunas',
        'cancelled' => 'Dibatalkan',
    ],
];
