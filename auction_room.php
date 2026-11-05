<?php
/**
 * Interactive Real-Time Auction Bidding Room
 * 
 * Features live countdown, HTML5 Canvas 6-point Radar Chart,
 * AJAX-based bidding with Anti-Snipe protection, and real-time polling bid history.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db_connect.php';

// Support POST (preferred) or GET fallback
$auction_id = (int)($_POST['auction_id'] ?? $_GET['id'] ?? 0);

if ($auction_id <= 0) {
    header('Location: auctions.php');
    exit;
}

$pdo = get_db_connection();

// Fetch auction and amulet details with spiritual stats
$stmt = $pdo->prepare("SELECT a.*, am.title, am.monk_name, am.temple_name, am.year_made, am.image_url, am.description,
                              c.name AS category_name,
                              s.protection, s.popularity, s.charm, s.evasion, s.invincibility, s.wealth
                       FROM auctions a
                       JOIN amulets am ON a.amulet_id = am.id
                       LEFT JOIN categories c ON am.category_id = c.id
                       LEFT JOIN amulet_stats s ON am.id = s.amulet_id
                       WHERE a.id = :id LIMIT 1");
$stmt->execute(['id' => $auction_id]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: auctions.php?msg=not_found');
    exit;
}

$stats = [
    'protection' => (int)($auction['protection'] ?? 5),
    'popularity' => (int)($auction['popularity'] ?? 5),
    'charm' => (int)($auction['charm'] ?? 5),
    'evasion' => (int)($auction['evasion'] ?? 5),
    'invincibility' => (int)($auction['invincibility'] ?? 5),
    'wealth' => (int)($auction['wealth'] ?? 5),
];

$current_user = $_SESSION['user'] ?? null;
$next_min_bid = (float)$auction['current_price'] + (float)$auction['min_bid_increment'];

$page_title = 'ห้องประมูล: ' . $auction['title'];
$extra_scripts = ['js/radar.js', 'js/auction.js'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<nav style="margin-bottom: 1.5rem; font-size: 0.9rem;">
    <a href="index.php" style="color: var(--text-light); opacity: 0.8;">หน้าแรก</a> &gt; 
    <a href="auctions.php" style="color: var(--text-light); opacity: 0.8;">ห้องประมูลสด</a> &gt; 
    <span style="color: var(--gold-accent);"><?= htmlspecialchars($auction['title'], ENT_QUOTES, 'UTF-8') ?></span>
</nav>

<!-- Anti-Snipe Toast Notification -->
<div id="snipeAlert" style="display: none; background: #e09f3e; color: #1c0003; padding: 1rem; border-radius: 6px; font-weight: bold; text-align: center; margin-bottom: 1.5rem; box-shadow: 0 4px 15px rgba(224, 159, 62, 0.4);">
</div>

<div class="detail-layout">
    <!-- Left Column: Image & Radar Chart -->
    <div>
        <div class="amulet-gallery">
            <?php if (!empty($auction['image_url'])): ?>
                <img src="<?= htmlspecialchars($auction['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($auction['title'], ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <div style="height: 320px; background: #2a0106; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: var(--gold-accent);">✦</div>
            <?php endif; ?>
        </div>

        <!-- Radar Chart Container -->
        <div class="chart-container" style="flex-direction: column;">
            <h3>สแตตัสพุทธคุณ 6 ด้าน (Radar Chart)</h3>
            <p class="text-light" style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 0.75rem;">
                คุณลักษณะความศักดิ์สิทธิ์ประจำองค์พระ
            </p>
            <canvas id="statChart" class="status-radar" width="400" height="400"></canvas>
        </div>
    </div>

    <!-- Right Column: Live Auction Clock & Bidding Console -->
    <div>
        <div class="card" style="padding: 1.75rem; border-color: var(--gold-accent);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <span class="badge badge-auction">ประมูลสด (Live)</span>
                <span style="color: var(--gold-accent); font-size: 0.85rem;">หมวดหมู่: <?= htmlspecialchars($auction['category_name'] ?: 'ทั่วไป', ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <h1 style="font-size: 1.8rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($auction['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="card-meta">
                <?= htmlspecialchars($auction['monk_name'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($auction['temple_name'], ENT_QUOTES, 'UTF-8') ?>
                <?php if (!empty($auction['year_made'])): ?>
                    (ปี <?= htmlspecialchars($auction['year_made'], ENT_QUOTES, 'UTF-8') ?>)
                <?php endif; ?>
            </p>

            <!-- Countdown Timer Clock -->
            <div style="background: #2a0106; border: 2px solid var(--border-gold); border-radius: 8px; padding: 1.25rem; text-align: center; margin: 1.25rem 0;">
                <div style="font-size: 0.9rem; color: var(--text-light); opacity: 0.85; margin-bottom: 0.25rem;">
                    ⏱ เวลานับถอยหลังสิ้นสุดการประมูล:
                </div>
                <div id="auctionCountdown" style="font-size: 2.2rem; font-weight: 800; letter-spacing: 2px; color: var(--gold-accent);">
                    --:--:--
                </div>
                <div style="font-size: 0.75rem; color: var(--gold-accent); margin-top: 0.35rem;">
                    * ระบบมี Anti-Snipe: เคาะ 1 นาทีสุดท้าย เวลาจะเพิ่มให้อัตโนมัติ 3 นาที
                </div>
            </div>

            <!-- Price Display -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: #350107; padding: 1rem; border-radius: 6px; border: 1px solid var(--border-gold);">
                    <div style="font-size: 0.8rem; color: var(--text-light); opacity: 0.8;">ราคาเริ่มต้น:</div>
                    <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-light);">
                        ฿<?= number_format((float)$auction['start_price'], 2) ?>
                    </div>
                </div>

                <div style="background: #350107; padding: 1rem; border-radius: 6px; border: 2px solid var(--gold-accent);">
                    <div style="font-size: 0.8rem; color: var(--gold-accent);">ราคาสูงสุดขณะนี้:</div>
                    <div id="currentAuctionPrice" style="font-size: 1.4rem; font-weight: 800; color: var(--gold-accent);">
                        ฿<?= number_format((float)$auction['current_price'], 2) ?>
                    </div>
                </div>
            </div>

            <!-- Bidding Action Console -->
            <?php if ($current_user): ?>
                <div id="bidFeedback" style="display: none; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.95rem;"></div>

                <form id="bidForm" style="background: #380108; padding: 1.25rem; border-radius: 6px; border: 1px solid var(--border-gold);">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="bidAmountInput">
                            เสนอราคาประมูล (บาท) *
                            <span style="font-size: 0.8rem; font-weight: normal; color: var(--gold-accent);">
                                (ขั้นต่ำเพิ่มครั้งละ ฿<?= number_format((float)$auction['min_bid_increment'], 2) ?>)
                            </span>
                        </label>
                        <input type="number" id="bidAmountInput" step="100" min="<?= $next_min_bid ?>" value="<?= $next_min_bid ?>" class="form-control" style="font-size: 1.2rem; font-weight: bold; color: var(--gold-accent);" required>
                    </div>

                    <button type="submit" id="btnSubmitBid" class="btn btn-gold" style="width: 100%; padding: 0.9rem; font-size: 1.1rem;">
                        🔨 เคาะราคาประมูล
                    </button>
                </form>
            <?php else: ?>
                <div style="background: #350107; border: 1px solid var(--border-gold); border-radius: 6px; padding: 1.5rem; text-align: center;">
                    <p class="text-light" style="margin-bottom: 1rem;">กรุณาเข้าสู่ระบบก่อนทำการเคาะราคาประมูล</p>
                    <a href="login.php?redirect=auctions.php" class="btn btn-gold">เข้าสู่ระบบเพื่อร่วมประมูล</a>
                </div>
            <?php endif; ?>

            <!-- Live Bid History Table -->
            <div style="margin-top: 2rem;">
                <h3 style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>ประวัติการเคาะราคา (เรียลไทม์)</span>
                    <span style="font-size: 0.75rem; font-weight: normal; color: var(--success); display: flex; align-items: center; gap: 0.35rem;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></span> สด
                    </span>
                </h3>

                <div style="background: #2a0106; border: 1px solid var(--border-gold); border-radius: 6px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-gold); background: #350107; color: var(--gold-accent);">
                                <th style="padding: 0.6rem 0.5rem;">ผู้เสนอราคา</th>
                                <th style="padding: 0.6rem 0.5rem;">ราคาเสนอ</th>
                                <th style="padding: 0.6rem 0.5rem;">เวลา</th>
                            </tr>
                        </thead>
                        <tbody id="bidHistoryBody">
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 1.5rem; opacity: 0.7;">
                                    กำลังโหลดประวัติราคา...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Description -->
            <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-gold); padding-top: 1.25rem;">
                <h4 style="margin-bottom: 0.5rem;">ประวัติและความเป็นมา</h4>
                <p class="text-light" style="font-size: 0.95rem; line-height: 1.7; opacity: 0.9; white-space: pre-line;">
                    <?= htmlspecialchars($auction['description'] ?: 'ไม่มีข้อมูลรายละเอียดเพิ่มเติมสำหรับพระเครื่ององค์นี้', ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize 6-Point Radar Chart
    const rawStats = <?= json_encode($stats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    render_amulet_radar_chart('statChart', rawStats);

    // 2. Initialize Real-Time Auction Engine
    AmuletAuction.initRoom(
        <?= (int)$auction['id'] ?>,
        "<?= date('Y-m-d\TH:i:s+07:00', strtotime($auction['end_time'])) ?>",
        <?= (float)$auction['current_price'] ?>,
        <?= (float)$auction['min_bid_increment'] ?>
    );
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

