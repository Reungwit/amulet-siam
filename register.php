<?php
/**
 * User Registration Page
 * 
 * Handles new account creation with bcrypt password hashing and validation.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';

$page_title = 'สมัครสมาชิกใหม่';
$errors = [];
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (strlen($password) < 6) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    }

    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานไปแล้วในระบบ';
            } else {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role) 
                                         VALUES (:username, :email, :password_hash, :full_name, :phone, 'user')");
                $insert->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'full_name' => $full_name,
                    'phone' => $phone,
                ]);

                $success_msg = 'สมัครสมาชิกสำเร็จ! ท่านสามารถเข้าสู่ระบบได้ทันที';
            }
        } catch (PDOException $e) {
            error_log('[REGISTRATION ERROR] ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 500px; margin: 2rem auto; background: var(--bg-card); padding: 2rem; border: 1px solid var(--border-gold); border-radius: 8px; box-shadow: var(--shadow-dark);">
    <h2 class="text-center" style="margin-bottom: 1.5rem;">สมัครสมาชิก Amulet Siam</h2>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(230, 57, 70, 0.15); border: 1px solid var(--danger); color: #ff8b94; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
            <ul style="margin-left: 1.25rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 1rem; border-radius: 4px; margin-bottom: 1.25rem; text-align: center;">
            <p><?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="login.php" class="btn btn-gold" style="margin-top: 0.75rem;">ไปหน้าเข้าสู่ระบบ</a>
        </div>
    <?php else: ?>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label class="form-label" for="username">ชื่อผู้ใช้งาน (Username) *</label>
                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">อีเมล (Email) *</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="full_name">ชื่อ-นามสกุลจริง</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">เบอร์โทรศัพท์ติดต่อ</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">รหัสผ่าน (อย่างน้อย 6 ตัวอักษร) *</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">ยืนยันรหัสผ่านอีกครั้ง *</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem; padding: 0.8rem;">ยืนยันการสมัครสมาชิก</button>
        </form>

        <p class="text-center" style="margin-top: 1.5rem; font-size: 0.95rem;">
            มีบัญชีผู้ใช้อยู่แล้ว? <a href="login.php" style="text-decoration: underline;">เข้าสู่ระบบที่นี่</a>
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

