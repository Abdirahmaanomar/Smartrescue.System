<?php
// Determine active page for nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
function navActive($page, $current) {
    return $page === $current ? 'active' : '';
}

// Load language system
if (!function_exists('t')) {
    require_once __DIR__ . '/lang.php';
}

// Sync profile image into session only if not already cached this session.
// This prevents an extra DB round-trip on every admin page load.
if (isset($conn) && isset($_SESSION['user_id']) && !isset($_SESSION['_profile_synced'])) {
    $_sb_uid = $_SESSION['user_id'];
    $_sb_res = mysqli_query($conn, "SELECT profile_image, fullname FROM users WHERE id='$_sb_uid' LIMIT 1");
    if ($_sb_res && $_sb_row = mysqli_fetch_assoc($_sb_res)) {
        $_SESSION['profile_image']  = $_sb_row['profile_image'] ?? '';
        $_SESSION['fullname']       = $_sb_row['fullname'] ?? ($_SESSION['fullname'] ?? 'Admin');
        $_SESSION['_profile_synced'] = true;
    }
}
?>
<style>
:root {
    --sidebar-bg: #080f1e;
    --sidebar-width: 268px;
    --nav-active-bg: linear-gradient(135deg, #3b82f6, #1d4ed8);
    --nav-hover-bg: rgba(255,255,255,0.07);
    --nav-text: rgba(255,255,255,0.58);
    --nav-active-text: #ffffff;
    --accent: #3b82f6;
}

.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0; top: 0;
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    z-index: 1100;
    box-shadow: 4px 0 30px rgba(0,0,0,0.3);
    overflow: hidden;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 28px 22px 22px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-decoration: none;
}
.sidebar-brand-icon {
    width: 40px; height: 40px;
    background: linear-gradient(135deg, #3b82f6, #1e40af);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 1rem;
    box-shadow: 0 4px 14px rgba(59,130,246,0.4);
    flex-shrink: 0;
}
.sidebar-brand-text {
    font-size: 1.05rem;
    font-weight: 800;
    color: white;
    letter-spacing: -0.3px;
    line-height: 1;
}
.sidebar-brand-text span { color: #60a5fa; }
.sidebar-brand-sub {
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 3px;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    margin-top: 2px;
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 18px 14px;
    scrollbar-width: none;
}
.sidebar-nav::-webkit-scrollbar { display: none; }

.nav-section-label {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 3px;
    color: rgba(255,255,255,0.2);
    text-transform: uppercase;
    padding: 14px 10px 6px;
}

.nav-item-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    border-radius: 11px;
    color: var(--nav-text);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.88rem;
    margin-bottom: 2px;
    transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
    position: relative;
}
.nav-item-link .nav-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    transition: all 0.22s;
    flex-shrink: 0;
}
.nav-item-link:hover {
    background: var(--nav-hover-bg);
    color: white;
    transform: translateX(3px);
}
.nav-item-link:hover .nav-icon { background: rgba(255,255,255,0.1); }
.nav-item-link.active {
    background: var(--nav-active-bg);
    color: white;
    box-shadow: 0 6px 20px rgba(59,130,246,0.35);
}
.nav-item-link.active .nav-icon {
    background: rgba(255,255,255,0.15);
    color: white;
}

.nav-badge {
    margin-left: auto;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 20px;
    background: #ef4444;
    color: white;
    min-width: 20px;
    text-align: center;
    animation: pulse-badge 2s infinite;
}
@keyframes pulse-badge {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
}

.sidebar-footer {
    padding: 16px 14px 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.admin-profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    margin-bottom: 0px;
    cursor: pointer;
    transition: 0.2s;
    user-select: none;
}
.admin-profile-card:hover {
    background: rgba(255,255,255,0.08);
}
.admin-avatar {
    width: 38px; height: 38px;
    border-radius: 10.5px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    color: white;
    font-size: 0.95rem;
    font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}
.admin-name { font-size: 0.85rem; font-weight: 700; color: white; line-height: 1.2; }
.admin-role { font-size: 0.65rem; color: rgba(255,255,255,0.4); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;}

/* ── Profile Dropdown ─────────────────────────────────── */
.profile-dropdown-wrapper {
    position: relative;
}

/* Trigger card */
.admin-profile-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: rgba(255,255,255,0.05);
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.22s ease, box-shadow 0.22s ease;
    user-select: none;
    border: 1px solid transparent;
}
.admin-profile-card:hover {
    background: rgba(255,255,255,0.09);
    border-color: rgba(255,255,255,0.07);
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
}
.admin-profile-card.pdd-open {
    background: rgba(59,130,246,0.12);
    border-color: rgba(59,130,246,0.2);
    box-shadow: 0 4px 20px rgba(59,130,246,0.15);
}

/* Chevron spin */
.pdd-chevron {
    font-size: 0.6rem;
    color: rgba(255,255,255,0.35);
    transition: transform 0.28s cubic-bezier(0.4,0,0.2,1), color 0.2s;
    flex-shrink: 0;
}
.admin-profile-card.pdd-open .pdd-chevron {
    transform: rotate(180deg);
    color: #60a5fa;
}

/* Dropdown panel */
.profile-dropdown-panel {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 0;
    width: 100%;
    background: #0d1526;
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(59,130,246,0.06);
    padding: 0;
    overflow: hidden;
    z-index: 9999;

    /* Animation */
    opacity: 0;
    transform: translateY(10px) scale(0.97);
    pointer-events: none;
    transition: opacity 0.25s cubic-bezier(0.4,0,0.2,1),
                transform 0.25s cubic-bezier(0.4,0,0.2,1);
    transform-origin: bottom center;
}
.profile-dropdown-panel.pdd-visible {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

/* Top user-info section */
.pdd-user-info {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 16px 16px 14px;
}
.pdd-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 60%, #ec4899 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.05rem;
    font-weight: 800;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.08), 0 4px 16px rgba(59,130,246,0.3);
}
.pdd-avatar img {
    width: 100%; height: 100%; object-fit: cover;
}
.pdd-user-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
    letter-spacing: -0.1px;
}
.pdd-user-role {
    font-size: 0.68rem;
    font-weight: 600;
    color: rgba(255,255,255,0.38);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 3px;
}
.pdd-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(59,130,246,0.15);
    color: #60a5fa;
    border-radius: 100px;
    padding: 2px 8px;
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    margin-top: 4px;
}
.pdd-role-badge i { font-size: 0.5rem; }

/* Divider */
.pdd-divider {
    height: 1px;
    background: rgba(255,255,255,0.06);
    margin: 0 12px;
}

/* Menu items */
.pdd-menu {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pdd-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 10px;
    color: rgba(255,255,255,0.72);
    text-decoration: none;
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    position: relative;
    overflow: hidden;
}
.pdd-item:hover {
    background: rgba(255,255,255,0.07);
    color: #fff;
    transform: translateX(3px);
}
.pdd-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    color: rgba(255,255,255,0.5);
    flex-shrink: 0;
    transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
}
.pdd-item:hover .pdd-item-icon {
    background: rgba(59,130,246,0.18);
    color: #60a5fa;
    transform: scale(1.08);
}
.pdd-item-label { flex: 1; }

/* Sign-out special styling */
.pdd-item.pdd-signout {
    color: rgba(239,105,105,0.82);
}
.pdd-item.pdd-signout .pdd-item-icon {
    background: rgba(239,68,68,0.1);
    color: rgba(239,100,100,0.7);
}
.pdd-item.pdd-signout:hover {
    background: rgba(239,68,68,0.1);
    color: #f87171;
    transform: translateX(3px);
}
.pdd-item.pdd-signout:hover .pdd-item-icon {
    background: rgba(239,68,68,0.2);
    color: #f87171;
}

/* Bottom padding */
.pdd-menu-bottom { padding-bottom: 6px; }
</style>

<aside class="sidebar">
    <a href="index.php" class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fa fa-truck-medical"></i></div>
        <div>
            <div class="sidebar-brand-text">Smart<span>Rescue</span></div>
            <div class="sidebar-brand-sub"><?= t('Admin Panel') ?></div>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="nav-section-label"><?= t('Overview') ?></div>
        <a href="index.php" class="nav-item-link <?= navActive('index.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-gauge-high"></i></span>
            <?= t('Dashboard') ?>
            <span class="nav-badge" id="sb-pending-badge" style="display:none;"></span>
        </a>
        <a href="live-tracking.php" class="nav-item-link <?= navActive('live-tracking.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-satellite-dish"></i></span>
            <?= t('Live Tracking') ?>
        </a>

        <div class="nav-section-label"><?= t('Management') ?></div>
        <a href="fleet.php" class="nav-item-link <?= navActive('fleet.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-truck-medical"></i></span>
            <?= t('Fleet Management') ?>
        </a>
        <a href="team.php" class="nav-item-link <?= navActive('team.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-users"></i></span>
            <?= t('Responder') ?>
        </a>
        <a href="users.php" class="nav-item-link <?= navActive('users.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-user-group"></i></span>
            <?= t('User Management') ?>
        </a>
        <a href="view-requests.php" class="nav-item-link <?= navActive('view-requests.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-list-check"></i></span>
            <?= t('Mission Logs') ?>
        </a>

        <div class="nav-section-label"><?= t('Intelligence') ?></div>
        <a href="analytics.php" class="nav-item-link <?= navActive('analytics.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-chart-line"></i></span>
            <?= t('Analytics') ?>
        </a>
        <a href="notifications.php" class="nav-item-link <?= navActive('notifications.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-bell"></i></span>
            <?= t('Notifications') ?>
            <span class="nav-badge" id="sb-notif-badge" style="display:none;"></span>
        </a>
        <a href="reports.php" class="nav-item-link <?= navActive('reports.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-file-lines"></i></span>
            <?= t('Reports') ?>
        </a>

        <div class="nav-section-label"><?= t('System') ?></div>
        <a href="settings.php" class="nav-item-link <?= navActive('settings.php', $current_page) ?>">
            <span class="nav-icon"><i class="fa fa-gear"></i></span>
            <?= t('Settings') ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="profile-dropdown-wrapper" id="pddWrapper">

            <!-- Trigger card -->
            <div class="admin-profile-card" id="pddTrigger" role="button" aria-haspopup="true" aria-expanded="false">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="admin-avatar">
                        <?php if(!empty($_SESSION['profile_image'])): ?>
                            <img src="../<?= htmlspecialchars($_SESSION['profile_image']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="avatar">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="admin-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></div>
                        <div class="admin-role">Chief Dispatcher</div>
                    </div>
                </div>
                <i class="fa fa-chevron-up pdd-chevron" aria-hidden="true"></i>
            </div>

            <!-- Dropdown panel -->
            <div class="profile-dropdown-panel" id="pddPanel" role="menu">

                <!-- User info header -->
                <div class="pdd-user-info">
                    <div class="pdd-avatar">
                        <?php if(!empty($_SESSION['profile_image'])): ?>
                            <img src="../<?= htmlspecialchars($_SESSION['profile_image']) ?>" alt="avatar">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="pdd-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></div>
                        <div class="pdd-role-badge"><i class="fa fa-shield-halved"></i> Chief Dispatcher</div>
                    </div>
                </div>

                <div class="pdd-divider"></div>

                <!-- Menu items -->
                <div class="pdd-menu">
                    <a href="profile.php" class="pdd-item" role="menuitem">
                        <span class="pdd-item-icon"><i class="fa fa-user"></i></span>
                        <span class="pdd-item-label">View Profile</span>
                        <i class="fa fa-chevron-right" style="font-size:0.55rem;opacity:0.3;"></i>
                    </a>
                    <a href="profile.php" class="pdd-item" role="menuitem">
                        <span class="pdd-item-icon"><i class="fa fa-pen-to-square"></i></span>
                        <span class="pdd-item-label">Edit Profile</span>
                        <i class="fa fa-chevron-right" style="font-size:0.55rem;opacity:0.3;"></i>
                    </a>
                </div>

                <div class="pdd-divider"></div>

                <div class="pdd-menu pdd-menu-bottom">
                    <a href="../auth/logout.php" class="pdd-item pdd-signout" role="menuitem">
                        <span class="pdd-item-icon"><i class="fa fa-arrow-right-from-bracket"></i></span>
                        <span class="pdd-item-label">Sign Out</span>
                    </a>
                </div>

            </div><!-- /pddPanel -->
        </div>
    </div>
</aside>

<script>
// ── Profile Dropdown ────────────────────────────────────
(function() {
    const trigger = document.getElementById('pddTrigger');
    const panel   = document.getElementById('pddPanel');
    if (!trigger || !panel) return;

    function openDropdown() {
        panel.classList.add('pdd-visible');
        trigger.classList.add('pdd-open');
        trigger.setAttribute('aria-expanded', 'true');
    }
    function closeDropdown() {
        panel.classList.remove('pdd-visible');
        trigger.classList.remove('pdd-open');
        trigger.setAttribute('aria-expanded', 'false');
    }
    function toggleDropdown() {
        panel.classList.contains('pdd-visible') ? closeDropdown() : openDropdown();
    }

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !panel.contains(e.target)) {
            closeDropdown();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDropdown();
    });
})();

// ── Sidebar Badge Polling ───────────────────────────────
// Uses lightweight /get_badge_counts.php (single COUNT query, no JOINs)
// instead of the full fleet data API. Polls every 15s (was 5s).
function updateSidebarBadge() {
    fetch('../api/admin/get_badge_counts.php')
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                const badge = document.getElementById('sb-pending-badge');
                const notifBadge = document.getElementById('sb-notif-badge');
                if (d.pending > 0) {
                    if(badge) { badge.textContent = d.pending; badge.style.display = 'inline'; }
                    if(notifBadge) { notifBadge.textContent = d.pending; notifBadge.style.display = 'inline'; }
                } else {
                    if(badge) badge.style.display = 'none';
                    if(notifBadge) notifBadge.style.display = 'none';
                }
            }
        }).catch(() => {});
}
updateSidebarBadge();
setInterval(updateSidebarBadge, 15000);
</script>