<?php
/**
 * Admin Access Guard
 * 
 * Enforces authenticated admin privileges before rendering any admin view.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validate that the active session belongs to an administrator.
 *
 * @return array User session data.
 */
function require_admin(): array
{
    $current_user = $_SESSION['user'] ?? null;

    if (!$current_user || ($current_user['role'] ?? '') !== 'admin') {
        error_log(sprintf('[%s] [ADMIN GUARD] Unauthorized access attempt blocked', date('c')));
        header('Location: ../login.php?error=unauthorized');
        exit;
    }

    return $current_user;
}

