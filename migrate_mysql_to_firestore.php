<?php
/**
 * Migration Script: MySQL to Firebase Firestore
 * 
 * Extracts data from MySQL tables (categories, users, amulets + stats, auctions, orders)
 * and imports them as clean NoSQL documents into Firebase Firestore collections.
 * 
 * Usage: /Applications/XAMPP/xamppfiles/bin/php migrate_mysql_to_firestore.php
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/firestore_client.php';

echo "=======================================================\n";
echo "   MIGRATION: MySQL (phpMyAdmin) -> Firebase Firestore\n";
echo "=======================================================\n";

if (FIREBASE_PROJECT_ID === 'YOUR_FIREBASE_PROJECT_ID' || FIREBASE_API_KEY === 'YOUR_FIREBASE_API_KEY') {
    echo "\033[31m[ERROR]\033[0m กรุณาเปิดไฟล์ includes/firebase_config.php แล้วกรอก FIREBASE_PROJECT_ID และ FIREBASE_API_KEY ของคุณก่อนรันสคริปต์นี้!\n";
    exit(1);
}

$pdo = get_db_connection();
$firestore = get_firestore();

try {
    // 1. Migrate Categories
    echo "\n[1/5] Migrating Categories...\n";
    $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
    foreach ($cats as $cat) {
        $docId = (string)$cat['id'];
        $firestore->setDocument('categories', $docId, [
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'description' => $cat['description'] ?? '',
            'created_at' => $cat['created_at'],
        ]);
        echo "  ✓ Category: {$cat['name']} (ID: {$docId})\n";
    }

    // 2. Migrate Users
    echo "\n[2/5] Migrating Users...\n";
    $users = $pdo->query("SELECT * FROM users")->fetchAll();
    foreach ($users as $u) {
        $docId = (string)$u['id'];
        $firestore->setDocument('users', $docId, [
            'username' => $u['username'],
            'email' => $u['email'],
            'password_hash' => $u['password_hash'],
            'full_name' => $u['full_name'] ?? '',
            'phone' => $u['phone'] ?? '',
            'address' => $u['address'] ?? '',
            'role' => $u['role'],
            'created_at' => $u['created_at'],
        ]);
        echo "  ✓ User: {$u['username']} ({$u['role']}) (ID: {$docId})\n";
    }

    // 3. Migrate Amulets & Nested Spiritual Stats Map
    echo "\n[3/5] Migrating Amulets (with 6-Point Spiritual Radar Stats)...\n";
    $amulets = $pdo->query("SELECT a.*, 
                                   s.protection, s.popularity, s.charm, s.evasion, s.invincibility, s.wealth
                            FROM amulets a
                            LEFT JOIN amulet_stats s ON a.id = s.amulet_id")->fetchAll();
    foreach ($amulets as $am) {
        $docId = (string)$am['id'];
        $firestore->setDocument('amulets', $docId, [
            'category_id' => (string)$am['category_id'],
            'title' => $am['title'],
            'monk_name' => $am['monk_name'],
            'temple_name' => $am['temple_name'],
            'year_made' => $am['year_made'] ?? '',
            'type' => $am['type'],
            'price' => (float)$am['price'],
            'image_url' => $am['image_url'] ?? '',
            'description' => $am['description'] ?? '',
            'is_available' => (bool)$am['is_available'],
            'created_at' => $am['created_at'],
            'stats' => [
                'protection' => (int)($am['protection'] ?? 5),
                'popularity' => (int)($am['popularity'] ?? 5),
                'charm' => (int)($am['charm'] ?? 5),
                'evasion' => (int)($am['evasion'] ?? 5),
                'invincibility' => (int)($am['invincibility'] ?? 5),
                'wealth' => (int)($am['wealth'] ?? 5),
            ]
        ]);
        echo "  ✓ Amulet: {$am['title']} (ID: {$docId})\n";
    }

    // 4. Migrate Auctions & Bids
    echo "\n[4/5] Migrating Auctions & Bids...\n";
    $auctions = $pdo->query("SELECT * FROM auctions")->fetchAll();
    foreach ($auctions as $auc) {
        $docId = (string)$auc['id'];
        $firestore->setDocument('auctions', $docId, [
            'amulet_id' => (string)$auc['amulet_id'],
            'start_price' => (float)$auc['start_price'],
            'current_price' => (float)$auc['current_price'],
            'min_bid_increment' => (float)$auc['min_bid_increment'],
            'start_time' => $auc['start_time'],
            'end_time' => $auc['end_time'],
            'status' => $auc['status'],
            'winner_id' => $auc['winner_id'] ? (string)$auc['winner_id'] : null,
            'created_at' => $auc['created_at'],
        ]);
        echo "  ✓ Auction #{$docId} (Amulet ID: {$auc['amulet_id']})\n";

        // Migrate Bids for this auction
        $bids = $pdo->query("SELECT b.*, u.username FROM bids b JOIN users u ON b.user_id = u.id WHERE b.auction_id = {$auc['id']}")->fetchAll();
        foreach ($bids as $b) {
            $bidDocId = (string)$b['id'];
            $firestore->setDocument('bids', $bidDocId, [
                'auction_id' => (string)$auc['id'],
                'user_id' => (string)$b['user_id'],
                'username' => $b['username'],
                'bid_amount' => (float)$b['bid_amount'],
                'created_at' => $b['created_at'],
            ]);
        }
    }

    // 5. Migrate Orders & Order Items
    echo "\n[5/5] Migrating Orders & Items...\n";
    $orders = $pdo->query("SELECT * FROM orders")->fetchAll();
    foreach ($orders as $ord) {
        $docId = (string)$ord['id'];
        $items = $pdo->query("SELECT oi.*, a.title FROM order_items oi JOIN amulets a ON oi.amulet_id = a.id WHERE oi.order_id = {$ord['id']}")->fetchAll();

        $itemsArray = [];
        foreach ($items as $it) {
            $itemsArray[] = [
                'amulet_id' => (string)$it['amulet_id'],
                'title' => $it['title'],
                'price' => (float)$it['price'],
            ];
        }

        $firestore->setDocument('orders', $docId, [
            'user_id' => (string)$ord['user_id'],
            'total_amount' => (float)$ord['total_amount'],
            'slip_image' => $ord['slip_image'] ?? '',
            'status' => $ord['status'],
            'tracking_number' => $ord['tracking_number'] ?? '',
            'shipping_address' => $ord['shipping_address'],
            'created_at' => $ord['created_at'],
            'items' => $itemsArray,
        ]);
        echo "  ✓ Order #{$docId} with " . count($itemsArray) . " item(s)\n";
    }

    echo "\n=======================================================\n";
    echo "🎉 MIGRATION COMPLETED SUCCESSFULLY TO FIRESTORE!\n";
    echo "=======================================================\n";

} catch (Exception $e) {
    echo "\n\033[31m[MIGRATION ERROR]\033[0m: " . $e->getMessage() . "\n";
    exit(1);
}

