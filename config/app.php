<?php

declare(strict_types=1);

/**
 * SPM WhatsApp Campaign Engine Configuration
 * Proprietary solution crafted by SPManchester Private Limited
 * Website: https://www.spmanchester.com | Inquiries: info@spmanchester.com
 */

return [
    'app' => [
        'name' => 'SPM WhatsApp Campaign Engine',
        'env' => getenv('APP_ENV') ?: 'production',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Karachi',
        'debug' => (bool)(getenv('APP_DEBUG') ?: false),
    ],
    'auth' => [
        'admin_user' => getenv('ADMIN_USERNAME') ?: 'admin',
        'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
    ],
    'meta' => [
        'graph_version' => getenv('META_GRAPH_VERSION') ?: 'v21.0',
        'phone_number_id' => getenv('META_PHONE_NUMBER_ID') ?: '',
        'access_token' => getenv('META_ACCESS_TOKEN') ?: '',
        'app_secret' => getenv('META_APP_SECRET') ?: '',
        'verify_token' => getenv('META_WEBHOOK_VERIFY_TOKEN') ?: '',
    ],
    'campaign' => [
        'enable_live_sending' => filter_var(getenv('ENABLE_LIVE_SENDING') ?: false, FILTER_VALIDATE_BOOLEAN),
        'estimated_cost_per_message' => (float)(getenv('ESTIMATED_COST_PER_MESSAGE') ?: 13.10),
        'budget_limit' => (float)(getenv('CAMPAIGN_BUDGET_LIMIT') ?: 40000.00),
        'max_per_worker_run' => (int)(getenv('MAX_DISPATCH_PER_RUN') ?: 25),
        'default_daily_limit' => (int)(getenv('DEFAULT_DAILY_LIMIT') ?: 500),
    ],
    'database' => [
        'sqlite_path' => dirname(__DIR__) . '/storage/db/campaign.sqlite',
    ],
];
