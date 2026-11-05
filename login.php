<?php
/**
 * User Login Page
 * 
 * Verifies credentials against MySQL using password_verify() and sets session data.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

$page_title = 'เข้าสู่ระบบ';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้/อีเมล และรหัสผ่าน';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, full_name, role FROM users WHERE username = :id_user OR email = :id_email LIMIT 1");
            $stmt->execute(['id_user' => $identifier, 'id_email' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session id to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                ];

                if ($user['role'] === 'admin') {
                    header('Location: admin/index.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $error = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            error_log('[LOGIN ERROR] ' . $e->getMessage());
            $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 440px; margin: 3rem auto; background: var(--bg-card); padding: 2.2rem; border: 1px solid var(--border-gold); border-radius: 8px; box-shadow: var(--shadow-dark);">
    <h2 class="text-center" style="margin-bottom: 1.5rem;">เข้าสู่ระบบ Amulet Siam</h2>

    <?php if (!empty($error)): ?>
        <div style="background: rgba(230, 57, 70, 0.15); border: 1px solid var(--danger); color: #ff8b94; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label class="form-label" for="identifier">ชื่อผู้ใช้งาน หรือ อีเมล</label>
            <input type="text" id="identifier" name="identifier" class="form-control" value="<?= htmlspecialchars($_POST['identifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem; padding: 0.8rem;">เข้าสู่ระบบ</button>
    </form>

    <div style="margin-top: 1.5rem; text-align: center; font-size: 0.95rem; border-top: 1px solid var(--border-gold); padding-top: 1rem;">
        <p>ยังไม่มีบัญชีผู้ใช้งาน? <a href="register.php" style="text-decoration: underline;">สมัครสมาชิกที่นี่</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

