<?php
/**
 * AJAX API: Auction Live Status & Bid History Polling
 * 
 * Fetches real-time auction status, remaining time, current price,
 * and latest bid history via POST.
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

require_once __DIR__ . '/../includes/db_connect.php';

$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$auction_id = (int)($json_data['auction_id'] ?? $_POST['auction_id'] ?? 0);

if ($auction_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Auction ID ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = get_db_connection();

    // Fetch auction details
    $stmt = $pdo->prepare("SELECT a.*, am.title, am.image_url, u.username AS winner_username 
                           FROM auctions a 
                           JOIN amulets am ON a.amulet_id = am.id 
                           LEFT JOIN users u ON a.winner_id = u.id 
                           WHERE a.id = :id LIMIT 1");
    $stmt->execute(['id' => $auction_id]);
    $auction = $stmt->fetch();

    if (!$auction) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการประมูล'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_time = time();
    $end_time = strtotime($auction['end_time']);
    $is_expired = $current_time >= $end_time;

    // Check expiration transition
    if ($is_expired && $auction['status'] === 'active') {
        $up = $pdo->prepare("UPDATE auctions SET status = 'ended' WHERE id = :id");
        $up->execute(['id' => $auction_id]);
        $auction['status'] = 'ended';
    }

    // Fetch latest 10 bids
    $bid_stmt = $pdo->prepare("SELECT b.bid_amount, b.created_at, u.username 
                              FROM bids b 
                              JOIN users u ON b.user_id = u.id 
                              WHERE b.auction_id = :aid 
                              ORDER BY b.id DESC 
                              LIMIT 10");
    $bid_stmt->execute(['aid' => $auction_id]);
    $raw_bids = $bid_stmt->fetchAll();

    $bids = [];
    foreach ($raw_bids as $b) {
        // Mask username for bidder privacy (e.g., somchai -> som***)
        $uname = $b['username'];
        $masked = strlen($uname) > 3 ? substr($uname, 0, 3) . '***' : $uname . '***';

        $bids[] = [
            'username' => $masked,
            'amount' => (float)$b['bid_amount'],
            'formatted_amount' => '฿' . number_format((float)$b['bid_amount'], 2),
            'time' => date('H:i:s', strtotime($b['created_at'])),
        ];
    }

    $remaining_seconds = max(0, $end_time - $current_time);

    echo json_encode([
        'success' => true,
        'auction_id' => $auction_id,
        'current_price' => (float)$auction['current_price'],
        'formatted_price' => '฿' . number_format((float)$auction['current_price'], 2),
        'min_increment' => (float)$auction['min_bid_increment'],
        'next_min_bid' => (float)$auction['current_price'] + (float)$auction['min_bid_increment'],
        'status' => $auction['status'],
        'is_expired' => $is_expired,
        'end_time' => $auction['end_time'],
        'remaining_seconds' => $remaining_seconds,
        'winner_username' => $auction['winner_username'] ? (strlen($auction['winner_username']) > 3 ? substr($auction['winner_username'], 0, 3) . '***' : $auction['winner_username']) : null,
        'total_bids' => count($bids),
        'bids' => $bids,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[AUCTION STATUS ERROR] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลสถานะประมูล'], JSON_UNESCAPED_UNICODE);
}

