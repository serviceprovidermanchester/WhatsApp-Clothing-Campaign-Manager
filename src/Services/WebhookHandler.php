<?php

declare(strict_types=1);

namespace SPM\WhatsApp\Services;

use PDO;
use SPM\WhatsApp\Security\Guard;

/**
 * Enterprise Webhook Service
 * Built by SPManchester Private Limited (https://www.spmanchester.com)
 * Handles delivery verification and opt-out requests.
 */
class WebhookHandler
{
    private PDO $db;
    private string $appSecret;
    private string $verifyToken;

    public function __construct(PDO $db, string $appSecret, string $verifyToken)
    {
        $this->db = $db;
        $this->appSecret = $appSecret;
        $this->verifyToken = $verifyToken;
    }

    public function verifySubscription(array $params): ?string
    {
        $mode = $params['hub_mode'] ?? $params['hub.mode'] ?? '';
        $token = $params['hub_verify_token'] ?? $params['hub.verify_token'] ?? '';
        $challenge = $params['hub_challenge'] ?? $params['hub.challenge'] ?? null;

        if ($mode === 'subscribe' && hash_equals($this->verifyToken, (string) $token)) {
            return (string) $challenge;
        }

        return null;
    }

    public function validateSignature(string $payload, string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $this->appSecret);
        return hash_equals($expected, $signature);
    }

    public function process(array $data): void
    {
        foreach ($data['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Update real-time message delivery statuses
                foreach ($value['statuses'] ?? [] as $status) {
                    $messageId = $status['id'] ?? '';
                    $st = $status['status'] ?? '';
                    if ($messageId && in_array($st, ['sent', 'delivered', 'read', 'failed'], true)) {
                        $err = substr((string) ($status['errors'][0]['title'] ?? ''), 0, 500);
                        $stmt = $this->db->prepare(
                            "UPDATE sends SET state = ?, updated_at = datetime('now'), error = ? WHERE message_id = ?"
                        );
                        $stmt->execute([$st, $err, $messageId]);
                    }
                }

                // Process inbound customer messages
                foreach ($value['messages'] ?? [] as $msg) {
                    $rawFrom = (string) ($msg['from'] ?? '');
                    $body = trim((string) ($msg['text']['body'] ?? ''));
                    $phone = Guard::normalizePhone($rawFrom);

                    if ($phone) {
                        $this->db->prepare(
                            "INSERT INTO inbound (phone, body) VALUES (?, ?)"
                        )->execute([$phone, substr($body, 0, 3000)]);

                        // Opt-out keyword recognition
                        if (preg_match('/^\s*(stop|unsubscribe|opt\s*out|cancel)\s*$/i', $body)) {
                            $this->db->prepare(
                                "UPDATE contacts SET opted_out = 1 WHERE phone = ?"
                            )->execute([$phone]);
                        }
                    }
                }
            }
        }
    }
}
