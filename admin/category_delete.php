<?php
/**
 * Admin Category Delete Handler (100% Firebase Firestore)
 * 
 * Removes empty categories from Firestore.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/firestore_client.php';

$cat_id = isset($_GET['id']) ? (string)$_GET['id'] : '';

if (!empty($cat_id)) {
    $fs = get_firestore();
    $amulets = $fs->listDocuments('amulets');

    $has_amulets = false;
    foreach ($amulets as $am) {
        if ((string)($am['category_id'] ?? '') === (string)$cat_id) {
            $has_amulets = true;
            break;
        }
    }

    if (!$has_amulets) {
        $fs->deleteDocument('categories', $cat_id);
    }
}

header('Location: categories.php');
exit;
