<?php
/**
 * AJAX API: Place Auction Bid
 * 
 * Handles bid placement via POST, verifies minimum bid increments,
 * enforces Anti-Snipe (+3 minutes if bid placed in last 60 seconds),
 * and records bid history atomically.
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

require_once __DIR__ . '/../includes/db_connect.php';

$user = $_SESSION['user'];
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$auction_id = (int)($json_data['auction_id'] ?? $_POST['auction_id'] ?? 0);
$bid_amount = (float)($json_data['bid_amount'] ?? $_POST['bid_amount'] ?? 0);

if ($auction_id <= 0 || $bid_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลการประมูลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Lock auction row for update to prevent race conditions
    $stmt = $pdo->prepare("SELECT a.*, am.title 
                           FROM auctions a 
                           JOIN amulets am ON a.amulet_id = am.id 
                           WHERE a.id = :id FOR UPDATE");
    $stmt->execute(['id' => $auction_id]);
    $auction = $stmt->fetch();

    if (!$auction) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'ไม่พบห้องประมูลนี้ในระบบ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_time = time();
    $end_time = strtotime($auction['end_time']);

    // Check if auction is active and not expired
    if ($auction['status'] !== 'active' || $current_time >= $end_time) {
        // If expired, ensure status is marked as ended
        if ($auction['status'] === 'active' && $current_time >= $end_time) {
            $end_stmt = $pdo->prepare("UPDATE auctions SET status = 'ended' WHERE id = :id");
            $end_stmt->execute(['id' => $auction_id]);
        }
        $pdo->commit();
        echo json_encode(['success' => false, 'message' => 'การประมูลรายการนี้สิ้นสุดลงแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $current_price = (float)$auction['current_price'];
    $min_increment = (float)$auction['min_bid_increment'];
    $min_required_bid = $current_price + $min_increment;

    if ($bid_amount < $min_required_bid) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => "ราคาเสนอต้องไม่ต่ำกว่า ฿" . number_format($min_required_bid, 2) . " (ขั้นต่ำเพิ่มครั้งละ ฿" . number_format($min_increment, 2) . ")"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Anti-Snipe Logic: If bid placed within the last 60 seconds, add 3 minutes (+180s)
    $remaining_seconds = $end_time - $current_time;
    $anti_snipe_applied = false;
    $new_end_time_str = $auction['end_time'];

    if ($remaining_seconds > 0 && $remaining_seconds <= 60) {
        $new_end_timestamp = $end_time + 180; // + 3 minutes
        $new_end_time_str = date('Y-m-d H:i:s', $new_end_timestamp);
        $anti_snipe_applied = true;
    }

    // 1. Insert into bids history
    $bid_stmt = $pdo->prepare("INSERT INTO bids (auction_id, user_id, bid_amount) VALUES (:aid, :uid, :amount)");
    $bid_stmt->execute([
        'aid' => $auction_id,
        'uid' => $user['id'],
        'amount' => $bid_amount,
    ]);

    // 2. Update auction current_price, winner_id, and end_time (if anti-snipe applied)
    $update_stmt = $pdo->prepare("UPDATE auctions SET 
                                  current_price = :price, 
                                  winner_id = :winner, 
                                  end_time = :end_time 
                                  WHERE id = :id");
    $update_stmt->execute([
        'price' => $bid_amount,
        'winner' => $user['id'],
        'end_time' => $new_end_time_str,
        'id' => $auction_id,
    ]);

    $pdo->commit();

    error_log(sprintf('[%s] [AUCTION] User %s placed bid ฿%s on auction #%d (Anti-snipe: %s)', 
        date('c'), $user['username'], number_format($bid_amount, 2), $auction_id, $anti_snipe_applied ? 'YES' : 'NO'));

    echo json_encode([
        'success' => true,
        'message' => 'เคาะราคาประมูลสำเร็จ!',
        'new_price' => $bid_amount,
        'formatted_price' => '฿' . number_format($bid_amount, 2),
        'anti_snipe' => $anti_snipe_applied,
        'new_end_time' => $new_end_time_str,
        'remaining_seconds' => strtotime($new_end_time_str) - time(),
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[PLACE BID ERROR] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการประมวลผลการประมูล'], JSON_UNESCAPED_UNICODE);
}

