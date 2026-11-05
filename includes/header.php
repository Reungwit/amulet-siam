<?php
/**
 * Global Site Header Template
 * 
 * Sets up session, HTML head, custom stylesheets, and main navigation bar.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/firebase_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'ศูนย์รวมพระเครื่องและประมูลพระยอดนิยม';
$current_user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Amulet Siam</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav-container">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">✦</span>
                <span>Amulet Siam (สยามพระเครื่อง)</span>
            </a>

            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="nav-link">หน้าแรก</a></li>
                    <li><a href="auctions.php" class="nav-link">ห้องประมูลสด</a></li>
                    <li><a href="cart.php" class="nav-link">
                        ตะกร้าสินค้า 
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <span style="background: var(--gold-accent); color: #380108; font-size: 0.75rem; font-weight: bold; padding: 0.15rem 0.45rem; border-radius: 10px; margin-left: 0.25rem;">
                                <?= count($_SESSION['cart']) ?>
                            </span>
                        <?php endif; ?>
                    </a></li>
                    <?php if ($current_user): ?>
                        <li><a href="profile.php" class="nav-link">โปรไฟล์</a></li>
                        <li><a href="my_orders.php" class="nav-link">คำสั่งเช่าของฉัน</a></li>
                        <?php if (($current_user['role'] ?? '') === 'admin'): ?>
                            <li><a href="admin/index.php" class="btn btn-outline-gold">ระบบแอดมิน</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="nav-link">เข้าสู่ระบบ</a></li>
                        <li><a href="register.php" class="btn btn-gold">สมัครสมาชิก</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="site-main">
        <div class="container">

