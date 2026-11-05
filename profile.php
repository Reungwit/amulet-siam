<?php
/**
 * User Profile & Account Settings (100% Firebase Firestore)
 * 
 * Allows users to view and update personal data and change password via Firestore.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/firestore_client.php';

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php?redirect=profile.php');
    exit;
}

$user = $_SESSION['user'];
$fs = get_firestore();
$errors = [];
$success_msg = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        try {
            $fs->updateDocument('users', (string)$user['id'], [
                'full_name' => $full_name,
                'phone' => $phone,
                'address' => $address,
            ]);

            $_SESSION['user']['full_name'] = $full_name;
            $success_msg = 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว';
        } catch (Exception $e) {
            error_log('[PROFILE UPDATE ERROR] ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูลส่วนตัว';
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            $errors[] = 'กรุณากรอกรหัสผ่านเดิมและรหัสผ่านใหม่';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
        } else {
            $u_data = $fs->getDocument('users', (string)$user['id']);
            if ($u_data && !empty($u_data['password_hash']) && password_verify($current_password, $u_data['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $fs->updateDocument('users', (string)$user['id'], [
                    'password_hash' => $new_hash,
                ]);
                $success_msg = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
            } else {
                $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
            }
        }
    }
}

// Fetch latest profile from Firestore
$profile = $fs->getDocument('users', (string)$user['id']) ?: $user;

// Order statistics from Firestore orders
$all_orders = $fs->listDocuments('orders');
$total_orders = 0;
foreach ($all_orders as $ord) {
    if ((string)($ord['user_id'] ?? '') === (string)$user['id']) {
        $total_orders++;
    }
}

$page_title = 'บัญชีและโปรไฟล์ของฉัน - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <h1 style="margin-bottom: 0.5rem;">โปรไฟล์ของฉัน</h1>
    <p class="text-light" style="opacity: 0.8; margin-bottom: 1.5rem;">จัดการข้อมูลส่วนตัว ที่อยู่จัดส่งพัสดุพระเครื่อง และความปลอดภัยของบัญชี</p>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(230, 57, 70, 0.15); border: 1px solid var(--danger); color: #ff8b94; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem;">
            <ul style="margin-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Overview Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-light); opacity: 0.8;">ผู้ใช้งานระบบ</div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--gold-accent);">
                <?= htmlspecialchars($profile['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                <?php if (($profile['role'] ?? '') === 'admin'): ?>
                    <span class="badge" style="background: var(--gold-accent); color: #380108; margin-left: 0.5rem;">ผู้ดูแลระบบ (Admin)</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-light);"><?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="card" style="padding: 1.25rem;">
            <div style="font-size: 0.85rem; color: var(--text-light); opacity: 0.8;">คำสั่งเช่าบูชาทั้งหมด</div>
            <div style="font-size: 1.6rem; font-weight: 700; color: var(--gold-accent);">
                <?= $total_orders ?> รายการ
            </div>
            <a href="my_orders.php" style="font-size: 0.85rem; text-decoration: underline;">ดูประวัติคำสั่งซื้อ &rarr;</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem;">
        <!-- Left: Personal Information & Address Form -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin-bottom: 1.25rem;">
                ข้อมูลส่วนตัวและที่อยู่สำหรับส่งพัสดุ
            </h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label class="form-label" for="username">ชื่อบัญชีผู้ใช้ (Username)</label>
                    <input type="text" id="username" class="form-control" value="<?= htmlspecialchars($profile['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled style="opacity: 0.7;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">อีเมล (Email)</label>
                    <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" disabled style="opacity: 0.7;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">ชื่อ - นามสกุลจริง (สำหรับจ่าหน้าซองพัสดุ)</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($profile['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น สมศักดิ์ มงคลสุข">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">เบอร์โทรศัพท์ติดต่อ</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="08xxxxxxxx">
                </div>

                <div class="form-group">
                    <label class="form-label" for="address">ที่อยู่สำหรับจัดส่งพระเครื่อง (ที่อยู่หลัก)</label>
                    <textarea id="address" name="address" rows="3" class="form-control" placeholder="บ้านเลขที่, หมู่, ถนน, ตำบล/แขวง, อำเภอ/เขต, จังหวัด, รหัสไปรษณีย์"><?= htmlspecialchars($profile['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 0.5rem; padding: 0.8rem;">
                    บันทึกข้อมูลส่วนตัว
                </button>
            </form>
        </div>

        <!-- Right: Change Password -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin-bottom: 1.25rem;">
                เปลี่ยนรหัสผ่าน
            </h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">รหัสผ่านปัจจุบัน</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-outline-gold" style="width: 100%; margin-top: 0.5rem; padding: 0.8rem;">
                    อัปเดตรหัสผ่าน
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
