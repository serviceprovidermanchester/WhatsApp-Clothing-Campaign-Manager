<?php
require __DIR__.'/db.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$db=db();
$db->exec("CREATE TABLE IF NOT EXISTS contacts (id INTEGER PRIMARY KEY, phone TEXT UNIQUE NOT NULL, name TEXT NOT NULL DEFAULT '', consent INTEGER NOT NULL DEFAULT 1, opted_out INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS campaigns (id INTEGER PRIMARY KEY, title TEXT NOT NULL, template_name TEXT NOT NULL, language TEXT NOT NULL DEFAULT 'en', website_url TEXT NOT NULL DEFAULT '', image_url TEXT NOT NULL DEFAULT '', daily_limit INTEGER NOT NULL DEFAULT 500, running INTEGER NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS sends (id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL, contact_id INTEGER NOT NULL, state TEXT NOT NULL DEFAULT 'queued', message_id TEXT UNIQUE, error TEXT NOT NULL DEFAULT '', cost_estimate REAL NOT NULL DEFAULT 0, attempted_at TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(campaign_id,contact_id));
CREATE INDEX IF NOT EXISTS sends_state ON sends(campaign_id,state);
CREATE TABLE IF NOT EXISTS inbound (id INTEGER PRIMARY KEY, phone TEXT, body TEXT, received_at TEXT DEFAULT CURRENT_TIMESTAMP);");
echo "Database initialized.\n";
