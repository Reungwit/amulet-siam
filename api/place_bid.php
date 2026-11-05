<?php
/**
 * AJAX API: Place Auction Bid (100% Firebase Firestore)
 * 
 * Handles bid placement via POST directly on Firestore auctions & bids collections,
 * verifying minimum bid increments and enforcing Anti-Snipe (+3 minutes).
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed (ใช้ได้เฉพาะ POST เท่านั้น)'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนทำการเคาะประมูล'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/firestore_client.php';

$user = $_SESSION['user'];
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$auction_id = (string)($json_data['auction_id'] ?? $_POST['auction_id'] ?? '');
$bid_amount = (float)($json_data['bid_amount'] ?? $_POST['bid_amount'] ?? 0);

if (empty($auction_id) || $bid_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลการประมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $fs = get_firestore();
    $auction = $fs->getDocument('auctions', $auction_id);

    if (!$auction) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบห้องประมูลนี้ในระบบ Firestore'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_time = time();
    $end_time = strtotime($auction['end_time'] ?? '');

    // Check if expired
    if (($auction['status'] ?? 'active') !== 'active' || $current_time >= $end_time) {
        if (($auction['status'] ?? '') === 'active' && $current_time >= $end_time) {
            $fs->updateDocument('auctions', $auction_id, ['status' => 'ended']);
        }
        echo json_encode(['success' => false, 'message' => 'การประมูลรายการนี้สิ้นสุดลงแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_price = (float)($auction['current_price'] ?? 0);
    $min_increment = (float)($auction['min_bid_increment'] ?? 100);
    $min_required_bid = $current_price + $min_increment;

    if ($bid_amount < $min_required_bid) {
        echo json_encode([
            'success' => false, 
            'message' => "ราคาเสนอต้องไม่ต่ำกว่า ฿" . number_format($min_required_bid, 2) . " (ขั้นต่ำเพิ่มครั้งละ ฿" . number_format($min_increment, 2) . ")"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Anti-Snipe Logic (+3 minutes if placed in last 60s)
    $remaining_seconds = $end_time - $current_time;
    $anti_snipe_applied = false;
    $new_end_time_str = $auction['end_time'];

    if ($remaining_seconds > 0 && $remaining_seconds <= 60) {
        $new_end_timestamp = $end_time + 180;
        $new_end_time_str = date('Y-m-d H:i:s', $new_end_timestamp);
        $anti_snipe_applied = true;
    }

    // 1. Insert into bids in Firestore
    $bidId = $fs->getNextId('bids');
    $fs->setDocument('bids', $bidId, [
        'auction_id' => $auction_id,
        'user_id' => (string)$user['id'],
        'username' => $user['username'],
        'bid_amount' => $bid_amount,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // 2. Update auction in Firestore
    $fs->updateDocument('auctions', $auction_id, [
        'current_price' => $bid_amount,
        'winner_id' => (string)$user['id'],
        'winner_username' => $user['username'],
        'end_time' => $new_end_time_str,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'เคาะราคาประมูลสำเร็จ!',
        'new_price' => $bid_amount,
        'formatted_price' => '฿' . number_format($bid_amount, 2),
        'anti_snipe' => $anti_snipe_applied,
        'new_end_time' => $new_end_time_str,
        'remaining_seconds' => strtotime($new_end_time_str) - time(),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[FIRESTORE BID ERROR] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการประมวลผลการประมูล'], JSON_UNESCAPED_UNICODE);
}
