<?php
/**
 * Database Connection Module (with Safe Fallback)
 * 
 * Sets global timezone to Asia/Bangkok (+07:00 TH).
 * If running on Cloud (Render) without MySQL, safely returns null without fatal crash.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

// Set default timezone across the entire application to Asia/Bangkok (+07:00 TH)
date_default_timezone_set('Asia/Bangkok');

/**
 * Obtain a shared PDO instance if available, or null if MySQL is not present.
 *
 * @return PDO|null Active PDO connection or null on cloud/no-mysql environments.
 */
function get_db_connection(): ?PDO
{
    static $pdo_instance = null;
    static $attempted = false;

    if ($pdo_instance instanceof PDO) {
        return $pdo_instance;
    }

    if ($attempted) {
        return null;
    }

    $attempted = true;

    $db_host = getenv('DB_HOST') ?: '127.0.0.1';
    $db_port = getenv('DB_PORT') ?: '3306';
    $db_name = getenv('DB_NAME') ?: 'amulet_db';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 2, // 2s quick timeout
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'",
    ];

    try {
        $pdo_instance = new PDO($dsn, $db_user, $db_pass, $options);
        return $pdo_instance;
    } catch (Throwable $e) {
        error_log('[DB NOTICE] MySQL not available, operating in pure Firebase Firestore mode.');
        return null;
    }
}
