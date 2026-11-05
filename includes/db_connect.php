<?php
/**
 * Database Connection Module
 * 
 * Provides a secure, persistent PDO connection to MySQL with robust error handling.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

// Set default timezone across the entire application to Asia/Bangkok (+07:00 TH)
date_default_timezone_set('Asia/Bangkok');

/**
 * Obtain a shared PDO instance configured with error reporting and UTF-8 encoding.
 *
 * @return PDO Active database connection instance.
 * @throws PDOException When connection cannot be established.
 */
function get_db_connection(): PDO
{
    // Log entry
    error_log(sprintf('[%s] [DB] Attempting database connection', date('c')));

    static $pdo_instance = null;

    if ($pdo_instance instanceof PDO) {
        return $pdo_instance;
    }

    $db_host = getenv('DB_HOST') ?: '127.0.0.1';
    $db_port = getenv('DB_PORT') ?: '3306';
    $db_name = getenv('DB_NAME') ?: 'amulet_db';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'",
    ];

    try {
        $pdo_instance = new PDO($dsn, $db_user, $db_pass, $options);
        error_log(sprintf('[%s] [DB] Database connection established successfully', date('c')));
        return $pdo_instance;
    } catch (PDOException $e) {
        error_log(sprintf('[%s] [DB ERROR] Connection failed: %s', date('c'), $e->getMessage()));
        // In local development or initial bootstrap, allow graceful handling
        throw $e;
    }
}

