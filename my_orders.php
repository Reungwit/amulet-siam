<?php
/**
 * Customer Order History & Tracking
 * 
 * Lists current user's past and active orders with payment status & tracking numbers.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db_connect.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php?redirect=my_orders.php');
    exit;
}

$user = $_SESSION['user'];
$pdo = get_db_connection();

// Fetch orders belonging to user
$stmt = $pdo->prepare("SELECT o.*, 
                              GROUP_CONCAT(a.title SEPARATOR '||') AS amulet_titles,
                              GROUP_CONCAT(a.price SEPARATOR '||') AS amulet_prices
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN amulets a ON oi.amulet_id = a.id
                       WHERE o.user_id = :user_id
                       GROUP BY o.id
                       ORDER BY o.id DESC");
$stmt->execute(['user_id' => $user['id']]);
$orders = $stmt->fetchAll();

$page_title = 'ประวัติคำสั่งเช่าบูชา - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1000px; margin: 0 auto;">
    <h1 style="margin-bottom: 1.5rem;">ประวัติคำสั่งเช่าบูชาของฉัน</h1>

    <?php if (empty($orders)): ?>
        <div class="card" style="padding: 3rem; text-align: center;">
            <p class="text-light" style="font-size: 1.1rem; margin-bottom: 1.5rem;">ท่านยังไม่มีประวัติคำสั่งเช่าบูชา</p>
            <a href="index.php" class="btn btn-gold">เลือกชมพระเครื่อง &rarr;</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($orders as $order): ?>
                <div class="card" style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-gold); padding-bottom: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span style="font-size: 1.1rem; font-weight: 700; color: var(--gold-accent);">
                                คำสั่งซื้อ #<?= str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT) ?>
                            </span>
                            <span style="font-size: 0.85rem; color: var(--text-light); opacity: 0.8; margin-left: 0.75rem;">
                                วันที่สั่ง: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($order['status'] === 'pending_verification'): ?>
                                <span class="badge" style="background: #e09f3e; color: #1c0003;">รอตรวจสอบสลิป</span>
                            <?php elseif ($order['status'] === 'preparing'): ?>
                                <span class="badge" style="background: #3a86ff; color: #ffffff;">กำลังเตรียมจัดส่ง</span>
                            <?php elseif ($order['status'] === 'shipped'): ?>
                                <span class="badge" style="background: var(--success); color: #ffffff;">จัดส่งแล้ว</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="badge" style="background: var(--danger); color: #ffffff;">ยกเลิกคำสั่งซื้อ</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div style="margin-bottom: 1rem;">
                        <?php 
                        $titles = explode('||', $order['amulet_titles']);
                        $prices = explode('||', $order['amulet_prices']);
                        foreach ($titles as $idx => $t): ?>
                            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0; font-size: 0.95rem; color: var(--text-light);">
                                <span>✦ <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></span>
                                <span style="color: var(--gold-accent);">฿<?= number_format((float)($prices[$idx] ?? 0), 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid rgba(233, 215, 147, 0.2); padding-top: 0.75rem; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <?php if (!empty($order['tracking_number'])): ?>
                                <div style="color: var(--gold-accent); font-weight: 600;">
                                    เลขพัสดุจัดส่ง (Tracking): <span style="background: #350107; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-gold);"><?= htmlspecialchars($order['tracking_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.85rem; color: var(--text-light); opacity: 0.7;">
                                    สถานะการส่ง: อยู่ระหว่างดำเนินการตรวจสอบ
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size: 0.9rem; color: var(--text-light);">ยอดรวมสุทธิ: </span>
                            <span style="font-size: 1.3rem; font-weight: 700; color: var(--gold-accent);">฿<?= number_format((float)$order['total_amount'], 2) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

