<?php
/**
 * Admin Panel Dashboard
 * 
 * Provides administrative overview, metrics, and navigation.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = get_db_connection();

// Collect dashboard summary statistics
$stat_amulets = $pdo->query("SELECT COUNT(*) FROM amulets")->fetchColumn();
$stat_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat_auctions = $pdo->query("SELECT COUNT(*) FROM auctions WHERE status = 'active'")->fetchColumn();
$stat_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending_verification'")->fetchColumn();

$page_title = 'แผงควบคุมระบบ (Admin CMS)';
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
                    <li><a href="../index.php" class="nav-link" target="_blank">หน้าร้านจริง ↗</a></li>
                    <li><a href="amulets.php" class="nav-link">จัดการพระเครื่อง</a></li>
                    <li><a href="orders.php" class="nav-link">ตรวจสอบสลิป</a></li>
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h1>ยินดีต้อนรับ, <?= htmlspecialchars($admin_user['full_name'] ?: $admin_user['username'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-light">ระบบศูนย์กลางการควบคุมพระเครื่องและประมูลออนไลน์</p>
                </div>
                <div>
                    <a href="amulet_form.php" class="btn btn-gold">+ ลงทะเบียนพระเครื่ององค์ใหม่</a>
                </div>
            </div>

            <!-- Overview Metrics Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <div class="card" style="padding: 1.5rem; text-align: center;">
                    <h3 style="font-size: 2.2rem; color: var(--gold-accent); margin-bottom: 0.25rem;"><?= $stat_amulets ?></h3>
                    <p class="text-light">รายการพระเครื่องทั้งหมด</p>
                    <a href="amulets.php" style="font-size: 0.85rem; margin-top: 0.5rem; display: inline-block;">จัดการรายการ &rarr;</a>
                </div>

                <div class="card" style="padding: 1.5rem; text-align: center;">
                    <h3 style="font-size: 2.2rem; color: var(--gold-accent); margin-bottom: 0.25rem;"><?= $stat_users ?></h3>
                    <p class="text-light">สมาชิกในระบบ</p>
                    <span style="font-size: 0.85rem; opacity: 0.7;">ผู้ใช้งานทั้งหมด</span>
                </div>

                <div class="card" style="padding: 1.5rem; text-align: center;">
                    <h3 style="font-size: 2.2rem; color: var(--gold-accent); margin-bottom: 0.25rem;"><?= $stat_auctions ?></h3>
                    <p class="text-light">ห้องประมูลที่เปิดอยู่</p>
                    <a href="auctions.php" style="font-size: 0.85rem; margin-top: 0.5rem; display: inline-block;">ดูห้องประมูล &rarr;</a>
                </div>

                <div class="card" style="padding: 1.5rem; text-align: center;">
                    <h3 style="font-size: 2.2rem; color: <?= $stat_orders > 0 ? 'var(--danger)' : 'var(--gold-accent)' ?>; margin-bottom: 0.25rem;"><?= $stat_orders ?></h3>
                    <p class="text-light">สลิปที่รอตรวจสอบ</p>
                    <a href="orders.php" style="font-size: 0.85rem; margin-top: 0.5rem; display: inline-block;">ตรวจสอบสลิป &rarr;</a>
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="card" style="padding: 1.5rem;">
                <h2 style="margin-bottom: 1rem;">เมนูด่วนการจัดการ</h2>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="amulet_form.php" class="btn btn-gold">+ เพิ่มพระเครื่องใหม่</a>
                    <a href="amulets.php" class="btn btn-outline-gold">ดูรายการพระเครื่องทั้งหมด</a>
                    <a href="orders.php" class="btn btn-outline-gold">ระบบตรวจสอบสลิปโอนเงิน</a>
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
