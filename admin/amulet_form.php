<?php
/**
 * Admin Amulet Form (Add / Edit)
 * 
 * Handles creation and editing of amulets, image uploads, and 6-point spiritual status attributes.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = get_db_connection();

$amulet_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$is_edit = $amulet_id !== null && $amulet_id > 0;

$errors = [];
$amulet = [
    'title' => '',
    'category_id' => 1,
    'monk_name' => '',
    'temple_name' => '',
    'year_made' => '',
    'type' => 'fixed',
    'price' => '0.00',
    'description' => '',
    'image_url' => '',
];

$stats = [
    'protection' => 5,
    'popularity' => 5,
    'charm' => 5,
    'evasion' => 5,
    'invincibility' => 5,
    'wealth' => 5,
];

// Fetch categories for select dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY id ASC")->fetchAll();

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM amulets WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $amulet_id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: amulets.php');
        exit;
    }
    $amulet = $existing;

    // Fetch existing stats
    $stat_stmt = $pdo->prepare("SELECT * FROM amulet_stats WHERE amulet_id = :id LIMIT 1");
    $stat_stmt->execute(['id' => $amulet_id]);
    $existing_stats = $stat_stmt->fetch();
    if ($existing_stats) {
        $stats = $existing_stats;
    }
}

// Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 1);
    $monk_name = trim($_POST['monk_name'] ?? '');
    $temple_name = trim($_POST['temple_name'] ?? '');
    $year_made = trim($_POST['year_made'] ?? '');
    $type = ($_POST['type'] ?? 'fixed') === 'auction' ? 'auction' : 'fixed';
    $price = (float)($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // 6 Status Attributes (1-10)
    $stat_protection = max(1, min(10, (int)($_POST['protection'] ?? 5)));
    $stat_popularity = max(1, min(10, (int)($_POST['popularity'] ?? 5)));
    $stat_charm = max(1, min(10, (int)($_POST['charm'] ?? 5)));
    $stat_evasion = max(1, min(10, (int)($_POST['evasion'] ?? 5)));
    $stat_invincibility = max(1, min(10, (int)($_POST['invincibility'] ?? 5)));
    $stat_wealth = max(1, min(10, (int)($_POST['wealth'] ?? 5)));

    if (empty($title)) {
        $errors[] = 'กรุณาระบุชื่อพระเครื่อง';
    }
    if (empty($monk_name)) {
        $errors[] = 'กรุณาระบุชื่อพระเกจิอาจารย์';
    }
    if (empty($temple_name)) {
        $errors[] = 'กรุณาระบุชื่อวัด';
    }

    // Image Upload Handling
    $image_path = $amulet['image_url'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_error = $_FILES['image']['error'];
        if ($upload_error !== UPLOAD_ERR_OK) {
            $upload_err_messages = [
                UPLOAD_ERR_INI_SIZE   => 'ขนาดไฟล์รูปภาพเกินกว่าที่เซิร์ฟเวอร์กำหนด (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'ขนาดไฟล์รูปภาพเกินกว่าที่ฟอร์มกำหนด',
                UPLOAD_ERR_PARTIAL    => 'ไฟล์ถูกอัปโหลดขึ้นมาไม่ครบถ้วน',
                UPLOAD_ERR_NO_FILE    => 'ไม่มีการเลือกไฟล์รูปภาพ',
                UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว (tmp dir) บนเซิร์ฟเวอร์',
                UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
                UPLOAD_ERR_EXTENSION  => 'การอัปโหลดไฟล์ถูกระงับโดยส่วนขยาย PHP',
            ];
            $errors[] = $upload_err_messages[$upload_error] ?? "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (รหัสข้อผิดพลาด: {$upload_error})";
        } else {
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_size = $_FILES['image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($file_ext, $allowed_exts, true)) {
                $errors[] = 'ไฟล์รูปภาพต้องเป็นนามสกุล jpg, jpeg, png, webp หรือ gif เท่านั้น (นามสกุลปัจจุบัน: ' . htmlspecialchars($file_ext) . ')';
            } elseif ($file_size > 20 * 1024 * 1024) {
                $errors[] = 'ขนาดไฟล์รูปภาพต้องไม่เกิน 20 MB';
            } else {
                $upload_dir = __DIR__ . '/../images/uploads/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                @chmod($upload_dir, 0777);

                $new_filename = 'amulet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    @chmod($destination, 0666);
                    $image_path = 'images/uploads/' . $new_filename;
                } else {
                    $errors[] = 'ไม่สามารถย้ายไฟล์รูปภาพเข้าสู่ไดเรกทอรี ' . $upload_dir . ' กรุณาตรวจสอบสิทธิ์ของโฟลเดอร์';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($is_edit) {
                $stmt = $pdo->prepare("UPDATE amulets SET 
                    category_id = :category_id,
                    title = :title,
                    monk_name = :monk_name,
                    temple_name = :temple_name,
                    year_made = :year_made,
                    type = :type,
                    price = :price,
                    description = :description,
                    image_url = :image_url
                    WHERE id = :id");
                $stmt->execute([
                    'category_id' => $category_id,
                    'title' => $title,
                    'monk_name' => $monk_name,
                    'temple_name' => $temple_name,
                    'year_made' => $year_made,
                    'type' => $type,
                    'price' => $price,
                    'description' => $description,
                    'image_url' => $image_path,
                    'id' => $amulet_id,
                ]);

                $stat_save = $pdo->prepare("REPLACE INTO amulet_stats 
                    (amulet_id, protection, popularity, charm, evasion, invincibility, wealth)
                    VALUES (:amulet_id, :protection, :popularity, :charm, :evasion, :invincibility, :wealth)");
                $stat_save->execute([
                    'amulet_id' => $amulet_id,
                    'protection' => $stat_protection,
                    'popularity' => $stat_popularity,
                    'charm' => $stat_charm,
                    'evasion' => $stat_evasion,
                    'invincibility' => $stat_invincibility,
                    'wealth' => $stat_wealth,
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO amulets 
                    (category_id, title, monk_name, temple_name, year_made, type, price, description, image_url)
                    VALUES (:category_id, :title, :monk_name, :temple_name, :year_made, :type, :price, :description, :image_url)");
                $stmt->execute([
                    'category_id' => $category_id,
                    'title' => $title,
                    'monk_name' => $monk_name,
                    'temple_name' => $temple_name,
                    'year_made' => $year_made,
                    'type' => $type,
                    'price' => $price,
                    'description' => $description,
                    'image_url' => $image_path,
                ]);
                $new_id = (int)$pdo->lastInsertId();

                $stat_save = $pdo->prepare("INSERT INTO amulet_stats 
                    (amulet_id, protection, popularity, charm, evasion, invincibility, wealth)
                    VALUES (:amulet_id, :protection, :popularity, :charm, :evasion, :invincibility, :wealth)");
                $stat_save->execute([
                    'amulet_id' => $new_id,
                    'protection' => $stat_protection,
                    'popularity' => $stat_popularity,
                    'charm' => $stat_charm,
                    'evasion' => $stat_evasion,
                    'invincibility' => $stat_invincibility,
                    'wealth' => $stat_wealth,
                ]);
            }

            $pdo->commit();
            header('Location: amulets.php?msg=saved');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[AMULET SAVE ERROR] ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

$page_title = $is_edit ? 'แก้ไขข้อมูลพระเครื่อง' : 'เพิ่มพระเครื่องใหม่';
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
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container" style="max-width: 800px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
                <a href="amulets.php" class="btn btn-outline-gold">&larr; กลับหน้ารายการ</a>
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

            <form method="POST" enctype="multipart/form-data" style="background: var(--bg-card); padding: 2rem; border: 1px solid var(--border-gold); border-radius: 8px;">
                <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin-bottom: 1.25rem;">1. ข้อมูลพื้นฐานพระเครื่อง</h3>

                <div class="form-group">
                    <label class="form-label" for="title">ชื่อพระเครื่อง / วัตถุมงคล *</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($amulet['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label" for="category_id">หมวดหมู่</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)$amulet['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="type">รูปแบบการจำหน่าย</label>
                        <select id="type" name="type" class="form-control">
                            <option value="fixed" <?= ($amulet['type'] === 'fixed') ? 'selected' : '' ?>>เช่าบูชาปกติ (Direct Sale)</option>
                            <option value="auction" <?= ($amulet['type'] === 'auction') ? 'selected' : '' ?>>เปิดประมูล (Auction)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label" for="monk_name">พระเกจิอาจารย์ *</label>
                        <input type="text" id="monk_name" name="monk_name" class="form-control" value="<?= htmlspecialchars($amulet['monk_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="temple_name">วัด *</label>
                        <input type="text" id="temple_name" name="temple_name" class="form-control" value="<?= htmlspecialchars($amulet['temple_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="year_made">ปีที่สร้าง (พ.ศ.)</label>
                        <input type="text" id="year_made" name="year_made" class="form-control" placeholder="เช่น 2497" value="<?= htmlspecialchars($amulet['year_made'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price">ราคาเช่าบูชา / ราคาเริ่มต้นประมูล (บาท) *</label>
                    <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" value="<?= htmlspecialchars((string)$amulet['price'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="image">รูปภาพพระเครื่อง (JPG, PNG, WEBP ไม่เกิน 5MB)</label>
                    <?php if (!empty($amulet['image_url'])): ?>
                        <div style="margin-bottom: 0.75rem;">
                            <img src="../<?= htmlspecialchars($amulet['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-height: 120px; border-radius: 4px; border: 1px solid var(--border-gold);">
                            <div style="font-size: 0.8rem; color: var(--text-light); opacity: 0.8;">รูปภาพปัจจุบัน</div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">รายละเอียดและประวัติพระเครื่อง</label>
                    <textarea id="description" name="description" rows="4" class="form-control"><?= htmlspecialchars($amulet['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <h3 style="border-bottom: 1px solid var(--border-gold); padding-bottom: 0.5rem; margin: 2rem 0 1.25rem;">2. กำหนดค่าสแตตัสพุทธคุณ 6 ด้าน (Module 3: คะแนน 1 - 10)</h3>
                <p class="text-light" style="margin-bottom: 1rem; font-size: 0.9rem;">
                    ค่าคะแนนเหล่านี้จะถูกนำไปใช้วาดกราฟหกเหลี่ยม Radar Chart เพื่อให้ผู้เช่ามองเห็นคุณลักษณะความศักดิ์สิทธิ์ได้ชัดเจน
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; background: #350107; padding: 1.25rem; border-radius: 6px; border: 1px solid var(--border-gold);">
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="protection">พุทธคุณ (Protection)</label>
                        <input type="number" min="1" max="10" id="protection" name="protection" class="form-control" value="<?= (int)($stats['protection'] ?? 5) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="popularity">เมตตามหานิยม (Kindness)</label>
                        <input type="number" min="1" max="10" id="popularity" name="popularity" class="form-control" value="<?= (int)($stats['popularity'] ?? 5) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="charm">มหาเสน่ห์ (Charm)</label>
                        <input type="number" min="1" max="10" id="charm" name="charm" class="form-control" value="<?= (int)($stats['charm'] ?? 5) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="evasion">แคล้วคลาด (Evasion)</label>
                        <input type="number" min="1" max="10" id="evasion" name="evasion" class="form-control" value="<?= (int)($stats['evasion'] ?? 5) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="invincibility">คงกระพันชาตรี (Invincibility)</label>
                        <input type="number" min="1" max="10" id="invincibility" name="invincibility" class="form-control" value="<?= (int)($stats['invincibility'] ?? 5) ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="wealth">โชคลาภ (Wealth)</label>
                        <input type="number" min="1" max="10" id="wealth" name="wealth" class="form-control" value="<?= (int)($stats['wealth'] ?? 5) ?>" required>
                    </div>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-gold" style="flex: 1; padding: 0.85rem;">บันทึกข้อมูลพระเครื่อง</button>
                    <a href="amulets.php" class="btn btn-outline-gold" style="padding: 0.85rem 1.5rem;">ยกเลิก</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Amulet Siam Admin CMS Panel.</p>
        </div>
    </footer>
</body>
</html>

