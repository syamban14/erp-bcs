<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fatigue Test Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Fatigue Test (Tes Kelelahan) K3 feature
    |
    */

    /**
     * Retry cooldown in hours
     * How long employee must wait before retrying after Severe result
     */
    'retry_cooldown_hours' => env('FATIGUE_RETRY_COOLDOWN', 3),

    /**
     * Maximum tests per day
     * Limit how many times an employee can take the test per day
     */
    'max_tests_per_day' => env('FATIGUE_MAX_TESTS_PER_DAY', 2),

    /**
     * Enable admin notification for Severe results
     */
    'severe_notification_enabled' => env('FATIGUE_SEVERE_NOTIFICATION', true),

    /**
     * Admin notification channels
     * Available: 'database', 'mail', 'slack'
     */
    'notification_channels' => ['database'],

    /**
     * Admin user IDs to notify (comma-separated in .env)
     */
    'admin_notification_users' => explode(',', env('FATIGUE_ADMIN_USERS', '')),
];
