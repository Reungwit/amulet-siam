<?php
/**
 * Frontend Amulet Catalog Page
 * 
 * Displays available amulets with category filtering and keyword searching via POST.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/repository.php';

$repo = get_amulet_repository();

// Process POST filters (with fallback support for GET if needed)
$search = trim($_POST['search'] ?? $_GET['search'] ?? '');
$category_id = (int)($_POST['category_id'] ?? $_GET['category_id'] ?? 0);
$type_filter = trim($_POST['type'] ?? $_GET['type'] ?? '');

// Fetch all categories for filter dropdown via Repository (Firestore / DB)
$categories = $repo->getCategories();

// Fetch available amulets with search & filters via Repository
$amulets = $repo->getAvailableAmulets($search, $category_id, $type_filter);

$page_title = 'ศูนย์รวมพระเครื่องยอดนิยม - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="hero-section text-center" style="padding: 2.5rem 0; margin-bottom: 2rem; border-bottom: 1px solid var(--border-gold);">
    <h1 style="font-size: 2.4rem;">ศูนย์รวมพระแท้และห้องประมูลพระเครื่องยอดนิยม</h1>
    <p class="text-light" style="max-width: 680px; margin: 0.75rem auto 1.5rem; font-size: 1.05rem;">
        คัดสรรพระเกจิอาจารย์ชื่อดังทั่วสยาม รับประกันความแท้ 100% พร้อมระบบตรวจวัดคุณลักษณะสแตตัสพุทธคุณ 6 ด้าน (Hexagonal Radar Chart)
    </p>
</section>

<!-- Filter & Search Bar using POST -->
<section class="filter-bar">
    <form method="POST" action="index.php" class="filter-grid">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="search">ค้นหาพระเครื่อง / เกจิอาจารย์ / วัด</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="พิมพ์ชื่อพระเครื่อง หรือ วัดที่ต้องการ..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="category_id">หมวดหมู่พระเครื่อง</label>
            <select id="category_id" name="category_id" class="form-control">
                <option value="0">-- ทุกหมวดหมู่ --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="type">ประเภท</label>
            <select id="type" name="type" class="form-control">
                <option value="">ทั้งหมด</option>
                <option value="fixed" <?= ($type_filter === 'fixed') ? 'selected' : '' ?>>เช่าบูชาปกติ</option>
                <option value="auction" <?= ($type_filter === 'auction') ? 'selected' : '' ?>>เปิดประมูล</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-gold" style="width: 100%; height: 42px;">ค้นหา</button>
        </div>
    </form>
</section>

<!-- Product Catalog Grid -->
<section>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h2>รายการวัตถุมงคล (<?= count($amulets) ?> องค์)</h2>
        <?php if (!empty($search) || $category_id > 0 || !empty($type_filter)): ?>
            <a href="index.php" class="btn btn-outline-gold" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">ล้างตัวกรอง</a>
        <?php endif; ?>
    </div>

    <?php if (empty($amulets)): ?>
        <div class="card" style="padding: 3rem; text-align: center;">
            <p class="text-light" style="font-size: 1.1rem; margin-bottom: 1rem;">ไม่พบรายการพระเครื่องที่ตรงกับเงื่อนไขการค้นหา</p>
            <a href="index.php" class="btn btn-gold">แสดงพระเครื่องทั้งหมด</a>
        </div>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($amulets as $item): ?>
                <div class="card">
                    <div class="card-img-wrap">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <span style="font-size: 3rem; color: var(--gold-accent); opacity: 0.6;">✦</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <?php if ($item['type'] === 'auction'): ?>
                                <span class="badge badge-auction">เปิดประมูล</span>
                            <?php else: ?>
                                <span class="badge badge-fixed">เช่าบูชาปกติ</span>
                            <?php endif; ?>
                            <span style="font-size: 0.8rem; color: var(--gold-accent);"><?= htmlspecialchars($item['category_name'] ?: 'ทั่วไป', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <h3 class="card-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="card-meta">
                            <?= htmlspecialchars($item['monk_name'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($item['temple_name'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($item['year_made'])): ?>
                                (พ.ศ. <?= htmlspecialchars($item['year_made'], ENT_QUOTES, 'UTF-8') ?>)
                            <?php endif; ?>
                        </p>

                        <div class="card-price">
                            <?= ($item['type'] === 'auction') ? 'ราคาเริ่มต้น: ' : 'บูชา: ' ?>
                            ฿<?= number_format((float)$item['price'], 2) ?>
                        </div>

                        <div style="margin-top: 1rem;">
                            <form method="POST" action="amulet.php">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn-outline-gold" style="width: 100%;">ดูรายละเอียดและสแตตัส &rarr;</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
