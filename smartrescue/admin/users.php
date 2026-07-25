<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Fetch all regular users (include location_updated_at for online/offline status)
$q = "SELECT id, fullname, email, phone, profile_image, created_at, role, location_updated_at
      FROM users
      ORDER BY created_at DESC";
$res = mysqli_query($conn, $q);
$users = [];
while ($row = mysqli_fetch_assoc($res)) $users[] = $row;

$total = count($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
/* ── RESET & MODERN VARIABLES ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:          #f4f7fc;
    --card:        #ffffff;
    --text:        #0f172a;
    --muted:       #64748b;
    --border:      #e2e8f0;
    
    /* Branding */
    --accent:      #3b82f6;
    --accent-dark: #1d4ed8;
    --accent-grad: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
    --accent-glow: rgba(59, 130, 246, 0.15);
    
    /* Semantic Colors */
    --green:       #10b981;
    --green-bg:    rgba(16, 185, 129, 0.08);
    --green-border: rgba(16, 185, 129, 0.15);
    
    --amber:       #f59e0b;
    --amber-bg:    rgba(245, 158, 11, 0.08);
    
    --red:         #ef4444;
    --red-bg:      rgba(239, 68, 68, 0.08);
    --red-border:  rgba(239, 68, 68, 0.15);
    
    --gray:        #94a3b8;
    --gray-bg:     rgba(148, 163, 184, 0.06);
    
    /* Dimensions & Radii */
    --sidebar-w:   268px;
    --topbar-h:    72px;
    --r-sm:        8px;
    --r-md:        12px;
    --r-lg:        16px;
    --r-xl:        22px;
    
    /* Premium Shadows */
    --shadow-sm:   0 1px 3px rgba(0, 0, 0, 0.02);
    --shadow-md:   0 10px 30px -10px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
    --shadow-lg:   0 20px 40px -15px rgba(0, 0, 0, 0.07), 0 1px 5px rgba(0, 0, 0, 0.03);
}

body { 
    font-family: 'Outfit', sans-serif; 
    background: var(--bg); 
    color: var(--text);
    overflow-x: hidden;
    position: relative;
}

/* Ambient Lighting Mesh backgrounds */
body::before {
    content: '';
    position: fixed;
    top: -10%;
    right: -10%;
    width: 60vw;
    height: 60vw;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.06) 0%, rgba(255, 255, 255, 0) 70%);
    z-index: -1;
    pointer-events: none;
}
body::after {
    content: '';
    position: fixed;
    bottom: -10%;
    left: 15%;
    width: 50vw;
    height: 50vw;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.04) 0%, rgba(255, 255, 255, 0) 70%);
    z-index: -1;
    pointer-events: none;
}

/* Custom Scrollbars */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* ── LAYOUT ──────────────────────────────────────────────────────── */
.main-wrapper {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ── STICKY GLASSMORPHIC TOP BAR ─────────────────────────────────── */
.topbar {
    position: sticky; 
    top: 0; 
    z-index: 200;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(16px) saturate(120%);
    -webkit-backdrop-filter: blur(16px) saturate(120%);
    border-bottom: 1px solid var(--border);
    height: var(--topbar-h);
    display: flex; 
    align-items: center;
    padding: 0 36px; 
    gap: 16px;
    box-shadow: var(--shadow-sm);
}
.topbar-title {
    font-size: 1.2rem; 
    font-weight: 800;
    display: flex; 
    align-items: center; 
    gap: 12px;
    letter-spacing: -0.3px;
}
.topbar-icon {
    width: 42px; 
    height: 42px;
    background: var(--accent-grad);
    border-radius: var(--r-md);
    display: flex; 
    align-items: center; 
    justify-content: center;
    color: #fff; 
    font-size: .95rem;
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.25);
    flex-shrink: 0;
    position: relative;
}
/* Subtle breathing ring around the icon */
.topbar-icon::after {
    content: '';
    position: absolute;
    inset: -3px;
    border: 1.5px solid rgba(59, 130, 246, 0.3);
    border-radius: calc(var(--r-md) + 3px);
    animation: breathing-ring 3s infinite ease-in-out;
}
@keyframes breathing-ring {
    0% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.05); opacity: 0.2; }
    100% { transform: scale(1); opacity: 0.8; }
}

.topbar-spacer { flex: 1; }

.topbar-clock {
    display: flex; 
    align-items: center; 
    gap: 8px;
    font-size: .85rem; 
    font-weight: 700; 
    color: var(--text);
    background: rgba(255, 255, 255, 0.7); 
    border: 1.5px solid var(--border);
    border-radius: 50px; 
    padding: 7px 16px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}
.topbar-clock:hover {
    border-color: rgba(59, 130, 246, 0.3);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.05);
}
.clock-dot { 
    width: 6px; 
    height: 6px; 
    border-radius: 50%; 
    background: var(--green); 
    box-shadow: 0 0 8px var(--green);
    animation: pulse-dot 2s infinite ease-in-out; 
}
@keyframes pulse-dot { 
    0%, 100% { opacity: 1; transform: scale(0.9); } 
    50% { opacity: 0.4; transform: scale(1.2); } 
}
#clock-time {
    font-family: 'Outfit', sans-serif;
    letter-spacing: 0.5px;
}

/* ── CONTENT AREA ────────────────────────────────────────────────── */
.content-area {
    padding: 32px 36px 48px;
    display: flex; 
    flex-direction: column; 
    gap: 28px;
    max-width: 1600px;
    width: 100%;
    margin: 0 auto;
}

/* ── TOOLBAR ─────────────────────────────────────────────────────── */
.toolbar {
    display: flex; 
    align-items: center; 
    gap: 16px; 
    flex-wrap: wrap;
    background: var(--card);
    padding: 18px 24px;
    border-radius: var(--r-xl);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}
.toolbar:hover {
    box-shadow: var(--shadow-lg);
    border-color: rgba(59, 130, 246, 0.15);
}
.search-wrap { 
    position: relative; 
    flex: 1; 
    min-width: 280px; 
}
.search-wrap i {
    position: absolute; 
    left: 18px; 
    top: 50%; 
    transform: translateY(-50%);
    color: var(--muted); 
    font-size: .9rem; 
    pointer-events: none;
    transition: color 0.3s ease;
}
.search-input {
    width: 100%; 
    padding: 12px 18px 12px 46px;
    border: 1.5px solid var(--border); 
    border-radius: 50px;
    font-family: 'Outfit', sans-serif; 
    font-size: .88rem; 
    font-weight: 500;
    color: var(--text); 
    background: var(--bg); 
    outline: none;
    transition: all .25s ease;
}
.search-input:focus { 
    border-color: var(--accent); 
    background: var(--card); 
    box-shadow: 0 0 0 4px var(--accent-glow); 
}
.search-input:focus + i {
    color: var(--accent);
}
.search-input::placeholder { color: var(--muted); }

.results-label {
    font-size: .85rem; 
    font-weight: 700; 
    color: #4f46e5;
    white-space: nowrap;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.05), rgba(59, 130, 246, 0.05)); 
    border-radius: 50px; 
    padding: 10px 18px; 
    border: 1.5px solid rgba(79, 70, 229, 0.12);
    display: inline-flex;
    align-items: center;
    box-shadow: var(--shadow-sm);
}

.add-user-btn {
    background: var(--accent-grad); 
    color: white; 
    padding: 12px 22px; 
    border-radius: 50px; 
    font-weight: 700; 
    font-size: .88rem; 
    border: none; 
    cursor: pointer; 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);
}
.add-user-btn:hover { 
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(59, 130, 246, 0.35); 
}
.add-user-btn:active {
    transform: translateY(0);
}

/* ── TABLE STYLES (Floating Card Rows Pattern) ─────────────────── */
.users-table-container {
    background: transparent;
    border: none;
    box-shadow: none;
    overflow: visible;
}
.table-responsive {
    overflow-x: auto;
    scrollbar-width: thin;
}
.table {
    border-collapse: separate !important;
    border-spacing: 0 12px !important;
    margin-bottom: 0;
    font-size: 0.92rem;
}
.table thead th {
    background: transparent;
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 8px 24px;
    border: none;
    white-space: nowrap;
}
.table tbody tr {
    background: var(--card);
    border-radius: var(--r-md);
    box-shadow: var(--shadow-md);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.table tbody tr:hover { 
    transform: translateY(-3px); 
    box-shadow: var(--shadow-lg);
    background: var(--card);
}
.table tbody td {
    padding: 20px 24px;
    vertical-align: middle;
    border: none;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    color: var(--text);
    font-weight: 500;
    background: var(--card);
    transition: background 0.25s ease;
}

/* Floating Row Borders and Corners */
.table tbody td:first-child {
    border-left: 1px solid var(--border);
    border-top-left-radius: var(--r-md);
    border-bottom-left-radius: var(--r-md);
    position: relative;
}
/* Indicator colored bar on hover */
.table tbody td:first-child::before {
    content: '';
    position: absolute;
    left: 0;
    top: 16px;
    bottom: 16px;
    width: 4px;
    background: var(--accent-grad);
    border-radius: 0 4px 4px 0;
    opacity: 0;
    transform: scaleY(0.7);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.table tbody tr:hover td:first-child::before {
    opacity: 1;
    transform: scaleY(1);
}

.table tbody td:last-child {
    border-right: 1px solid var(--border);
    border-top-right-radius: var(--r-md);
    border-bottom-right-radius: var(--r-md);
}

/* Cells styling */
.user-cell {
    display: flex; 
    align-items: center; 
    gap: 16px;
}
.user-avatar {
    width: 46px; 
    height: 46px;
    border-radius: 14px;
    display: flex; 
    align-items: center; 
    justify-content: center;
    color: #fff; 
    font-weight: 800; 
    font-size: 1.05rem;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    flex-shrink: 0;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.3s ease;
}
.table tbody tr:hover .user-avatar {
    transform: scale(1.05) rotate(2deg);
}
.user-avatar img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
}
.user-name { 
    font-weight: 700; 
    color: #1e293b; 
    font-size: 0.95rem;
    transition: color 0.2s ease;
}
.table tbody tr:hover .user-name {
    color: var(--accent);
}
.user-email { 
    font-size: 0.78rem; 
    color: var(--muted); 
    font-weight: 500; 
    margin-top: 3px; 
    display: flex;
    align-items: center;
    gap: 4px;
}
.user-email.no-email {
    font-style: italic;
    color: #94a3b8;
}

/* Pulsing Status Badge — Active (online) */
.status-badge {
    display: inline-flex; 
    align-items: center; 
    gap: 8px;
    padding: 6px 14px; 
    border-radius: 50px;
    font-size: .75rem; 
    font-weight: 700; 
    text-transform: capitalize;
    background: var(--green-bg);  
    color: #047857;
    border: 1.5px solid var(--green-border);
    box-shadow: var(--shadow-sm);
}
.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
    box-shadow: 0 0 6px #10b981;
    animation: status-pulse 2s infinite ease-in-out;
}
@keyframes status-pulse {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 10px #10b981; }
    100% { transform: scale(0.9); opacity: 0.7; }
}
/* Static Offline Badge */
.status-badge-offline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 50px;
    font-size: .75rem;
    font-weight: 700;
    text-transform: capitalize;
    background: rgba(148, 163, 184, 0.08);
    color: #64748b;
    border: 1.5px solid rgba(148, 163, 184, 0.18);
    box-shadow: var(--shadow-sm);
}
.status-dot-offline {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #94a3b8;
    display: inline-block;
}

/* Modern Action Buttons */
.action-btn {
    width: 38px; 
    height: 38px;
    border-radius: 50% !important;
    display: inline-flex; 
    align-items: center; 
    justify-content: center;
    background: var(--bg); 
    color: var(--muted);
    border: 1.5px solid var(--border);
    cursor: pointer; 
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    font-size: 0.85rem;
    margin-left: 6px;
}
.btn-call {
    background: rgba(59, 130, 246, 0.05);
    color: #2563eb;
    border-color: rgba(59, 130, 246, 0.1);
}
.btn-call:hover {
    background: #2563eb !important;
    color: #fff !important;
    border-color: #2563eb !important;
    box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
    transform: translateY(-3px) scale(1.08);
}
.btn-view {
    background: rgba(139, 92, 246, 0.05);
    color: #7c3aed;
    border-color: rgba(139, 92, 246, 0.1);
}
.btn-view:hover {
    background: #7c3aed !important;
    color: #fff !important;
    border-color: #7c3aed !important;
    box-shadow: 0 6px 14px rgba(124, 58, 237, 0.3);
    transform: translateY(-3px) scale(1.08);
}
.btn-delete {
    background: rgba(239, 68, 68, 0.05);
    color: #dc2626;
    border-color: rgba(239, 68, 68, 0.1);
}
.btn-delete:hover {
    background: #dc2626 !important;
    color: #fff !important;
    border-color: #dc2626 !important;
    box-shadow: 0 6px 14px rgba(220, 38, 38, 0.3);
    transform: translateY(-3px) scale(1.08);
}

/* ── EMPTY STATE ─────────────────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 80px 24px;
    background: var(--card);
    border-radius: var(--r-xl);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-md);
}
.empty-state-icon {
    width: 72px; 
    height: 72px; 
    border-radius: 50%;
    background: var(--bg); 
    border: 2px solid var(--border);
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-size: 2rem; 
    margin: 0 auto 20px; 
    color: var(--muted);
    opacity: .8;
}
.empty-state h3 { 
    font-size: 1.25rem; 
    font-weight: 800; 
    margin-bottom: 8px; 
    color: var(--text); 
}
.empty-state p {
    color: var(--muted);
    font-size: 0.88rem;
}

#no-results { 
    display: none; 
    text-align: center; 
    padding: 50px; 
    background: var(--card);
    border-radius: var(--r-xl);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-md);
    color: var(--muted); 
    font-weight: 600; 
    font-size: 0.95rem;
}

/* ── RESPONSIVENESS ──────────────────────────────────────────────── */
@media(max-width: 992px) {
    .main-wrapper { margin-left: 0; }
    .topbar { padding: 0 20px; }
    .content-area { padding: 24px 20px 32px; }
}
@media(max-width: 768px) {
    .topbar-clock { display: none; }
    .toolbar { padding: 16px; }
    .table tbody td { padding: 16px; }
    .action-btn { width: 34px; height: 34px; margin-left: 4px; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-wrapper">

  <!-- ── TOP BAR ─────────────────────────────────────────────────── -->
  <header class="topbar">
    <div class="topbar-title">
      <div class="topbar-icon"><i class="fa fa-user-group"></i></div>
      User Management
    </div>
    <div class="topbar-spacer"></div>
    <div class="topbar-clock">
      <span class="clock-dot"></span>
      <span id="clock-time">--:--</span>
    </div>
  </header>

  <!-- ── MAIN CONTENT ────────────────────────────────────────────── -->
  <div class="content-area">

    <!-- ── TOOLBAR ─────────────────────────────────────────────── -->
    <div class="toolbar">
      <div class="search-wrap">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="search-input" placeholder="Search users by name, email or phone…" oninput="filterUsers()" autocomplete="off">
      </div>
      <span class="results-label" id="results-label"><i class="fa fa-users" style="margin-right:6px"></i> <?= $total ?> Total Users</span>
      <button class="add-user-btn" onclick="openAddModal()"><i class="fa fa-plus"></i> Add User</button>
    </div>

    <!-- ── TABLE ─────────────────────────────────────────────────── -->
    <div class="users-table-container">
      <?php if (empty($users)): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fa fa-user-xmark"></i></div>
        <h3>No Users Found</h3>
        <p>No registered users found in the database.</p>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>User</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Joined Date</th>
              <th style="text-align:right">Actions</th>
            </tr>
          </thead>
          <tbody id="users-tbody">
            <?php 
            $avatar_gradients = [
                'linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%)', // Indigo to Cyan
                'linear-gradient(135deg, #ec4899 0%, #f43f5e 100%)', // Pink to Rose
                'linear-gradient(135deg, #10b981 0%, #059669 100%)', // Emerald
                'linear-gradient(135deg, #f59e0b 0%, #e11d48 100%)', // Amber to Rose
                'linear-gradient(135deg, #8b5cf6 0%, #d946ef 100%)', // Purple to Magenta
            ];
            foreach ($users as $u):
                $search = strtolower($u['fullname'] . ' ' . $u['email'] . ' ' . $u['phone']);
                $first_letter = strtoupper(substr($u['fullname'], 0, 1));
                $grad_index = (ord($first_letter) + strlen($u['fullname'])) % count($avatar_gradients);
                $avatar_style = "background: " . $avatar_gradients[$grad_index] . ";";
            ?>
            <tr data-search="<?= htmlspecialchars($search) ?>">
              <td>
                <div class="user-cell">
                  <div class="user-avatar" style="<?= $avatar_style ?>">
                    <?php if (!empty($u['profile_image'])): ?>
                      <img src="../<?= htmlspecialchars($u['profile_image']) ?>" alt="avatar">
                    <?php else: ?>
                      <?= $first_letter ?>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="user-name"><?= htmlspecialchars($u['fullname']) ?></div>
                    <div class="user-email <?= $u['email'] ? '' : 'no-email' ?>">
                      <i class="fa <?= $u['email'] ? 'fa-envelope' : 'fa-envelope-open' ?>" style="font-size:0.7rem;opacity:0.6;margin-right:4px;"></i>
                      <?= htmlspecialchars($u['email'] ?: 'No email provided') ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:0.85rem;font-weight:600;color:var(--text);">
                  <i class="fa fa-phone" style="margin-right:6px;opacity:0.4;font-size:0.75rem;"></i><?= htmlspecialchars($u['phone']) ?>
                </div>
              </td>
              <td>
                <?php
                  // Consider a user Active if their location was updated in the last 10 minutes
                  $last_seen = strtotime($u['location_updated_at']);
                  $is_online = (time() - $last_seen) <= 600; // 600 seconds = 10 minutes
                ?>
                <?php if ($is_online): ?>
                  <span class="status-badge">
                    <span class="status-dot"></span>
                    Active
                  </span>
                <?php else: ?>
                  <span class="status-badge-offline">
                    <span class="status-dot-offline"></span>
                    Offline
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:0.82rem;color:var(--muted);font-weight:600;">
                  <i class="fa fa-calendar" style="margin-right:6px;opacity:0.5"></i><?= date('M d, Y', strtotime($u['created_at'])) ?>
                </div>
              </td>
              <td style="text-align:right">
                <a href="tel:<?= htmlspecialchars($u['phone']) ?>" class="action-btn btn-call" title="Call User"><i class="fa fa-phone"></i></a>
                <a href="view_user.php?id=<?= $u['id'] ?>" class="action-btn btn-view" title="View Profile"><i class="fa fa-eye"></i></a>
                <button class="action-btn btn-delete" title="Delete User" onclick="openDeleteModal('delete_user.php?id=<?= $u['id'] ?>')"><i class="fa fa-trash"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div id="no-results">No users match your search criteria.</div>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /content-area -->
</div><!-- /main-wrapper -->

  <!-- ── DELETE MODAL ────────────────────────────────────────────── -->
  <div class="modal-overlay" id="deleteModal" onclick="if(event.target===this) closeDeleteModal()">
    <div class="modal-box">
      <div class="modal-del-icon"><i class="fa fa-trash-can"></i></div>
      <h3 class="modal-title">Delete User</h3>
      <p class="modal-desc">Are you sure you want to permanently delete this user? This action cannot be undone.</p>
      <div class="modal-actions">
        <button class="modal-btn modal-cancel" onclick="closeDeleteModal()">Cancel</button>
        <a href="#" id="delete-confirm-href" class="modal-btn modal-delete">Yes, Delete</a>
      </div>
    </div>
  </div>

  <!-- ── ADD USER MODAL ──────────────────────────────────────────── -->
  <div class="modal-overlay" id="addUserModal" onclick="if(event.target===this) closeAddModal()">
    <div class="modal-box text-start">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="topbar-icon" style="background: var(--accent-grad); width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);">
          <i class="fa fa-user-plus" style="font-size: 0.85rem;"></i>
        </div>
        <h3 class="modal-title m-0" style="font-size: 1.2rem; font-weight: 800; color: #0f172a;">Add New User</h3>
      </div>
      
      <form action="add_user.php" method="POST">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="fullname" class="form-control" placeholder="e.g. Abdirahman Ali" required autocomplete="off">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="e.g. 61xxxxxxx" required autocomplete="off">
        </div>
        <div class="mb-3">
          <label class="form-label">Email Address (Optional)</label>
          <input type="email" name="email" class="form-control" placeholder="e.g. user@domain.com" autocomplete="off">
        </div>
        <div class="mb-4">
          <label class="form-label">Password</label>
          <div class="pwd-wrapper">
            <input type="password" name="password" id="user-password" class="form-control" placeholder="••••••••" required>
            <button type="button" class="pwd-toggle" onclick="togglePassword('user-password', this)" tabindex="-1" aria-label="Show/Hide password">
              <i class="fa fa-eye-slash" id="pwd-icon-user-password"></i>
            </button>
          </div>
        </div>
        <div class="modal-actions mt-4 pt-2">
          <button type="button" class="modal-btn modal-cancel" onclick="closeAddModal()">Cancel</button>
          <button type="submit" class="modal-btn modal-save">Save User</button>
        </div>
      </form>
    </div>
  </div>

<style>
/* ── MODALS STYLE SYSTEM ─────────────────────────────────────────── */
.modal-overlay {
    display: none; 
    position: fixed; 
    inset: 0;
    background: rgba(15, 23, 42, 0.4); 
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999; 
    align-items: center; 
    justify-content: center;
    opacity: 0; 
    transition: all 0.3s ease;
}
.modal-overlay.show { 
    display: flex; 
    opacity: 1; 
}
.modal-box {
    background: var(--card); 
    border-radius: var(--r-xl);
    width: 90%; 
    max-width: 440px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 40px rgba(0, 0, 0, 0.03);
    padding: 36px;
    transform: scale(0.9) translateY(15px);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
    border: 1px solid var(--border);
}
.modal-overlay.show .modal-box { 
    transform: scale(1) translateY(0); 
}
.modal-del-icon {
    width: 68px; 
    height: 68px; 
    border-radius: 50%;
    background: var(--red-bg); 
    color: var(--red);
    display: flex; 
    align-items: center; 
    justify-content: center;
    font-size: 2rem; 
    margin: 0 auto 20px;
    border: 1.5px solid var(--red-border);
    box-shadow: 0 0 16px rgba(239, 68, 68, 0.1);
    animation: heartbeat 1.5s infinite ease-in-out;
}
@keyframes heartbeat {
    0% { transform: scale(1); }
    50% { transform: scale(1.08); box-shadow: 0 0 24px rgba(239, 68, 68, 0.2); }
    100% { transform: scale(1); }
}
.modal-title { 
    font-size: 1.25rem; 
    font-weight: 900; 
    margin-bottom: 8px; 
    color: var(--text);
}
.modal-desc  { 
    font-size: .88rem; 
    color: var(--muted); 
    font-weight: 500; 
    line-height: 1.6; 
    margin-bottom: 28px; 
}
.modal-actions { 
    display: flex; 
    gap: 12px; 
}
.modal-btn {
    flex: 1; 
    padding: 12px 20px;
    border-radius: var(--r-md);
    font-family: 'Outfit', sans-serif; 
    font-size: .88rem; 
    font-weight: 700;
    border: none; 
    cursor: pointer; 
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none; 
    display: flex; 
    align-items: center; 
    justify-content: center;
}
.modal-cancel { 
    background: var(--bg); 
    color: var(--muted); 
}
.modal-cancel:hover { 
    background: var(--border); 
    color: var(--text); 
}
.modal-delete { 
    background: var(--red); 
    color: #fff; 
    box-shadow: 0 4px 12px rgba(239,68,68,.3); 
}
.modal-delete:hover { 
    background: #dc2626; 
    transform: translateY(-2px); 
    box-shadow: 0 8px 20px rgba(239,68,68,.45); 
    color: #fff; 
}
.modal-save {
    background: var(--accent-grad);
    color: white;
    box-shadow: 0 4px 12px rgba(59,130,246,.25);
}
.modal-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59,130,246,.35);
}
.modal-save:active {
    transform: translateY(0);
}

/* Form Styling within Modals */
.form-label {
    font-size: .78rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
    display: block;
}
.form-control {
    border-radius: var(--r-md) !important;
    padding: 12px 16px !important;
    font-size: .9rem !important;
    font-weight: 500 !important;
    background: #f8fafc !important;
    border: 1.5px solid var(--border) !important;
    color: var(--text) !important;
    transition: all 0.25s ease !important;
    box-shadow: none !important;
}
.form-control:focus {
    background: #fff !important;
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 4px var(--accent-glow) !important;
    outline: none !important;
}
.form-control::placeholder {
    color: #94a3b8;
    font-weight: 400;
}

/* Password visibility toggle */
input::-ms-reveal,
input::-ms-clear {
    display: none !important;
}
.pwd-wrapper {
    position: relative;
    width: 100%;
}
.pwd-toggle {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 0;
    font-size: 1rem;
    z-index: 10;
    transition: color 0.2s ease;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pwd-toggle:hover {
    color: var(--accent, #3b82f6);
}
.pwd-wrapper .form-control {
    padding-right: 46px !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live clock
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

// Search filtering
function filterUsers() {
    const q = document.getElementById('search-input').value.trim().toLowerCase();
    const rows = document.querySelectorAll('#users-tbody tr');
    let visible = 0;
    
    rows.forEach(row => {
        if (!q || row.dataset.search.includes(q)) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const nr = document.getElementById('no-results');
    if (nr) nr.style.display = (visible === 0) ? 'block' : 'none';
    
    const rl = document.getElementById('results-label');
    if (rl) rl.innerHTML = `<i class="fa fa-users" style="margin-right:6px"></i> ${visible} User${visible !== 1 ? 's' : ''} Found`;
}

// Delete Modal
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

// Add User Modal
function openAddModal() {
    const m = document.getElementById('addUserModal');
    m.style.display = 'flex';
    requestAnimationFrame(() => m.classList.add('show'));
}
function closeAddModal() {
    const m = document.getElementById('addUserModal');
    m.classList.remove('show');
    setTimeout(() => { m.style.display = 'none'; }, 250);
}

function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById('pwd-icon-' + fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.style.color = 'var(--accent, #3b82f6)';
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.style.color = '';
    }
}
</script>
</body>
</html>
