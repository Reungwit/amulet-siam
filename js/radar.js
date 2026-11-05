/**
 * Amulet Status Graphic - 6-Point Radar / Spider Web Chart
 * 
 * Renders a high-DPI HTML5 Canvas hexagon radar chart visualizing 6 sacred attributes
 * using Gold accent (#E9D793) over Dark Crimson (#5e030f).
 * 
 * @package AmuletPlatform
 */

'use strict';

/**
 * Render animated hexagon radar chart on specified canvas element.
 * 
 * @param {string} canvasId - Canvas DOM element ID
 * @param {Object} rawStats - Spiritual attribute values (1 - 10)
 */
function render_amulet_radar_chart(canvasId, rawStats) {
    console.log(`[${new Date().toISOString()}] [RADAR] Initializing radar chart on #${canvasId}`);

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.error(`[RADAR ERROR] Canvas #${canvasId} not found`);
        return;
    }

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Retina display / High DPI scaling
    const dpr = window.devicePixelRatio || 1;
    const displayWidth = 400;
    const displayHeight = 400;

    canvas.width = displayWidth * dpr;
    canvas.height = displayHeight * dpr;
    canvas.style.width = `${displayWidth}px`;
    canvas.style.height = `${displayHeight}px`;
    ctx.scale(dpr, dpr);

    const centerX = displayWidth / 2;
    const centerY = displayHeight / 2;
    const maxRadius = 135;
    const totalAxes = 6;
    const maxScore = 10;

    const attributes = [
        { key: 'protection', label: 'พุทธคุณ', value: Math.min(10, Math.max(1, rawStats.protection || 5)) },
        { key: 'popularity', label: 'เมตตามหานิยม', value: Math.min(10, Math.max(1, rawStats.popularity || 5)) },
        { key: 'charm', label: 'มหาเสน่ห์', value: Math.min(10, Math.max(1, rawStats.charm || 5)) },
        { key: 'evasion', label: 'แคล้วคลาด', value: Math.min(10, Math.max(1, rawStats.evasion || 5)) },
        { key: 'invincibility', label: 'คงกระพันชาตรี', value: Math.min(10, Math.max(1, rawStats.invincibility || 5)) },
        { key: 'wealth', label: 'โชคลาภ', value: Math.min(10, Math.max(1, rawStats.wealth || 5)) },
    ];

    /**
     * Get 2D coordinates for a given axis index and radius.
     * Starts from top (-PI/2) and goes clockwise.
     */
    function get_vertex_coords(index, radius) {
        const angle = -Math.PI / 2 + (index * 2 * Math.PI / totalAxes);
        return {
            x: centerX + radius * Math.cos(angle),
            y: centerY + radius * Math.sin(angle)
        };
    }

    let progress = 0;
    const duration = 900; // ms
    let startTime = null;

    function draw_frame(timestamp) {
        if (!startTime) startTime = timestamp;
        const elapsed = timestamp - startTime;
        progress = Math.min(1, elapsed / duration);
        // Ease-out cubic formula
        const easeProgress = 1 - Math.pow(1 - progress, 3);

        ctx.clearRect(0, 0, displayWidth, displayHeight);

        // 1. Draw Background Radial Glow
        const gradient = ctx.createRadialGradient(centerX, centerY, 10, centerX, centerY, maxRadius + 30);
        gradient.addColorStop(0, 'rgba(110, 4, 18, 0.85)');
        gradient.addColorStop(1, 'rgba(40, 2, 7, 0.95)');
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(centerX, centerY, maxRadius + 20, 0, 2 * Math.PI);
        ctx.fill();

        // 2. Draw 5 Concentric Hexagonal Grid Lines (Scores 2, 4, 6, 8, 10)
        const levels = [2, 4, 6, 8, 10];
        levels.forEach(level => {
            const levelRadius = (level / maxScore) * maxRadius;
            ctx.beginPath();
            for (let i = 0; i < totalAxes; i++) {
                const pt = get_vertex_coords(i, levelRadius);
                if (i === 0) ctx.moveTo(pt.x, pt.y);
                else ctx.lineTo(pt.x, pt.y);
            }
            ctx.closePath();
            ctx.strokeStyle = (level === 10) ? 'rgba(233, 215, 147, 0.45)' : 'rgba(233, 215, 147, 0.15)';
            ctx.lineWidth = (level === 10) ? 1.5 : 1;
            ctx.stroke();
        });

        // 3. Draw 6 Radial Axis Lines & Labels
        attributes.forEach((attr, i) => {
            const outerPt = get_vertex_coords(i, maxRadius);
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.lineTo(outerPt.x, outerPt.y);
            ctx.strokeStyle = 'rgba(233, 215, 147, 0.25)';
            ctx.lineWidth = 1;
            ctx.stroke();

            // Label positioning
            const labelRadius = maxRadius + 24;
            const labelPt = get_vertex_coords(i, labelRadius);

            ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Sarabun", sans-serif';
            ctx.fillStyle = '#E9D793';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            // Score tag
            ctx.fillText(`${attr.label}`, labelPt.x, labelPt.y - 7);
            ctx.font = '10px -apple-system, BlinkMacSystemFont, sans-serif';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
            ctx.fillText(`(${attr.value}/10)`, labelPt.x, labelPt.y + 7);
        });

        // 4. Draw Animated Data Polygon
        ctx.beginPath();
        attributes.forEach((attr, i) => {
            const currentRadius = (attr.value / maxScore) * maxRadius * easeProgress;
            const pt = get_vertex_coords(i, currentRadius);
            if (i === 0) ctx.moveTo(pt.x, pt.y);
            else ctx.lineTo(pt.x, pt.y);
        });
        ctx.closePath();

        // Polygon Fill: Semi-transparent Gold
        ctx.fillStyle = 'rgba(233, 215, 147, 0.5)';
        ctx.fill();

        // Polygon Border: Solid Gold
        ctx.strokeStyle = '#E9D793';
        ctx.lineWidth = 2.5;
        ctx.shadowColor = 'rgba(233, 215, 147, 0.7)';
        ctx.shadowBlur = 10;
        ctx.stroke();
        ctx.shadowBlur = 0; // Reset shadow

        // 5. Draw Glowing Vertex Dots
        attributes.forEach((attr, i) => {
            const currentRadius = (attr.value / maxScore) * maxRadius * easeProgress;
            const pt = get_vertex_coords(i, currentRadius);

            ctx.beginPath();
            ctx.arc(pt.x, pt.y, 4.5, 0, 2 * Math.PI);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#E9D793';
            ctx.stroke();
        });

        if (progress < 1) {
            requestAnimationFrame(draw_frame);
        } else {
            console.log(`[${new Date().toISOString()}] [RADAR] Radar chart render completed`);
        }
    }

    requestAnimationFrame(draw_frame);
}

window.render_amulet_radar_chart = render_amulet_radar_chart;

