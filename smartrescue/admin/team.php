<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Fetch all drivers with their assigned unit info
$q = "SELECT u.id, u.fullname, u.email, u.phone, u.profile_image, u.created_at,
             e.unit_name, e.unit_type, e.status as unit_status, e.id as unit_id
      FROM users u
      LEFT JOIN emergency_units e ON e.driver_id = u.id
      WHERE u.role = 'driver'
      ORDER BY u.fullname ASC";
$res = mysqli_query($conn, $q);
$team = [];
while ($row = mysqli_fetch_assoc($res)) $team[] = $row;

$total     = count($team);
$on_mission = count(array_filter($team, fn($t) => $t['unit_status'] === 'busy'));
$available  = count(array_filter($team, fn($t) => $t['unit_status'] === 'available'));
$offline    = count(array_filter($team, fn($t) => empty($t['unit_status']) || $t['unit_status'] === 'offline'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Responder | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
/* ── RESET & VARS ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:          #f0f4f9;
    --card:        #ffffff;
    --text:        #0f172a;
    --muted:       #64748b;
    --border:      #e4eaf3;
    --accent:      #3b82f6;
    --accent-dark: #1d4ed8;
    --green:       #22c55e;
    --green-bg:    rgba(34,197,94,.09);
    --amber:       #f59e0b;
    --amber-bg:    rgba(245,158,11,.09);
    --red:         #ef4444;
    --red-bg:      rgba(239,68,68,.09);
    --gray:        #94a3b8;
    --gray-bg:     rgba(148,163,184,.08);
    --sidebar-w:   268px;
    --topbar-h:    64px;
    --shadow-sm:   0 1px 4px rgba(0,0,0,.05);
    --shadow-md:   0 4px 24px rgba(0,0,0,.07);
    --shadow-lg:   0 12px 40px rgba(0,0,0,.11);
    --r-sm:        8px;
    --r-md:        12px;
    --r-lg:        16px;
    --r-xl:        20px;
}
body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }

/* ── LAYOUT ──────────────────────────────────────────────────────── */
.main-wrapper {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── STICKY TOP BAR ──────────────────────────────────────────────── */
.team-topbar {
    position: sticky; top: 0; z-index: 200;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    height: var(--topbar-h);
    display: flex; align-items: center;
    padding: 0 28px; gap: 16px;
    box-shadow: var(--shadow-sm);
}
.team-topbar-title {
    font-size: 1.08rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px;
}
.team-topbar-icon {
    width: 34px; height: 34px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .85rem;
    box-shadow: 0 4px 12px rgba(59,130,246,.3);
    flex-shrink: 0;
}
.topbar-spacer { flex: 1; }

/* Live clock */
.topbar-clock {
    display: flex; align-items: center; gap: 6px;
    font-size: .78rem; font-weight: 700; color: var(--muted);
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: 50px; padding: 6px 14px;
}
.clock-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }

/* Status indicator */
.topbar-status {
    display: flex; align-items: center; gap: 6px;
    font-size: .75rem; font-weight: 800;
    color: var(--green);
    background: var(--green-bg);
    border: 1px solid rgba(34,197,94,.2);
    border-radius: 50px; padding: 5px 12px;
}

/* Add Responder btn */
.btn-add-responder {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 20px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff; font-family: 'Outfit', sans-serif;
    font-weight: 700; font-size: .83rem;
    border: none; border-radius: 50px;
    text-decoration: none; cursor: pointer;
    box-shadow: 0 4px 14px rgba(59,130,246,.3);
    transition: transform .2s, box-shadow .2s;
    white-space: nowrap;
}
.btn-add-responder:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(59,130,246,.4); color: #fff; }

/* ── CONTENT AREA ────────────────────────────────────────────────── */
.content-area {
    padding: 28px 28px 40px;
    display: flex; flex-direction: column; gap: 24px;
}

/* ── SUCCESS ALERT ───────────────────────────────────────────────── */
.success-alert {
    display: flex; align-items: center; gap: 12px;
    background: var(--green-bg);
    border: 1px solid rgba(34,197,94,.2);
    border-left: 4px solid var(--green);
    border-radius: var(--r-md);
    padding: 14px 18px;
    font-size: .88rem; font-weight: 600; color: #15803d;
    animation: slideDown .35s ease;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── STAT STRIP ──────────────────────────────────────────────────── */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
.stat-card {
    background: var(--card);
    border-radius: var(--r-lg);
    padding: 16px 18px;
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex; align-items: center; gap: 14px;
    cursor: pointer;
    transition: all .22s;
    position: relative; overflow: hidden;
}
.stat-card::after {
    content: ''; position: absolute;
    bottom: 0; left: 0; right: 0; height: 3px;
    background: transparent;
    border-radius: 0 0 var(--r-lg) var(--r-lg);
    transition: background .22s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card.active         { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.1), var(--shadow-md); }
.stat-card.active::after  { background: var(--accent); }
.stat-card.active-green   { border-color: var(--green);  box-shadow: 0 0 0 3px rgba(34,197,94,.1), var(--shadow-md); }
.stat-card.active-green::after  { background: var(--green); }
.stat-card.active-amber   { border-color: var(--amber);  box-shadow: 0 0 0 3px rgba(245,158,11,.1), var(--shadow-md); }
.stat-card.active-amber::after  { background: var(--amber); }
.stat-card.active-gray    { border-color: var(--gray);   box-shadow: 0 0 0 3px rgba(148,163,184,.12), var(--shadow-md); }
.stat-card.active-gray::after   { background: var(--gray); }
.stat-icon {
    width: 42px; height: 42px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; flex-shrink: 0;
}
.stat-num   { font-size: 1.6rem; font-weight: 900; line-height: 1; }
.stat-label { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); margin-top: 3px; }

/* ── TOOLBAR ─────────────────────────────────────────────────────── */
.toolbar {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.search-wrap { position: relative; flex: 1; min-width: 240px; }
.search-wrap i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .82rem; pointer-events: none;
}
.search-input {
    width: 100%; padding: 10px 14px 10px 38px;
    border: 1.5px solid var(--border); border-radius: 50px;
    font-family: 'Outfit', sans-serif; font-size: .85rem; font-weight: 500;
    color: var(--text); background: var(--card); outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.search-input::placeholder { color: var(--muted); }

.filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    border: 1.5px solid var(--border);
    background: var(--card);
    border-radius: 50px;
    font-family: 'Outfit', sans-serif; font-size: .78rem; font-weight: 700;
    color: var(--muted); cursor: pointer; transition: all .2s;
}
.filter-pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,.04); }
.filter-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.fp-dot { width: 6px; height: 6px; border-radius: 50%; }

/* Results count */
.results-label {
    font-size: .75rem; font-weight: 700; color: var(--muted);
    margin-left: auto; white-space: nowrap;
}

/* ── TEAM GRID ───────────────────────────────────────────────────── */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(272px, 1fr));
    gap: 18px;
}

/* ── MEMBER CARD ─────────────────────────────────────────────────── */
.member-card {
    background: var(--card);
    border-radius: var(--r-xl);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: transform .22s, box-shadow .22s, border-color .22s;
    display: flex; flex-direction: column;
    animation: fadeUp .4s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.member-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: #cdd9ec; }
.member-card.hidden { display: none; }

/* Top accent strip */
.card-accent-bar { height: 4px; width: 100%; }

/* Card body */
.card-body-inner { padding: 22px 20px 0; text-align: center; }

/* Avatar */
.member-avatar {
    width: 68px; height: 68px;
    border-radius: 18px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; font-weight: 900; color: #fff;
    margin: 0 auto 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,.14);
    position: relative;
    overflow: hidden;
}
.avatar-status-dot {
    position: absolute; bottom: -3px; right: -3px;
    width: 14px; height: 14px; border-radius: 50%;
    border: 2.5px solid var(--card);
}

/* Name & role */
.member-name { font-size: 1rem; font-weight: 800; color: var(--text); margin-bottom: 2px; }
.member-role { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: var(--muted); margin-bottom: 14px; }

/* Phone */
.member-phone {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .8rem; font-weight: 600; color: var(--muted);
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 50px; padding: 5px 13px;
    margin-bottom: 14px;
    text-decoration: none; transition: all .2s;
}
.member-phone:hover { color: var(--accent); border-color: var(--accent); background: rgba(59,130,246,.05); }

/* Status pill */
.status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 50px;
    font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .7px;
}
.sb-available { background: var(--green-bg);  color: #15803d; }
.sb-busy      { background: var(--amber-bg);  color: #b45309; }
.sb-offline   { background: var(--gray-bg);   color: #64748b; }
.sb-dot { width: 6px; height: 6px; border-radius: 50%; }

/* Unit tag */
.unit-tag {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 8px; margin-bottom: 18px;
    padding: 4px 11px; border-radius: var(--r-sm);
    background: var(--bg); border: 1px solid var(--border);
    font-size: .72rem; font-weight: 700; color: var(--muted);
}
.unit-tag i { font-size: .68rem; }

/* No unit */
.no-unit-tag { color: #b0bcc9; font-size: .72rem; font-weight: 600; margin: 8px 0 18px; }

/* Card divider */
.card-divider { height: 1px; background: var(--border); margin: 0 -1px; }

/* Card actions */
.card-actions {
    display: flex; gap: 6px;
    padding: 14px 16px;
}
.ca-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
    padding: 9px 8px;
    border-radius: var(--r-sm);
    font-family: 'Outfit', sans-serif; font-size: .76rem; font-weight: 700;
    border: 1.5px solid transparent;
    cursor: pointer; transition: all .18s; text-decoration: none;
    white-space: nowrap;
}
.ca-call {
    background: var(--green-bg);
    color: #15803d;
    border-color: rgba(34,197,94,.2);
}
.ca-call:hover { background: rgba(34,197,94,.16); color: #166534; }
.ca-logs {
    background: var(--bg);
    color: var(--muted);
    border-color: var(--border);
}
.ca-logs:hover { background: var(--border); color: var(--text); }
.ca-delete {
    background: none;
    color: var(--red);
    border-color: rgba(239,68,68,.2);
    background: var(--red-bg);
}
.ca-delete:hover { background: rgba(239,68,68,.14); border-color: rgba(239,68,68,.3); }

/* ── EMPTY STATE ─────────────────────────────────────────────────── */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 24px;
    color: var(--muted);
}
.empty-state-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--bg); border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin: 0 auto 16px; opacity: .4;
}
.empty-state h3 { font-size: 1rem; font-weight: 800; margin-bottom: 8px; }
.empty-state p  { font-size: .85rem; font-weight: 500; }
.empty-state a  { color: var(--accent); font-weight: 700; text-decoration: none; }

/* No results message */
#no-results {
    display: none; grid-column: 1/-1;
    text-align: center; padding: 48px 24px;
    font-size: .88rem; font-weight: 600; color: var(--muted);
    background: var(--card); border-radius: var(--r-lg);
    border: 1.5px dashed var(--border);
}

/* ── DELETE MODAL ────────────────────────────────────────────────── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55); backdrop-filter: blur(4px);
    z-index: 9999; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .25s;
}
.modal-overlay.show { display: flex; opacity: 1; }
.modal-box {
    background: var(--card); border-radius: var(--r-xl);
    width: 90%; max-width: 380px;
    box-shadow: 0 30px 60px rgba(0,0,0,.2);
    text-align: center; padding: 36px 28px;
    transform: scale(.95) translateY(8px);
    transition: transform .3s cubic-bezier(.34,1.56,.64,1);
}
.modal-overlay.show .modal-box { transform: scale(1) translateY(0); }
.modal-del-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: var(--red-bg); color: var(--red);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; margin: 0 auto 18px;
}
.modal-title { font-size: 1.15rem; font-weight: 900; margin-bottom: 8px; }
.modal-desc  { font-size: .88rem; color: var(--muted); font-weight: 500; line-height: 1.6; margin-bottom: 24px; }
.modal-actions { display: flex; gap: 10px; }
.modal-btn {
    flex: 1; padding: 12px;
    border-radius: var(--r-md);
    font-family: 'Outfit', sans-serif; font-size: .9rem; font-weight: 700;
    border: none; cursor: pointer; transition: all .2s;
    text-decoration: none; display: flex; align-items: center; justify-content: center;
}
.modal-cancel { background: var(--bg); color: var(--muted); }
.modal-cancel:hover { background: var(--border); color: var(--text); }
.modal-delete { background: var(--red); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,.3); }
.modal-delete:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(239,68,68,.4); color: #fff; }

/* ── RESPONSIVE ──────────────────────────────────────────────────── */
@media(max-width:1200px){ .stat-strip{ grid-template-columns: repeat(2,1fr); } }
@media(max-width:900px) {
    .team-topbar { padding: 0 18px; }
    .content-area { padding: 20px 18px 32px; }
    .topbar-clock, .topbar-status { display: none; }
}
@media(max-width:600px) {
    .stat-strip { grid-template-columns: 1fr 1fr; }
    .team-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-wrapper">

  <!-- ── TOP BAR ─────────────────────────────────────────────────── -->
  <header class="team-topbar">
    <div class="team-topbar-title">
      <div class="team-topbar-icon"><i class="fa fa-users"></i></div>
      Responder
    </div>
    <div class="topbar-spacer"></div>
    <div class="topbar-clock" id="live-clock">
      <span class="clock-dot"></span>
      <span id="clock-time">--:--</span>
    </div>
    <div class="topbar-status"><i class="fa fa-satellite-dish" style="font-size:.65rem"></i> System Online</div>
    <a href="add_responder.php" class="btn-add-responder">
      <i class="fa fa-user-plus"></i> Add Responder
    </a>
  </header>

  <!-- ── MAIN CONTENT ────────────────────────────────────────────── -->
  <div class="content-area">

    <!-- Success notice -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'responder_added'): ?>
    <div class="success-alert">
      <i class="fa fa-circle-check" style="color:var(--green);font-size:1.1rem;flex-shrink:0"></i>
      <span>Responder has been successfully registered and added to the active team!</span>
    </div>
    <?php endif; ?>

    <!-- ── STAT STRIP ──────────────────────────────────────────── -->
    <div class="stat-strip">
      <div class="stat-card active" id="stat-all" onclick="filterStatus('all',this,'')">
        <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--accent)">
          <i class="fa fa-users"></i>
        </div>
        <div>
          <div class="stat-num"><?= $total ?></div>
          <div class="stat-label">Total Responders</div>
        </div>
      </div>
      <div class="stat-card" id="stat-busy" onclick="filterStatus('busy',this,'active-amber')">
        <div class="stat-icon" style="background:var(--amber-bg);color:var(--amber)">
          <i class="fa fa-person-running"></i>
        </div>
        <div>
          <div class="stat-num" style="color:var(--amber)"><?= $on_mission ?></div>
          <div class="stat-label">On Mission</div>
        </div>
      </div>
      <div class="stat-card" id="stat-available" onclick="filterStatus('available',this,'active-green')">
        <div class="stat-icon" style="background:var(--green-bg);color:var(--green)">
          <i class="fa fa-circle-check"></i>
        </div>
        <div>
          <div class="stat-num" style="color:var(--green)"><?= $available ?></div>
          <div class="stat-label">Available</div>
        </div>
      </div>
      <div class="stat-card" id="stat-offline" onclick="filterStatus('offline',this,'active-gray')">
        <div class="stat-icon" style="background:var(--gray-bg);color:var(--gray)">
          <i class="fa fa-moon"></i>
        </div>
        <div>
          <div class="stat-num" style="color:var(--gray)"><?= $offline ?></div>
          <div class="stat-label">Offline</div>
        </div>
      </div>
    </div>

    <!-- ── TOOLBAR ─────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="search-wrap">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="team-search" placeholder="Search by name or phone…" oninput="doFilter()" autocomplete="off">
      </div>
      <button class="filter-pill active" id="fp-all"       onclick="filterStatus('all',       document.getElementById('stat-all'),       '')">All</button>
      <button class="filter-pill"        id="fp-busy"      onclick="filterStatus('busy',      document.getElementById('stat-busy'),      'active-amber')">
        <span class="fp-dot" style="background:var(--amber)"></span>On Mission
      </button>
      <button class="filter-pill"        id="fp-available" onclick="filterStatus('available', document.getElementById('stat-available'), 'active-green')">
        <span class="fp-dot" style="background:var(--green)"></span>Available
      </button>
      <button class="filter-pill"        id="fp-offline"   onclick="filterStatus('offline',   document.getElementById('stat-offline'),   'active-gray')">
        <span class="fp-dot" style="background:var(--gray)"></span>Offline
      </button>
      <span class="results-label" id="results-label"><?= $total ?> responder<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- ── TEAM GRID ─────────────────────────────────────────────── -->
    <div class="team-grid" id="team-grid">

      <!-- No-results placeholder -->
      <div id="no-results"><i class="fa fa-magnifying-glass" style="display:inline;opacity:.3;margin-right:8px"></i>No responders match your search.</div>

      <?php if (empty($team)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-users"></i></div>
        <h3>No Responders Yet</h3>
        <p>Drivers registered via the auth system will appear here.<br>
           <a href="add_responder.php">Register your first responder →</a></p>
      </div>
      <?php else: ?>

      <?php
      $palette = [
          '#3b82f6','#8b5cf6','#ec4899','#f59e0b',
          '#22c55e','#ef4444','#06b6d4','#84cc16','#f97316'
      ];
      foreach ($team as $idx => $m):
          $us = strtolower($m['unit_status'] ?? 'offline');
          if ($us === 'busy') {
              $sLabel = 'On Mission'; $sBadge = 'sb-busy';
              $dotColor = '#f59e0b'; $barColor = '#f59e0b';
          } elseif ($us === 'available') {
              $sLabel = 'Available'; $sBadge = 'sb-available';
              $dotColor = '#22c55e'; $barColor = '#22c55e';
          } else {
              $sLabel = 'Offline'; $sBadge = 'sb-offline';
              $dotColor = '#94a3b8'; $barColor = '#94a3b8'; $us = 'offline';
          }
          $color = $palette[$idx % count($palette)];
          $initial = strtoupper(mb_substr($m['fullname'], 0, 1));
          $phone  = htmlspecialchars($m['phone'] ?? '');
          $search = strtolower(($m['fullname'] ?? '').' '.($m['phone'] ?? ''));

          // Unit type icon
          $utype = strtolower($m['unit_type'] ?? '');
          $uIcon = str_contains($utype,'fire') ? 'fa-fire-extinguisher' : (str_contains($utype,'police') ? 'fa-shield-halved' : 'fa-truck-medical');
          $delay = ($idx * 50); // stagger animation
      ?>
      <div class="member-card"
           data-status="<?= $us ?>"
           data-search="<?= htmlspecialchars($search) ?>"
           style="animation-delay:<?= $delay ?>ms">

        <!-- Accent bar -->
        <div class="card-accent-bar" style="background:<?= $barColor ?>"></div>

        <!-- Body -->
        <div class="card-body-inner">

          <!-- Avatar -->
          <div class="member-avatar" style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc)">
            <?php if (!empty($m['profile_image'])): ?>
              <img src="../<?= htmlspecialchars($m['profile_image']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($m['fullname']) ?>&background=<?= str_replace('#','',$color) ?>&color=fff'">
            <?php else: ?>
              <?= $initial ?>
            <?php endif; ?>
            <span class="avatar-status-dot" style="background:<?= $dotColor ?>"></span>
          </div>

          <!-- Name & Role -->
          <div class="member-name"><?= htmlspecialchars($m['fullname']) ?></div>
          <div class="member-role">Emergency Responder</div>

          <!-- Phone -->
          <a href="tel:<?= $phone ?>" class="member-phone">
            <i class="fa fa-phone" style="font-size:.72rem"></i>
            <?= $phone ?: 'No phone' ?>
          </a>

          <!-- Status badge -->
          <div>
            <span class="status-badge <?= $sBadge ?>">
              <span class="sb-dot" style="background:<?= $dotColor ?>"></span>
              <?= $sLabel ?>
            </span>
          </div>

          <!-- Assigned unit -->
          <?php if ($m['unit_name']): ?>
          <div>
            <span class="unit-tag">
              <i class="fa <?= $uIcon ?>"></i>
              <?= htmlspecialchars($m['unit_name']) ?>
            </span>
          </div>
          <?php else: ?>
          <div class="no-unit-tag"><i class="fa fa-circle-minus" style="margin-right:4px"></i>No unit assigned</div>
          <?php endif; ?>

        </div><!-- /card-body-inner -->

        <div class="card-divider"></div>

        <!-- Actions -->
        <div class="card-actions">
          <a href="tel:<?= $phone ?>" class="ca-btn ca-call">
            <i class="fa fa-phone"></i> Call
          </a>
          <a href="view-requests.php" class="ca-btn ca-logs">
            <i class="fa fa-list-check"></i> Logs
          </a>
          <button onclick="openDeleteModal('delete_responder.php?id=<?= $m['id'] ?>')" class="ca-btn ca-delete">
            <i class="fa fa-trash"></i>
          </button>
        </div>

      </div><!-- /member-card -->
      <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /team-grid -->
  </div><!-- /content-area -->
</div><!-- /main-wrapper -->

<!-- ── DELETE CONFIRM MODAL ─────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal" onclick="if(event.target===this) closeDeleteModal()">
  <div class="modal-box">
    <div class="modal-del-icon"><i class="fa fa-triangle-exclamation"></i></div>
    <div class="modal-title">Delete Responder?</div>
    <p class="modal-desc">This will permanently remove the responder from the system and all assigned units.<br>This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="modal-btn modal-cancel" onclick="closeDeleteModal()">Cancel</button>
      <a id="delete-confirm-href" href="#" class="modal-btn modal-delete">
        <i class="fa fa-trash" style="margin-right:6px"></i>Delete
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── LIVE CLOCK ─────────────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    const el = document.getElementById('clock-time');
    if (el) el.textContent = `${h}:${m}:${s}`;
}
updateClock();
setInterval(updateClock, 1000);

// ── FILTER STATE ───────────────────────────────────────────────────
let activeStatus = 'all';

function filterStatus(status, statCard, activeClass) {
    activeStatus = status;
    // Stat cards reset
    document.querySelectorAll('.stat-card').forEach(c => c.className = 'stat-card');
    if (statCard) statCard.classList.add(activeClass || 'active');
    // Filter pills reset
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    const fp = document.getElementById('fp-' + status);
    if (fp) fp.classList.add('active');
    doFilter();
}

// ── SEARCH + FILTER ────────────────────────────────────────────────
function doFilter() {
    const q = document.getElementById('team-search').value.trim().toLowerCase();
    const cards = document.querySelectorAll('.member-card');
    let visible = 0;
    cards.forEach(card => {
        const matchSearch = !q || card.dataset.search.includes(q);
        const matchStatus = activeStatus === 'all' || card.dataset.status === activeStatus;
        const show = matchSearch && matchStatus;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    const nr = document.getElementById('no-results');
    if (nr) nr.style.display = (visible === 0) ? 'block' : 'none';
    const rl = document.getElementById('results-label');
    if (rl) rl.textContent = `${visible} responder${visible !== 1 ? 's' : ''}`;
}

// ── DELETE MODAL ────────────────────────────────────────────────────
function openDeleteModal(url) {
    document.getElementById('delete-confirm-href').href = url;
    const m = document.getElementById('deleteModal');
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function closeDeleteModal() {
    const m = document.getElementById('deleteModal');
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; document.getElementById('delete-confirm-href').href = '#'; }, 250);
}
</script>
</body>
</html>
