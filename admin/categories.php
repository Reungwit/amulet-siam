<?php
/**
 * Admin Category CRUD Management
 * 
 * Lists, creates, and updates amulet categories.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_guard.php';
$admin_user = require_admin();

require_once __DIR__ . '/../includes/db_connect.php';

$pdo = get_db_connection();
$errors = [];
$success_msg = '';

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_category = null;

if ($edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $edit_id]);
    $edit_category = $stmt->fetch();
}

// Process Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'กรุณากรอกชื่อหมวดหมู่';
    }
    if (empty($slug)) {
        // Auto-generate slug from name if empty
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(str_replace(' ', '-', $name))) ?: 'cat-' . time();
    }

    if (empty($errors)) {
        try {
            if ($cat_id > 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id");
                $stmt->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'id' => $cat_id
                ]);
                $success_msg = 'อัปเดตข้อมูลหมวดหมู่เรียบร้อยแล้ว';
                $edit_category = null;
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)");
                $stmt->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                ]);
                $success_msg = 'เพิ่มหมวดหมู่ใหม่สำเร็จ';
            }
        } catch (PDOException $e) {
            error_log('[CATEGORY ERROR] ' . $e->getMessage());
            $errors[] = 'เกิดข้อผิดพลาด: ชื่อหมวดหมู่หรือ Slug อาจซ้ำกับที่มีอยู่แล้ว';
        }
    }
}

// Fetch categories along with amulet counts
$categories = $pdo->query("SELECT c.*, COUNT(a.id) AS total_amulets 
                          FROM categories c 
                          LEFT JOIN amulets a ON c.id = a.category_id 
                          GROUP BY c.id 
                          ORDER BY c.id ASC")->fetchAll();

$page_title = 'จัดการหมวดหมู่พระเครื่อง (Categories)';
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
                    <li><a href="categories.php" class="nav-link active">หมวดหมู่</a></li>
                    <li><a href="orders.php" class="nav-link">ตรวจสอบสลิป</a></li>
                    <li><a href="../logout.php" class="btn btn-outline-gold">ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h1>จัดการหมวดหมู่พระเครื่อง</h1>
                <a href="amulets.php" class="btn btn-outline-gold">&larr; ไปยังระบบจัดการพระเครื่อง</a>
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

            <?php if ($success_msg): ?>
                <div style="background: rgba(42, 157, 143, 0.2); border: 1px solid var(--success); color: #7fe3d7; padding: 0.75rem 1rem; border-radius: 4px; margin-bottom: 1.25rem;">
                    <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 2rem;">
                <!-- Category Add / Edit Form -->
                <div class="card" style="padding: 1.5rem;">
                    <h3><?= $edit_category ? 'แก้ไขหมวดหมู่' : '+ เพิ่มหมวดหมู่ใหม่' ?></h3>
                    <form method="POST" action="categories.php" style="margin-top: 1rem;">
                        <input type="hidden" name="category_id" value="<?= (int)($edit_category['id'] ?? 0) ?>">

                        <div class="form-group">
                            <label class="form-label" for="name">ชื่อหมวดหมู่ *</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($edit_category['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required placeholder="เช่น พระสมเด็จ, พระกรุ">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="slug">Slug (URL identifier)</label>
                            <input type="text" id="slug" name="slug" class="form-control" value="<?= htmlspecialchars($edit_category['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="เช่น somdej, kru">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">คำอธิบายหมวดหมู่</label>
                            <textarea id="description" name="description" rows="3" class="form-control"><?= htmlspecialchars($edit_category['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button type="submit" class="btn btn-gold" style="flex: 1;">
                                <?= $edit_category ? 'บันทึกการแก้ไข' : '+ เพิ่มหมวดหมู่' ?>
                            </button>
                            <?php if ($edit_category): ?>
                                <a href="categories.php" class="btn btn-outline-gold">ยกเลิก</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Category List Table -->
                <div class="card" style="padding: 1.5rem;">
                    <h3>หมวดหมู่ที่มีอยู่ในระบบ (<?= count($categories) ?> หมวด)</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-gold); text-align: left; color: var(--gold-accent);">
                                <th style="padding: 0.6rem 0.5rem;">ชื่อหมวดหมู่</th>
                                <th style="padding: 0.6rem 0.5rem;">Slug</th>
                                <th style="padding: 0.6rem 0.5rem; text-align: center;">จำนวนพระ</th>
                                <th style="padding: 0.6rem 0.5rem; text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr style="border-bottom: 1px solid rgba(233, 215, 147, 0.15);">
                                    <td style="padding: 0.75rem 0.5rem;">
                                        <strong><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--text-light); opacity: 0.8;">
                                            <?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; color: var(--text-light); font-size: 0.85rem;">
                                        <?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center; color: var(--gold-accent); font-weight: 700;">
                                        <?= $cat['total_amulets'] ?>
                                    </td>
                                    <td style="padding: 0.75rem 0.5rem; text-align: center;">
                                        <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn btn-outline-gold" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">แก้ไข</a>
                                        <?php if ((int)$cat['total_amulets'] === 0): ?>
                                            <a href="category_delete.php?id=<?= $cat['id'] ?>" class="btn btn-outline-gold" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; border-color: var(--danger); color: #ff8b94;" onclick="return confirm('ยืนยันการลบหมวดหมู่นี้?');">ลบ</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

