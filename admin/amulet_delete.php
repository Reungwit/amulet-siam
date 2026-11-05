<?php
/**
 * Admin Amulet Deletion Handler
 * 
 * Removes an amulet and its associated stats and files.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$amulet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($amulet_id > 0) {
    try {
        $pdo = get_db_connection();

        // Check if image exists to delete
        $stmt = $pdo->prepare("SELECT image_url FROM amulets WHERE id = :id");
        $stmt->execute(['id' => $amulet_id]);
        $amulet = $stmt->fetch();

        if ($amulet && !empty($amulet['image_url'])) {
            $file_on_disk = __DIR__ . '/../' . $amulet['image_url'];
            if (file_exists($file_on_disk)) {
                @unlink($file_on_disk);
            }
        }

        // Deleting from amulets cascades to amulet_stats based on schema foreign keys
        $del = $pdo->prepare("DELETE FROM amulets WHERE id = :id");
        $del->execute(['id' => $amulet_id]);

        error_log(sprintf('[%s] [ADMIN] Deleted amulet ID %d by admin %s', date('c'), $amulet_id, $admin_user['username']));
    } catch (PDOException $e) {
        error_log('[AMULET DELETE ERROR] ' . $e->getMessage());
    }
}

header('Location: amulets.php?msg=deleted');
exit;

