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

$unit_q = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
$unit   = mysqli_fetch_assoc($unit_q);
$unit_id     = (int)($unit['id'] ?? 0);
$unit_name   = $unit['unit_name']   ?? 'Rescue Unit';
$unit_type   = strtolower($unit['unit_type'] ?? 'medical');
$plate_num   = $unit['plate_number'] ?? 'SOM-000-DRV';
$unit_status = strtolower($unit['status'] ?? 'available');
$is_avail    = ($unit_status === 'available');

$type_map = [
    'medical'  => ['icon' => 'fa-truck-medical',     'color' => '#3b82f6'],
    'fire'     => ['icon' => 'fa-fire-extinguisher', 'color' => '#ef4444'],
    'police'   => ['icon' => 'fa-shield-halved',     'color' => '#2563eb'],
    'accident' => ['icon' => 'fa-car-burst',         'color' => '#f59e0b'],
];
$tm = $type_map[$unit_type] ?? $type_map['medical'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Live Map | SmartRescue Driver</title>
<meta name="description" content="SmartRescue Driver - Live Map with real-time GPS tracking and emergency dispatch routing.">

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
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.14);
    --sidebar-w: 260px;
}
[data-theme="dark"] {
    --bg:        #080f1e;
    --surface:   #0f1c30;
    --border:    #1a2d47;
    --text:      #f1f5f9;
    --muted:     #7a8fac;
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
    --shadow:    0 4px 24px rgba(0,0,0,0.4);
    --shadow-lg: 0 8px 40px rgba(0,0,0,0.5);
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);height:100vh;overflow:hidden;transition:background .3s,color .3s}

/* ══════════════════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════════════════ */
.sidebar {
    position: fixed; top:0; left:0; bottom:0;
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    z-index: 400; box-shadow: var(--shadow);
    transition: transform .3s ease;
}
.sidebar-logo {
    padding: 22px 20px 18px;
    display: flex; align-items: center; gap: 10px;
    border-bottom: 1px solid var(--border);
}
.logo-icon {
    width:38px; height:38px; border-radius:12px;
    background: linear-gradient(135deg,#1d4ed8,#2563eb);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:16px;
    box-shadow:0 4px 12px rgba(37,99,235,.4); flex-shrink:0;
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

/* ── iOS Toggle Switch ──────────────────────────────── */
.toggle-switch { display:inline-flex; align-items:center; gap:9px; cursor:pointer; user-select:none; }
.toggle-track {
    position:relative; width:44px; height:26px;
    border-radius:30px; transition:background .25s, box-shadow .25s; flex-shrink:0;
}
.toggle-track.on  { background:var(--green); box-shadow:0 0 10px rgba(16,185,129,.4); }
.toggle-track.off { background:#cbd5e1; }
[data-theme="dark"] .toggle-track.off { background:#334155; }
.toggle-knob {
    position:absolute; top:3px; left:3px; width:20px; height:20px; border-radius:50%;
    background:#fff; box-shadow:0 2px 6px rgba(0,0,0,.25);
    transition:transform .25s cubic-bezier(.34,1.56,.64,1);
}
.toggle-track.on  .toggle-knob { transform:translateX(18px); }
.toggle-track.off .toggle-knob { transform:translateX(0); }
.toggle-label { font-weight:700; font-size:12px; letter-spacing:0.4px; transition:color .2s; }
.toggle-switch:has(.toggle-track.on)  .toggle-label { color:var(--green); }
.toggle-switch:has(.toggle-track.off) .toggle-label { color:var(--muted); }

.icon-btn {
    width:40px; height:40px; border-radius:12px;
    border:1px solid var(--border); background:var(--bg);
    display:flex; align-items:center; justify-content:center;
    color:var(--muted); cursor:pointer; transition:all .2s;
    font-size:15px; text-decoration:none;
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
   MAP AREA  (full screen after sidebar + topbar)
══════════════════════════════════════════════════════ */
.map-wrapper {
    position: fixed;
    top: 64px; left: var(--sidebar-w); right: 0; bottom: 0;
    z-index: 1;
}
#googleMap {
    width:100%; height:100%;
    z-index: 1;
}

/* ══════════════════════════════════════════════════════
   FLOATING CONTROLS
══════════════════════════════════════════════════════ */
/* GPS Badge top-left of map area */
.gps-badge {
    position:absolute; top:16px; left:16px; z-index:1000;
    background:var(--surface); border:1px solid var(--border);
    border-radius:30px; padding:8px 16px;
    font-size:12px; font-weight:700;
    display:flex; align-items:center; gap:6px;
    box-shadow:var(--shadow);
    pointer-events:all;
}
.gps-dot { width:9px; height:9px; border-radius:50%; background:var(--green); box-shadow:0 0 8px var(--green); }
.gps-dot.searching { background:var(--amber); box-shadow:0 0 8px var(--amber); animation:blink 1.2s infinite; }

/* Map type toggle top-center */
.map-type-toggle {
    position:absolute; top:16px; left:50%; transform:translateX(-50%); z-index:1000;
    display:flex; background:var(--surface); border:1px solid var(--border);
    border-radius:30px; padding:4px; gap:4px; box-shadow:var(--shadow);
    pointer-events:all;
}
.mtt-btn {
    display:flex; align-items:center; gap:6px;
    padding:7px 16px; border-radius:24px;
    font-size:12px; font-weight:700; cursor:pointer;
    border:none; font-family:'Inter',sans-serif;
    background:transparent; color:var(--muted); transition:all .2s;
}
.mtt-btn.active { background:var(--blue); color:#fff; box-shadow:0 3px 10px rgba(37,99,235,.35); }

/* Floating Action Controls top-right */
.map-fab-column {
    position: absolute; top: 16px; right: 16px; z-index: 1000;
    display: flex; flex-direction: column; gap: 8px;
    pointer-events: all;
}
.fab-btn {
    width: 42px; height: 42px; border-radius: 12px;
    background: var(--surface); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: var(--blue); cursor: pointer;
    box-shadow: var(--shadow); transition: all .2s;
}
.fab-btn:hover { background: var(--blue); color: #fff; }
.leaflet-control-zoom { display: none !important; }
/* Ensure leaflet panes stay below our controls */
.leaflet-map-pane { z-index: 2 !important; }
.leaflet-tile-pane { z-index: 2 !important; }
.leaflet-overlay-pane { z-index: 3 !important; }
.leaflet-marker-pane { z-index: 4 !important; }
.leaflet-top, .leaflet-bottom { z-index: 5 !important; }


/* ══════════════════════════════════════════════════════
   MISSION SIDE PANEL
══════════════════════════════════════════════════════ */
.mission-panel {
    position:absolute; bottom:24px; right:24px; z-index:1000;
    width:340px;
    background:var(--surface); border:1px solid var(--border);
    border-radius:20px; box-shadow:var(--shadow-lg);
    overflow:hidden;
    transition:transform .3s, opacity .3s;
    pointer-events:all;
}
.mp-header {
    padding:16px 18px 12px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
}
.mp-header h4 { font-weight:800; font-size:14px; flex:1; }
.mp-body { padding:16px 18px; }

/* Standby state */
.standby-panel {
    text-align:center; padding:28px 20px;
}
.standby-icon {
    width:64px; height:64px; border-radius:20px;
    background:rgba(37,99,235,.08); color:var(--blue);
    display:flex; align-items:center; justify-content:center;
    font-size:28px; margin:0 auto 14px;
    animation:float 3s ease-in-out infinite;
}
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
.standby-title { font-weight:800; font-size:15px; margin-bottom:6px; }
.standby-sub   { font-size:12px; color:var(--muted); line-height:1.6; }

/* Active mission */
.status-badge {
    display:inline-flex; align-items:center; gap:5px;
    border-radius:20px; padding:4px 12px;
    font-size:11px; font-weight:800; letter-spacing:0.3px;
}
.dispatch-title { font-weight:900; font-size:16px; margin:10px 0 4px; }
.dispatch-loc   { font-size:12px; color:var(--muted); font-weight:600; margin-bottom:12px; }

.route-info { display:flex; flex-direction:column; gap:0; margin-bottom:14px; }
.route-row  { display:flex; align-items:center; gap:10px; padding:7px 0; }
.route-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.route-dashed { width:2px; height:18px; border-left:2px dashed var(--border); margin-left:4px; }
.route-lbl  { font-size:10px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.route-val  { font-size:13px; font-weight:700; }

.act-btn {
    width:100%; height:46px; border-radius:12px; border:none;
    font-family:'Inter',sans-serif; font-weight:800; font-size:13px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    cursor:pointer; color:#fff; transition:all .2s; margin-top:10px;
}
.act-btn:hover { transform:translateY(-1px); }
.act-btn:active { transform:scale(.98); opacity:.9; }
.btn-accept  { background:linear-gradient(135deg,#1d4ed8,#2563eb); box-shadow:0 6px 16px rgba(37,99,235,.35); }
.btn-enroute { background:linear-gradient(135deg,#b45309,#f59e0b); box-shadow:0 6px 16px rgba(245,158,11,.35); }
.btn-arrived { background:linear-gradient(135deg,#0e7490,#06b6d4); box-shadow:0 6px 16px rgba(6,182,212,.35); }
.btn-done    { background:linear-gradient(135deg,#047857,#10b981); box-shadow:0 6px 16px rgba(16,185,129,.35); }
.refresh-route-btn {
    width:100%; height:42px; border-radius:12px; border:none;
    font-family:'Inter',sans-serif; font-weight:800; font-size:12px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    cursor:pointer; color:#2563eb; background:rgba(37,99,235,.08);
    border:1.5px solid rgba(37,99,235,.15); transition:all .2s; margin-top:10px;
}
.refresh-route-btn:hover { background:rgba(37,99,235,.15); }
.call-row    { display:flex; gap:8px; }
.call-btn-map {
    display:flex; align-items:center; gap:6px;
    background:rgba(16,185,129,.1); color:var(--green);
    border:1.5px solid rgba(16,185,129,.3); border-radius:10px;
    padding:8px 14px; font-size:12px; font-weight:700;
    text-decoration:none; transition:all .2s; margin-top:10px;
}
.call-btn-map:hover { background:rgba(16,185,129,.2); }
/* Route stat items */
.route-stat-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:14px 0;
}
.route-stat-item {
    display:flex; align-items:center; gap:10px;
    background:var(--surface-alt,rgba(37,99,235,.04)); border:1px solid var(--border);
    border-radius:12px; padding:10px 12px;
}
.route-stat-icon {
    width:32px; height:32px; border-radius:10px;
    display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0;
}
.route-stat-lbl { font-size:9.5px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }
.route-stat-val { font-size:13px; font-weight:900; }

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

@media (max-width:768px) {
    .mob-toggle { display:flex; }
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .sidebar-overlay.open { display:block; }
    .topbar { left:0; padding-left:60px; }
    .map-wrapper { left:0; }
    .mission-panel { width:calc(100vw - 32px); bottom:16px; right:16px; left:16px; }
}
</style>
</head>
<body>

<!-- ── MOBILE HAMBURGER -->
<button class="mob-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══ SIDEBAR ══════════════════════════════════════════ -->
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
        <a href="index.php" class="nav-item"><i class="fa-solid fa-house-chimney"></i> Dashboard</a>
        <a href="map.php"   class="nav-item active"><i class="fa-solid fa-map-location-dot"></i> Live Map</a>
        <a href="history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
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
        <span class="page-title">Live Map</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span>Real-time GPS Tracking</span>
    </div>
    <div class="topbar-actions">
        <label class="toggle-switch" onclick="toggleDispatch()">
            <div id="onlinePill" class="toggle-track <?php echo $is_avail ? 'on' : 'off'; ?>">
                <div class="toggle-knob"></div>
            </div>
            <span id="onlineText" class="toggle-label"><?php echo $is_avail ? 'Online' : 'Offline'; ?></span>
        </label>
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

<!-- ══ MAP AREA ══════════════════════════════════════════ -->
<div class="map-wrapper">
    <!-- GPS Badge -->
    <div class="gps-badge">
        <span class="gps-dot searching" id="gpsDot"></span>
        <span id="gpsText">Locating…</span>
    </div>

    <!-- Map Type Toggle Bar (Matching App) -->
    <div class="map-type-toggle">
        <button class="mtt-btn active" id="btnRoad" onclick="switchMapType('roadmap')"><i class="fa-solid fa-map" style="margin-right:4px;"></i> Map</button>
        <button class="mtt-btn" id="btnSat" onclick="switchMapType('satellite')"><i class="fa-solid fa-layer-group" style="margin-right:4px;"></i> Satellite</button>
    </div>

    <!-- Floating Action Column (Matching App) -->
    <div class="map-fab-column">
        <button class="fab-btn" onclick="centerOnDriver()" title="Center on My Location">
            <i class="fa-solid fa-location-crosshairs"></i>
        </button>
        <button class="fab-btn" onclick="zoomInMap()" title="Zoom In">
            <i class="fa-solid fa-plus"></i>
        </button>
        <button class="fab-btn" onclick="zoomOutMap()" title="Zoom Out">
            <i class="fa-solid fa-minus"></i>
        </button>
    </div>

    <!-- Google Map -->
    <div id="googleMap"></div>

    <!-- Mission Panel -->
    <div class="mission-panel">
        <div class="mp-header">
            <div style="width:28px;height:28px;border-radius:8px;background:rgba(239,68,68,.1);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0">
                <i class="fa-solid fa-bolt-lightning"></i>
            </div>
            <h4>Active Dispatch</h4>
            <span id="panelStatus" style="font-size:11px;font-weight:700;color:var(--muted)">On Standby</span>
        </div>
        <div class="mp-body" id="missionBody">
            <div class="standby-panel" style="padding:16px 12px; text-align:center;">
                <div class="standby-icon" style="width:auto; height:auto; background:transparent; color:#94a3b8; font-size:28px; margin-bottom:8px; animation:none;"><i class="fa-solid fa-location-dot"></i></div>
                <div class="standby-title" style="font-weight:800; font-size:1.15rem; color:var(--text); margin-bottom:4px;">No Active Mission</div>
                <div class="standby-sub" style="font-size:0.85rem; color:var(--muted); font-weight:500; line-height:1.5;">Accept an emergency SOS to view live route coordinates.</div>
            </div>
        </div>
    </div>
</div>

<script>
const DRV_ID  = <?php echo (int)$driver_id; ?>;
const UNIT_ID = <?php echo (int)$unit_id; ?>;
let isOnline  = <?php echo $is_avail ? 'true' : 'false'; ?>;
let gMap, driverMarker, victimMarker, routeLine;
let dLat = 2.0469, dLng = 45.3182;
let currentMapType = 'roadmap';

// ── GOOGLE MAP INIT ──────────────────────────────────────
function initMap() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    const darkStyles = [
        { elementType:'geometry', stylers:[{color:'#0f1c30'}] },
        { elementType:'labels.text.stroke', stylers:[{color:'#0f1c30'}] },
        { elementType:'labels.text.fill',   stylers:[{color:'#7a8fac'}] },
        { featureType:'road', elementType:'geometry', stylers:[{color:'#1a2d47'}] },
        { featureType:'road', elementType:'geometry.stroke', stylers:[{color:'#0f1c30'}] },
        { featureType:'road', elementType:'labels.text.fill', stylers:[{color:'#9ca3af'}] },
        { featureType:'road.highway', elementType:'geometry', stylers:[{color:'#1e3a5f'}] },
        { featureType:'water', elementType:'geometry', stylers:[{color:'#051523'}] },
        { featureType:'water', elementType:'labels.text.fill', stylers:[{color:'#4a6080'}] },
        { featureType:'poi', elementType:'geometry', stylers:[{color:'#132032'}] },
        { featureType:'poi.park', elementType:'geometry', stylers:[{color:'#0d2a1a'}] },
        { featureType:'transit', elementType:'geometry', stylers:[{color:'#132032'}] },
        { featureType:'administrative', elementType:'geometry', stylers:[{color:'#1a2d47'}] },
    ];

    gMap = new google.maps.Map(document.getElementById('googleMap'), {
        center: { lat: dLat, lng: dLng },
        zoom: 15,
        mapTypeId: 'roadmap',
        disableDefaultUI: true,
        zoomControl: false,
        gestureHandling: 'greedy',
        styles: isDark ? darkStyles : []
    });

    // Driver marker
    driverMarker = new google.maps.Marker({
        position: { lat: dLat, lng: dLng },
        map: gMap,
        icon: {
            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48">
                    <circle cx="24" cy="24" r="22" fill="#2563eb" stroke="white" stroke-width="3"/>
                    <text x="24" y="30" text-anchor="middle" font-size="18" fill="white">🚑</text>
                </svg>`),
            scaledSize: new google.maps.Size(48, 48),
            anchor: new google.maps.Point(24, 24)
        },
        title: 'Your Position',
        zIndex: 10
    });

    startGPS();
    pollJob();
    setInterval(pollJob, 4000);
}

// ── MAP TYPE SWITCHER ────────────────────────────────────
function switchMapType(type) {
    gMap.setMapTypeId(type);
    currentMapType = type;
    document.querySelectorAll('.mtt-btn').forEach(b => b.classList.remove('active'));
    const ids = { roadmap:'btnRoad', satellite:'btnSat', terrain:'btnTerrain' };
    document.getElementById(ids[type])?.classList.add('active');
}

// ── CENTER ON DRIVER ─────────────────────────────────────
function centerOnDriver() {
    gMap.panTo({ lat: dLat, lng: dLng });
    gMap.setZoom(16);
}

// ── GPS TRACKING ──────────────────────────────────────────
function startGPS() {
    if (!('geolocation' in navigator)) return;
    navigator.geolocation.watchPosition(pos => {
        dLat = pos.coords.latitude;
        dLng = pos.coords.longitude;
        const acc = Math.round(pos.coords.accuracy);

        driverMarker.setPosition({ lat: dLat, lng: dLng });
        document.getElementById('gpsDot').classList.remove('searching');
        document.getElementById('gpsText').textContent = '±' + acc + 'm';

        fetch('../api/driver/update_driver_location.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`lat=${dLat}&lng=${dLng}&driver_id=${DRV_ID}`
        }).catch(() => {});

        if (routeLine && victimMarker) {
            routeLine.setPath([
                { lat: dLat, lng: dLng },
                victimMarker.getPosition()
            ]);
        }
    }, () => {
        document.getElementById('gpsDot').classList.add('searching');
        document.getElementById('gpsText').textContent = 'No GPS';
    }, { enableHighAccuracy:true, maximumAge:2000, timeout:10000 });
}

// ── POLL ACTIVE JOB ──────────────────────────────────────
function pollJob() {
    fetch(`../api/driver/get_active_job.php?driver_id=${DRV_ID}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.request) {
                renderMission(data.request);
                checkAndShowDispatchModal(data.request);
            } else {
                renderNoMission();
                hideDispatchModal();
            }
        }).catch(() => {});
}

function renderNoMission() {
    document.getElementById('panelStatus').textContent = 'On Standby';
    document.getElementById('missionBody').innerHTML = `
        <div class="standby-panel" style="padding:16px 12px; text-align:center;">
            <div class="standby-icon" style="width:auto; height:auto; background:transparent; color:#94a3b8; font-size:28px; margin-bottom:8px; animation:none;"><i class="fa-solid fa-location-dot"></i></div>
            <div class="standby-title" style="font-weight:800; font-size:1.15rem; color:var(--text); margin-bottom:4px;">No Active Mission</div>
            <div class="standby-sub" style="font-size:0.85rem; color:var(--muted); font-weight:500; line-height:1.5;">Accept an emergency SOS to view live route coordinates.</div>
        </div>`;
    _clearVictim();
}

function renderMission(req) {
    const st     = req.status;
    const etype  = req.emergency_type || 'Medical';
    const pName  = req.patient_name   || 'Victim';
    const pPhone = req.patient_phone  || '';
    const loc    = req.neighborhood   || 'Unknown area';
    const vLat   = parseFloat(req.lat);
    const vLng   = parseFloat(req.lng);

    const badges = {
        pending:  ['rgba(239,68,68,.1)','#ef4444','fa-circle-exclamation','NEW SOS'],
        accepted: ['rgba(37,99,235,.1)','#3b82f6','fa-check-circle',      'ACCEPTED'],
        en_route: ['rgba(245,158,11,.1)','#f59e0b','fa-truck-fast',       'EN ROUTE'],
        arrived:  ['rgba(16,185,129,.1)','#10b981','fa-location-dot',     'ON SCENE'],
    };
    const [bbg,btx,bic,blbl] = badges[st] || badges.accepted;

    const actionBtns = {
        pending:  `<button class="act-btn btn-accept"  onclick="doAction(${req.id},'accept')"><i class="fa-solid fa-check-double"></i> Accept Dispatch</button>`,
        accepted: `<button class="act-btn btn-enroute" onclick="doAction(${req.id},'on_the_way')"><i class="fa-solid fa-truck-fast"></i> Start Trip</button>`,
        en_route: `<button class="act-btn btn-arrived" onclick="doAction(${req.id},'arrived')"><i class="fa-solid fa-flag-checkered"></i> Arrived At Scene</button>`,
        arrived:  `<button class="act-btn btn-done"    onclick="doAction(${req.id},'complete')"><i class="fa-solid fa-circle-check"></i> Complete Mission</button>`,
    };

    document.getElementById('panelStatus').textContent = blbl;
    document.getElementById('panelStatus').style.color = btx;

    document.getElementById('missionBody').innerHTML = `
        <span class="status-badge" style="background:${bbg};color:${btx}">
            <i class="fa-solid ${bic}"></i> ${blbl}
        </span>
        <div class="dispatch-title"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;margin-right:6px;font-size:14px"></i>${etype} Emergency</div>
        <div class="dispatch-loc"><i class="fa-solid fa-location-dot" style="color:var(--blue);margin-right:4px"></i>${loc}</div>
        <div class="route-info">
            <div class="route-row">
                <span class="route-dot" style="background:#2563eb"></span>
                <div><div class="route-lbl">Your Location</div><div class="route-val">${dLat.toFixed(5)}, ${dLng.toFixed(5)}</div></div>
            </div>
            <div style="border-left:2px dashed var(--border);height:14px;margin-left:4px"></div>
            <div class="route-row">
                <span class="route-dot" style="background:#ef4444"></span>
                <div><div class="route-lbl">Victim</div><div class="route-val">${pName}</div></div>
            </div>
        </div>

        <!-- Route Stat Grid (Matching Mobile App) -->
        <div class="route-stat-grid">
            <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(37,99,235,.1); color:#2563eb;"><i class="fa-solid fa-route"></i></div>
                <div>
                    <div class="route-stat-lbl">Road Distance</div>
                    <div class="route-stat-val" id="routeDistVal">—</div>
                </div>
            </div>
            <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(245,158,11,.1); color:#f59e0b;"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="route-stat-lbl">Estimated Time</div>
                    <div class="route-stat-val" id="routeDurVal">—</div>
                </div>
            </div>
            <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(16,185,129,.1); color:#10b981;"><i class="fa-solid fa-gauge-high"></i></div>
                <div>
                    <div class="route-stat-lbl">Avg Speed</div>
                    <div class="route-stat-val" id="routeSpeedVal">—</div>
                </div>
            </div>
            <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(100,116,139,.1); color:#64748b;"><i class="fa-solid fa-rotate"></i></div>
                <div>
                    <div class="route-stat-lbl">Last Updated</div>
                    <div class="route-stat-val" id="routeLastVal">—</div>
                </div>
            </div>
        </div>

        <div class="call-row">
            ${actionBtns[st] || ''}
            ${pPhone ? `<a href="tel:${pPhone}" class="call-btn-map" style="margin-top:10px"><i class="fa-solid fa-phone"></i> Call</a>` : ''}
        </div>
        <button class="refresh-route-btn" onclick="refreshRouteFn(${vLat},${vLng})"><i class="fa-solid fa-rotate"></i> Refresh Route</button>`;

    if (!isNaN(vLat) && !isNaN(vLng)) {
        _placeVictimOnMap(vLat, vLng, etype);
    }
}

// ── STATUS ACTION ─────────────────────────────────────────
function doAction(rid, action) {
    fetch('../api/driver/update_status.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`request_id=${rid}&unit_id=${UNIT_ID}&action=${action}&driver_id=${DRV_ID}`
    }).then(r => r.json()).then(() => pollJob()).catch(() => {});
}

// ── ONLINE TOGGLE ─────────────────────────────────────────
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
            pill.className = 'toggle-track ' + (isOnline ? 'on' : 'off');
            document.getElementById('onlineText').textContent = isOnline ? 'Online' : 'Offline';
        }
    }).catch(() => {});
}

// ── THEME TOGGLE ──────────────────────────────────────────
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    document.getElementById('themeIcon').className = 'fa-solid ' + (isDark ? 'fa-moon' : 'fa-sun');
    fetch('../api/driver/save_setting.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`key=dark_mode&value=${isDark ? 0 : 1}&driver_id=${DRV_ID}`
    }).catch(() => {});
    // Reload map to apply dark/light styles
    setTimeout(() => location.reload(), 300);
}

// ── MOBILE SIDEBAR ────────────────────────────────────────
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
</script>

<!-- Google Maps API - Using OpenStreetMap-based approach via Maps JavaScript API -->
<!-- NOTE: Replace YOUR_API_KEY with a real Google Maps API key -->
<!-- Leaflet for map (works without API key) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Use Leaflet (OpenStreetMap) — no API key needed
function initMap() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

    gMap = L.map('googleMap', { zoomControl: false }).setView([dLat, dLng], 15);

    // Tile layers (Google Maps layer matching mobile app)
    const roadLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',{maxZoom:20, attribution:'&copy; Google Maps'});
    const darkLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{maxZoom:20,subdomains:'abcd'});
    const satLayer  = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',{maxZoom:20, attribution:'&copy; Google Maps'});
    const terrLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}',{maxZoom:20, attribution:'&copy; Google Maps'});

    window._layers = { roadmap: isDark ? darkLayer : roadLayer, satellite: satLayer, terrain: terrLayer, _dark: darkLayer, _road: roadLayer };
    (isDark ? darkLayer : roadLayer).addTo(gMap);

    // Driver marker
    driverMarker = L.marker([dLat, dLng], { icon: makeDriverIcon() }).addTo(gMap);

    startGPS();
    pollJob();
    setInterval(pollJob, 4000);
}

function makeDriverIcon() {
    return L.divIcon({
        className: '',
        html: `
            <div style="position:relative; width:70px; height:70px; display:flex; align-items:center; justify-content:center;">
                <!-- Outer blue pulsing ring -->
                <div class="drv-pulse" style="
                    position:absolute; border-radius:50%;
                    width:68px; height:68px; z-index:0;
                "></div>
                <!-- Inner white glow ring -->
                <div style="
                    position:absolute; width:50px; height:50px; border-radius:50%;
                    background:#ffffff;
                    box-shadow: 0 0 14px 5px rgba(37,99,235,0.4);
                    z-index:1;
                "></div>
                <!-- Main solid blue circle with ambulance icon -->
                <div style="
                    position:relative; z-index:2;
                    width:40px; height:40px; border-radius:50%;
                    background:#2563eb;
                    display:flex; align-items:center; justify-content:center;
                    color:#ffffff; font-size:18px;
                    box-shadow: 0 4px 16px rgba(37,99,235,0.55);
                ">
                    <i class="fa-solid fa-truck-medical"></i>
                </div>
                <!-- Blue beacon dot top-right -->
                <div style="
                    position:absolute; top:6px; right:6px; z-index:3;
                    width:9px; height:9px; border-radius:50%;
                    background:#60a5fa;
                    border:1.5px solid #fff;
                    box-shadow: 0 0 7px #2563eb;
                "></div>
            </div>
            <style>
            @keyframes drvPulse {
                0%   { transform:scale(0.65); background:rgba(37,99,235,0.25); opacity:0.95; }
                100% { transform:scale(1.05); background:rgba(37,99,235,0.0);  opacity:0; }
            }
            .drv-pulse { animation: drvPulse 1.2s ease-out infinite; }
            </style>`,
        iconSize: [70, 70],
        iconAnchor: [35, 35]
    });
}
function makeVictimIcon(emergencyType) {
    return L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:#2563eb;box-shadow:0 2px 6px rgba(0,0,0,0.35);border:1px solid #1d4ed8;"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7]
    });
}

// Override switchMapType for Leaflet
function switchMapType(type) {
    const layers = window._layers;
    gMap.eachLayer(l => { if(l instanceof L.TileLayer) gMap.removeLayer(l); });
    layers[type].addTo(gMap);
    document.querySelectorAll('.mtt-btn').forEach(b => b.classList.remove('active'));
    const ids = { roadmap:'btnRoad', satellite:'btnSat', terrain:'btnTerrain' };
    document.getElementById(ids[type])?.classList.add('active');
}

// Override centerOnDriver for Leaflet
function centerOnDriver() {
    gMap.flyTo([dLat, dLng], 16, { animate:true, duration:1 });
}

// Override GPS for Leaflet
function startGPS() {
    if (!('geolocation' in navigator)) return;
    navigator.geolocation.watchPosition(pos => {
        dLat = pos.coords.latitude; dLng = pos.coords.longitude;
        const acc = Math.round(pos.coords.accuracy);
        driverMarker.setLatLng([dLat, dLng]);
        document.getElementById('gpsDot').classList.remove('searching');
        document.getElementById('gpsText').textContent = '±' + acc + 'm';
        fetch('../api/driver/update_driver_location.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`lat=${dLat}&lng=${dLng}&driver_id=${DRV_ID}`}).catch(()=>{});
        if (routeLine && victimMarker) { routeLine.setLatLngs([[dLat,dLng], victimMarker.getLatLng()]); }
    }, ()=>{ document.getElementById('gpsDot').classList.add('searching'); document.getElementById('gpsText').textContent='No GPS'; },
    { enableHighAccuracy:true, maximumAge:2000, timeout:10000 });
}

function zoomInMap() {
    if (gMap) gMap.zoomIn();
}
function zoomOutMap() {
    if (gMap) gMap.zoomOut();
}

// Override mission render victim marker for Leaflet with OSRM Road Routing
function _placeVictimOnMap(vLat, vLng, emergencyType) {
    if (!victimMarker) {
        victimMarker = L.marker([vLat, vLng], { icon: makeVictimIcon(emergencyType) }).addTo(gMap);
    } else {
        victimMarker.setLatLng([vLat, vLng]);
        // Update icon if emergency type changed
        victimMarker.setIcon(makeVictimIcon(emergencyType));
    }
    // Store type for auto-refresh
    victimMarker._emergencyType = emergencyType;
    if (routeLine) gMap.removeLayer(routeLine);
    
    fetch(`https://router.project-osrm.org/route/v1/driving/${dLng},${dLat};${vLng},${vLat}?overview=full&geometries=geojson`)
        .then(r => r.json())
        .then(data => {
            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                const route = data.routes[0];
                const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                
                routeLine = L.polyline(coords, {
                    color: '#2563eb',
                    weight: 6,
                    opacity: 0.9,
                    lineJoin: 'round'
                }).addTo(gMap);

                const distKm = (route.distance / 1000).toFixed(1);
                const durMin = Math.ceil(route.duration / 60);
                const speedKmh = route.duration > 0 ? Math.round((route.distance / 1000) / (route.duration / 3600)) : 40;
                const now = new Date();
                const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

                const distEl = document.getElementById('routeDistVal');
                const durEl  = document.getElementById('routeDurVal');
                const spdEl  = document.getElementById('routeSpeedVal');
                const lstEl  = document.getElementById('routeLastVal');
                if (distEl) distEl.textContent = distKm + ' km';
                if (durEl)  durEl.textContent  = durMin + ' mins';
                if (spdEl)  spdEl.textContent  = speedKmh + ' km/h';
                if (lstEl)  lstEl.textContent  = timeStr;

                const g = L.featureGroup([driverMarker, victimMarker]);
                gMap.fitBounds(g.getBounds().pad(0.3));
            } else {
                routeLine = L.polyline([[dLat,dLng],[vLat,vLng]],{color:'#2563eb',weight:5,dashArray:'8,8',opacity:.9}).addTo(gMap);
            }
        }).catch(() => {
            routeLine = L.polyline([[dLat,dLng],[vLat,vLng]],{color:'#2563eb',weight:5,dashArray:'8,8',opacity:.9}).addTo(gMap);
        });

    const g = L.featureGroup([driverMarker, victimMarker]);
    gMap.fitBounds(g.getBounds().pad(0.3));
}
function _clearVictim() {
    if(victimMarker){gMap.removeLayer(victimMarker);victimMarker=null;}
    if(routeLine){gMap.removeLayer(routeLine);routeLine=null;}
}

// Manual refresh route button
function refreshRouteFn(vLat, vLng) {
    if (!isNaN(vLat) && !isNaN(vLng)) {
        const etype = victimMarker ? victimMarker._emergencyType : 'medical';
        _placeVictimOnMap(vLat, vLng, etype);
    }
}

// Auto-refresh route every 30 seconds when victim is on map
setInterval(() => {
    if (victimMarker) {
        const vll = victimMarker.getLatLng();
        _placeVictimOnMap(vll.lat, vll.lng, victimMarker._emergencyType);
    }
}, 30000);

document.addEventListener('DOMContentLoaded', initMap);
</script>
<?php require_once 'dispatch_modal.php'; ?>
</body>
</html>
