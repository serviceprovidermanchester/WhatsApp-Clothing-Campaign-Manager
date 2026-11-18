<?php

declare(strict_types=1);

namespace SPM\WhatsApp\Database;

use PDO;
use RuntimeException;

/**
 * Database connection provider.
 * Maintained by SPManchester Private Limited (https://www.spmanchester.com).
 */
class Connection
{
    private static ?PDO $instance = null;

    public static function get(string $sqlitePath): PDO
    {
        if (self::$instance === null) {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::$instance = new PDO('sqlite:' . $sqlitePath);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA busy_timeout = 5000;');
            self::$instance->exec('PRAGMA journal_mode = WAL;');
        }

        return self::$instance;
    }
}
