<?php
/**
 * Admin Auction Management (100% Firebase Firestore)
 * 
 * Lists all auctions, creates new auction rooms from available amulets,
 * and allows closing or cancelling auctions directly in Firestore.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/firestore_client.php';
require_once __DIR__ . '/../includes/repository.php';

$fs = get_firestore();
$repo = get_amulet_repository();
$errors = [];
$msg = '';

// Handle Create or Update Auction via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $amulet_id = trim((string)($_POST['amulet_id'] ?? ''));
        $start_price = (float)($_POST['start_price'] ?? 0);
        $min_increment = (float)($_POST['min_bid_increment'] ?? 100);
        $duration_hours = (int)($_POST['duration_hours'] ?? 24);

        if (empty($amulet_id) || $start_price <= 0) {
            $errors[] = 'กรุณาเลือกพระเครื่องและระบุราคาเริ่มต้นที่ถูกต้อง';
        } else {
            try {
                $start_time = date('Y-m-d H:i:s');
                $end_time = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));

                $newId = $fs->getNextId('auctions');
                $fs->setDocument('auctions', $newId, [
                    'amulet_id' => $amulet_id,
                    'start_price' => $start_price,
                    'current_price' => $start_price,
                    'min_bid_increment' => $min_increment,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'status' => 'active',
                    'winner_id' => null,
                    'winner_username' => null,
                    'created_at' => $start_time,
                ]);

                // Ensure amulet type is set to auction
                $fs->updateDocument('amulets', $amulet_id, [
                    'type' => 'auction',
                    'is_available' => true,
                ]);

                $msg = 'เปิดห้องประมูลพระเครื่ององค์ใหม่สำเร็จแล้ว!';
            } catch (Exception $e) {
                error_log('[ADMIN AUCTION FIRESTORE ERROR] ' . $e->getMessage());
                $errors[] = 'เกิดข้อผิดพลาดในการเปิดประมูล: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'cancel' || $action === 'end') {
        $auc_id = trim((string)($_POST['auction_id'] ?? ''));
        $target_status = $action === 'cancel' ? 'cancelled' : 'ended';

        if (!empty($auc_id)) {
            $fs->updateDocument('auctions', $auc_id, ['status' => $target_status]);
            $msg = "อัปเดตสถานะห้องประมูล #{$auc_id} เป็น '{$target_status}' เรียบร้อยแล้ว";
        }
    }
}

// Fetch all amulets and active auctions from Firestore
$all_amulets = $fs->listDocuments('amulets');
$all_auctions = $fs->listDocuments('auctions');

$active_amulet_ids = [];
foreach ($all_auctions as $a) {
    if (($a['status'] ?? '') === 'active') {
        $active_amulet_ids[] = (string)($a['amulet_id'] ?? '');
    }
}

// Candidates for new auction
$candidates = [];
foreach ($all_amulets as $am) {
    if (!in_array((string)$am['id'], $active_amulet_ids, true)) {
        $candidates[] = $am;
    }
}

// Attach amulet info to auctions
$auctions = [];
foreach ($all_auctions as $auc) {
    $am = $repo->getAmuletById((string)($auc['amulet_id'] ?? ''));
    $auc['amulet'] = $am ?: [];
    $auctions[] = $auc;
}

// Sort newest first
usort($auctions, function($a, $b) {
    return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
});

$page_title = 'ระบบจัดการห้องประมูลพระเครื่อง (Auctions CMS)';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Amulet Siam Admin</title>
    <link rel="stylesheet" href="../css/style.css">
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
                    <li><a href="orders.php" class="nav-link">ตรวจสอบสลิป</a></li>
                    <li><a href="auctions.php" class="nav-link active">จัดการประมูล</a></li>
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1>จัดการห้องประมูลพระเครื่อง (Cloud Firestore)</h1>
                <a href="../auctions.php" target="_blank" class="btn btn-outline-gold">ดูห้องประมูลหน้าร้าน ↗</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div style="background: rgba(230, 57, 70, 0.15); border: 1px solid var(--danger); color: #ff8b94; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
                    <ul style="margin-left: 1.25rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($msg): ?>
                <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
                    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Open New Auction Form -->
                <div class="card" style="padding: 1.5rem;">
                    <h3>+ เปิดห้องประมูลใหม่</h3>
                    <form method="POST" action="auctions.php" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label class="form-label" for="amulet_id">เลือกพระเครื่อง *</label>
                            <select id="amulet_id" name="amulet_id" class="form-control" required>
                                <option value="">-- เลือกพระเครื่องที่พร้อมประมูล --</option>
                                <?php foreach ($candidates as $cand): ?>
                                    <option value="<?= $cand['id'] ?>">
                                        <?= htmlspecialchars($cand['title'] ?? '', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($cand['monk_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="start_price">ราคาเริ่มต้นประมูล (บาท) *</label>
                            <input type="number" step="0.01" min="1" id="start_price" name="start_price" class="form-control" required placeholder="เช่น 10000">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="min_bid_increment">ขั้นต่ำการเคาะราคา (บาท) *</label>
                            <input type="number" step="10" min="10" id="min_bid_increment" name="min_bid_increment" class="form-control" value="100" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="duration_hours">ระยะเวลาเปิดประมูล (ชั่วโมง) *</label>
                            <select id="duration_hours" name="duration_hours" class="form-control">
                                <option value="1">1 ชั่วโมง (ประมูลด่วน)</option>
                                <option value="6">6 ชั่วโมง</option>
                                <option value="12">12 ชั่วโมง</option>
                                <option value="24" selected>24 ชั่วโมง (1 วัน)</option>
                                <option value="48">48 ชั่วโมง (2 วัน)</option>
                                <option value="72">72 ชั่วโมง (3 วัน)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 0.5rem; padding: 0.8rem;">
                            🚀 เริ่มเปิดการประมูลทันที
                        </button>
                    </form>
                </div>

                <!-- Active & Past Auctions Table -->
                <div class="card" style="padding: 1.5rem; overflow-x: auto;">
                    <h3>รายการประมูลทั้งหมด (<?= count($auctions) ?> รายการ)</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-gold); text-align: left; color: var(--gold-accent);">
                                <th style="padding: 0.6rem 0.5rem;">พระเครื่อง</th>
                                <th style="padding: 0.6rem 0.5rem;">ราคาสูงสุด</th>
                                <th style="padding: 0.6rem 0.5rem;">สิ้นสุด</th>
                                <th style="padding: 0.6rem 0.5rem;">สถานะ</th>
                                <th style="padding: 0.6rem 0.5rem; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auctions)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-light); opacity: 0.8;">
                                        ยังไม่มีรายการประมูลในระบบ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auctions as $a): ?>
                                    <?php $am = $a['amulet']; ?>
                                    <tr style="border-bottom: 1px solid rgba(233, 215, 147, 0.15);">
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <strong><?= htmlspecialchars($am['title'] ?? 'พระเครื่อง', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <div style="font-size: 0.75rem; color: var(--text-light); opacity: 0.7;">
                                                <?= htmlspecialchars($am['monk_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; font-weight: bold; color: var(--gold-accent);">
                                            ฿<?= number_format((float)($a['current_price'] ?? 0), 2) ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; font-size: 0.8rem; color: var(--text-light);">
                                            <?= htmlspecialchars($a['end_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <?php $st = $a['status'] ?? 'active'; ?>
                                            <?php if ($st === 'active'): ?>
                                                <span class="badge badge-auction">เปิดอยู่</span>
                                            <?php elseif ($st === 'ended'): ?>
                                                <span class="badge badge-fixed">จบแล้ว</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #555;">ยกเลิก</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                            <div style="display: flex; gap: 0.35rem; justify-content: center;">
                                                <form method="POST" action="../auction_room.php" target="_blank">
                                                    <input type="hidden" name="auction_id" value="<?= htmlspecialchars((string)$a['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-outline-gold" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">ดูห้อง</button>
                                                </form>

                                                <?php if (($a['status'] ?? '') === 'active'): ?>
                                                    <form method="POST" action="auctions.php" onsubmit="return confirm('ยืนยันที่จะปิดการประมูลนี้ก่อนเวลา?');">
                                                        <input type="hidden" name="action" value="end">
                                                        <input type="hidden" name="auction_id" value="<?= htmlspecialchars((string)$a['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn btn-outline-gold" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-color: var(--danger); color: #ff8b94;">ปิด</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Amulet Siam Admin CMS Panel.</p>
        </div>
    </footer>
</body>
</html>
