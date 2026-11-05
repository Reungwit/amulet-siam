<?php
/**
 * Checkout & Payment Slip Upload Page (100% Firebase Firestore)
 * 
 * Enforces login, collects shipping address, accepts bank transfer slip upload,
 * and atomically creates orders document in Firestore while reserving amulets.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/firestore_client.php';
require_once __DIR__ . '/includes/repository.php';

// Must be logged in to checkout
if (!isset($_SESSION['user'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$user = $_SESSION['user'];
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

$fs = get_firestore();
$repo = get_amulet_repository();

// Fetch cart items from Firestore
$cart_items = [];
$total_amount = 0.0;
foreach ($cart as $id) {
    $item = $repo->getAmuletById((string)$id);
    if ($item && !empty($item['is_available'])) {
        $cart_items[] = $item;
        $total_amount += (float)($item['price'] ?? 0);
    }
}

if (empty($cart_items)) {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit;
}

$errors = [];
$success_order_id = null;

// Fetch user profile default address and phone from Firestore
$user_profile = $fs->getDocument('users', (string)$user['id']) ?: $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($shipping_address)) {
        $errors[] = 'กรุณาระบุที่อยู่สำหรับจัดส่งพระเครื่อง';
    }
    if (empty($phone)) {
        $errors[] = 'กรุณาระบุเบอร์โทรศัพท์สำหรับติดต่อส่งพัสดุ';
    }

    // Slip Upload Handling
    $slip_path = null;
    if (!isset($_FILES['slip'])) {
        $errors[] = 'กรุณาแนบไฟล์สลิปหลักฐานการโอนเงิน';
    } elseif ($_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
        $slip_err = $_FILES['slip']['error'];
        $slip_err_messages = [
            UPLOAD_ERR_INI_SIZE   => 'ขนาดไฟล์สลิปเกินกว่าค่าที่เซิร์ฟเวอร์กำหนด',
            UPLOAD_ERR_FORM_SIZE  => 'ขนาดไฟล์สลิปเกินกว่าที่ฟอร์มกำหนด',
            UPLOAD_ERR_PARTIAL    => 'ไฟล์สลิปถูกอัปโหลดขึ้นมาไม่ครบถ้วน',
            UPLOAD_ERR_NO_FILE    => 'กรุณาเลือกไฟล์สลิปหลักฐานการโอนเงิน',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับอัปโหลดบนเซิร์ฟเวอร์',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์สลิปลงดิสก์ได้',
        ];
        $errors[] = $slip_err_messages[$slip_err] ?? "เกิดข้อผิดพลาดในการอัปโหลดสลิป (รหัสข้อผิดพลาด: {$slip_err})";
    } else {
        $file_tmp = $_FILES['slip']['tmp_name'];
        $file_name = $_FILES['slip']['name'];
        $file_size = $_FILES['slip']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($file_ext, $allowed_exts, true)) {
            $errors[] = 'ไฟล์สลิปต้องเป็นรูปภาพ (JPG, JPEG, PNG, WEBP) หรือ PDF เท่านั้น';
        } elseif ($file_size > 20 * 1024 * 1024) {
            $errors[] = 'ขนาดไฟล์สลิปต้องไม่เกิน 20 MB';
        } else {
            $upload_dir = __DIR__ . '/images/uploads/slips/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            @chmod($upload_dir, 0777);

            $new_filename = 'slip_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                @chmod($destination, 0666);
                $slip_path = 'images/uploads/slips/' . $new_filename;
            } else {
                $errors[] = 'ไม่สามารถบันทึกไฟล์สลิปเข้าสู่ ' . $upload_dir . ' กรุณาตรวจสอบสิทธิ์โฟลเดอร์';
            }
        }
    }

    if (empty($errors) && $slip_path !== null) {
        try {
            // 1. Prepare items array
            $order_items = [];
            foreach ($cart_items as $item) {
                $order_items[] = [
                    'amulet_id' => (string)$item['id'],
                    'title' => $item['title'] ?? '',
                    'price' => (float)($item['price'] ?? 0),
                ];
            }

            // 2. Create Order in Firestore
            $order_id = $fs->getNextId('orders');
            $fs->setDocument('orders', $order_id, [
                'user_id' => (string)$user['id'],
                'user_name' => $user_profile['full_name'] ?: $user['username'],
                'total_amount' => (float)$total_amount,
                'slip_image' => $slip_path,
                'status' => 'pending_verification',
                'tracking_number' => '',
                'shipping_address' => "เบอร์ติดต่อ: {$phone}\nที่อยู่: {$shipping_address}",
                'created_at' => date('Y-m-d H:i:s'),
                'items' => $order_items,
            ]);

            // 3. Mark amulets as reserved in Firestore
            foreach ($cart_items as $item) {
                $fs->updateDocument('amulets', (string)$item['id'], [
                    'is_available' => false,
                ]);
            }

            // Clear Cart
            $_SESSION['cart'] = [];
            $success_order_id = $order_id;

        } catch (Exception $e) {
            error_log('[CHECKOUT FIRESTORE ERROR] ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาดในการประมวลผลคำสั่งเช่าบูชา กรุณาลองใหม่อีกครั้ง';
        }
    }
}

$page_title = 'ยืนยันการเช่าบูชาและชำระเงิน - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <h1 style="margin-bottom: 1.5rem;">แจ้งชำระเงินและยืนยันการสั่งเช่าบูชา</h1>

    <?php if ($success_order_id): ?>
        <div class="card" style="padding: 2.5rem; text-align: center; border-color: var(--success);">
            <div style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;">✓</div>
            <h2 style="color: var(--gold-accent);">ระบบได้รับข้อมูลคำสั่งเช่าบูชาเรียบร้อยแล้ว!</h2>
            <p class="text-light" style="margin: 1rem 0; font-size: 1.1rem;">
                รหัสคำสั่งเช่าบูชาของคุณคือ: <strong>#<?= str_pad((string)$success_order_id, 6, '0', STR_PAD_LEFT) ?></strong>
            </p>
            <p class="text-light" style="opacity: 0.85; margin-bottom: 2rem;">
                เจ้าหน้าที่จะทำการตรวจสอบสลิปการโอนเงินและดำเนินการจัดส่งพัสดุพร้อมแจ้งเลขพัสดุ (Tracking) ให้ท่านทราบ
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem;">
                <a href="my_orders.php" class="btn btn-gold">ตรวจสอบสถานะคำสั่งซื้อของฉัน</a>
                <a href="index.php" class="btn btn-outline-gold">กลับสู่หน้าหลัก</a>
            </div>
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div style="background: rgba(230, 57, 70, 0.15); border: 1px solid var(--danger); color: #ff8b94; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem;">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem;">
            <!-- Form Side -->
            <div>
                <form method="POST" action="checkout.php" enctype="multipart/form-data" class="card" style="padding: 1.75rem;">
                    <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin-bottom: 1.25rem;">
                        ข้อมูลการจัดส่งพัสดุ
                    </h3>

                    <div class="form-group">
                        <label class="form-label" for="full_name">ผู้รับพัสดุ</label>
                        <input type="text" id="full_name" class="form-control" value="<?= htmlspecialchars($user_profile['full_name'] ?: $user['username'], ENT_QUOTES, 'UTF-8') ?>" readonly style="opacity: 0.8;">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">เบอร์โทรศัพท์ที่ติดต่อได้สะดวก *</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($user_profile['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="08xxxxxxxx">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="shipping_address">ที่อยู่จัดส่งพัสดุอย่างละเอียด *</label>
                        <textarea id="shipping_address" name="shipping_address" rows="3" class="form-control" required placeholder="บ้านเลขที่, หมู่, ถนน, ตำบล/แขวง, อำเภอ/เขต, จังหวัด, รหัสไปรษณีย์"><?= htmlspecialchars($user_profile['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin: 1.5rem 0 1.25rem;">
                        อัปโหลดสลิปโอนเงิน (หลักฐานชำระเงิน)
                    </h3>

                    <div class="form-group">
                        <label class="form-label" for="slip">แนบไฟล์สลิปหลักฐานการโอน * (JPG, PNG, WEBP, PDF)</label>
                        <input type="file" id="slip" name="slip" class="form-control" accept="image/*,.pdf" required>
                    </div>

                    <button type="submit" class="btn btn-gold" style="width: 100%; padding: 0.9rem; font-size: 1.1rem; margin-top: 1rem;">
                        ✓ ยืนยันการแจ้งชำระเงิน
                    </button>
                </form>
            </div>

            <!-- Summary & Bank Info Side -->
            <div>
                <!-- Bank Info Box -->
                <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; border-color: var(--gold-accent);">
                    <h3 style="color: var(--gold-accent); margin-bottom: 0.75rem;">บัญชีสำหรับโอนเงินบูชา</h3>
                    <div style="background: #350107; padding: 1rem; border-radius: 6px; font-size: 0.95rem; line-height: 1.8;">
                        <div><strong>ธนาคารกสิกรไทย (KBANK)</strong></div>
                        <div>เลขที่บัญชี: <span style="font-size: 1.2rem; font-weight: 700; color: var(--gold-accent);">123-4-56789-0</span></div>
                        <div>ชื่อบัญชี: บจก. สยามพระเครื่อง จำกัด</div>
                        <div style="margin-top: 0.5rem; color: var(--gold-accent); font-weight: 700;">
                            ยอดเงินที่ต้องโอน: ฿<?= number_format($total_amount, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="card" style="padding: 1.5rem;">
                    <h3 style="margin-bottom: 1rem;">สรุปรายการพระเครื่อง (<?= count($cart_items) ?> องค์)</h3>
                    <ul style="list-style: none; margin-bottom: 1.25rem;">
                        <?php foreach ($cart_items as $item): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(233, 215, 147, 0.15); font-size: 0.9rem;">
                                <span><?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                <span style="font-weight: 700; color: var(--gold-accent);">฿<?= number_format((float)($item['price'] ?? 0), 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; color: var(--gold-accent); padding-top: 0.5rem; border-top: 2px solid var(--border-gold);">
                        <span>ยอดชำระรวม</span>
                        <span>฿<?= number_format($total_amount, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
