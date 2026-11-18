<?php

declare(strict_types=1);

/**
 * SPM WhatsApp Campaign Engine - Web Control Portal
 * Copyright (c) 2026 SPManchester Private Limited
 * Website: https://www.spmanchester.com | Inquiries: info@spmanchester.com
 */

require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/../src/Security/Guard.php';

use SPM\WhatsApp\Security\Guard;

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['app']['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

$db = \SPM\WhatsApp\Database\Connection::get($config['database']['sqlite_path']);
$notice = '';
$noticeType = 'info';

// Authentication Handling
if (isset($_POST['action_login'])) {
    $user = (string) ($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');

    if (
        hash_equals($config['auth']['admin_user'], $user) &&
        password_verify($pass, $config['auth']['admin_password_hash'])
    ) {
        session_regenerate_id(true);
        $_SESSION['spm_authenticated'] = true;
    } else {
        $notice = 'Invalid username or credentials provided.';
        $noticeType = 'error';
    }
}

if (isset($_POST['action_logout'])) {
    if (Guard::verifyCsrfToken($_POST['csrf'] ?? null)) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

// Authenticated Actions
if (!empty($_SESSION['spm_authenticated']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_login'])) {
    if (!Guard::verifyCsrfToken($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }

    try {
        // 1. CSV Contacts Import
        if (isset($_POST['action_import'])) {
            if (!is_uploaded_file($_FILES['contacts_csv']['tmp_name'] ?? '')) {
                throw new RuntimeException('Please select a valid CSV file.');
            }
            $handle = fopen($_FILES['contacts_csv']['tmp_name'], 'r');
            $imported = 0;
            $stmt = $db->prepare('INSERT OR IGNORE INTO contacts (phone, name, consent) VALUES (?, ?, 1)');

            while (($row = fgetcsv($handle)) !== false) {
                $phone = Guard::normalizePhone((string) ($row[0] ?? ''));
                if (!$phone) {
                    continue;
                }
                $name = substr(trim((string) ($row[1] ?? '')), 0, 100);
                $stmt->execute([$phone, $name]);
                $imported += $stmt->rowCount();
            }
            fclose($handle);
            $notice = "Successfully imported $imported opted-in contact(s). Duplicates skipped.";
            $noticeType = 'success';
        }

        // 2. Create Campaign
        if (isset($_POST['action_new_campaign'])) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $template = trim((string) ($_POST['template'] ?? ''));
            $lang = trim((string) ($_POST['language'] ?? 'en'));
            $url = trim((string) ($_POST['website_url'] ?? ''));
            $image = trim((string) ($_POST['image_url'] ?? ''));
            $daily = min(10000, max(1, (int) ($_POST['daily_limit'] ?? 500)));

            if ($title === '' || !preg_match('/^[a-z0-9_]{1,512}$/', $template)) {
                throw new RuntimeException('Invalid campaign title or approved template name format.');
            }
            foreach ([$url, $image] as $link) {
                if ($link !== '' && (!filter_var($link, FILTER_VALIDATE_URL) || !str_starts_with($link, 'https://'))) {
                    throw new RuntimeException('URLs must be secure HTTPS links.');
                }
            }

            $stmt = $db->prepare(
                'INSERT INTO campaigns (title, template_name, language, website_url, image_url, daily_limit) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$title, $template, $lang, $url, $image, $daily]);
            $notice = 'Campaign created successfully in PAUSED mode.';
            $noticeType = 'success';
        }

        // 3. Toggle Campaign Status
        if (isset($_POST['action_toggle'])) {
            $cid = (int) $_POST['campaign_id'];
            $db->prepare('UPDATE campaigns SET running = CASE WHEN running = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$cid]);
            $notice = 'Campaign execution state updated.';
            $noticeType = 'success';
        }

        // 4. Manual Opt-out
        if (isset($_POST['action_optout'])) {
            $phone = Guard::normalizePhone((string) ($_POST['phone'] ?? ''));
            if (!$phone) {
                throw new RuntimeException('Invalid mobile number format.');
            }
            $db->prepare('UPDATE contacts SET opted_out = 1 WHERE phone = ?')->execute([$phone]);
            $notice = "Phone $phone has been placed on the opt-out suppression list.";
            $noticeType = 'success';
        }
    } catch (\Throwable $e) {
        $notice = 'Error: ' . $e->getMessage();
        $noticeType = 'error';
    }
}

$csrf = Guard::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPM WhatsApp Campaign Engine | SPManchester Private Limited</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --spm-navy: #0B192C;
            --spm-dark: #1E3E62;
            --spm-accent: #008170;
            --spm-light: #F8FAFC;
            --spm-border: #E2E8F0;
            --spm-text: #1E293B;
            --spm-muted: #64748B;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: var(--spm-light);
            color: var(--spm-text);
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        header {
            background: var(--spm-navy);
            color: #fff;
            padding: 20px 0;
            border-bottom: 3px solid var(--spm-accent);
        }
        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .brand-logo {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .brand-sub {
            font-size: 0.825rem;
            color: #94A3B8;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
            border: 1px solid var(--spm-border);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media(max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-box {
            background: var(--spm-light);
            border: 1px solid var(--spm-border);
            padding: 18px;
            border-radius: 10px;
        }
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--spm-navy);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--spm-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input, select, button {
            font-family: inherit;
            font-size: 0.95rem;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--spm-border);
        }
        input, select {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 14px;
        }
        button {
            cursor: pointer;
            font-weight: 600;
            background: var(--spm-accent);
            color: #fff;
            border: none;
            transition: opacity 0.2s;
        }
        button:hover { opacity: 0.9; }
        button.secondary {
            background: #E2E8F0;
            color: var(--spm-text);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid var(--spm-border);
        }
        th {
            background: #F8FAFC;
            font-size: 0.85rem;
            color: var(--spm-muted);
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
        }
        .badge-active { background: #DCFCE7; color: #166534; }
        .badge-paused { background: #FEF3C7; color: #92400E; }
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.925rem;
        }
        .alert-success { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        footer {
            margin-top: 40px;
            padding: 25px 0;
            text-align: center;
            font-size: 0.85rem;
            color: var(--spm-muted);
            border-top: 1px solid var(--spm-border);
        }
        footer a { color: var(--spm-accent); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<header>
    <div class="container header-content">
        <div>
            <div class="brand-logo">SPM WhatsApp Campaign Engine™</div>
            <div class="brand-sub">SPManchester Private Limited · Enterprise Automation</div>
        </div>
        <?php if (!empty($_SESSION['spm_authenticated'])): ?>
            <form method="post" style="margin: 0;">
                <input type="hidden" name="csrf" value="<?= Guard::escape($csrf) ?>">
                <button name="action_logout" value="1" class="secondary" style="padding: 6px 14px;">Logout</button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="container" style="margin-top: 25px;">

    <?php if ($notice): ?>
        <div class="alert alert-<?= $noticeType === 'success' ? 'success' : 'error' ?>">
            <?= Guard::escape($notice) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($_SESSION['spm_authenticated'])): ?>
        <div class="card" style="max-width: 420px; margin: 60px auto;">
            <h2 style="margin-top: 0;">Portal Sign In</h2>
            <p style="color: var(--spm-muted); font-size: 0.9rem;">Access the WhatsApp marketing & compliance control suite.</p>
            <form method="post">
                <label>Admin Username</label>
                <input type="text" name="username" placeholder="admin" required autofocus>
                
                <label>Secure Password</label>
                <input type="password" name="password" placeholder="••••••••••••" required>
                
                <button type="submit" name="action_login" value="1" style="width: 100%; margin-top: 10px;">Sign In</button>
            </form>
        </div>
    <?php else: ?>

        <?php
        $totalContacts = (int) $db->query('SELECT COUNT(*) FROM contacts WHERE consent = 1 AND opted_out = 0')->fetchColumn();
        $totalOptedOut = (int) $db->query('SELECT COUNT(*) FROM contacts WHERE opted_out = 1')->fetchColumn();
        $statusBreakdown = $db->query('SELECT state, COUNT(*) as cnt FROM sends GROUP BY state')->fetchAll();
        $campaigns = $db->query('SELECT * FROM campaigns ORDER BY id DESC')->fetchAll();
        ?>

        <!-- Telemetry Cards -->
        <div class="card">
            <h3 style="margin-top: 0;">Dispatch Telemetry & Overview</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($totalContacts) ?></div>
                    <div class="stat-label">Consented Contacts</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($totalOptedOut) ?></div>
                    <div class="stat-label">Opted-Out (Suppressed)</div>
                </div>
                <?php foreach ($statusBreakdown as $row): ?>
                    <div class="stat-box">
                        <div class="stat-value"><?= number_format((int)$row['cnt']) ?></div>
                        <div class="stat-label"><?= Guard::escape(strtoupper($row['state'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid-2">
            <!-- Contact Upload Form -->
            <div class="card">
                <h3>1. Ingest Consented Audience</h3>
                <p style="color: var(--spm-muted); font-size: 0.875rem;">
                    Upload CSV format: <code>Phone (E.164 e.g. 923001234567), Name</code>. Only customers with verified marketing opt-in must be imported.
                </p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= Guard::escape($csrf) ?>">
                    <input type="file" name="contacts_csv" accept=".csv,text/csv" required>
                    <button type="submit" name="action_import" value="1" style="width: 100%;">Upload & Verify Contacts</button>
                </form>
            </div>

            <!-- Manual Opt-Out -->
            <div class="card">
                <h3>2. Instant Opt-Out Suppression</h3>
                <p style="color: var(--spm-muted); font-size: 0.875rem;">
                    Immediately unsubscribe a phone number from receiving any further automated campaigns.
                </p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= Guard::escape($csrf) ?>">
                    <label>Phone Number (E.164 digits)</label>
                    <input type="text" name="phone" placeholder="923001234567" required>
                    <button type="submit" name="action_optout" value="1" class="secondary" style="width: 100%;">Add to Opt-Out Blacklist</button>
                </form>
            </div>
        </div>

        <!-- Campaign Creation -->
        <div class="card">
            <h3>3. Configure New Meta WhatsApp Campaign</h3>
            <p style="color: var(--spm-muted); font-size: 0.875rem;">
                Campaigns must strictly match approved templates in Meta Business Manager.
            </p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= Guard::escape($csrf) ?>">
                <div class="grid-2">
                    <div>
                        <label>Campaign Title</label>
                        <input type="text" name="title" placeholder="e.g. Winter Collection Promo" required>
                    </div>
                    <div>
                        <label>Approved Template Name</label>
                        <input type="text" name="template" placeholder="e.g. winter_sale_2026" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label>Language Code</label>
                        <input type="text" name="language" value="en" required>
                    </div>
                    <div>
                        <label>Daily Recipient Ceiling</label>
                        <input type="number" name="daily_limit" min="1" max="10000" value="500" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label>Target Website Link (Populates {{1}} placeholder)</label>
                        <input type="url" name="website_url" placeholder="https://example.com/collection">
                    </div>
                    <div>
                        <label>Header Image URL (Public HTTPS)</label>
                        <input type="url" name="image_url" placeholder="https://example.com/banner.jpg">
                    </div>
                </div>
                <button type="submit" name="action_new_campaign" value="1" style="margin-top: 10px;">Create Campaign (Paused)</button>
            </form>
        </div>

        <!-- Campaign Records -->
        <div class="card">
            <h3>4. Active & Scheduled Campaigns</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Template</th>
                        <th>Daily Limit</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--spm-muted);">No campaigns configured yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $camp): ?>
                            <tr>
                                <td>#<?= (int)$camp['id'] ?></td>
                                <td><strong><?= Guard::escape($camp['title']) ?></strong></td>
                                <td><code><?= Guard::escape($camp['template_name']) ?></code></td>
                                <td><?= number_format((int)$camp['daily_limit']) ?> / day</td>
                                <td>
                                    <span class="badge <?= $camp['running'] ? 'badge-active' : 'badge-paused' ?>">
                                        <?= $camp['running'] ? 'ACTIVE' : 'PAUSED' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf" value="<?= Guard::escape($csrf) ?>">
                                        <input type="hidden" name="campaign_id" value="<?= (int)$camp['id'] ?>">
                                        <button type="submit" name="action_toggle" value="1" class="secondary" style="padding: 5px 12px; font-size:0.8rem;">
                                            <?= $camp['running'] ? 'Pause' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

<footer>
    <div class="container">
        SPM WhatsApp Campaign Engine™ · Engineered by <a href="https://www.spmanchester.com" target="_blank" rel="noopener">SPManchester Private Limited</a><br>
        Lead Architect: <strong>Sayed Mudassar Akbar</strong> · Inquiries: <a href="mailto:info@spmanchester.com">info@spmanchester.com</a>
    </div>
</footer>

</body>
</html>
