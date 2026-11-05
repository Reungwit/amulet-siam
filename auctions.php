<?php
/**
 * Real-time Auction Listings Page (100% Firebase Firestore)
 * 
 * Displays all active and upcoming auctions from Firestore with live countdown timers.
 * 
 * @package AmuletPlatform
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/firestore_client.php';
require_once __DIR__ . '/includes/repository.php';

$fs = get_firestore();
$repo = get_amulet_repository();

// Fetch all auctions from Firestore
$all_auctions = $fs->listDocuments('auctions');
$now_ts = time();
$active_auctions = [];

foreach ($all_auctions as $auc) {
    $end_ts = strtotime($auc['end_time'] ?? '');
    $status = $auc['status'] ?? 'active';

    // Auto mark as ended in Firestore if time passed
    if ($status === 'active' && $end_ts <= $now_ts) {
        $fs->updateDocument('auctions', (string)$auc['id'], ['status' => 'ended']);
        $status = 'ended';
    }

    if ($status === 'active') {
        // Attach amulet details
        $amulet = $repo->getAmuletById((string)($auc['amulet_id'] ?? ''));
        $auc['amulet'] = $amulet ?: [];
        $active_auctions[] = $auc;
    }
}

// Sort by ending soonest
usort($active_auctions, function($a, $b) {
    return strtotime($a['end_time'] ?? '') - strtotime($b['end_time'] ?? '');
});

$page_title = 'ห้องประมูลพระเครื่องยอดนิยม - Amulet Siam';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-section text-center" style="padding: 2rem 0; margin-bottom: 2rem; border-bottom: 1px solid var(--border-gold);">
    <h1 style="font-size: 2.2rem;">ห้องประมูลพระเครื่องเรียลไทม์ (Live Auction)</h1>
    <p class="text-light" style="max-width: 650px; margin: 0.5rem auto 1rem;">
        ร่วมประมูลพระแท้โปร่งใส เคาะราคาแบบไม่โหลดหน้าใหม่ พร้อมระบบต่อเวลาอัตโนมัติ (Anti-Snipe +3 นาที) ป้องกันการปาดหน้าวินาทีสุดท้าย
    </p>
</section>

<div style="margin-bottom: 2rem;">
    <h2>รายการพระเครื่องที่กำลังเปิดประมูลสด (<?= count($active_auctions) ?> รายการ)</h2>

    <?php if (empty($active_auctions)): ?>
        <div class="card" style="padding: 3.5rem; text-align: center; margin-top: 1.5rem;">
            <p class="text-light" style="font-size: 1.15rem; margin-bottom: 1rem;">ขณะนี้ยังไม่มีรายการพระเครื่องที่เปิดประมูล</p>
            <p class="text-light" style="opacity: 0.7; font-size: 0.9rem; margin-bottom: 1.5rem;">ผู้ดูแลระบบจะทำการเปิดรอบประมูลใหม่เร็วๆ นี้</p>
            <a href="index.php" class="btn btn-gold">เลือกชมพระเครื่องเช่าบูชาปกติ &rarr;</a>
        </div>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($active_auctions as $auc): ?>
                <?php $am = $auc['amulet']; ?>
                <div class="card">
                    <div class="card-img-wrap" style="position: relative;">
                        <?php if (!empty($am['image_url'])): ?>
                            <img src="<?= htmlspecialchars($am['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($am['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <span style="font-size: 3rem; color: var(--gold-accent);">✦</span>
                        <?php endif; ?>
                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0, 0, 0, 0.75); border: 1px solid var(--danger); color: #ff8b94; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                            ● กำลังประมูล
                        </div>
                    </div>

                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem; font-size: 0.8rem;">
                            <span style="color: var(--gold-accent);"><?= htmlspecialchars($am['monk_name'] ?? 'พระเกจิ', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <h3 class="card-title"><?= htmlspecialchars($am['title'] ?? 'พระเครื่องเปิดประมูล', ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="card-meta">
                            <?= htmlspecialchars($am['temple_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <!-- Countdown Bar on Card -->
                        <div style="background: #350107; padding: 0.6rem; border-radius: 4px; border: 1px solid var(--border-gold); text-align: center; margin: 0.75rem 0;">
                            <div style="font-size: 0.75rem; color: var(--text-light); opacity: 0.8;">เวลาคงเหลือ:</div>
                            <div id="timer_<?= $auc['id'] ?>" style="font-size: 1.1rem; font-weight: bold; color: var(--gold-accent);">
                                กำลังคำนวณ...
                            </div>
                        </div>

                        <div class="card-price">
                            ราคาสูงสุด: ฿<?= number_format((float)($auc['current_price'] ?? 0), 2) ?>
                        </div>

                        <!-- Submit via POST to room -->
                        <form method="POST" action="auction_room.php" style="margin-top: 1rem;">
                            <input type="hidden" name="auction_id" value="<?= htmlspecialchars((string)$auc['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn btn-gold" style="width: 100%;">
                                🔨 เข้าสู่ห้องเคาะราคาประมูล &rarr;
                            </button>
                        </form>
                    </div>
                </div>

                <script>
                    (function() {
                        const targetElem = document.getElementById('timer_<?= $auc['id'] ?>');
                        const endMs = new Date("<?= date('Y-m-d\TH:i:s+07:00', strtotime($auc['end_time'] ?? '')) ?>").getTime();

                        function updateCardTimer() {
                            const diff = endMs - Date.now();
                            if (diff <= 0) {
                                targetElem.textContent = 'สิ้นสุดเวลาแล้ว';
                                targetElem.classList.add('text-danger');
                                return;
                            }
                            const h = Math.floor(diff / (1000 * 60 * 60));
                            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                            const s = Math.floor((diff % (1000 * 60)) / 1000);
                            targetElem.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                        }
                        updateCardTimer();
                        setInterval(updateCardTimer, 1000);
                    })();
                </script>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
