<?php

declare(strict_types=1);

/**
 * Background Campaign Worker Dispatcher
 * Engineered by SPManchester Private Limited (https://www.spmanchester.com)
 * Lead Architect: Sayed Mudassar Akbar <info@spmanchester.com>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Worker can only be run via CLI.\n");
}

require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/../src/Client/WhatsAppClient.php';

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['app']['timezone']);

if (!$config['campaign']['enable_live_sending']) {
    echo "[DRY RUN] Live sending is disabled in configuration. Exiting cleanly.\n";
    exit(0);
}

// Single instance lock to prevent race conditions & duplicate dispatch
$lockFile = __DIR__ . '/worker.lock';
$lock = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another instance of worker is actively dispatching. Exiting.\n";
    exit(0);
}

$db = \SPM\WhatsApp\Database\Connection::get($config['database']['sqlite_path']);
$client = new \SPM\WhatsApp\Client\WhatsAppClient(
    $config['meta']['graph_version'],
    $config['meta']['phone_number_id'],
    $config['meta']['access_token']
);

$today = date('Y-m-d');
$rate = (float) $config['campaign']['estimated_cost_per_message'];
$budget = (float) $config['campaign']['budget_limit'];
$maxPerRun = (int) $config['campaign']['max_per_worker_run'];

// Calculate total current spend across all campaigns
$stmtSpend = $db->query("SELECT COALESCE(SUM(cost_estimate), 0) FROM sends WHERE state IN ('submitted', 'sent', 'delivered', 'read')");
$currentSpend = (float) $stmtSpend->fetchColumn();

// Fetch active campaigns
$campaigns = $db->query("SELECT * FROM campaigns WHERE running = 1 ORDER BY id ASC")->fetchAll();

foreach ($campaigns as $campaign) {
    $cid = (int) $campaign['id'];

    // Check today's dispatches for this campaign
    $stmtCount = $db->prepare(
        "SELECT COUNT(*) FROM sends WHERE campaign_id = ? AND attempted_at >= ? AND attempted_at < ?"
    );
    $stmtCount->execute([$cid, $today . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00']);
    $dispatchedToday = (int) $stmtCount->fetchColumn();

    $dailyLimit = (int) ($campaign['daily_limit'] ?: $config['campaign']['default_daily_limit']);
    $remainingQuota = min($dailyLimit - $dispatchedToday, $maxPerRun);

    if ($remainingQuota <= 0) {
        continue;
    }

    echo "Processing Campaign ID: $cid ('{$campaign['title']}'). Quota this cycle: $remainingQuota\n";

    while ($remainingQuota-- > 0) {
        if ($currentSpend + $rate > $budget) {
            echo "[GUARD] Campaign budget ceiling reached (Current: {$currentSpend}, Limit: {$budget}). Halting.\n";
            exit(0);
        }

        // Select the next eligible contact who hasn't been sent this campaign yet
        $stmtContact = $db->prepare(
            "SELECT id, phone FROM contacts 
             WHERE consent = 1 AND opted_out = 0 
               AND id NOT IN (SELECT contact_id FROM sends WHERE campaign_id = ?) 
             ORDER BY id ASC LIMIT 1"
        );
        $stmtContact->execute([$cid]);
        $contact = $stmtContact->fetch();

        if (!$contact) {
            echo "No further pending contacts for Campaign ID $cid.\n";
            break;
        }

        // Lock contact attempt in database
        $stmtInsert = $db->prepare(
            "INSERT OR IGNORE INTO sends (campaign_id, contact_id, state, attempted_at, updated_at) 
             VALUES (?, ?, 'attempting', datetime('now'), datetime('now'))"
        );
        $stmtInsert->execute([$cid, $contact['id']]);

        try {
            $result = $client->sendTemplateMessage($contact['phone'], $campaign);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message_id' => null, 'error' => $e->getMessage(), 'http_code' => 500];
        }

        $state = $result['success'] ? 'submitted' : 'failed';
        $costRecorded = $result['success'] ? $rate : 0.0;

        $stmtUpdate = $db->prepare(
            "UPDATE sends 
             SET state = ?, message_id = ?, error = ?, cost_estimate = ?, updated_at = datetime('now') 
             WHERE campaign_id = ? AND contact_id = ?"
        );
        $stmtUpdate->execute([
            $state,
            $result['message_id'],
            substr($result['error'] ?? '', 0, 500),
            $costRecorded,
            $cid,
            $contact['id']
        ]);

        if ($result['success']) {
            $currentSpend += $rate;
        }

        echo "Contact ID {$contact['id']} -> Result: {$state}\n";

        // Halt on critical authorization / rate-limit failures
        if (!$result['success'] && in_array((int)($result['http_code'] ?? 0), [401, 403, 429], true)) {
            echo "[HALT] Meta Cloud API returned authorization or rate limitation error ({$result['http_code']}).\n";
            exit(1);
        }

        // Throttle pacing to avoid sudden spikes
        usleep(250000);
    }
}

flock($lock, LOCK_UN);
fclose($lock);
echo "Worker cycle complete.\n";
