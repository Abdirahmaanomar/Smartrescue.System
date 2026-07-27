<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../includes/session_guard.php';

$driver_id = $_SESSION['user_id'];
$user_q    = mysqli_query($conn, "SELECT * FROM users WHERE id = '$driver_id' LIMIT 1");
$driver    = mysqli_fetch_assoc($user_q);
$fullname  = $driver['fullname']  ?? 'Driver';
$dark_mode = (int)($driver['dark_mode'] ?? 1);
$avatar_raw = $driver['profile_image'] ?? '';
$avatar = '';
if (!empty($avatar_raw)) {
    if (strpos($avatar_raw, 'http') === 0 || strpos($avatar_raw, '../') === 0) {
        $avatar = htmlspecialchars($avatar_raw);
    } else {
        $avatar = '../' . htmlspecialchars(ltrim($avatar_raw, '/'));
    }
}

// Unit
$unit_q  = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
$unit    = mysqli_fetch_assoc($unit_q);
$unit_id      = (int)($unit['id'] ?? 0);
$unit_name    = $unit['unit_name']   ?? 'Rescue Unit';
$unit_type    = strtolower($unit['unit_type'] ?? 'medical');
$plate_num    = $unit['plate_number'] ?? 'SOM-000-DRV';
$unit_status  = strtolower($unit['status'] ?? 'available');
$is_avail     = ($unit_status === 'available');

$type_map = [
    'medical'  => ['icon' => 'fa-truck-medical',     'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.12)'],
    'fire'     => ['icon' => 'fa-fire-extinguisher', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.12)'],
    'police'   => ['icon' => 'fa-shield-halved',     'color' => '#6366f1', 'bg' => 'rgba(99,102,241,0.12)'],
    'accident' => ['icon' => 'fa-car-burst',         'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.12)'],
];
$tm = $type_map[$unit_type] ?? $type_map['medical'];

// Stats
$total_q    = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id'");
$total      = (int)(mysqli_fetch_assoc($total_q)['c'] ?? 0);

$done_q     = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status='completed'");
$done       = (int)(mysqli_fetch_assoc($done_q)['c'] ?? 0);

$cancelled_q = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status IN('cancelled','rejected')");
$cancelled   = (int)(mysqli_fetch_assoc($cancelled_q)['c'] ?? 0);

$active_q   = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status IN('pending','accepted','en_route','arrived')");
$active     = (int)(mysqli_fetch_assoc($active_q)['c'] ?? 0);

// Filter
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'completed', 'rejected', 'cancelled', 'en_route', 'accepted'];
if (!in_array($filter, $allowed_filters)) $filter = 'all';

$where_status = ($filter === 'all') ? '' : (($filter === 'rejected' || $filter === 'cancelled') ? "AND r.status IN('cancelled','rejected')" : "AND r.status = '$filter'");

$history_q = "SELECT r.*, u.fullname AS patient_name, u.phone AS patient_phone
              FROM rescue_requests r
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.assigned_unit_id = '$unit_id' $where_status
              ORDER BY r.created_at DESC";
$history_res = mysqli_query($conn, $history_q);
$rows = [];
while ($row = mysqli_fetch_assoc($history_res)) $rows[] = $row;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Mission History | SmartRescue Driver</title>
<meta name="description" content="SmartRescue Driver - View your complete rescue mission history and performance stats.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════════ */
:root {
    --bg:        #f0f4ff;
    --surface:   #ffffff;
    --surface2:  #f8faff;
    --border:    #e4e9f2;
    --text:      #0f172a;
    --muted:     #64748b;
    --blue:      #2563eb;
    --green:     #10b981;
    --red:       #ef4444;
    --amber:     #f59e0b;
    --purple:    #6366f1;
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
    --shadow:    0 4px 24px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.12);
    --radius:    16px;
    --sidebar-w: 260px;
}
[data-theme="dark"] {
    --bg:        #080f1e;
    --surface:   #0f1c30;
    --surface2:  #121f35;
    --border:    #1a2d47;
    --text:      #f1f5f9;
    --muted:     #7a8fac;
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
    --shadow:    0 4px 24px rgba(0,0,0,0.4);
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.5);
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;overflow-x:hidden}

/* ══════════════════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════════════════ */
.sidebar {
    position:fixed; top:0; left:0; bottom:0;
    width:var(--sidebar-w);
    background:var(--surface); border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    z-index:400; box-shadow:var(--shadow); transition:transform .3s ease;
}
.sidebar-logo {
    padding:22px 20px 18px;
    display:flex; align-items:center; gap:10px;
    border-bottom:1px solid var(--border);
}
.logo-icon {
    width:38px; height:38px; border-radius:12px;
    background:linear-gradient(135deg,#1d4ed8,#2563eb);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:16px; box-shadow:0 4px 12px rgba(37,99,235,.4); flex-shrink:0;
}
.logo-text { font-weight:900; font-size:17px; color:var(--blue); letter-spacing:-0.5px; line-height:1.1; }
.logo-sub  { font-size:10px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }

.sidebar-nav {
    flex:1; padding:16px 12px; overflow-y:auto;
    display:flex; flex-direction:column; gap:2px;
}
.nav-label { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1.2px; padding:10px 10px 6px; }
.nav-item {
    display:flex; align-items:center; gap:12px;
    padding:11px 14px; border-radius:12px;
    text-decoration:none; color:var(--muted);
    font-weight:600; font-size:14px; transition:all .2s;
    border:none; background:none; width:100%; text-align:left; cursor:pointer;
}
.nav-item:hover { background:var(--bg); color:var(--text); }
.nav-item.active { background:rgba(37,99,235,.1); color:var(--blue); font-weight:700; }
.nav-item i { width:20px; text-align:center; font-size:16px; flex-shrink:0; }
.nav-badge { margin-left:auto; background:var(--green); color:#fff; font-size:10px; font-weight:800; padding:2px 7px; border-radius:20px; }

.sidebar-unit {
    margin:0 12px 12px; padding:14px;
    background:var(--bg); border-radius:14px; border:1px solid var(--border);
}
.sidebar-unit-label { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
.sidebar-unit-name  { font-weight:800; font-size:13px; color:var(--text); }
.sidebar-unit-meta  { font-size:11px; color:var(--muted); margin-top:2px; }

.sidebar-footer { padding:14px 12px; border-top:1px solid var(--border); }
.logout-btn {
    display:flex; align-items:center; gap:10px;
    padding:11px 14px; border-radius:12px;
    color:var(--red); font-weight:700; font-size:14px;
    text-decoration:none; transition:all .2s;
    background:none; border:none; width:100%; cursor:pointer;
}
.logout-btn:hover { background:rgba(239,68,68,.08); }

/* ══════════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════════ */
.topbar {
    position:fixed; top:0; left:var(--sidebar-w); right:0; height:64px;
    background:var(--surface); border-bottom:1px solid var(--border);
    display:flex; align-items:center; padding:0 28px; gap:16px;
    z-index:300; box-shadow:var(--shadow-sm);
}
.topbar-breadcrumb { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--muted); font-weight:600; flex:1; }
.topbar-breadcrumb .page-title { font-size:16px; font-weight:800; color:var(--text); }
.topbar-actions { display:flex; align-items:center; gap:12px; }

.online-pill {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 18px; border-radius:30px; cursor:pointer;
    font-weight:700; font-size:12px; transition:all .2s; user-select:none; border:none;
}
.online-pill.on  { background:rgba(16,185,129,.12); color:var(--green); border:1.5px solid rgba(16,185,129,.3); }
.online-pill.off { background:rgba(239,68,68,.1);   color:var(--red);   border:1.5px solid rgba(239,68,68,.3); }
.online-dot { width:8px; height:8px; border-radius:50%; }
.on  .online-dot { background:var(--green); box-shadow:0 0 8px var(--green); animation:blink 1.5s infinite; }
.off .online-dot { background:var(--red); }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

.icon-btn {
    width:40px; height:40px; border-radius:12px;
    border:1px solid var(--border); background:var(--bg);
    display:flex; align-items:center; justify-content:center;
    color:var(--muted); cursor:pointer; transition:all .2s; font-size:15px; text-decoration:none;
}
.icon-btn:hover { border-color:var(--blue); color:var(--blue); }

.avatar-wrap {
    display:flex; align-items:center; gap:10px; padding:6px 12px;
    border-radius:30px; border:1px solid var(--border); background:var(--bg);
    cursor:pointer; transition:all .2s; text-decoration:none;
}
.avatar-wrap:hover { border-color:var(--blue); }
.avatar-circle {
    width:32px; height:32px; border-radius:50%;
    background:linear-gradient(135deg,var(--blue),#7c3aed);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:800; font-size:13px; flex-shrink:0;
}
.avatar-circle img { width:32px; height:32px; border-radius:50%; object-fit:cover; }
.avatar-name { font-weight:700; font-size:13px; color:var(--text); }
.avatar-role { font-size:10px; color:var(--muted); font-weight:600; }

/* ══════════════════════════════════════════════════════
   MAIN CONTENT
══════════════════════════════════════════════════════ */
.main { margin-left:var(--sidebar-w); padding-top:64px; min-height:100vh; }
.page-content { padding:28px 32px; max-width:1200px; }

/* ══════════════════════════════════════════════════════
   PAGE HEADER
══════════════════════════════════════════════════════ */
.page-header {
    display:flex; align-items:center; gap:16px;
    margin-bottom:24px;
}
.page-icon {
    width:52px; height:52px; border-radius:16px;
    background:rgba(99,102,241,.1); color:var(--purple);
    display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;
}
.page-header h1 { font-weight:900; font-size:22px; }
.page-header p  { font-size:13px; color:var(--muted); margin-top:3px; }

/* ══════════════════════════════════════════════════════
   STATS ROW
══════════════════════════════════════════════════════ */
.stats-row {
    display:grid; grid-template-columns:repeat(4,1fr); gap:14px;
    margin-bottom:24px;
}
.mini-stat {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); padding:18px 16px;
    box-shadow:var(--shadow); display:flex; align-items:center; gap:14px;
    transition:transform .2s;
}
.mini-stat:hover { transform:translateY(-2px); }
.ms-icon {
    width:44px; height:44px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
}
.ms-val   { font-weight:900; font-size:24px; line-height:1; }
.ms-label { font-size:11px; color:var(--muted); font-weight:700; margin-top:3px; }

/* ══════════════════════════════════════════════════════
   FILTER BAR
══════════════════════════════════════════════════════ */
.filter-bar {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
    margin-bottom:20px;
}
.filter-btn {
    display:flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:30px; font-size:12px; font-weight:700;
    border:1.5px solid var(--border); background:var(--surface); color:var(--muted);
    text-decoration:none; transition:all .2s; cursor:pointer;
}
.filter-btn:hover { border-color:var(--blue); color:var(--blue); }
.filter-btn.active { background:var(--blue); color:#fff; border-color:var(--blue); box-shadow:0 4px 12px rgba(37,99,235,.3); }
.filter-btn.green.active  { background:var(--green); border-color:var(--green); box-shadow:0 4px 12px rgba(16,185,129,.3); }
.filter-btn.red.active    { background:var(--red);   border-color:var(--red);   box-shadow:0 4px 12px rgba(239,68,68,.3); }
.filter-btn.amber.active  { background:var(--amber); border-color:var(--amber); box-shadow:0 4px 12px rgba(245,158,11,.3); }
.filter-count {
    background:rgba(255,255,255,.25); border-radius:20px; padding:1px 7px; font-size:10px;
}
.filter-btn:not(.active) .filter-count { background:var(--bg); color:var(--muted); }

/* ══════════════════════════════════════════════════════
   MISSION CARDS
══════════════════════════════════════════════════════ */
.missions-grid { display:flex; flex-direction:column; gap:12px; }

.mission-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); padding:20px 22px;
    box-shadow:var(--shadow-sm);
    display:flex; align-items:flex-start; gap:16px;
    transition:transform .2s, box-shadow .2s;
    text-decoration:none; color:inherit;
    position:relative; overflow:hidden;
}
.mission-card:hover { transform:translateY(-1px); box-shadow:var(--shadow); }
.mission-card::before {
    content:''; position:absolute; left:0; top:0; bottom:0; width:4px;
}
.mission-card.completed::before { background:var(--green); }
.mission-card.cancelled::before { background:var(--red); }
.mission-card.pending::before   { background:var(--amber); }
.mission-card.en_route::before  { background:var(--blue); }
.mission-card.arrived::before   { background:#06b6d4; }
.mission-card.accepted::before  { background:var(--purple); }

.mc-icon {
    width:50px; height:50px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex-shrink:0;
}
.mc-body { flex:1; min-width:0; }
.mc-top  { display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap; }
.mc-title { font-weight:800; font-size:15px; }
.mc-badge {
    display:inline-flex; align-items:center; gap:5px;
    font-size:10px; font-weight:800; border-radius:20px; padding:3px 10px;
    text-transform:uppercase; letter-spacing:0.5px; margin-left:auto;
}
.mc-meta { display:flex; gap:16px; flex-wrap:wrap; }
.mc-meta-item { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--muted); font-weight:600; }
.mc-meta-item i { font-size:11px; }
.mc-desc { margin-top:8px; font-size:12px; color:var(--muted); line-height:1.5; background:var(--bg); border-radius:8px; padding:8px 12px; }

.mc-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.mc-date  { font-size:11px; color:var(--muted); font-weight:600; white-space:nowrap; }
.mc-time  { font-size:10px; color:var(--muted); font-weight:500; }
.mc-phone {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(16,185,129,.1); color:var(--green);
    border:1px solid rgba(16,185,129,.25); border-radius:8px;
    padding:5px 10px; font-size:11px; font-weight:700;
    text-decoration:none; transition:all .2s; white-space:nowrap;
}
.mc-phone:hover { background:rgba(16,185,129,.2); }

/* Empty state */
.empty-state {
    text-align:center; padding:60px 24px;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); box-shadow:var(--shadow-sm);
}
.empty-icon { font-size:48px; opacity:.25; margin-bottom:16px; }
.empty-title { font-weight:800; font-size:17px; margin-bottom:8px; }
.empty-sub   { font-size:13px; color:var(--muted); line-height:1.6; }

/* ══════════════════════════════════════════════════════
   MOBILE
══════════════════════════════════════════════════════ */
.mob-toggle {
    display:none; position:fixed; top:14px; left:14px; z-index:500;
    width:40px; height:40px; border-radius:12px;
    background:var(--surface); border:1px solid var(--border);
    box-shadow:var(--shadow); align-items:center; justify-content:center;
    cursor:pointer; font-size:16px; color:var(--text);
}
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:399; }

@media (max-width:1100px) { .stats-row { grid-template-columns:repeat(2,1fr); } }
@media (max-width:768px) {
    .mob-toggle { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .sidebar-overlay.open { display:block; }
    .topbar { left:0; padding-left:60px; }
    .main { margin-left:0; }
    .page-content { padding:20px 16px; }
    .stats-row { grid-template-columns:repeat(2,1fr); }
    .mission-card { flex-direction:column; }
    .mc-right { align-items:flex-start; }
}
@media (max-width:480px) { .stats-row { grid-template-columns:1fr; } }
</style>
</head>
<body>

<!-- ── MOBILE HAMBURGER -->
<button class="mob-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
        <div>
            <div class="logo-text">SmartRescue</div>
            <div class="logo-sub">Driver Portal</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="index.php"   class="nav-item"><i class="fa-solid fa-house-chimney"></i> Dashboard</a>
        <a href="map.php"     class="nav-item"><i class="fa-solid fa-map-location-dot"></i> Live Map</a>
        <a href="history.php" class="nav-item active">
            <i class="fa-solid fa-clock-rotate-left"></i> History
            <?php if ($total > 0): ?>
            <span class="nav-badge"><?php echo $total; ?></span>
            <?php endif; ?>
        </a>
        <div class="nav-label" style="margin-top:8px">Account</div>
        <a href="profile.php"  class="nav-item"><i class="fa-solid fa-user-shield"></i> Profile</a>
        <a href="settings.php" class="nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
    </nav>
    <div class="sidebar-unit">
        <div class="sidebar-unit-label">Assigned Unit</div>
        <div class="sidebar-unit-name"><?php echo htmlspecialchars($unit_name); ?></div>
        <div class="sidebar-unit-meta"><?php echo htmlspecialchars($plate_num); ?> · <?php echo ucfirst($unit_type); ?></div>
    </div>
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</aside>

<!-- ══ TOPBAR ════════════════════════════════════════════ -->
<header class="topbar">
    <div class="topbar-breadcrumb">
        <span class="page-title">Mission History</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?php echo $total; ?> missions total</span>
    </div>
    <div class="topbar-actions">
        <button id="onlinePill" class="online-pill <?php echo $is_avail ? 'on' : 'off'; ?>" onclick="toggleDispatch()">
            <span class="online-dot"></span>
            <span id="onlineText"><?php echo $is_avail ? 'Online' : 'Offline'; ?></span>
        </button>
        <button class="icon-btn" onclick="toggleTheme()" title="Toggle theme">
            <i class="fa-solid <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>" id="themeIcon"></i>
        </button>
        <a href="profile.php" class="avatar-wrap">
            <div class="avatar-circle">
                <?php if ($avatar): ?>
                    <img src="<?php echo $avatar; ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;"><?php echo strtoupper(mb_substr($fullname, 0, 1)); ?></span>
                <?php else: ?>
                    <span><?php echo strtoupper(mb_substr($fullname, 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div class="avatar-name"><?php echo htmlspecialchars(explode(' ', $fullname)[0]); ?></div>
                <div class="avatar-role">Driver</div>
            </div>
        </a>
    </div>
</header>

<!-- ══ MAIN CONTENT ══════════════════════════════════════ -->
<main class="main">
<div class="page-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
        <div>
            <h1>Rescue Mission History</h1>
            <p>All emergency operations assigned to <strong><?php echo htmlspecialchars($unit_name); ?></strong></p>
        </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats-row">
        <div class="mini-stat">
            <div class="ms-icon" style="background:rgba(37,99,235,.1);color:var(--blue)">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <div>
                <div class="ms-val"><?php echo $total; ?></div>
                <div class="ms-label">Total Missions</div>
            </div>
        </div>
        <div class="mini-stat">
            <div class="ms-icon" style="background:rgba(16,185,129,.1);color:var(--green)">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="ms-val" style="color:var(--green)"><?php echo $done; ?></div>
                <div class="ms-label">Completed</div>
            </div>
        </div>
        <div class="mini-stat">
            <div class="ms-icon" style="background:rgba(245,158,11,.1);color:var(--amber)">
                <i class="fa-solid fa-spinner"></i>
            </div>
            <div>
                <div class="ms-val" style="color:var(--amber)"><?php echo $active; ?></div>
                <div class="ms-label">Active Now</div>
            </div>
        </div>
        <div class="mini-stat">
            <div class="ms-icon" style="background:rgba(239,68,68,.1);color:var(--red)">
                <i class="fa-solid fa-ban"></i>
            </div>
            <div>
                <div class="ms-val" style="color:var(--red)"><?php echo $cancelled; ?></div>
                <div class="ms-label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <a href="?filter=all"       class="filter-btn <?php echo $filter==='all'       ? 'active' : ''; ?>">
            <i class="fa-solid fa-list"></i> All <span class="filter-count"><?php echo $total; ?></span>
        </a>
        <a href="?filter=completed" class="filter-btn green <?php echo $filter==='completed' ? 'active' : ''; ?>">
            <i class="fa-solid fa-circle-check"></i> Completed <span class="filter-count"><?php echo $done; ?></span>
        </a>
        <a href="?filter=en_route"  class="filter-btn <?php echo $filter==='en_route'  ? 'active' : ''; ?>">
            <i class="fa-solid fa-truck-fast"></i> En Route
        </a>
        <a href="?filter=rejected" class="filter-btn red <?php echo ($filter==='rejected' || $filter==='cancelled') ? 'active' : ''; ?>">
            <i class="fa-solid fa-ban"></i> Rejected <span class="filter-count"><?php echo $cancelled; ?></span>
        </a>
    </div>

    <!-- MISSIONS LIST -->
    <div class="missions-grid">
    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
            <div class="empty-title">No missions found</div>
            <div class="empty-sub">
                <?php echo $filter !== 'all' ? "No <strong>$filter</strong> missions found. Try a different filter." : "No rescue missions have been assigned to your unit yet."; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $row):
            $st    = $row['status'] ?? 'pending';
            $etype = ucfirst($row['emergency_type'] ?? 'Medical');
            $loc   = $row['neighborhood'] ?? 'Unknown location';
            $pname = $row['patient_name']  ?? 'Unknown';
            $phone = $row['patient_phone'] ?? '';
            $desc  = $row['description']   ?? '';
            $date  = date('M j, Y', strtotime($row['created_at']));
            $time  = date('g:i a',  strtotime($row['created_at']));

            $badge_map = [
                'completed' => ['bg'=>'rgba(16,185,129,.12)', 'color'=>'#10b981', 'icon'=>'fa-circle-check',    'label'=>'Completed'],
                'cancelled' => ['bg'=>'rgba(239,68,68,.12)',  'color'=>'#ef4444', 'icon'=>'fa-ban',             'label'=>'Rejected'],
                'rejected'  => ['bg'=>'rgba(239,68,68,.12)',  'color'=>'#ef4444', 'icon'=>'fa-ban',             'label'=>'Rejected'],
                'pending'   => ['bg'=>'rgba(245,158,11,.12)', 'color'=>'#f59e0b', 'icon'=>'fa-clock',           'label'=>'Pending'],
                'accepted'  => ['bg'=>'rgba(99,102,241,.12)', 'color'=>'#6366f1', 'icon'=>'fa-check-circle',    'label'=>'Accepted'],
                'en_route'  => ['bg'=>'rgba(37,99,235,.12)',  'color'=>'#2563eb', 'icon'=>'fa-truck-fast',      'label'=>'En Route'],
                'arrived'   => ['bg'=>'rgba(6,182,212,.12)',  'color'=>'#06b6d4', 'icon'=>'fa-location-dot',   'label'=>'Arrived'],
            ];
            $bm = $badge_map[$st] ?? $badge_map['pending'];
        ?>
        <div class="mission-card <?php echo htmlspecialchars($st); ?>">

            <!-- Icon -->
            <div class="mc-icon" style="background:<?php echo $tm['bg']; ?>;color:<?php echo $tm['color']; ?>">
                <i class="fa-solid <?php echo $tm['icon']; ?>"></i>
            </div>

            <!-- Body -->
            <div class="mc-body">
                <div class="mc-top">
                    <span class="mc-title"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;margin-right:5px;font-size:13px"></i><?php echo $etype; ?> Emergency</span>
                    <span class="mc-badge" style="background:<?php echo $bm['bg']; ?>;color:<?php echo $bm['color']; ?>">
                        <i class="fa-solid <?php echo $bm['icon']; ?>"></i> <?php echo $bm['label']; ?>
                    </span>
                </div>
                <div class="mc-meta">
                    <span class="mc-meta-item"><i class="fa-solid fa-user" style="color:var(--blue)"></i> <?php echo htmlspecialchars($pname); ?></span>
                    <span class="mc-meta-item"><i class="fa-solid fa-location-dot" style="color:var(--red)"></i> <?php echo htmlspecialchars($loc); ?></span>
                    <?php if ($phone): ?>
                    <span class="mc-meta-item"><i class="fa-solid fa-phone" style="color:var(--green)"></i> <?php echo htmlspecialchars($phone); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($desc): ?>
                <div class="mc-desc"><?php echo htmlspecialchars($desc); ?></div>
                <?php endif; ?>
            </div>

            <div class="mc-right">
                <div class="mc-date"><?php echo $date; ?></div>
                <div class="mc-time"><?php echo $time; ?></div>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

</div>
</main>

<script>
const DRV_ID = <?php echo (int)$driver_id; ?>;
let isOnline = <?php echo $is_avail ? 'true' : 'false'; ?>;

function toggleDispatch() {
    const newStatus = isOnline ? 'offline' : 'available';
    fetch('../api/driver/update_unit_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`status=${newStatus}&driver_id=${DRV_ID}`
    }).then(r => r.json()).then(d => {
        if (d.status === 'success') {
            isOnline = !isOnline;
            const pill = document.getElementById('onlinePill');
            pill.className = 'online-pill ' + (isOnline ? 'on' : 'off');
            document.getElementById('onlineText').textContent = isOnline ? 'Online' : 'Offline';
        }
    }).catch(() => {});
}

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    document.getElementById('themeIcon').className = 'fa-solid ' + (isDark ? 'fa-moon' : 'fa-sun');
    fetch('../api/driver/save_setting.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`key=dark_mode&value=${isDark ? 0 : 1}&driver_id=${DRV_ID}`
    }).catch(() => {});
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
</script>
</body>
</html>
