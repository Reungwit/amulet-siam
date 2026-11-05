<?php
/**
 * Shopping Cart Handler & View
 * 
 * Manages shopping cart session, item removal, price calculation,
 * and navigation to checkout.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db_connect.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pdo = get_db_connection();
$flash_msg = '';
$flash_type = 'success';

// Handle Cart Actions (POST preferred)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amulet_id = (int)($_POST['amulet_id'] ?? 0);

    if ($action === 'add' && $amulet_id > 0) {
        // Verify amulet exists and is available for fixed purchase
        $stmt = $pdo->prepare("SELECT id, title, price, is_available, type FROM amulets WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $amulet_id]);
        $item = $stmt->fetch();

        if ($item && (int)$item['is_available'] === 1 && $item['type'] === 'fixed') {
            if (!in_array($amulet_id, $_SESSION['cart'], true)) {
                $_SESSION['cart'][] = $amulet_id;
                $flash_msg = "เพิ่มพระเครื่อง '{$item['title']}' ลงในตะกร้าเรียบร้อยแล้ว";
            } else {
                $flash_msg = "พระเครื่ององค์นี้อยู่ในตะกร้าสินค้าของท่านแล้ว";
                $flash_type = 'warning';
            }
        } else {
            $flash_msg = "ไม่สามารถเช่าพระเครื่ององค์นี้ได้ หรือรายการถูกเช่าไปแล้ว";
            $flash_type = 'danger';
        }
    } elseif ($action === 'remove' && $amulet_id > 0) {
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($id) => $id !== $amulet_id));
        $flash_msg = "ลบรายการออกจากตะกร้าเรียบร้อยแล้ว";
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        $flash_msg = "ล้างรายการทั้งหมดในตะกร้าเรียบร้อยแล้ว";
    }
}

// Fetch details for all items currently in cart
$cart_items = [];
$total_amount = 0.0;

if (!empty($_SESSION['cart'])) {
    $in_placeholders = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $query = "SELECT a.*, c.name AS category_name 
              FROM amulets a
              LEFT JOIN categories c ON a.category_id = c.id
              WHERE a.id IN ({$in_placeholders})";
    $stmt = $pdo->prepare($query);
    $stmt->execute($_SESSION['cart']);
    $cart_items = $stmt->fetchAll();

    foreach ($cart_items as $item) {
        $total_amount += (float)$item['price'];
    }
}

$page_title = 'ตะกร้าสินค้า - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <h1 style="margin-bottom: 1.5rem;">ตะกร้าเช่าบูชาพระเครื่อง</h1>

    <?php if ($flash_msg): ?>
        <div style="background: <?= $flash_type === 'danger' ? 'rgba(230, 57, 70, 0.2)' : ($flash_type === 'warning' ? 'rgba(233, 215, 147, 0.2)' : 'rgba(42, 157, 143, 0.2)') ?>; 
                    border: 1px solid <?= $flash_type === 'danger' ? 'var(--danger)' : ($flash_type === 'warning' ? 'var(--gold-accent)' : 'var(--success)') ?>; 
                    color: <?= $flash_type === 'danger' ? '#ff8b94' : 'var(--text-light)' ?>; 
                    padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($flash_msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="card" style="padding: 3rem; text-align: center;">
            <p class="text-light" style="font-size: 1.2rem; margin-bottom: 1.5rem;">ไม่มีพระเครื่องในตะกร้าสินค้าของท่าน</p>
            <a href="index.php" class="btn btn-gold">เลือกชมพระเครื่องในตลาด &rarr;</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-gold); color: var(--gold-accent);">
                        <th style="padding: 0.75rem 0.5rem;">รูปภาพ</th>
                        <th style="padding: 0.75rem 0.5rem;">รายการพระเครื่อง</th>
                        <th style="padding: 0.75rem 0.5rem;">หมวดหมู่</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: right;">ราคาบูชา</th>
                        <th style="padding: 0.75rem 0.5rem; text-align: center;">การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr style="border-bottom: 1px solid rgba(233, 215, 147, 0.15);">
                            <td style="padding: 0.75rem 0.5rem; width: 70px;">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-gold);">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; background: #2a0106; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: var(--gold-accent); border: 1px solid var(--border-gold);">✦</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <strong><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div style="font-size: 0.85rem; color: var(--text-light); opacity: 0.8;">
                                    <?= htmlspecialchars($item['monk_name'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($item['temple_name'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-light);">
                                <?= htmlspecialchars($item['category_name'] ?: 'ทั่วไป', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: var(--gold-accent);">
                                ฿<?= number_format((float)$item['price'], 2) ?>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                <form method="POST" action="cart.php" onsubmit="return confirm('ยืนยันที่จะนำพระเครื่ององค์นี้ออกจากตะกร้า?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="amulet_id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn btn-outline-gold" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; border-color: var(--danger); color: #ff8b94;">ลบ</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top: 2px solid var(--border-gold);">
                        <td colspan="3" style="padding: 1.25rem 0.5rem; font-size: 1.2rem; font-weight: 700;">ยอดรวมทั้งหมด</td>
                        <td style="padding: 1.25rem 0.5rem; text-align: right; font-size: 1.4rem; font-weight: 700; color: var(--gold-accent);">
                            ฿<?= number_format($total_amount, 2) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 1rem;">
                <a href="index.php" class="btn btn-outline-gold">&larr; เลือกชมพระเครื่องเพิ่ม</a>
                <form method="POST" action="cart.php" onsubmit="return confirm('ต้องการล้างตะกร้าทั้งหมดหรือไม่?');">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-outline-gold" style="border-color: var(--danger); color: #ff8b94;">ล้างตะกร้า</button>
                </form>
            </div>
            <div>
                <a href="checkout.php" class="btn btn-gold" style="padding: 0.85rem 2rem; font-size: 1.1rem;">
                    ดำเนินการเช่าบูชา / สั่งซื้อ &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

