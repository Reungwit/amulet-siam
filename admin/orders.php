<?php
/**
 * Admin Order & Payment Slip Verification CMS
 * 
 * Reviews uploaded slips, updates order lifecycle statuses,
 * records postal tracking numbers, and restocks cancelled items.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = get_db_connection();
$msg = '';

// Handle Status & Tracking Number Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $tracking_number = trim($_POST['tracking_number'] ?? '');

    $valid_statuses = ['pending_verification', 'preparing', 'shipped', 'cancelled'];

    if ($order_id > 0 && in_array($new_status, $valid_statuses, true)) {
        try {
            $pdo->beginTransaction();

            // Fetch current order status to handle inventory restocking if cancelled
            $cur_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id FOR UPDATE");
            $cur_stmt->execute(['id' => $order_id]);
            $current_order = $cur_stmt->fetch();

            if ($current_order) {
                // If transitioning to cancelled, restock amulets (is_available = 1)
                if ($new_status === 'cancelled' && $current_order['status'] !== 'cancelled') {
                    $item_stmt = $pdo->prepare("SELECT amulet_id FROM order_items WHERE order_id = :id");
                    $item_stmt->execute(['id' => $order_id]);
                    $items = $item_stmt->fetchAll();

                    $restock_stmt = $pdo->prepare("UPDATE amulets SET is_available = 1 WHERE id = :amulet_id");
                    foreach ($items as $it) {
                        $restock_stmt->execute(['amulet_id' => $it['amulet_id']]);
                    }
                }

                // If moving out of cancelled back to active, re-mark as unavailable (is_available = 0)
                if ($current_order['status'] === 'cancelled' && $new_status !== 'cancelled') {
                    $item_stmt = $pdo->prepare("SELECT amulet_id FROM order_items WHERE order_id = :id");
                    $item_stmt->execute(['id' => $order_id]);
                    $items = $item_stmt->fetchAll();

                    $reserve_stmt = $pdo->prepare("UPDATE amulets SET is_available = 0 WHERE id = :amulet_id");
                    foreach ($items as $it) {
                        $reserve_stmt->execute(['amulet_id' => $it['amulet_id']]);
                    }
                }

                $update = $pdo->prepare("UPDATE orders SET status = :status, tracking_number = :tracking WHERE id = :id");
                $update->execute([
                    'status' => $new_status,
                    'tracking' => $tracking_number ?: null,
                    'id' => $order_id,
                ]);

                $pdo->commit();
                $msg = "อัปเดตคำสั่งซื้อ #{$order_id} เป็นสถานะ '" . htmlspecialchars($new_status) . "' เรียบร้อยแล้ว";
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[ADMIN ORDER ERROR] ' . $e->getMessage());
        }
    }
}

// Fetch all orders with user information
$orders = $pdo->query("SELECT o.*, u.username, u.full_name, u.phone AS user_phone,
                              GROUP_CONCAT(a.title SEPARATOR '||') AS amulet_titles,
                              GROUP_CONCAT(a.price SEPARATOR '||') AS amulet_prices
                       FROM orders o
                       JOIN users u ON o.user_id = u.id
                       JOIN order_items oi ON o.id = oi.order_id
                       JOIN amulets a ON oi.amulet_id = a.id
                       GROUP BY o.id
                       ORDER BY o.id DESC")->fetchAll();

$page_title = 'ระบบตรวจสอบสลิปและจัดการคำสั่งซื้อ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Amulet Siam Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85);
            align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal img { max-width: 90%; max-height: 85vh; border: 2px solid var(--gold-accent); border-radius: 6px; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container nav-container">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">⚙</span>
                <span>Amulet Siam Admin Panel</span>
            </a>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-link">แดชบอร์ด</a></li>
                    <li><a href="amulets.php" class="nav-link">จัดการพระเครื่อง</a></li>
                    <li><a href="categories.php" class="nav-link">หมวดหมู่</a></li>
                    <li><a href="orders.php" class="nav-link active">ตรวจสอบสลิป</a></li>
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h1>ตรวจสอบสลิปและสถานะการจัดส่ง</h1>
                    <p class="text-light">พบทั้งหมด <?= count($orders) ?> รายการคำสั่งซื้อ</p>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
                    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php if (empty($orders)): ?>
                    <div class="card" style="padding: 3rem; text-align: center;">
                        <p class="text-light">ยังไม่มีคำสั่งเช่าบูชาในระบบ</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $ord): ?>
                        <div class="card" style="padding: 1.5rem;">
                            <div style="display: grid; grid-template-columns: 2fr 1.2fr 1fr; gap: 1.5rem; align-items: start;">
                                <!-- Order & Customer Details -->
                                <div>
                                    <div style="font-size: 1.15rem; font-weight: 700; color: var(--gold-accent); margin-bottom: 0.25rem;">
                                        คำสั่งซื้อ #<?= str_pad((string)$ord['id'], 6, '0', STR_PAD_LEFT) ?>
                                        <span style="font-size: 0.85rem; font-weight: normal; color: var(--text-light); opacity: 0.8; margin-left: 0.5rem;">
                                            (<?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?>)
                                        </span>
                                    </div>
                                    <div style="font-size: 0.95rem; margin: 0.5rem 0;">
                                        ผู้สั่งเช่า: <strong><?= htmlspecialchars($ord['full_name'] ?: $ord['username'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($ord['username'], ENT_QUOTES, 'UTF-8') ?>)
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-light); background: #350107; padding: 0.75rem; border-radius: 4px; border: 1px solid var(--border-gold); white-space: pre-line;">
                                        <?= htmlspecialchars($ord['shipping_address'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>

                                    <!-- Items Ordered -->
                                    <div style="margin-top: 0.75rem; font-size: 0.9rem;">
                                        <?php 
                                        $t_arr = explode('||', $ord['amulet_titles']);
                                        $p_arr = explode('||', $ord['amulet_prices']);
                                        foreach ($t_arr as $i => $title): ?>
                                            <div>✦ <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> (฿<?= number_format((float)($p_arr[$i] ?? 0), 2) ?>)</div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Slip Preview -->
                                <div style="text-align: center;">
                                    <h4 style="margin-bottom: 0.5rem;">สลิปการโอนเงิน</h4>
                                    <?php if (!empty($ord['slip_image'])): ?>
                                        <img src="../<?= htmlspecialchars($ord['slip_image'], ENT_QUOTES, 'UTF-8') ?>" alt="Payment Slip" style="max-height: 140px; cursor: pointer; border-radius: 4px; border: 1px solid var(--border-gold);" onclick="show_slip_modal(this.src);">
                                        <div style="font-size: 0.75rem; color: var(--gold-accent); margin-top: 0.35rem; cursor: pointer;" onclick="show_slip_modal('../<?= htmlspecialchars($ord['slip_image'], ENT_QUOTES, 'UTF-8') ?>');">
                                            🔍 คลิกเพื่อดูสลิปภาพใหญ่
                                        </div>
                                    <?php else: ?>
                                        <span class="text-danger" style="font-size: 0.85rem;">ไม่มีการแนบสลิป</span>
                                    <?php endif; ?>
                                    <div style="font-size: 1.15rem; font-weight: 700; color: var(--gold-accent); margin-top: 0.75rem;">
                                        ยอดชำระ: ฿<?= number_format((float)$ord['total_amount'], 2) ?>
                                    </div>
                                </div>

                                <!-- Order Management Action Form -->
                                <form method="POST" action="orders.php" style="background: #350107; padding: 1rem; border-radius: 6px; border: 1px solid var(--border-gold);">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">

                                    <div class="form-group" style="margin-bottom: 0.75rem;">
                                        <label class="form-label" style="font-size: 0.85rem;">เปลี่ยนสถานะ</label>
                                        <select name="status" class="form-control" style="font-size: 0.85rem; padding: 0.4rem;">
                                            <option value="pending_verification" <?= ($ord['status'] === 'pending_verification') ? 'selected' : '' ?>>1. รอตรวจสอบสลิป</option>
                                            <option value="preparing" <?= ($ord['status'] === 'preparing') ? 'selected' : '' ?>>2. เตรียมจัดส่ง</option>
                                            <option value="shipped" <?= ($ord['status'] === 'shipped') ? 'selected' : '' ?>>3. จัดส่งแล้ว</option>
                                            <option value="cancelled" <?= ($ord['status'] === 'cancelled') ? 'selected' : '' ?>>4. ยกเลิกคำสั่งซื้อ</option>
                                        </select>
                                    </div>

                                    <div class="form-group" style="margin-bottom: 0.75rem;">
                                        <label class="form-label" style="font-size: 0.85rem;">เลขพัสดุ (Tracking)</label>
                                        <input type="text" name="tracking_number" class="form-control" style="font-size: 0.85rem; padding: 0.4rem;" placeholder="เช่น ED123456789TH" value="<?= htmlspecialchars($ord['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>

                                    <button type="submit" class="btn btn-gold" style="width: 100%; font-size: 0.85rem; padding: 0.5rem;">
                                        บันทึกสถานะ
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Slip Image Modal -->
    <div id="slipModal" class="modal" onclick="this.classList.remove('active');">
        <img id="modalImg" src="" alt="Slip Full Preview">
    </div>

    <script>
        function show_slip_modal(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('slipModal').classList.add('active');
        }
    </script>
</body>
</html>

