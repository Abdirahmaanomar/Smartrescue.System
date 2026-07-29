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
$email     = $driver['email']     ?? '';
$phone     = $driver['phone']     ?? '';
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
$unit_q = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
$unit   = mysqli_fetch_assoc($unit_q);
if (!$unit) {
    $safe = mysqli_real_escape_string($conn, $fullname);
    $plt  = "SOM-" . rand(100, 999) . "-DRV";
    mysqli_query($conn, "INSERT INTO emergency_units (unit_name,unit_type,plate_number,status,driver_id,current_lat,current_lng)
                         VALUES ('{$safe} Unit','medical','$plt','offline','$driver_id',2.0469,45.3182)");
    $unit_q = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
    $unit   = mysqli_fetch_assoc($unit_q);
}
$unit_id     = (int)($unit['id']          ?? 0);
$unit_name   = $unit['unit_name']   ?? 'Rescue Unit';
$unit_type   = strtolower($unit['unit_type'] ?? 'medical');
$unit_type_label = ucfirst($unit_type);
$plate_num   = $unit['plate_number'] ?? 'SOM-000-DRV';
$unit_status = strtolower($unit['status'] ?? 'available');
$is_avail    = ($unit_status === 'available');

// Stats
$saves_q     = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status='completed'");
$total_saves = (int)(mysqli_fetch_assoc($saves_q)['c'] ?? 0);

$missions_q  = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id'");
$total_missions = (int)(mysqli_fetch_assoc($missions_q)['c'] ?? 0);

$pending_q   = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status IN('pending','accepted','en_route','arrived')");
$active_cnt  = (int)(mysqli_fetch_assoc($pending_q)['c'] ?? 0);

// Rank based on saves
$rank = 'Rookie Responder';
if ($total_saves >= 50) $rank = 'Elite Responder';
elseif ($total_saves >= 20) $rank = 'Senior Responder';
elseif ($total_saves >= 10) $rank = 'Expert Responder';
elseif ($total_saves >= 5)  $rank = 'Skilled Responder';

// Type colours/icons
$type_map = [
    'medical'  => ['icon' => 'fa-truck-medical',      'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)', 'grad' => 'linear-gradient(135deg,#1e40af,#3b82f6)'],
    'fire'     => ['icon' => 'fa-fire-extinguisher',  'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.15)',  'grad' => 'linear-gradient(135deg,#991b1b,#ef4444)'],
    'police'   => ['icon' => 'fa-shield-halved',      'color' => '#6366f1', 'bg' => 'rgba(99,102,241,0.15)', 'grad' => 'linear-gradient(135deg,#3730a3,#6366f1)'],
    'accident' => ['icon' => 'fa-car-burst',          'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.15)', 'grad' => 'linear-gradient(135deg,#92400e,#f59e0b)'],
];
$tm = $type_map[$unit_type] ?? $type_map['medical'];

// Recent missions
$recent_q = mysqli_query($conn, "SELECT r.*, u.fullname AS patient_name
    FROM rescue_requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.assigned_unit_id = '$unit_id'
    ORDER BY r.created_at DESC LIMIT 3");
$recent_missions = [];
while ($row = mysqli_fetch_assoc($recent_q)) $recent_missions[] = $row;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Driver Dashboard | SmartRescue</title>
<meta name="description" content="SmartRescue Driver Portal - Manage emergency dispatches and track your rescue missions in real time.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

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
    --blue-dark: #1d4ed8;
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
    position: fixed;
    top: 0; left: 0; bottom: 0;
    width: var(--sidebar-w);
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 400;
    box-shadow: var(--shadow);
    transition: transform .3s ease;
}

.sidebar-logo {
    padding: 22px 20px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid var(--border);
}
.logo-icon {
    width: 38px; height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 16px;
    box-shadow: 0 4px 12px rgba(37,99,235,.4);
    flex-shrink: 0;
}
.logo-text { font-weight: 900; font-size: 17px; color: var(--blue); letter-spacing: -0.5px; line-height: 1.1; }
.logo-sub  { font-size: 10px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }

.sidebar-nav {
    flex: 1;
    padding: 16px 12px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.nav-label {
    font-size: 10px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: 1.2px;
    padding: 10px 10px 6px;
}
.nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; border-radius: 12px;
    text-decoration: none; color: var(--muted);
    font-weight: 600; font-size: 14px;
    transition: all .2s; cursor: pointer;
    border: none; background: none; width: 100%; text-align: left;
}
.nav-item:hover {
    background: var(--bg);
    color: var(--text);
}
.nav-item.active {
    background: rgba(37,99,235,.1);
    color: var(--blue);
    font-weight: 700;
}
.nav-item i { width: 20px; text-align: center; font-size: 16px; flex-shrink: 0; }
.nav-badge {
    margin-left: auto;
    background: var(--red);
    color: #fff;
    font-size: 10px; font-weight: 800;
    padding: 2px 7px; border-radius: 20px;
    min-width: 20px; text-align: center;
}
.nav-badge.green { background: var(--green); }

/* Unit badge in sidebar */
.sidebar-unit {
    margin: 0 12px 12px;
    padding: 14px;
    background: var(--bg);
    border-radius: 14px;
    border: 1px solid var(--border);
}
.sidebar-unit-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.sidebar-unit-name  { font-weight: 800; font-size: 13px; color: var(--text); }
.sidebar-unit-meta  { font-size: 11px; color: var(--muted); margin-top: 2px; }

.sidebar-footer {
    padding: 14px 12px;
    border-top: 1px solid var(--border);
}
.logout-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 14px; border-radius: 12px;
    color: var(--red); font-weight: 700; font-size: 14px;
    text-decoration: none; transition: all .2s;
    background: none; border: none; width: 100%; cursor: pointer;
}
.logout-btn:hover { background: rgba(239,68,68,.08); }

/* ══════════════════════════════════════════════════════
   TOPBAR
══════════════════════════════════════════════════════ */
.topbar {
    position: fixed;
    top: 0; left: var(--sidebar-w); right: 0;
    height: 64px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 28px; gap: 16px;
    z-index: 300;
    box-shadow: var(--shadow-sm);
}
.topbar-breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--muted); font-weight: 600; flex: 1;
}
.topbar-breadcrumb .page-title {
    font-size: 16px; font-weight: 800; color: var(--text);
}
.topbar-breadcrumb i { font-size: 12px; }

.topbar-actions { display: flex; align-items: center; gap: 12px; }

/* ── iOS Toggle Switch ──────────────────────────────── */
.toggle-switch {
    display: inline-flex; align-items: center; gap: 9px;
    cursor: pointer; user-select: none;
}
.toggle-track {
    position: relative; width: 44px; height: 26px;
    border-radius: 30px; transition: background .25s, box-shadow .25s;
    flex-shrink: 0;
}
.toggle-track.on  { background: var(--green); box-shadow: 0 0 10px rgba(16,185,129,.4); }
.toggle-track.off { background: #cbd5e1; }
[data-theme="dark"] .toggle-track.off { background: #334155; }
.toggle-knob {
    position: absolute; top: 3px; left: 3px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.25);
    transition: transform .25s cubic-bezier(.34,1.56,.64,1);
}
.toggle-track.on  .toggle-knob { transform: translateX(18px); }
.toggle-track.off .toggle-knob { transform: translateX(0); }
.toggle-label {
    font-weight: 700; font-size: 12px; letter-spacing: 0.4px;
    transition: color .2s;
}
.toggle-switch:has(.toggle-track.on)  .toggle-label { color: var(--green); }
.toggle-switch:has(.toggle-track.off) .toggle-label { color: var(--muted); }

/* Icon btn */
.icon-btn {
    width: 40px; height: 40px; border-radius: 12px;
    border: 1px solid var(--border); background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); cursor: pointer; transition: all .2s;
    font-size: 15px; text-decoration: none; position: relative;
}
.icon-btn:hover { border-color: var(--blue); color: var(--blue); }

/* Avatar */
.avatar-wrap {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 12px; border-radius: 30px;
    border: 1px solid var(--border); background: var(--bg);
    cursor: pointer; transition: all .2s; text-decoration: none;
}
.avatar-wrap:hover { border-color: var(--blue); }
.avatar-circle {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--blue), #7c3aed);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 13px;
    flex-shrink: 0;
}
.avatar-circle img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.avatar-name { font-weight: 700; font-size: 13px; color: var(--text); }
.avatar-role { font-size: 10px; color: var(--muted); font-weight: 600; }

/* ══════════════════════════════════════════════════════
   MAIN CONTENT
══════════════════════════════════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    padding-top: 64px;
    min-height: 100vh;
}
.page-content {
    padding: 28px 32px;
    max-width: 1400px;
}

/* ══════════════════════════════════════════════════════
   WELCOME BANNER
══════════════════════════════════════════════════════ */
.welcome-banner {
    display: flex; align-items: center; gap: 20px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px 28px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    position: relative; overflow: hidden;
}
.welcome-banner::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, rgba(37,99,235,.04) 0%, transparent 60%);
    pointer-events: none;
}
.wb-avatar {
    width: 68px; height: 68px; border-radius: 20px;
    background: <?php echo $tm['grad']; ?>;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; color: #fff; flex-shrink: 0;
    box-shadow: 0 8px 24px <?php echo str_replace(')', ',.35)', str_replace('rgba(', 'rgba(', $tm['bg'])); ?>;
}
.wb-info { flex: 1; min-width: 0; }
.wb-name  { font-weight: 900; font-size: 22px; color: var(--text); line-height: 1.2; }
.wb-sub   { font-size: 13px; color: var(--muted); margin-top: 4px; font-weight: 500; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.wb-sub i { color: var(--blue); font-size: 11px; }
.wb-rank {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(245,158,11,.1); color: #d97706;
    border: 1px solid rgba(245,158,11,.3);
    border-radius: 20px; padding: 4px 12px;
    font-size: 11px; font-weight: 700; margin-top: 8px;
}
.wb-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; }
.wb-unit-label { font-size: 10px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
.wb-unit-name  { font-weight: 900; font-size: 16px; color: var(--text); text-align: right; }
.wb-unit-code  { font-size: 11px; color: var(--muted); font-weight: 600; text-align: right; }

/* ══════════════════════════════════════════════════════
   STATS ROW
══════════════════════════════════════════════════════ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 16px;
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.stat-card::after {
    content: '';
    position: absolute; top: 0; right: 0;
    width: 80px; height: 80px;
    border-radius: 50%;
    transform: translate(20px, -20px);
    opacity: .06;
}
.stat-card.saves::after  { background: var(--green); }
.stat-card.missions::after{ background: var(--blue); }
.stat-card.active::after  { background: var(--red); }
.stat-icon-wrap {
    width: 52px; height: 52px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.stat-info { flex: 1; min-width: 0; }
.stat-value { font-weight: 900; font-size: 30px; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 3px; }
.stat-trend { font-size: 11px; font-weight: 700; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.stat-trend.up   { color: var(--green); }
.stat-trend.info { color: var(--blue); }

/* ══════════════════════════════════════════════════════
   TWO COLUMN LAYOUT
══════════════════════════════════════════════════════ */
.two-col {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 20px;
}
.one-col { display: flex; flex-direction: column; gap: 20px; }

/* ══════════════════════════════════════════════════════
   SECTION CARD
══════════════════════════════════════════════════════ */
.section-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px;
    box-shadow: var(--shadow);
}
.section-head {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 18px;
}
.section-head h3 { font-weight: 800; font-size: 15px; flex: 1; }
.section-head .badge-count {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 11px; font-weight: 700; color: var(--muted);
    padding: 3px 10px;
}
.section-icon {
    width: 32px; height: 32px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════
   DISPATCH (ACTIVE JOB)
══════════════════════════════════════════════════════ */
.standby-wrap {
    text-align: center; padding: 32px 20px;
}
.standby-icon {
    width: 80px; height: 80px; border-radius: 24px;
    background: rgba(37,99,235,.08);
    display: flex; align-items: center; justify-content: center;
    font-size: 34px; color: var(--blue);
    margin: 0 auto 18px;
    animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
.standby-title { font-weight: 800; font-size: 17px; margin-bottom: 8px; }
.standby-sub   { font-size: 13px; color: var(--muted); line-height: 1.6; }

/* Dispatch active card */
.dispatch-card {
    border-radius: 14px;
    border: 1.5px solid var(--border);
    padding: 20px;
    background: var(--surface2);
}
.dispatch-card.sos-new {
    border-color: var(--red) !important;
    animation: sos-pulse 2s infinite;
}
@keyframes sos-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.45); }
    70%  { box-shadow: 0 0 0 14px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
}
.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    border-radius: 20px; padding: 5px 14px;
    font-size: 12px; font-weight: 700; letter-spacing: 0.3px;
}
.dispatch-title  { font-weight: 900; font-size: 18px; margin: 12px 0 4px; }
.dispatch-loc    { font-size: 13px; color: var(--muted); font-weight: 600; }
.info-row        { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.info-col        { background: var(--bg); border-radius: 12px; padding: 11px 14px; }
.info-col-label  { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.info-col-val    { font-size: 14px; font-weight: 700; margin-top: 3px; }

/* Action buttons */
.act-btn {
    width: 100%; height: 48px; border-radius: 12px; border: none;
    font-family: 'Inter', sans-serif; font-weight: 800; font-size: 14px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    cursor: pointer; transition: all .2s; color: #fff; margin-top: 14px;
}
.act-btn:hover   { transform: translateY(-1px); }
.act-btn:active  { transform: scale(.98); opacity: .9; }
.btn-accept  { background: linear-gradient(135deg,#1d4ed8,#2563eb); box-shadow: 0 6px 16px rgba(37,99,235,.35); }
.btn-enroute { background: linear-gradient(135deg,#b45309,#f59e0b); box-shadow: 0 6px 16px rgba(245,158,11,.35); }
.btn-arrived { background: linear-gradient(135deg,#0e7490,#06b6d4); box-shadow: 0 6px 16px rgba(6,182,212,.35); }
.btn-done    { background: linear-gradient(135deg,#047857,#10b981); box-shadow: 0 6px 16px rgba(16,185,129,.35); }

/* call btn */
.call-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(16,185,129,.1); color: var(--green);
    border: 1.5px solid rgba(16,185,129,.3); border-radius: 10px;
    padding: 8px 14px; font-size: 13px; font-weight: 700;
    text-decoration: none; transition: all .2s; margin-top: 0;
}
.call-btn:hover { background: rgba(16,185,129,.2); }

/* ══════════════════════════════════════════════════════
   MAP
══════════════════════════════════════════════════════ */
#driverMap {
    height: 280px; border-radius: 14px;
    overflow: hidden; border: 1px solid var(--border);
}
.gps-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; color: var(--green);
    margin-left: auto;
}

/* ══════════════════════════════════════════════════════
   RECENT MISSIONS
══════════════════════════════════════════════════════ */
.mission-item {
    display: flex; align-items: center; gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.mission-item:last-child { border-bottom: none; padding-bottom: 0; }
.mission-icon-wrap {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.mission-info { flex: 1; min-width: 0; }
.mission-type { font-weight: 700; font-size: 13px; }
.mission-loc  { font-size: 12px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mission-right{ text-align: right; flex-shrink: 0; }
.mission-date  { font-size: 11px; color: var(--muted); font-weight: 600; }
.mission-status-pill {
    display: inline-block; margin-top: 4px;
    font-size: 10px; font-weight: 700; border-radius: 20px;
    padding: 2px 9px;
}

/* ══════════════════════════════════════════════════════
   UNIT INFO CARD
══════════════════════════════════════════════════════ */
.unit-card-header {
    background: <?php echo $tm['grad']; ?>;
    border-radius: 14px; padding: 22px 20px;
    margin-bottom: 16px; position: relative; overflow: hidden;
}
.unit-card-header::before {
    content: ''; position: absolute; top: -30px; right: -30px;
    width: 120px; height: 120px; border-radius: 50%;
    background: rgba(255,255,255,.1);
}
.unit-card-header::after {
    content: ''; position: absolute; bottom: -40px; left: 20px;
    width: 90px; height: 90px; border-radius: 50%;
    background: rgba(255,255,255,.06);
}
.uc-icon { font-size: 36px; color: rgba(255,255,255,.9); margin-bottom: 12px; }
.uc-name { font-weight: 900; font-size: 18px; color: #fff; }
.uc-meta { font-size: 12px; color: rgba(255,255,255,.75); margin-top: 4px; font-weight: 500; }
.uc-plate {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.18); border-radius: 20px;
    padding: 5px 14px; font-size: 12px; font-weight: 700; color: #fff;
    margin-top: 12px;
}

.unit-detail-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 0; border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.unit-detail-row:last-child { border-bottom: none; padding-bottom: 0; }
.unit-detail-key { color: var(--muted); font-weight: 600; display: flex; align-items: center; gap: 8px; }
.unit-detail-key i { width: 16px; text-align: center; color: var(--blue); font-size: 12px; }
.unit-detail-val { font-weight: 700; text-align: right; }
.status-dot-text {
    display: inline-flex; align-items: center; gap: 6px;
    font-weight: 700;
}
.dot {
    width: 8px; height: 8px; border-radius: 50%;
}
.dot.green { background: var(--green); box-shadow: 0 0 6px var(--green); }
.dot.red   { background: var(--red); }
.dot.amber { background: var(--amber); }

/* ══════════════════════════════════════════════════════
   MOBILE HAMBURGER
══════════════════════════════════════════════════════ */
.mob-toggle {
    display: none;
    position: fixed; top: 14px; left: 14px; z-index: 500;
    width: 40px; height: 40px; border-radius: 12px;
    background: var(--surface); border: 1px solid var(--border);
    box-shadow: var(--shadow);
    align-items: center; justify-content: center;
    cursor: pointer; font-size: 16px; color: var(--text);
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 399;
}

/* Leaflet overrides */
.leaflet-control-attribution { display: none !important; }
.leaflet-control-zoom { border: none !important; border-radius: 10px !important; overflow: hidden; }
.leaflet-control-zoom a {
    width: 32px !important; height: 32px !important; line-height: 32px !important;
    font-size: 15px !important;
    background: var(--surface) !important; color: var(--text) !important;
}

/* ══════════════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════════════ */
@media (max-width: 1024px) {
    .two-col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .mob-toggle { display: flex; }
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .sidebar-overlay.open { display: block; }
    .topbar { left: 0; padding-left: 60px; }
    .main   { margin-left: 0; }
    .page-content { padding: 20px 16px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .welcome-banner { flex-direction: column; align-items: flex-start; gap: 14px; }
    .wb-right { align-items: flex-start; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
}
@keyframes spin { to { transform: rotate(360deg) } }
</style>
</head>
<body>

<!-- ── MOBILE HAMBURGER ──────────────────────────────── -->
<button class="mob-toggle" id="mobToggle" onclick="toggleSidebar()" aria-label="Menu">
    <i class="fa-solid fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ══════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
        <div>
            <div class="logo-text">SmartRescue</div>
            <div class="logo-sub">Driver Portal</div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>

        <a href="index.php" class="nav-item active" id="nav-dashboard">
            <i class="fa-solid fa-house-chimney"></i>
            Dashboard
        </a>

        <a href="map.php" class="nav-item" id="nav-map">
            <i class="fa-solid fa-map-location-dot"></i>
            Live Map
        </a>

        <a href="history.php" class="nav-item" id="nav-history">
            <i class="fa-solid fa-clock-rotate-left"></i>
            History
            <?php if ($total_missions > 0): ?>
            <span class="nav-badge green"><?php echo $total_missions; ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-label" style="margin-top:8px">Account</div>

        <a href="profile.php" class="nav-item" id="nav-profile">
            <i class="fa-solid fa-user-shield"></i>
            Profile
        </a>

        <a href="settings.php" class="nav-item" id="nav-settings">
            <i class="fa-solid fa-gear"></i>
            Settings
        </a>
    </nav>

    <!-- Unit badge -->
    <div class="sidebar-unit">
        <div class="sidebar-unit-label">Assigned Unit</div>
        <div class="sidebar-unit-name"><?php echo htmlspecialchars($unit_name); ?></div>
        <div class="sidebar-unit-meta"><?php echo htmlspecialchars($plate_num); ?> · <?php echo $unit_type_label; ?></div>
    </div>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>
    </div>

</aside>

<!-- ══════════════════════════════════════════════════
     TOPBAR
══════════════════════════════════════════════════════ -->
<header class="topbar">
    <div class="topbar-breadcrumb">
        <span class="page-title">Dashboard</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($unit_name); ?></span>
    </div>

    <div class="topbar-actions">

        <!-- Online Toggle Switch -->
        <label class="toggle-switch" onclick="toggleDispatch()">
            <div id="onlinePill" class="toggle-track <?php echo $is_avail ? 'on' : 'off'; ?>">
                <div class="toggle-knob"></div>
            </div>
            <span id="onlineText" class="toggle-label"><?php echo $is_avail ? 'Online' : 'Offline'; ?></span>
        </label>

        <!-- Theme toggle -->
        <button class="icon-btn" id="themeBtn" onclick="toggleTheme()" title="Toggle theme">
            <i class="fa-solid <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>" id="themeIcon"></i>
        </button>

        <!-- Avatar -->
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

<!-- ══════════════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════════════════ -->
<main class="main">
<div class="page-content">

    <!-- WELCOME BANNER -->
    <div class="welcome-banner">
        <div class="wb-avatar">
            <i class="fa-solid <?php echo $tm['icon']; ?>"></i>
        </div>
        <div class="wb-info">
            <div class="wb-name"><?php echo htmlspecialchars($fullname); ?></div>
            <div class="wb-sub">
                <span><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($email ?: 'No email'); ?></span>
                <?php if ($phone): ?>
                <span><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($phone); ?></span>
                <?php endif; ?>
                <span><i class="fa-solid fa-<?php echo $is_avail ? 'circle-check' : 'circle-xmark'; ?>" style="color:<?php echo $is_avail ? 'var(--green)' : 'var(--red)'; ?>"></i>
                    <?php echo $is_avail ? 'Online & Available' : 'Offline'; ?>
                </span>
            </div>
            <div class="wb-rank">
                <i class="fa-solid fa-trophy"></i>
                <?php echo $rank; ?>
            </div>
        </div>
        <div class="wb-right">
            <div class="wb-unit-label">Unit</div>
            <div class="wb-unit-name"><?php echo htmlspecialchars($unit_name); ?></div>
            <div class="wb-unit-code"><?php echo htmlspecialchars($plate_num); ?></div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card saves">
            <div class="stat-icon-wrap" style="background:rgba(16,185,129,.12);color:#10b981">
                <i class="fa-solid fa-shield-heart"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="statSaves"><?php echo $total_saves; ?></div>
                <div class="stat-label">Lives Saved</div>
                <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Expert Responder</div>
            </div>
        </div>

        <div class="stat-card missions">
            <div class="stat-icon-wrap" style="background:rgba(37,99,235,.12);color:#2563eb">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $total_missions; ?></div>
                <div class="stat-label">Total Missions</div>
                <div class="stat-trend info"><i class="fa-solid fa-calendar"></i> All time</div>
            </div>
        </div>

        <div class="stat-card active">
            <div class="stat-icon-wrap" style="background:rgba(245,158,11,.12);color:#f59e0b">
                <i class="fa-solid fa-location-crosshairs"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" id="statGps" style="font-size:18px;padding-top:4px">—</div>
                <div class="stat-label">GPS Accuracy</div>
                <div class="stat-trend" style="color:var(--muted)"><i class="fa-solid fa-satellite"></i> Tracking live</div>
            </div>
        </div>
    </div>

    <!-- TWO COLUMN LAYOUT -->
    <div class="two-col">

        <!-- LEFT COLUMN -->
        <div class="one-col">

            <!-- ACTIVE DISPATCH -->
            <div class="section-card" id="dispatchSection">
                <div class="section-head">
                    <div class="section-icon" style="background:rgba(239,68,68,.1);color:#ef4444">
                        <i class="fa-solid fa-bolt-lightning"></i>
                    </div>
                    <h3>Active Dispatch</h3>
                    <span id="dispatchStatus" style="font-size:11px;font-weight:700;color:var(--muted)">Checking…</span>
                </div>
                <div id="dispatchBody">
                    <div class="standby-wrap">
                        <div style="width:36px;height:36px;margin:0 auto 12px">
                            <svg viewBox="0 0 50 50" style="animation:spin .9s linear infinite;width:36px;height:36px">
                                <circle cx="25" cy="25" r="20" fill="none" stroke="var(--blue)" stroke-width="5" stroke-dasharray="90 60"/>
                            </svg>
                        </div>
                        <div style="color:var(--muted);font-size:14px;font-weight:600">Checking dispatch server…</div>
                    </div>
                </div>
            </div>

            <!-- RECENT MISSIONS -->
            <div class="section-card">
                <div class="section-head">
                    <div class="section-icon" style="background:rgba(99,102,241,.1);color:#6366f1">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <h3>Recent Missions</h3>
                    <a href="history.php" style="font-size:12px;color:var(--blue);font-weight:700;text-decoration:none;margin-left:auto">View all →</a>
                </div>
                <?php if (empty($recent_missions)): ?>
                <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;font-weight:600">
                    <i class="fa-solid fa-inbox" style="font-size:28px;margin-bottom:8px;display:block;opacity:.4"></i>
                    No missions yet
                </div>
                <?php else: ?>
                <?php foreach ($recent_missions as $m):
                    $st = $m['status'] ?? 'pending';
                    $etype = ucfirst($m['emergency_type'] ?? 'Medical');
                    $loc   = $m['neighborhood'] ?? 'Unknown';
                    $date  = date('M j, g:i a', strtotime($m['created_at']));
                    $pill_bg  = ['completed'=>'rgba(16,185,129,.1)', 'pending'=>'rgba(239,68,68,.1)', 'accepted'=>'rgba(37,99,235,.1)', 'en_route'=>'rgba(245,158,11,.1)', 'arrived'=>'rgba(6,182,212,.1)'][$st] ?? 'rgba(100,116,139,.1)';
                    $pill_col = ['completed'=>'#10b981', 'pending'=>'#ef4444', 'accepted'=>'#2563eb', 'en_route'=>'#f59e0b', 'arrived'=>'#06b6d4'][$st] ?? '#64748b';
                    $pill_txt = ['completed'=>'Completed', 'pending'=>'Pending', 'accepted'=>'Accepted', 'en_route'=>'En Route', 'arrived'=>'Arrived'][$st] ?? ucfirst($st);
                ?>
                <div class="mission-item">
                    <div class="mission-icon-wrap" style="background:<?php echo $tm['bg']; ?>;color:<?php echo $tm['color']; ?>">
                        <i class="fa-solid <?php echo $tm['icon']; ?>"></i>
                    </div>
                    <div class="mission-info">
                        <div class="mission-type"><?php echo $etype; ?> Emergency</div>
                        <div class="mission-loc"><i class="fa-solid fa-location-dot" style="font-size:10px;margin-right:3px;color:var(--blue)"></i><?php echo htmlspecialchars($loc); ?></div>
                    </div>
                    <div class="mission-right">
                        <div class="mission-date"><?php echo $date; ?></div>
                        <span class="mission-status-pill" style="background:<?php echo $pill_bg; ?>;color:<?php echo $pill_col; ?>"><?php echo $pill_txt; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /left col -->

        <!-- RIGHT COLUMN -->
        <div class="one-col">

            <!-- UNIT INFO CARD -->
            <div class="section-card" style="padding:0;overflow:hidden">
                <div class="unit-card-header">
                    <div class="uc-icon"><i class="fa-solid <?php echo $tm['icon']; ?>"></i></div>
                    <div class="uc-name"><?php echo htmlspecialchars($unit_name); ?></div>
                    <div class="uc-meta"><?php echo $unit_type_label; ?> Response Unit</div>
                    <div class="uc-plate">
                        <i class="fa-solid fa-id-card"></i>
                        <?php echo htmlspecialchars($plate_num); ?>
                    </div>
                </div>
                <div style="padding:18px 20px">
                    <div class="unit-detail-row">
                        <span class="unit-detail-key"><i class="fa-solid fa-circle-dot"></i> Status</span>
                        <span class="unit-detail-val">
                            <span class="status-dot-text">
                                <span class="dot <?php echo $is_avail ? 'green' : 'red'; ?>"></span>
                                <span id="unitStatusText"><?php echo $is_avail ? 'Available' : 'Offline'; ?></span>
                            </span>
                        </span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-key"><i class="fa-solid fa-tag"></i> Unit Type</span>
                        <span class="unit-detail-val"><?php echo $unit_type_label; ?></span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-key"><i class="fa-solid fa-shield-heart"></i> Lives Saved</span>
                        <span class="unit-detail-val" style="color:var(--green)"><?php echo $total_saves; ?></span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-key"><i class="fa-solid fa-flag-checkered"></i> Missions</span>
                        <span class="unit-detail-val"><?php echo $total_missions; ?></span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-key"><i class="fa-solid fa-location-crosshairs"></i> GPS</span>
                        <span class="unit-detail-val" id="gpsDetailVal">Acquiring…</span>
                    </div>
                </div>
            </div>



        </div><!-- /right col -->

    </div><!-- /two-col -->

</div><!-- /page-content -->
</main>

<!-- SCRIPTS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const DRV_ID  = <?php echo $driver_id; ?>;
const UNIT_ID = <?php echo $unit_id; ?>;
let dLat = 2.0469, dLng = 45.3182;
let map, driverMark, victimMark, routeLine;
let isOnline = <?php echo $is_avail ? 'true' : 'false'; ?>;
let lastAlertId = null, lastAudio = 0;

/* ── MAP INIT ───────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('driverMap');
    if (mapEl) {
        map = L.map('driverMap', { zoomControl: false }).setView([dLat, dLng], 14);
        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20, attribution: '&copy; Google Maps'
        }).addTo(map);
        driverMark = L.marker([dLat, dLng], { icon: driverIcon() }).addTo(map);
    }
    startGPS();
    poll();
    setInterval(poll, 3500);
});

function driverIcon() {
    return L.divIcon({
        className: '',
        html: `
            <div style="position:relative; width:70px; height:70px; display:flex; align-items:center; justify-content:center;">
                <div class="drv-pulse2" style="
                    position:absolute; border-radius:50%;
                    width:68px; height:68px; z-index:0;
                "></div>
                <div style="
                    position:absolute; width:50px; height:50px; border-radius:50%;
                    background:#ffffff;
                    box-shadow: 0 0 14px 5px rgba(37,99,235,0.4);
                    z-index:1;
                "></div>
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
                <div style="
                    position:absolute; top:6px; right:6px; z-index:3;
                    width:9px; height:9px; border-radius:50%;
                    background:#60a5fa; border:1.5px solid #fff;
                    box-shadow: 0 0 7px #2563eb;
                "></div>
            </div>
            <style>
            @keyframes drvPulse2 {
                0%   { transform:scale(0.65); background:rgba(37,99,235,0.25); opacity:0.95; }
                100% { transform:scale(1.05); background:rgba(37,99,235,0.0);  opacity:0; }
            }
            .drv-pulse2 { animation: drvPulse2 1.2s ease-out infinite; }
            </style>`,
        iconSize: [70, 70],
        iconAnchor: [35, 35]
    });
}
function victimIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="width:14px;height:14px;border-radius:50%;background:#2563eb;box-shadow:0 2px 6px rgba(0,0,0,0.35);border:1px solid #1d4ed8;"></div>`,
        iconSize: [14, 14], iconAnchor: [7, 7]
    });
}

/* ── GPS ───────────────────────────────── */
function startGPS() {
    if (!('geolocation' in navigator)) return;
    navigator.geolocation.watchPosition(pos => {
        dLat = pos.coords.latitude;
        dLng = pos.coords.longitude;
        const acc = Math.round(pos.coords.accuracy);
        if (driverMark) driverMark.setLatLng([dLat, dLng]);
        const sGps = document.getElementById('statGps');
        if (sGps) sGps.textContent = '±' + acc + 'm';
        const dGps = document.getElementById('gpsDetailVal');
        if (dGps) dGps.textContent = '±' + acc + 'm';
        const bGps = document.getElementById('gpsBadge');
        if (bGps) bGps.innerHTML = '<i class="fa-solid fa-circle" style="font-size:8px"></i> ±' + acc + 'm';

        fetch('../api/driver/update_driver_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lat=${dLat}&lng=${dLng}&driver_id=${DRV_ID}`
        }).catch(() => {});

        if (routeLine && victimMark) {
            const vll = victimMark.getLatLng();
            routeLine.setLatLngs([[dLat,dLng],[vll.lat,vll.lng]]);
        }
    }, err => {
        const bGps = document.getElementById('gpsBadge');
        if (bGps) bGps.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b"></i> No GPS';
        const dGps = document.getElementById('gpsDetailVal');
        if (dGps) dGps.textContent = 'Unavailable';
    }, { enableHighAccuracy: true, maximumAge: 2000, timeout: 10000 });
}

/* ── POLL ──────────────────────────────── */
function poll() {
    fetch(`../api/driver/get_active_job.php?driver_id=${DRV_ID}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.request) {
                renderDispatch(data.request);
                checkAndShowDispatchModal(data.request);
                document.getElementById('dispatchStatus').textContent = 'Active';
                document.getElementById('dispatchStatus').style.color = '#ef4444';
            } else {
                renderStandby();
                hideDispatchModal();
                document.getElementById('dispatchStatus').textContent = 'On Standby';
                document.getElementById('dispatchStatus').style.color = 'var(--muted)';
            }
        }).catch(() => {});
}

function renderStandby() {
    document.getElementById('dispatchBody').innerHTML = `
        <div class="standby-wrap">
            <div class="standby-icon"><i class="fa-solid fa-satellite-dish"></i></div>
            <div class="standby-title">On Standby</div>
            <div class="standby-sub">Waiting for dispatch. New assignments will appear here and trigger an <strong>alert</strong>.</div>
        </div>`;
    if (victimMark) { map.removeLayer(victimMark); victimMark = null; }
    if (routeLine)  { map.removeLayer(routeLine);  routeLine  = null; }
}

function renderDispatch(req) {
    const st     = req.status;
    const etype  = req.emergency_type || 'Medical';
    const pName  = req.patient_name   || 'Victim';
    const pPhone = req.patient_phone  || '';
    const loc    = req.neighborhood   || 'Unknown area';
    const desc   = req.description    || '—';
    const vLat   = parseFloat(req.lat);
    const vLng   = parseFloat(req.lng);

    if (st === 'pending' && req.id !== lastAlertId) { lastAlertId = req.id; playAlert(); }

    const badges = {
        pending:  ['rgba(239,68,68,.1)','#ef4444','fa-circle-exclamation','NEW SOS ALERT'],
        accepted: ['rgba(37,99,235,.1)','#3b82f6','fa-check-circle',      'DISPATCH ACCEPTED'],
        en_route: ['rgba(245,158,11,.1)','#f59e0b','fa-truck-fast',       'EN ROUTE'],
        arrived:  ['rgba(16,185,129,.1)','#10b981','fa-location-dot',     'ON SCENE'],
    };
    const [bbg,btx,bic,blbl] = badges[st] || badges.accepted;

    const actionBtns = {
        pending:  `<button class="act-btn btn-accept"  onclick="doAction(${req.id},'accept')"><i class="fa-solid fa-check-double"></i> Accept Emergency Dispatch</button>`,
        accepted: `<button class="act-btn btn-enroute" onclick="doAction(${req.id},'on_the_way')"><i class="fa-solid fa-truck-fast"></i> Start Trip</button>`,
        en_route: `<button class="act-btn btn-arrived" onclick="doAction(${req.id},'arrived')"><i class="fa-solid fa-flag-checkered"></i> Arrived At Scene</button>`,
        arrived:  `<button class="act-btn btn-done"    onclick="doAction(${req.id},'complete')"><i class="fa-solid fa-circle-check"></i> Complete & Resolve Mission</button>`,
    };

    document.getElementById('dispatchBody').innerHTML = `
        <div class="dispatch-card ${st === 'pending' ? 'sos-new' : ''}">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <span class="status-badge" style="background:${bbg};color:${btx}">
                    <i class="fa-solid ${bic}"></i> ${blbl}
                </span>
                ${pPhone ? `<a href="tel:${pPhone}" class="call-btn"><i class="fa-solid fa-phone"></i> Call Victim</a>` : ''}
            </div>
            <div class="dispatch-title"><i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;margin-right:8px;font-size:16px"></i>${etype} Emergency</div>
            <div class="dispatch-loc"><i class="fa-solid fa-location-dot" style="color:var(--blue);margin-right:4px"></i>${loc}</div>
            <div class="info-row">
                <div class="info-col">
                    <div class="info-col-label">Victim Name</div>
                    <div class="info-col-val">${pName}</div>
                </div>
                <div class="info-col">
                    <div class="info-col-label">Phone</div>
                    <div class="info-col-val">${pPhone || 'N/A'}</div>
                </div>
            </div>
            ${desc !== '—' ? `<div style="margin-top:12px;padding:12px;background:var(--bg);border-radius:10px;font-size:13px;color:var(--muted);line-height:1.6">${desc}</div>` : ''}
            ${actionBtns[st] || ''}
        </div>`;

    if (!isNaN(vLat) && !isNaN(vLng)) {
        if (!victimMark) victimMark = L.marker([vLat,vLng],{icon:victimIcon()}).addTo(map);
        else victimMark.setLatLng([vLat,vLng]);
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline([[dLat,dLng],[vLat,vLng]],{
            color:'#ef4444', weight:4, dashArray:'10,8', opacity:0.9
        }).addTo(map);
        map.fitBounds(L.featureGroup([driverMark,victimMark]).getBounds().pad(0.3));
    }
}

/* ── ACTIONS ───────────────────────────── */
function doAction(rid, action) {
    fetch('../api/driver/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `request_id=${rid}&unit_id=${UNIT_ID}&action=${action}&driver_id=${DRV_ID}`
    }).then(r => r.json()).then(() => poll()).catch(() => {});
}

function toggleDispatch() {
    const newStatus = isOnline ? 'offline' : 'available';
    fetch('../api/driver/update_unit_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `status=${newStatus}&driver_id=${DRV_ID}`
    }).then(r => r.json()).then(d => {
        if (d.status === 'success') {
            isOnline = !isOnline;
            const pill = document.getElementById('onlinePill');
            pill.className = 'toggle-track ' + (isOnline ? 'on' : 'off');
            document.getElementById('onlineText').textContent = isOnline ? 'Online' : 'Offline';
            document.getElementById('unitStatusText').textContent = isOnline ? 'Available' : 'Offline';
            const dot = pill.querySelector('.online-dot');
        }
    }).catch(() => {});
}

/* ── THEME ─────────────────────────────── */
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    document.getElementById('themeIcon').className = 'fa-solid ' + (isDark ? 'fa-moon' : 'fa-sun');
    fetch('../api/driver/save_setting.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `key=dark_mode&value=${isDark ? 0 : 1}&driver_id=${DRV_ID}`
    }).catch(() => {});
}

/* ── MOBILE SIDEBAR ────────────────────── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ── ALERT SOUND ───────────────────────── */
function playAlert() {
    const now = Date.now();
    if (now - lastAudio < 5000) return;
    lastAudio = now;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [[880,0],[1100,0.15],[880,0.30]].forEach(([f,t]) => {
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = f;
            g.gain.setValueAtTime(0.3, ctx.currentTime + t);
            g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + t + 0.12);
            o.connect(g); g.connect(ctx.destination);
            o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.15);
        });
    } catch(e) {}
}
</script>
<?php require_once 'dispatch_modal.php'; ?>
</body>
</html>
