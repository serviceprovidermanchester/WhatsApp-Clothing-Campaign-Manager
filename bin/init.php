<?php

declare(strict_types=1);

/**
 * SPM WhatsApp Campaign Engine - Database Schema Initializer
 * SPManchester Private Limited (https://www.spmanchester.com)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be executed via PHP CLI.\n");
}

require_once __DIR__ . '/../src/Database/Connection.php';

$config = require __DIR__ . '/../config/app.php';
$db = \SPM\WhatsApp\Database\Connection::get($config['database']['sqlite_path']);

$schema = <<<SQL
CREATE TABLE IF NOT EXISTS contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL DEFAULT '',
    consent INTEGER NOT NULL DEFAULT 1,
    opted_out INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS campaigns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    template_name TEXT NOT NULL,
    language TEXT NOT NULL DEFAULT 'en',
    website_url TEXT NOT NULL DEFAULT '',
    image_url TEXT NOT NULL DEFAULT '',
    daily_limit INTEGER NOT NULL DEFAULT 500,
    running INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id INTEGER NOT NULL,
    contact_id INTEGER NOT NULL,
    state TEXT NOT NULL DEFAULT 'queued',
    message_id TEXT UNIQUE,
    error TEXT NOT NULL DEFAULT '',
    cost_estimate REAL NOT NULL DEFAULT 0,
    attempted_at TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(campaign_id, contact_id)
);

CREATE INDEX IF NOT EXISTS idx_sends_state ON sends(campaign_id, state);

CREATE TABLE IF NOT EXISTS inbound (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    phone TEXT,
    body TEXT,
    received_at TEXT DEFAULT CURRENT_TIMESTAMP
);
SQL;

$db->exec($schema);

echo "========================================================\n";
echo "SPM WhatsApp Campaign Engine database initialized!\n";
echo "Location: " . $config['database']['sqlite_path'] . "\n";
echo "Engineered by SPManchester Private Limited (www.spmanchester.com)\n";
echo "========================================================\n";
