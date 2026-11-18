<?php

declare(strict_types=1);

namespace SPM\WhatsApp\Security;

/**
 * Web Application Security & Sanitization Helper
 * Designed for SPManchester Private Limited
 */
class Guard
{
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['spm_csrf'])) {
            $_SESSION['spm_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['spm_csrf'];
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $stored = $_SESSION['spm_csrf'] ?? '';
        return !empty($stored) && !empty($token) && hash_equals($stored, $token);
    }

    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        // Specific localization for Pakistan numbers (03xx -> 923xx)
        if (str_starts_with($digits, '03') && strlen($digits) === 11) {
            $digits = '92' . substr($digits, 1);
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }
        return $digits;
    }
}
