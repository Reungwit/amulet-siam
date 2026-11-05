<?php
/**
 * Admin Amulet Deletion Handler (100% Firebase Firestore)
 * 
 * Removes an amulet document from Firestore and deletes its uploaded photo.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/firestore_client.php';

$amulet_id = isset($_GET['id']) ? (string)$_GET['id'] : '';

if (!empty($amulet_id)) {
    try {
        $fs = get_firestore();
        $amulet = $fs->getDocument('amulets', $amulet_id);

        if ($amulet && !empty($amulet['image_url'])) {
            $file_on_disk = __DIR__ . '/../' . $amulet['image_url'];
            if (file_exists($file_on_disk)) {
                @unlink($file_on_disk);
            }
        }

        $fs->deleteDocument('amulets', $amulet_id);
        error_log(sprintf('[%s] [ADMIN FIRESTORE] Deleted amulet ID %s by %s', date('c'), $amulet_id, $admin_user['username']));
    } catch (Exception $e) {
        error_log('[AMULET DELETE ERROR] ' . $e->getMessage());
    }
}

header('Location: amulets.php?msg=deleted');
exit;
