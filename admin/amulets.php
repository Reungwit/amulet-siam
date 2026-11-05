<?php
/**
 * Admin Amulet List Management (100% Firebase Firestore)
 * 
 * Displays all registered amulets from Firestore with category resolution and action triggers.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/firestore_client.php';

$fs = get_firestore();

// Fetch amulets and categories from Firestore
$amulets = $fs->listDocuments('amulets');
$categories_list = $fs->listDocuments('categories');

$categories_map = [];
foreach ($categories_list as $c) {
    $categories_map[(string)$c['id']] = $c['name'] ?? '';
}

// Sort newest first by id
usort($amulets, function($a, $b) {
    return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0);
});

$page_title = 'จัดการรายการพระเครื่อง';
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
                    <li><a href="amulets.php" class="nav-link active">จัดการพระเครื่อง</a></li>
                    <li><a href="categories.php" class="nav-link">หมวดหมู่</a></li>
                    <li><a href="orders.php" class="nav-link">ตรวจสอบสลิป</a></li>
                    <li><a href="auctions.php" class="nav-link">จัดการประมูล</a></li>
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <h1>รายการพระเครื่องทั้งหมด (Firestore)</h1>
                    <p class="text-light">พบทั้งหมด <?= count($amulets) ?> รายการ</p>
                </div>
                <div>
                    <a href="amulet_form.php" class="btn btn-gold">+ เพิ่มพระเครื่องใหม่</a>
                </div>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    ลบข้อมูลพระเครื่องเรียบร้อยแล้ว
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
                <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1rem;">
                    บันทึกข้อมูลพระเครื่องสำเร็จแล้ว
                </div>
            <?php endif; ?>

            <div style="background: var(--bg-card); border: 1px solid var(--border-gold); border-radius: 8px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-gold); background: #380108; color: var(--gold-accent);">
                            <th style="padding: 1rem;">รูปภาพ</th>
                            <th style="padding: 1rem;">ชื่อพระเครื่อง</th>
                            <th style="padding: 1rem;">หมวดหมู่</th>
                            <th style="padding: 1rem;">เกจิ / วัด</th>
                            <th style="padding: 1rem;">ประเภท</th>
                            <th style="padding: 1rem;">ราคา (บาท)</th>
                            <th style="padding: 1rem; text-align: center;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($amulets)): ?>
                            <tr>
                                <td colspan="7" style="padding: 2.5rem; text-align: center; color: var(--text-light);">
                                    ยังไม่มีข้อมูลพระเครื่องในระบบ Firestore <a href="amulet_form.php" style="color: var(--gold-accent); text-decoration: underline;">คลิกที่นี่เพื่อเพิ่มองค์แรก</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($amulets as $item): ?>
                                <tr style="border-bottom: 1px solid rgba(233, 215, 147, 0.15);">
                                    <td style="padding: 0.75rem 1rem; width: 80px;">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-gold);">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: #2a0106; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: var(--gold-accent); border: 1px solid var(--border-gold);">✦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <strong><?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--text-light); opacity: 0.8;">
                                            ปีสร้าง: <?= htmlspecialchars($item['year_made'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; color: var(--text-light);">
                                        <?= htmlspecialchars($categories_map[(string)($item['category_id'] ?? '')] ?? 'ทั่วไป', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-size: 0.9rem;">
                                        <div><?= htmlspecialchars($item['monk_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div style="color: var(--text-light); opacity: 0.8;"><?= htmlspecialchars($item['temple_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td style="padding: 0.75rem 1rem;">
                                        <?php if (($item['type'] ?? '') === 'auction'): ?>
                                            <span class="badge badge-auction">เปิดประมูล</span>
                                        <?php else: ?>
                                            <span class="badge badge-fixed">เช่าบูชาปกติ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; font-weight: 700; color: var(--gold-accent);">
                                        ฿<?= number_format((float)($item['price'] ?? 0), 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem 1rem; text-align: center;">
                                        <div style="display: flex; gap: 0.35rem; justify-content: center;">
                                            <a href="amulet_form.php?id=<?= $item['id'] ?>" class="btn btn-outline-gold" style="padding: 0.3rem 0.6rem; font-size: 0.85rem;">แก้ไข</a>
                                            <a href="amulet_delete.php?id=<?= $item['id'] ?>" class="btn btn-outline-gold" style="padding: 0.3rem 0.6rem; font-size: 0.85rem; border-color: var(--danger); color: #ff8b94;" onclick="return confirm('ยืนยันการลบพระเครื่ององค์นี้หรือไม่?');">ลบ</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
