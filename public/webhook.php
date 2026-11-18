<?php

declare(strict_types=1);

/**
 * Meta WhatsApp Cloud API Webhook Endpoint
 * SPManchester Private Limited (https://www.spmanchester.com)
 */

require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/../src/Security/Guard.php';
require_once __DIR__ . '/../src/Services/WebhookHandler.php';

$config = require __DIR__ . '/../config/app.php';
$db = \SPM\WhatsApp\Database\Connection::get($config['database']['sqlite_path']);

$handler = new \SPM\WhatsApp\Services\WebhookHandler(
    $db,
    $config['meta']['app_secret'],
    $config['meta']['verify_token']
);

// Meta Subscription Verification (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = $handler->verifySubscription($_GET);
    if ($challenge !== null) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    exit('Forbidden');
}

// Inbound Event Processing (POST)
$rawBody = file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

if (!$handler->validateSignature($rawBody, $signature)) {
    http_response_code(403);
    exit('Invalid Signature');
}

$payload = json_decode($rawBody, true);
if (is_array($payload)) {
    $handler->process($payload);
}

http_response_code(200);
echo 'OK';
