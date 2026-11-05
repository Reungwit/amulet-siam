<?php
/**
 * AJAX API: Auction Live Status & Bid History Polling (100% Firebase Firestore)
 * 
 * Fetches real-time auction status, remaining time, current price,
 * and latest bid history from Firestore via POST.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed (ใช้ได้เฉพาะ POST เท่านั้น)'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/firestore_client.php';

$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$auction_id = (string)($json_data['auction_id'] ?? $_POST['auction_id'] ?? '');

if (empty($auction_id)) {
    echo json_encode(['success' => false, 'message' => 'Auction ID ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $fs = get_firestore();
    $auction = $fs->getDocument('auctions', $auction_id);

    if (!$auction) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการประมูลใน Firestore'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_time = time();
    $end_time = strtotime($auction['end_time'] ?? '');
    $is_expired = $current_time >= $end_time;

    if ($is_expired && ($auction['status'] ?? '') === 'active') {
        $fs->updateDocument('auctions', $auction_id, ['status' => 'ended']);
        $auction['status'] = 'ended';
    }

    // Fetch bids for this auction from Firestore
    $all_bids = $fs->listDocuments('bids');
    $auction_bids = [];
    foreach ($all_bids as $b) {
        if ((string)($b['auction_id'] ?? '') === (string)$auction_id) {
            $auction_bids[] = $b;
        }
    }

    // Sort newest first
    usort($auction_bids, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    $bids = [];
    foreach (array_slice($auction_bids, 0, 10) as $b) {
        $uname = $b['username'] ?? 'User';
        $masked = strlen($uname) > 3 ? substr($uname, 0, 3) . '***' : $uname . '***';

        $bids[] = [
            'username' => $masked,
            'amount' => (float)($b['bid_amount'] ?? 0),
            'formatted_amount' => '฿' . number_format((float)($b['bid_amount'] ?? 0), 2),
            'time' => !empty($b['created_at']) ? date('H:i:s', strtotime($b['created_at'])) : '',
        ];
    }

    $remaining_seconds = max(0, $end_time - $current_time);

    echo json_encode([
        'success' => true,
        'auction_id' => $auction_id,
        'current_price' => (float)($auction['current_price'] ?? 0),
        'formatted_price' => '฿' . number_format((float)($auction['current_price'] ?? 0), 2),
        'min_increment' => (float)($auction['min_bid_increment'] ?? 100),
        'next_min_bid' => (float)($auction['current_price'] ?? 0) + (float)($auction['min_bid_increment'] ?? 100),
        'status' => $auction['status'] ?? 'active',
        'is_expired' => $is_expired,
        'end_time' => $auction['end_time'] ?? '',
        'remaining_seconds' => $remaining_seconds,
        'total_bids' => count($auction_bids),
        'bids' => $bids,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[AUCTION STATUS FIRESTORE ERROR] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลสถานะประมูล'], JSON_UNESCAPED_UNICODE);
}
