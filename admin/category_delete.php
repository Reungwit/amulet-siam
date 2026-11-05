<?php
/**
 * Admin Category Delete Handler
 * 
 * Removes empty categories.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($cat_id > 0) {
    $pdo = get_db_connection();

    // Ensure category has no associated amulets before deleting
    $count = (int)$pdo->query("SELECT COUNT(*) FROM amulets WHERE category_id = {$cat_id}")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute(['id' => $cat_id]);
    }
}

header('Location: categories.php');
exit;

