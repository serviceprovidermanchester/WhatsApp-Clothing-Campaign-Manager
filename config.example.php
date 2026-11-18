<?php
return [
 'admin_user' => 'admin',
 'admin_password_hash' => 'REPLACE_WITH_PASSWORD_HASH', // php -r 'echo password_hash("YOUR-STRONG-PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'
 'graph_version' => 'v23.0', // verify currently supported Graph API version
 'phone_number_id' => 'YOUR_PHONE_NUMBER_ID',
 'access_token' => 'YOUR_SYSTEM_USER_ACCESS_TOKEN',
 'app_secret' => 'YOUR_META_APP_SECRET',
 'verify_token' => 'YOUR_RANDOM_WEBHOOK_VERIFY_TOKEN',
 'timezone' => 'Asia/Karachi',
 'estimated_pkr_per_message' => 13.10, // illustrative only, check Meta's latest rates
 'campaign_budget_pkr' => 40000,
 'max_daily' => 500,
 'max_per_run' => 25,
 'enable_live_sending' => false, // change only after test and review
];
