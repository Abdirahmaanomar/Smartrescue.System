<?php
// $page_title and $page_subtitle should be set before including this file
$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
?>
<style>
.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 36px;
    flex-wrap: wrap;
    gap: 16px;
}
.topbar-left h6 {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--accent, #3b82f6);
    margin-bottom: 4px;
}
.topbar-left h1 {
    font-size: clamp(1.8rem, 3vw, 2.4rem);
    font-weight: 900;
    color: var(--text, #0f172a);
    margin: 0;
    line-height: 1.1;
    letter-spacing: -1px;
}
.topbar-left h1 span { font-weight: 300; opacity: 0.4; }

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.topbar-clock {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: var(--card-bg, #fff);
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-muted, #64748b);
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid rgba(0,0,0,0.04);
}
.topbar-clock i { color: var(--accent, #3b82f6); }
.live-pulse {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    background: var(--card-bg, #fff);
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 800;
    color: #22c55e;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid rgba(34,197,94,0.15);
}
.pulse-dot {
    width: 8px; height: 8px;
    background: #22c55e;
    border-radius: 50%;
    animation: live-pulse 1.4s infinite;
}
@keyframes live-pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
    50% { opacity: 0.8; box-shadow: 0 0 0 6px rgba(34,197,94,0); }
}
.topbar-notif-btn {
    position: relative;
    width: 40px; height: 40px;
    border-radius: 12px;
    background: var(--card-bg, #fff);
    border: 1px solid rgba(0,0,0,0.06);
    display: flex; align-items: center; justify-content: center;
    color: #64748b;
    text-decoration: none;
    font-size: 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    transition: 0.2s;
}
.topbar-notif-btn:hover { background: #3b82f6; color: white; border-color: #3b82f6; transform: scale(1.05); }
.topbar-notif-count {
    position: absolute;
    top: -4px; right: -4px;
    background: #ef4444;
    color: white;
    font-size: 0.6rem;
    font-weight: 800;
    min-width: 18px; height: 18px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px;
    border: 2px solid var(--bg, #f1f5f9);
    display: none;
}
</style>

<div class="topbar">
    <div class="topbar-left">
        <h6><?= htmlspecialchars($page_subtitle) ?></h6>
        <h1><?= htmlspecialchars($page_title) ?> <?php if($page_subtitle): ?><span><?= htmlspecialchars($page_subtitle) ?></span><?php endif; ?></h1>
    </div>
    <div class="topbar-right">
        <div class="topbar-clock">
            <i class="fa fa-clock"></i>
            <span id="topbar-time">--:--:--</span>
        </div>
        <div class="live-pulse">
            <div class="pulse-dot"></div>
            System Live
        </div>
        <a href="notifications.php" class="topbar-notif-btn" title="Notifications">
            <i class="fa fa-bell"></i>
            <span class="topbar-notif-count" id="topbar-notif-count"></span>
        </a>
    </div>
</div>

<script>
(function() {
    function updateClock() {
        const el = document.getElementById('topbar-time');
        if (el) el.textContent = new Date().toLocaleTimeString('en-US', {hour12: false});
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
