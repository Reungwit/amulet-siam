/**
 * Real-time Auction Engine
 * 
 * Manages live countdown clocks, Fetch API POST bidding with Anti-Snipe handling,
 * and background status polling.
 * 
 * @package AmuletPlatform
 */

'use strict';

/**
 * Global Auction Controller
 */
const AmuletAuction = {
    timerInterval: null,
    pollingInterval: null,
    endTimeMs: 0,
    auctionId: 0,

    /**
     * Parse date string into UTC milliseconds, ensuring +07:00 timezone if not explicitly provided.
     */
    parseDateToMs(dateStr) {
        if (!dateStr) return 0;
        let s = dateStr.trim();
        // If MySQL datetime 'YYYY-MM-DD HH:MM:SS', convert to ISO with +07:00
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(s)) {
            s = s.replace(' ', 'T') + '+07:00';
        } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(s)) {
            s = s + '+07:00';
        }
        return new Date(s).getTime();
    },

    /**
     * Initialize the interactive auction room.
     * 
     * @param {number} auctionId - Auction record primary ID
     * @param {string} initialEndTime - End time in ISO/MySQL date format
     * @param {number} currentPrice - Current highest price
     * @param {number} minIncrement - Minimum bid step
     */
    initRoom(auctionId, initialEndTime, currentPrice, minIncrement) {
        this.auctionId = auctionId;
        this.endTimeMs = this.parseDateToMs(initialEndTime);

        this.startCountdown();
        this.startPolling();
        this.bindBidForm();

        console.log(`[AUCTION] Room initialized for auction #${auctionId} with end time: ${initialEndTime} (${this.endTimeMs})`);
    },

    /**
     * Start high-precision 1-second countdown clock.
     */
    startCountdown() {
        const timerElem = document.getElementById('auctionCountdown');
        const alertBox = document.getElementById('snipeAlert');

        const updateClock = () => {
            const now = Date.now();
            const diff = this.endTimeMs - now;

            if (diff <= 0) {
                if (timerElem) {
                    timerElem.textContent = '00:00:00 (สิ้นสุดการประมูล)';
                    timerElem.classList.add('text-danger');
                }
                const bidBtn = document.getElementById('btnSubmitBid');
                if (bidBtn) {
                    bidBtn.disabled = true;
                    bidBtn.textContent = 'การประมูลปิดแล้ว';
                }
                clearInterval(this.timerInterval);
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            if (timerElem) {
                timerElem.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Warning color if under 1 minute
                if (diff <= 60000) {
                    timerElem.style.color = 'var(--danger)';
                } else {
                    timerElem.style.color = 'var(--gold-accent)';
                }
            }
        };

        updateClock();
        this.timerInterval = setInterval(updateClock, 1000);
    },

    /**
     * Attach AJAX POST submission to the bidding form.
     */
    bindBidForm() {
        const form = document.getElementById('bidForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const bidInput = document.getElementById('bidAmountInput');
            const submitBtn = document.getElementById('btnSubmitBid');
            const feedbackElem = document.getElementById('bidFeedback');

            const amount = parseFloat(bidInput.value);
            if (isNaN(amount) || amount <= 0) {
                this.showFeedback('กรุณาระบุจำนวนเงินประมูลที่ถูกต้อง', 'danger');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'กำลังส่งราคาประมูล...';

            try {
                // Submit via POST using Fetch API
                const response = await fetch('api/place_bid.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        auction_id: this.auctionId,
                        bid_amount: amount,
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.showFeedback(`✓ ${data.message} ราคาสูงสุดขณะนี้: ${data.formatted_price}`, 'success');

                    // If anti-snipe extended the timer, update local clock
                    if (data.anti_snipe && data.new_end_time) {
                        this.endTimeMs = this.parseDateToMs(data.new_end_time);
                        this.triggerAntiSnipeNotification();
                    }

                    // Poll immediately to refresh list
                    this.pollStatus();
                } else {
                    this.showFeedback(`✕ ${data.message}`, 'danger');
                }
            } catch (err) {
                console.error('[BID AJAX ERROR]', err);
                this.showFeedback('เกิดข้อผิดพลาดในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง', 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🔨 เคาะราคาประมูล';
            }
        });
    },

    /**
     * Start polling status and bid history every 3 seconds via POST.
     */
    startPolling() {
        this.pollingInterval = setInterval(() => {
            this.pollStatus();
        }, 3000);
    },

    /**
     * Query API via POST to update highest bid and table without reloading.
     */
    async pollStatus() {
        try {
            const res = await fetch('api/auction_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ auction_id: this.auctionId })
            });
            const data = await res.json();

            if (!data.success) return;

            // Update current highest price display
            const priceElem = document.getElementById('currentAuctionPrice');
            if (priceElem) {
                priceElem.textContent = data.formatted_price;
            }

            // Update recommended minimum input
            const inputElem = document.getElementById('bidAmountInput');
            if (inputElem && parseFloat(inputElem.value) < data.next_min_bid) {
                inputElem.value = data.next_min_bid;
                inputElem.min = data.next_min_bid;
            }

            // Sync end_time if changed by anti-snipe from another user
            const serverEndMs = this.parseDateToMs(data.end_time);
            if (serverEndMs > this.endTimeMs + 1000) {
                this.endTimeMs = serverEndMs;
                this.triggerAntiSnipeNotification();
            }

            // Render bids table
            this.renderBidHistory(data.bids);

        } catch (e) {
            console.error('[POLL ERROR]', e);
        }
    },

    /**
     * Display visual Anti-Snipe notice when time is extended.
     */
    triggerAntiSnipeNotification() {
        const alertBox = document.getElementById('snipeAlert');
        if (alertBox) {
            alertBox.style.display = 'block';
            alertBox.innerHTML = '⚡ มีการเคาะราคาในนาทีสุดท้าย! <strong>ระบบต่อเวลาอัตโนมัติเพิ่ม 3 นาที (Anti-Snipe)</strong>';
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 6000);
        }
    },

    /**
     * Render the bids log table.
     */
    renderBidHistory(bids) {
        const tbody = document.getElementById('bidHistoryBody');
        if (!tbody) return;

        if (!bids || bids.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" style="text-align: center; padding: 1.5rem; opacity: 0.7;">ยังไม่มีการเสนอราคา</td></tr>`;
            return;
        }

        let html = '';
        bids.forEach((b, idx) => {
            const isLatest = idx === 0;
            html += `
                <tr style="border-bottom: 1px solid rgba(233, 215, 147, 0.15); ${isLatest ? 'background: rgba(233, 215, 147, 0.1);' : ''}">
                    <td style="padding: 0.6rem 0.5rem;">
                        ${isLatest ? '<span style="color: var(--gold-accent); font-weight: bold;">👑 ผู้นำราคา: </span>' : ''}
                        ${b.username}
                    </td>
                    <td style="padding: 0.6rem 0.5rem; font-weight: bold; color: var(--gold-accent);">
                        ${b.formatted_amount}
                    </td>
                    <td style="padding: 0.6rem 0.5rem; font-size: 0.85rem; opacity: 0.8;">
                        ${b.time}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    },

    showFeedback(msg, type) {
        const feedbackElem = document.getElementById('bidFeedback');
        if (!feedbackElem) return;

        feedbackElem.style.display = 'block';
        feedbackElem.style.background = type === 'danger' ? 'rgba(230, 57, 70, 0.2)' : 'rgba(42, 157, 143, 0.2)';
        feedbackElem.style.borderColor = type === 'danger' ? 'var(--danger)' : 'var(--success)';
        feedbackElem.style.color = type === 'danger' ? '#ff8b94' : '#7fe3d7';
        feedbackElem.textContent = msg;
    }
};

window.AmuletAuction = AmuletAuction;
