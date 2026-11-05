<?php
/**
 * Amulet Detail View
 * 
 * Displays full sacred provenance, high-resolution photo, action triggers,
 * and the 6-point HTML5 Canvas Radar Chart Graphic.
 * Accepts request via POST (preferred) or GET fallback.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/repository.php';

// Accept ID via POST (preferred per user request) with GET fallback
$amulet_id = $_POST['id'] ?? $_GET['id'] ?? '';

if (empty($amulet_id)) {
    header('Location: index.php');
    exit;
}

$repo = get_amulet_repository();
$amulet = $repo->getAmuletById($amulet_id);

if (!$amulet) {
    header('Location: index.php?msg=not_found');
    exit;
}

$stats = [
    'protection' => (int)($amulet['stats']['protection'] ?? $amulet['protection'] ?? 5),
    'popularity' => (int)($amulet['stats']['popularity'] ?? $amulet['popularity'] ?? 5),
    'charm' => (int)($amulet['stats']['charm'] ?? $amulet['charm'] ?? 5),
    'evasion' => (int)($amulet['stats']['evasion'] ?? $amulet['evasion'] ?? 5),
    'invincibility' => (int)($amulet['stats']['invincibility'] ?? $amulet['invincibility'] ?? 5),
    'wealth' => (int)($amulet['stats']['wealth'] ?? $amulet['wealth'] ?? 5),
];

$page_title = $amulet['title'] . ' - สยามพระเครื่อง';
$extra_scripts = ['js/radar.js'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<nav style="margin-bottom: 1.5rem; font-size: 0.9rem;">
    <a href="index.php" style="color: var(--text-light); opacity: 0.8;">หน้าแรก</a> &gt; 
    <span style="color: var(--text-light); opacity: 0.8;"><?= htmlspecialchars($amulet['category_name'] ?: 'หมวดหมู่ทั่วไป', ENT_QUOTES, 'UTF-8') ?></span> &gt; 
    <span style="color: var(--gold-accent);"><?= htmlspecialchars($amulet['title'], ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<div class="detail-layout">
    <!-- Left Column: Photo & Radar Chart -->
    <div>
        <div class="amulet-gallery">
            <?php if (!empty($amulet['image_url'])): ?>
                <img src="<?= htmlspecialchars($amulet['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($amulet['title'], ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <div style="height: 350px; background: #2a0106; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: var(--gold-accent);">✦</div>
            <?php endif; ?>
        </div>

        <!-- Radar Chart Container -->
        <div class="chart-container" style="flex-direction: column;">
            <h3 style="margin-bottom: 0.25rem;">กราฟแสดงสแตตัสพุทธคุณ 6 ด้าน</h3>
            <p class="text-light" style="font-size: 0.85rem; opacity: 0.85; margin-bottom: 1rem;">
                ระดับความศักดิ์สิทธิ์และคุณลักษณะตามตำราโบราณ (คะแนน 1 - 10)
            </p>

            <canvas id="statChart" class="status-radar" width="400" height="400"></canvas>

            <!-- Stat Breakdown Pills -->
            <div class="stats-grid-pills" style="width: 100%;">
                <div class="stat-pill">
                    <span class="stat-pill-label">พุทธคุณ:</span>
                    <span class="stat-pill-score"><?= $stats['protection'] ?> / 10</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-label">เมตตามหานิยม:</span>
                    <span class="stat-pill-score"><?= $stats['popularity'] ?> / 10</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-label">มหาเสน่ห์:</span>
                    <span class="stat-pill-score"><?= $stats['charm'] ?> / 10</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-label">แคล้วคลาด:</span>
                    <span class="stat-pill-score"><?= $stats['evasion'] ?> / 10</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-label">คงกระพันชาตรี:</span>
                    <span class="stat-pill-score"><?= $stats['invincibility'] ?> / 10</span>
                </div>
                <div class="stat-pill">
                    <span class="stat-pill-label">โชคลาภ:</span>
                    <span class="stat-pill-score"><?= $stats['wealth'] ?> / 10</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Provenance & Purchase Actions -->
    <div>
        <div class="card" style="padding: 2rem;">
            <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                <?php if ($amulet['type'] === 'auction'): ?>
                    <span class="badge badge-auction">เปิดประมูลสด</span>
                <?php else: ?>
                    <span class="badge badge-fixed">เช่าบูชาทันที</span>
                <?php endif; ?>
                <span style="font-size: 0.85rem; color: var(--gold-accent);">หมวดหมู่: <?= htmlspecialchars($amulet['category_name'] ?: 'ทั่วไป', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($amulet['title'], ENT_QUOTES, 'UTF-8') ?></h1>

            <div style="font-size: 1.8rem; font-weight: 700; color: var(--gold-accent); margin: 1rem 0; padding: 0.75rem 1rem; background: #350107; border: 1px solid var(--border-gold); border-radius: 6px;">
                <?= ($amulet['type'] === 'auction') ? 'ราคาเริ่มต้น: ' : 'ราคาเช่าบูชา: ' ?>
                ฿<?= number_format((float)$amulet['price'], 2) ?>
            </div>

            <!-- Provenance Metadata Table -->
            <table class="provenance-table">
                <tbody>
                    <tr>
                        <th>พระเกจิอาจารย์</th>
                        <td><?= htmlspecialchars($amulet['monk_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th>วัดที่ประกอบพิธี</th>
                        <td><?= htmlspecialchars($amulet['temple_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th>ปีที่จัดสร้าง</th>
                        <td><?= htmlspecialchars($amulet['year_made'] ?: 'ไม่ระบุ', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <th>การรับประกัน</th>
                        <td>รับประกันพระแท้ตลอดชีพ 100%</td>
                    </tr>
                </tbody>
            </table>

            <!-- Action Trigger / CTAs -->
            <div style="margin: 1.5rem 0;">
                <?php if ($amulet['type'] === 'auction'): ?>
                    <form method="POST" action="auction_room.php">
                        <input type="hidden" name="amulet_id" value="<?= (int)$amulet['id'] ?>">
                        <button type="submit" class="btn btn-gold" style="width: 100%; padding: 0.9rem; font-size: 1.1rem;">
                            🔨 เข้าสู่ห้องประมูลสดองค์นี้
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="cart.php">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="amulet_id" value="<?= (int)$amulet['id'] ?>">
                        <button type="submit" class="btn btn-gold" style="width: 100%; padding: 0.9rem; font-size: 1.1rem;">
                            🛒 เช่าบูชา (เพิ่มลงในตะกร้า)
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- History & Description -->
            <div style="margin-top: 2rem; border-top: 1px solid var(--border-gold); padding-top: 1.5rem;">
                <h3>ประวัติและความเป็นมา</h3>
                <div style="color: var(--text-light); line-height: 1.8; margin-top: 0.75rem; white-space: pre-line;">
                    <?= htmlspecialchars($amulet['description'] ?: 'ไม่มีข้อมูลรายละเอียดเพิ่มเติมสำหรับพระเครื่ององค์นี้', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rawStats = <?= json_encode($stats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    render_amulet_radar_chart('statChart', rawStats);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

