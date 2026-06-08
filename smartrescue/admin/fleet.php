<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Handle inline edit POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_unit_id'])) {
    $unit_id   = mysqli_real_escape_string($conn, $_POST['edit_unit_id']);
    $unit_name = mysqli_real_escape_string($conn, $_POST['unit_name']);
    $plate     = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);
    $driver_id = mysqli_real_escape_string($conn, $_POST['driver_id']);
    mysqli_query($conn, "UPDATE emergency_units SET unit_name='$unit_name', plate_number='$plate', status='$status', driver_id='$driver_id' WHERE id='$unit_id'");
    header("Location: fleet.php"); exit();
}

// Fetch drivers for edit modal
$drivers_arr = [];
$dr = mysqli_query($conn, "SELECT id, fullname FROM users WHERE role='driver' ORDER BY fullname ASC");
while ($d = mysqli_fetch_assoc($dr)) $drivers_arr[] = $d;

// Fetch all units with driver info
$query = "SELECT e.*, u.fullname as driver_name, u.phone as driver_phone
          FROM emergency_units e
          LEFT JOIN users u ON e.driver_id = u.id
          ORDER BY FIELD(e.status,'available','busy','offline'), e.unit_name ASC";
$result = mysqli_query($conn, $query);
$units = [];
while ($row = mysqli_fetch_assoc($result)) $units[] = $row;

$total    = count($units);
$available = count(array_filter($units, fn($u) => strtolower($u['status']) === 'available'));
$busy      = count(array_filter($units, fn($u) => strtolower($u['status']) === 'busy'));
$offline   = count(array_filter($units, fn($u) => strtolower($u['status']) === 'offline'));

// Group units
$grouped = [
    'available' => array_filter($units, fn($u) => strtolower($u['status']) === 'available'),
    'busy'      => array_filter($units, fn($u) => strtolower($u['status']) === 'busy'),
    'offline'   => array_filter($units, fn($u) => strtolower($u['status']) === 'offline'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fleet Management | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
/* ─── RESET & BASE ───────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:           #f0f4f9;
    --card:         #ffffff;
    --text:         #0f172a;
    --muted:        #64748b;
    --border:       #e4eaf3;
    --accent:       #3b82f6;
    --accent-dark:  #1d4ed8;
    --green:        #22c55e;
    --green-bg:     rgba(34,197,94,.09);
    --amber:        #f59e0b;
    --amber-bg:     rgba(245,158,11,.09);
    --red:          #ef4444;
    --red-bg:       rgba(239,68,68,.09);
    --gray:         #94a3b8;
    --gray-bg:      rgba(148,163,184,.08);
    --sidebar-w:    268px;
    --topbar-h:     64px;
    --shadow-sm:    0 1px 4px rgba(0,0,0,.06);
    --shadow-md:    0 4px 20px rgba(0,0,0,.07);
    --shadow-lg:    0 12px 36px rgba(0,0,0,.10);
    --radius-sm:    8px;
    --radius-md:    12px;
    --radius-lg:    16px;
    --radius-xl:    20px;
}
body {
    font-family: 'Outfit', sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────────────── */
.main-wrapper {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ─── TOPBAR ─────────────────────────────────────────────────────── */
.fleet-topbar {
    position: sticky;
    top: 0;
    z-index: 200;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    height: var(--topbar-h);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 16px;
    box-shadow: var(--shadow-sm);
}
.fleet-topbar-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}
.fleet-topbar-title i {
    width: 34px; height: 34px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: .85rem;
    box-shadow: 0 4px 12px rgba(59,130,246,.3);
    flex-shrink: 0;
}
.topbar-spacer { flex: 1; }
.topbar-search {
    position: relative;
    width: 280px;
}
.topbar-search i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .8rem; pointer-events: none;
}
.topbar-search input {
    width: 100%;
    padding: 8px 14px 8px 34px;
    border: 1.5px solid var(--border);
    border-radius: 50px;
    font-family: 'Outfit', sans-serif;
    font-size: .83rem;
    font-weight: 500;
    color: var(--text);
    background: var(--bg);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.topbar-search input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: #fff;
}
.topbar-search input::placeholder { color: var(--muted); }
.btn-add-unit {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 20px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: .83rem;
    border: none;
    border-radius: 50px;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(59,130,246,.3);
    transition: transform .2s, box-shadow .2s;
    white-space: nowrap;
}
.btn-add-unit:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(59,130,246,.4); color: #fff; }

/* ─── PAGE BODY ──────────────────────────────────────────────────── */
.fleet-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ─── UNITS PANEL (full-width) ───────────────────────────────────── */
.fleet-left {
    padding: 24px 28px 36px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
    height: calc(100vh - var(--topbar-h));
}

/* ─── STAT STRIP ─────────────────────────────────────────────────── */
.stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.stat-card {
    background: var(--card);
    border-radius: var(--radius-lg);
    padding: 14px 16px;
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all .2s;
    position: relative;
    overflow: hidden;
}
.stat-card::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 3px;
    background: transparent;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    transition: background .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card.active { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,.1), var(--shadow-md); }
.stat-card.active::after { background: var(--accent); }
.stat-card.active-green { border-color: var(--green); box-shadow: 0 0 0 3px rgba(34,197,94,.1), var(--shadow-md); }
.stat-card.active-green::after { background: var(--green); }
.stat-card.active-amber { border-color: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,.1), var(--shadow-md); }
.stat-card.active-amber::after { background: var(--amber); }
.stat-card.active-gray { border-color: var(--gray); box-shadow: 0 0 0 3px rgba(148,163,184,.12), var(--shadow-md); }
.stat-card.active-gray::after { background: var(--gray); }
.stat-icon {
    width: 40px; height: 40px;
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.stat-num { font-size: 1.55rem; font-weight: 900; line-height: 1; }
.stat-label { font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); margin-top: 2px; }

/* ─── FILTER ROW ─────────────────────────────────────────────────── */
.filter-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px;
    border-radius: 50px;
    border: 1.5px solid var(--border);
    background: var(--card);
    font-family: 'Outfit', sans-serif;
    font-size: .78rem;
    font-weight: 700;
    color: var(--muted);
    cursor: pointer;
    transition: all .2s;
}
.filter-pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,.04); }
.filter-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.filter-pill .dot { width: 6px; height: 6px; border-radius: 50%; }
.filter-spacer { flex: 1; }
.sort-select {
    padding: 7px 12px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'Outfit', sans-serif;
    font-size: .78rem;
    font-weight: 600;
    color: var(--muted);
    background: var(--card);
    outline: none;
    cursor: pointer;
}
.sort-select:focus { border-color: var(--accent); }

/* ─── GROUP HEADER ───────────────────────────────────────────────── */
.group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.group-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.group-title {
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: var(--muted);
}
.group-count {
    font-size: .68rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 20px;
    background: var(--bg);
    color: var(--muted);
    border: 1px solid var(--border);
}
.group-line {
    flex: 1;
    height: 1px;
    background: var(--border);
}
.group-section { display: flex; flex-direction: column; gap: 6px; }
.group-section.hidden { display: none; }

/* ─── UNIT CARD (HORIZONTAL COMPACT) ────────────────────────────── */
.unit-card {
    background: var(--card);
    border-radius: var(--radius-lg);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    padding: 0;
    min-height: 72px;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    position: relative;
    z-index: 1;
}
.unit-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: #d0daea; z-index: 5; }
.unit-card:focus-within { z-index: 10; }
.unit-card:has(.uc-dropdown.open) { z-index: 100; }
/* Colored left accent bar */
.unit-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}
.unit-card.s-available::before { background: var(--green); }
.unit-card.s-busy::before      { background: var(--amber); }
.unit-card.s-offline::before   { background: var(--gray); }

/* Hide in search/filter */
.unit-card.hidden { display: none; }

/* Icon col */
.uc-icon {
    width: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding-left: 16px;
}
.uc-icon-inner {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
    flex-shrink: 0;
}
.ico-ambulance { background: rgba(59,130,246,.1); color: #3b82f6; }
.ico-fire      { background: rgba(239,68,68,.1);  color: #ef4444; }
.ico-police    { background: rgba(100,116,139,.1);color: #64748b; }

/* Main info col */
.uc-info {
    flex: 1;
    min-width: 0;
    padding: 12px 12px 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.uc-top {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.uc-name {
    font-weight: 800;
    font-size: .9rem;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.uc-plate {
    font-size: .68rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 5px;
    background: var(--bg);
    color: var(--muted);
    border: 1px solid var(--border);
    letter-spacing: .8px;
    white-space: nowrap;
}
.uc-status-pill {
    font-size: .62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .6px;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.pill-available { background: var(--green-bg); color: #15803d; }
.pill-busy      { background: var(--amber-bg); color: #b45309; }
.pill-offline   { background: var(--gray-bg);  color: #64748b; }

.uc-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.uc-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: .77rem;
    color: var(--muted);
    font-weight: 500;
    white-space: nowrap;
}
.uc-meta-item i { font-size: .7rem; opacity: .7; }
.uc-meta-item strong { font-weight: 700; color: var(--text); }

/* GPS indicator */
.gps-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.gps-live { background: var(--green); box-shadow: 0 0 0 2px rgba(34,197,94,.25); animation: gps-pulse 2s infinite; }
.gps-none { background: #cbd5e1; }
@keyframes gps-pulse {
    0%, 100% { box-shadow: 0 0 0 2px rgba(34,197,94,.25); }
    50%       { box-shadow: 0 0 0 4px rgba(34,197,94,.12); }
}

/* Divider between info blocks */
.uc-divider {
    width: 1px;
    align-self: stretch;
    background: var(--border);
    margin: 10px 0;
    flex-shrink: 0;
}

/* Actions col */
.uc-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px 0 12px;
    flex-shrink: 0;
}
.uca-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    padding: 7px 14px;
    border-radius: var(--radius-sm);
    font-family: 'Outfit', sans-serif;
    font-size: .75rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    white-space: nowrap;
}
.uca-primary {
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    box-shadow: 0 3px 10px rgba(59,130,246,.25);
}
.uca-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59,130,246,.35); color: #fff; }
.uca-icon {
    width: 32px; height: 32px;
    background: var(--bg);
    color: var(--muted);
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    padding: 0;
    font-size: .78rem;
}
.uca-icon:hover { background: var(--card); color: var(--text); border-color: #c0cfe0; }

/* Dropdown menu */
.uc-dropdown { position: relative; z-index: 2; }
.uc-dropdown.open { z-index: 500; }
.uc-dropdown-menu {
    display: none;
    position: absolute;
    right: 0; top: calc(100% + 6px);
    background: var(--card);
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    min-width: 160px;
    z-index: 500;
    overflow: hidden;
}
.uc-dropdown:hover .uc-dropdown-menu,
.uc-dropdown.open .uc-dropdown-menu { display: block; }
.uc-dropdown-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    font-size: .8rem;
    font-weight: 600;
    color: var(--text);
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
    border: none;
    background: none;
    width: 100%;
    font-family: 'Outfit', sans-serif;
}
.uc-dropdown-item i { width: 14px; text-align: center; color: var(--muted); font-size: .75rem; }
.uc-dropdown-item:hover { background: var(--bg); }
.uc-dropdown-item.danger { color: var(--red); }
.uc-dropdown-item.danger i { color: var(--red); }
.uc-dropdown-item.danger:hover { background: var(--red-bg); }
.uc-dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state i { font-size: 2.5rem; opacity: .15; display: block; margin-bottom: 12px; }
.empty-state p { font-size: .88rem; font-weight: 600; }
.empty-state a { color: var(--accent); text-decoration: none; }

/* No-result state (JS injected) */
#no-results-msg {
    display: none;
    text-align: center;
    padding: 32px;
    color: var(--muted);
    font-weight: 600;
    font-size: .88rem;
    background: var(--card);
    border-radius: var(--radius-lg);
    border: 1.5px dashed var(--border);
}

/* (Live map panel removed) */

/* ─── MODALS ─────────────────────────────────────────────────────── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.55);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center; justify-content: center;
    opacity: 0; transition: opacity .25s;
}
.modal-overlay.show { display: flex; opacity: 1; }
.modal-box {
    background: var(--card);
    border-radius: var(--radius-xl);
    width: 90%; max-width: 420px;
    box-shadow: 0 30px 60px rgba(0,0,0,.2);
    overflow: hidden;
    transform: scale(.96) translateY(8px);
    transition: transform .3s cubic-bezier(.34,1.56,.64,1);
}
.modal-overlay.show .modal-box { transform: scale(1) translateY(0); }
.modal-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.modal-header h5 {
    font-size: 1rem; font-weight: 800; flex: 1;
    display: flex; align-items: center; gap: 8px;
}
.modal-close-btn {
    width: 30px; height: 30px; border-radius: 50%;
    border: none; background: var(--bg); color: var(--muted);
    cursor: pointer; font-size: .8rem; transition: all .2s;
    display: flex; align-items: center; justify-content: center;
}
.modal-close-btn:hover { background: var(--border); color: var(--text); }
.modal-body { padding: 20px 24px; }
.modal-footer { padding: 14px 24px 20px; display: flex; gap: 10px; justify-content: flex-end; }
.form-group { margin-bottom: 14px; }
.form-label {
    display: block;
    font-size: .68rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .8px;
    color: var(--muted); margin-bottom: 6px;
}
.form-input, .form-select {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    font-family: 'Outfit', sans-serif;
    font-size: .88rem; font-weight: 600;
    color: var(--text); background: var(--bg);
    outline: none; transition: border-color .2s, box-shadow .2s;
    appearance: none;
}
.form-input:focus, .form-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,.1);
    background: #fff;
}
.btn-modal {
    padding: 10px 22px;
    border-radius: var(--radius-md);
    font-family: 'Outfit', sans-serif;
    font-size: .85rem; font-weight: 700;
    border: none; cursor: pointer;
    transition: all .2s;
}
.btn-cancel { background: var(--bg); color: var(--muted); border: 1.5px solid var(--border); }
.btn-cancel:hover { background: var(--border); color: var(--text); }
.btn-save {
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    box-shadow: 0 4px 12px rgba(59,130,246,.3);
}
.btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(59,130,246,.35); }

/* Delete confirm modal */
.delete-modal-icon {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--red-bg);
    color: var(--red);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    margin: 0 auto 16px;
}
.btn-delete-confirm {
    background: linear-gradient(135deg, #dc2626, var(--red));
    color: #fff;
    box-shadow: 0 4px 12px rgba(239,68,68,.3);
}
.btn-delete-confirm:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(239,68,68,.4); }
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-wrapper">

  <!-- ── TOPBAR ──────────────────────────────────────────────────── -->
  <header class="fleet-topbar">
    <div class="fleet-topbar-title">
      <i class="fa fa-truck-medical"></i>
      Fleet Management
    </div>
    <div class="topbar-spacer"></div>
    <div class="topbar-search">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" id="search-input" placeholder="Search units, drivers, plates…" oninput="doSearch()" autocomplete="off">
    </div>
    <a href="add_unit.php" class="btn-add-unit">
      <i class="fa fa-plus"></i> Add Unit
    </a>
  </header>

  <!-- ── PAGE BODY ───────────────────────────────────────────────── -->
  <div class="fleet-body">

    <!-- ── LEFT: UNITS LIST ──────────────────────────────────────── -->
    <div class="fleet-left" id="fleet-left">

      <!-- STAT STRIP -->
      <div class="stat-strip">
        <div class="stat-card active" id="stat-all" onclick="filterStatus('all', this, '')">
          <div class="stat-icon" style="background:rgba(59,130,246,.1);color:#3b82f6"><i class="fa fa-truck-medical"></i></div>
          <div>
            <div class="stat-num"><?= $total ?></div>
            <div class="stat-label">Total Units</div>
          </div>
        </div>
        <div class="stat-card" id="stat-available" onclick="filterStatus('available', this, 'active-green')">
          <div class="stat-icon" style="background:var(--green-bg);color:var(--green)"><i class="fa fa-circle-check"></i></div>
          <div>
            <div class="stat-num" style="color:var(--green)"><?= $available ?></div>
            <div class="stat-label">Available</div>
          </div>
        </div>
        <div class="stat-card" id="stat-busy" onclick="filterStatus('busy', this, 'active-amber')">
          <div class="stat-icon" style="background:var(--amber-bg);color:var(--amber)"><i class="fa fa-person-running"></i></div>
          <div>
            <div class="stat-num" style="color:var(--amber)"><?= $busy ?></div>
            <div class="stat-label">On Mission</div>
          </div>
        </div>
        <div class="stat-card" id="stat-offline" onclick="filterStatus('offline', this, 'active-gray')">
          <div class="stat-icon" style="background:var(--gray-bg);color:var(--gray)"><i class="fa fa-circle-xmark"></i></div>
          <div>
            <div class="stat-num" style="color:var(--gray)"><?= $offline ?></div>
            <div class="stat-label">Offline</div>
          </div>
        </div>
      </div>

      <!-- FILTER ROW -->
      <div class="filter-row">
        <button class="filter-pill active" id="fp-all" onclick="filterStatus('all', document.getElementById('stat-all'), '')">
          All Units
        </button>
        <button class="filter-pill" id="fp-available" onclick="filterStatus('available', document.getElementById('stat-available'), 'active-green')">
          <span class="dot" style="background:var(--green)"></span> Available
        </button>
        <button class="filter-pill" id="fp-busy" onclick="filterStatus('busy', document.getElementById('stat-busy'), 'active-amber')">
          <span class="dot" style="background:var(--amber)"></span> On Mission
        </button>
        <button class="filter-pill" id="fp-offline" onclick="filterStatus('offline', document.getElementById('stat-offline'), 'active-gray')">
          <span class="dot" style="background:var(--gray)"></span> Offline
        </button>
        <div class="filter-spacer"></div>
        <select class="sort-select" onchange="sortUnits(this.value)">
          <option value="name">Sort: Name A–Z</option>
          <option value="status">Sort: Status</option>
          <option value="gps">Sort: GPS Active</option>
        </select>
      </div>

      <!-- NO RESULTS -->
      <div id="no-results-msg"><i class="fa fa-magnifying-glass" style="display:inline;font-size:1rem;opacity:.3;margin-right:8px"></i>No units match your search.</div>

      <?php if (empty($units)): ?>
      <div class="empty-state">
        <i class="fa fa-truck-medical"></i>
        <p>No units registered yet. <a href="add_unit.php">Add your first unit →</a></p>
      </div>
      <?php else: ?>

      <!-- ── GROUP: AVAILABLE ────────────────────────────────────── -->
      <div class="group-section" id="group-available" data-group="available">
        <div class="group-header">
          <span class="group-dot" style="background:var(--green)"></span>
          <span class="group-title">Available</span>
          <span class="group-count"><?= count($grouped['available']) ?></span>
          <span class="group-line"></span>
        </div>
        <?php foreach ($grouped['available'] as $u): ?>
          <?php echo renderUnitCard($u); ?>
        <?php endforeach; ?>
        <?php if (empty($grouped['available'])): ?>
          <div style="text-align:center;padding:20px;font-size:.82rem;color:var(--muted)">No available units</div>
        <?php endif; ?>
      </div>

      <!-- ── GROUP: ON MISSION ───────────────────────────────────── -->
      <div class="group-section" id="group-busy" data-group="busy">
        <div class="group-header">
          <span class="group-dot" style="background:var(--amber)"></span>
          <span class="group-title">On Mission</span>
          <span class="group-count"><?= count($grouped['busy']) ?></span>
          <span class="group-line"></span>
        </div>
        <?php foreach ($grouped['busy'] as $u): ?>
          <?php echo renderUnitCard($u); ?>
        <?php endforeach; ?>
        <?php if (empty($grouped['busy'])): ?>
          <div style="text-align:center;padding:20px;font-size:.82rem;color:var(--muted)">No units on mission</div>
        <?php endif; ?>
      </div>

      <!-- ── GROUP: OFFLINE ─────────────────────────────────────── -->
      <div class="group-section" id="group-offline" data-group="offline">
        <div class="group-header">
          <span class="group-dot" style="background:var(--gray)"></span>
          <span class="group-title">Offline</span>
          <span class="group-count"><?= count($grouped['offline']) ?></span>
          <span class="group-line"></span>
        </div>
        <?php foreach ($grouped['offline'] as $u): ?>
          <?php echo renderUnitCard($u); ?>
        <?php endforeach; ?>
        <?php if (empty($grouped['offline'])): ?>
          <div style="text-align:center;padding:20px;font-size:.82rem;color:var(--muted)">No offline units</div>
        <?php endif; ?>
      </div>

      <?php endif; ?>
    </div><!-- /fleet-left -->



  </div><!-- /fleet-body -->
</div><!-- /main-wrapper -->


<!-- ── EDIT UNIT MODAL ───────────────────────────────────────────── -->
<div class="modal-overlay" id="editModal" onclick="if(event.target===this) closeEdit()">
  <div class="modal-box">
    <div class="modal-header">
      <h5><i class="fa fa-pen-to-square" style="color:var(--accent)"></i> Edit Unit</h5>
      <button class="modal-close-btn" onclick="closeEdit()"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="edit_unit_id" id="edit_unit_id">
        <div class="form-group">
          <label class="form-label">Unit Call-Sign</label>
          <input type="text" name="unit_name" id="edit_unit_name" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Registry ID (Plate)</label>
          <input type="text" name="plate_number" id="edit_plate_number" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="edit_status" class="form-select">
            <option value="available">✅ Available</option>
            <option value="busy">⚠️ On Mission</option>
            <option value="offline">❌ Offline</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Assigned Responder</label>
          <select name="driver_id" id="edit_driver_id" class="form-select" required>
            <?php foreach($drivers_arr as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['fullname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal btn-cancel" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn-modal btn-save"><i class="fa fa-save" style="margin-right:6px"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ── DELETE CONFIRM MODAL ──────────────────────────────────────── -->
<div class="modal-overlay" id="deleteModal" onclick="if(event.target===this) closeDelete()">
  <div class="modal-box">
    <div class="modal-body" style="text-align:center;padding:32px 28px">
      <div class="delete-modal-icon"><i class="fa fa-triangle-exclamation"></i></div>
      <div style="font-size:1.1rem;font-weight:800;margin-bottom:8px">Delete Unit?</div>
      <p style="font-size:.88rem;color:var(--muted);line-height:1.6;margin-bottom:24px">
        This will permanently remove the unit from the fleet.<br>This action cannot be undone.
      </p>
      <div style="display:flex;gap:10px">
        <button class="btn-modal btn-cancel" style="flex:1" onclick="closeDelete()">Cancel</button>
        <a id="delete-confirm-btn" href="#" class="btn-modal btn-delete-confirm" style="flex:1;text-align:center;text-decoration:none" onclick="executeDelete(event, this.href)">
          <i class="fa fa-trash" style="margin-right:6px"></i>Delete
        </a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── EDIT MODAL ─────────────────────────────────────────────────────
function openEdit(data) {
    document.getElementById('edit_unit_id').value    = data.id;
    document.getElementById('edit_unit_name').value  = data.unit_name;
    document.getElementById('edit_plate_number').value = data.plate_number;
    document.getElementById('edit_status').value     = data.status;
    document.getElementById('edit_driver_id').value  = data.driver_id;
    showModal('editModal');
}
function closeEdit() { hideModal('editModal'); }

// ── DELETE MODAL ───────────────────────────────────────────────────
function openDelete(url) {
    const btn = document.getElementById('delete-confirm-btn');
    btn.href = url;
    // Reset button state just in case
    btn.innerHTML = '<i class="fa fa-trash" style="margin-right:6px"></i>Delete';
    btn.style.pointerEvents = 'auto';
    showModal('deleteModal');
}
function closeDelete() { hideModal('deleteModal'); }

function executeDelete(e, url) {
    if (url === '#' || url.endsWith('#')) return;
    e.preventDefault();
    const btn = document.getElementById('delete-confirm-btn');
    btn.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Deleting…';
    btn.style.pointerEvents = 'none';

    const fetchUrl = url + (url.includes('?') ? '&ajax=1' : '?ajax=1');

    fetch(fetchUrl)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                closeDelete();
                showToast('Unit successfully deleted');
                // Reload the page to reflect changes without navigating
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error('Deletion failed');
            }
        })
        .catch(err => {
            closeDelete();
            showToast('Unable to delete this unit right now.');
            btn.innerHTML = '<i class="fa fa-trash" style="margin-right:6px"></i>Delete';
            btn.style.pointerEvents = 'auto';
        });
}

// ── MODAL HELPERS ──────────────────────────────────────────────────
function showModal(id) {
    const m = document.getElementById(id);
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function hideModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; }, 250);
}

// ── DROPDOWN TOGGLE ────────────────────────────────────────────────
function toggleDropdown(id) {
    const el = document.getElementById(id);
    el.classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('.uc-dropdown')) {
        document.querySelectorAll('.uc-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

// ── SEARCH ─────────────────────────────────────────────────────────
function doSearch() {
    const q = document.getElementById('search-input').value.trim().toLowerCase();
    const cards = document.querySelectorAll('.unit-card');
    let visible = 0;
    cards.forEach(card => {
        const match = !q || card.dataset.search.includes(q);
        const statusMatch = (activeFilter === 'all') || card.dataset.status === activeFilter;
        const show = match && statusMatch;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    updateGroupVisibility();
    document.getElementById('no-results-msg').style.display = (visible === 0 && q) ? 'block' : 'none';
}

// ── STATUS FILTER ──────────────────────────────────────────────────
let activeFilter = 'all';
function filterStatus(status, statCard, activeClass) {
    activeFilter = status;
    // Stat cards
    document.querySelectorAll('.stat-card').forEach(c => c.className = 'stat-card');
    if (statCard) statCard.classList.add(activeClass || 'active');
    // Filter pills
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    const fpId = 'fp-' + status;
    if (document.getElementById(fpId)) document.getElementById(fpId).classList.add('active');
    doSearch();
}

// ── GROUP VISIBILITY ───────────────────────────────────────────────
function updateGroupVisibility() {
    document.querySelectorAll('.group-section').forEach(group => {
        const g = group.dataset.group;
        if (activeFilter !== 'all' && g !== activeFilter) {
            group.classList.add('hidden');
            return;
        }
        group.classList.remove('hidden');
    });
}

// ── SORT ────────────────────────────────────────────────────────────
function sortUnits(by) {
    // Sorting is PHP-side for initial load; this stub can be extended with AJAX
    // For now, it just shows a brief feedback toast
    showToast('Units sorted by ' + by);
}

// ── TOAST ───────────────────────────────────────────────────────────
function showToast(msg) {
    let t = document.getElementById('fleet-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'fleet-toast';
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);background:#1e293b;color:#fff;padding:10px 22px;border-radius:50px;font-weight:700;font-size:.82rem;z-index:99999;transition:.35s;opacity:0;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(-50%) translateY(60px)'; }, 2800);
}
</script>
</body>
</html>
<?php
// ── helper: render a compact horizontal unit card ──────────────────
function renderUnitCard($u) {
    $status   = strtolower($u['status'] ?? 'offline');
    $type     = strtolower($u['unit_type'] ?? 'ambulance');
    $isFire   = str_contains($type, 'fire');
    $isPolice = str_contains($type, 'police');
    $iconClass = $isFire ? 'ico-fire' : ($isPolice ? 'ico-police' : 'ico-ambulance');
    $icon      = $isFire ? 'fa-fire-extinguisher' : ($isPolice ? 'fa-shield-halved' : 'fa-truck-medical');
    $hasGPS    = !empty($u['current_lat']) && !empty($u['current_lng']);
    $driverName = htmlspecialchars($u['driver_name'] ?? 'Unassigned');
    $phone      = htmlspecialchars($u['driver_phone'] ?? '—');
    $gpsLabel   = $hasGPS
        ? round($u['current_lat'],4).', '.round($u['current_lng'],4)
        : 'No signal';
    $search = strtolower(($u['unit_name']??'').' '.($u['driver_name']??'').' '.($u['plate_number']??'').' '.$type);
    $jsonData = json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT);
    $dropId = 'drop-'.$u['id'];
    ob_start(); ?>
<div class="unit-card s-<?= $status ?>"
     data-status="<?= $status ?>"
     data-search="<?= htmlspecialchars($search) ?>">

  <!-- Icon -->
  <div class="uc-icon">
    <div class="uc-icon-inner <?= $iconClass ?>"><i class="fa <?= $icon ?>"></i></div>
  </div>

  <!-- Info -->
  <div class="uc-info">
    <div class="uc-top">
      <span class="uc-name"><?= htmlspecialchars($u['unit_name']) ?></span>
      <span class="uc-plate"><?= htmlspecialchars($u['plate_number']) ?></span>
      <span class="uc-status-pill pill-<?= $status ?>">
        <?= $status === 'busy' ? 'On Mission' : ucfirst($status) ?>
      </span>
    </div>
    <div class="uc-meta">
      <span class="uc-meta-item">
        <i class="fa fa-user"></i>
        <strong><?= $driverName ?></strong>
      </span>
      <?php if ($u['driver_phone']): ?>
      <span class="uc-meta-item">
        <i class="fa fa-phone"></i>
        <?= $phone ?>
      </span>
      <?php endif; ?>
      <span class="uc-meta-item">
        <span class="gps-dot <?= $hasGPS ? 'gps-live' : 'gps-none' ?>"></span>
        <?= $hasGPS ? 'GPS: '.$gpsLabel : 'No GPS' ?>
      </span>
    </div>
  </div>

  <div class="uc-divider"></div>

  <!-- Actions -->
  <div class="uc-actions">
    <button class="uca-btn uca-primary" onclick='openEdit(<?= $jsonData ?>)'>
      <i class="fa fa-pen"></i> Edit
    </button>
    <div class="uc-dropdown" id="<?= $dropId ?>">
      <button class="uca-btn uca-icon" onclick="toggleDropdown('<?= $dropId ?>')" title="More options">
        <i class="fa fa-ellipsis-vertical"></i>
      </button>
      <div class="uc-dropdown-menu">
        <button class="uc-dropdown-item" onclick='openEdit(<?= $jsonData ?>); toggleDropdown("<?= $dropId ?>")'>
          <i class="fa fa-pen"></i> Edit Unit
        </button>
        <?php if ($u['current_lat']): ?>
        <a class="uc-dropdown-item" href="live-tracking.php">
          <i class="fa fa-map-location-dot"></i> View on Map
        </a>
        <?php endif; ?>
        <div class="uc-dropdown-divider"></div>
        <button class="uc-dropdown-item danger" onclick="openDelete('delete_unit.php?id=<?= $u['id'] ?>'); toggleDropdown('<?= $dropId ?>')">
          <i class="fa fa-trash"></i> Delete Unit
        </button>
      </div>
    </div>
  </div>
</div>
<?php return ob_get_clean();
}
?>
