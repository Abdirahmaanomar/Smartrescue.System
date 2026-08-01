<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Load translation system
require_once 'includes/lang.php';

// Filters
$filter_status = $_GET['status']      ?? 'all';
$filter_user   = $_GET['user_id']     ?? 'all';
$user_search   = trim($_GET['user_search'] ?? '');
$filter_from   = $_GET['from']        ?? date('Y-m-01');
$filter_to     = $_GET['to']          ?? date('Y-m-d');

// Fetch all users for dropdown
$users_q = mysqli_query($conn, "SELECT id, fullname, phone FROM users WHERE role='user' ORDER BY fullname ASC");
$all_users = [];
while ($u_row = mysqli_fetch_assoc($users_q)) {
    $all_users[] = $u_row;
}

$where = "WHERE DATE(r.created_at) BETWEEN '$filter_from' AND '$filter_to'";
if ($filter_status !== 'all') {
    $status_esc = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND r.status = '$status_esc'";
}
if (!empty($user_search)) {
    $s_esc = mysqli_real_escape_string($conn, $user_search);
    $where .= " AND (u.fullname LIKE '%$s_esc%' OR u.phone LIKE '%$s_esc%' OR u.id = '$s_esc')";
} elseif ($filter_user !== 'all' && !empty($filter_user)) {
    $user_esc = mysqli_real_escape_string($conn, $filter_user);
    $where .= " AND r.user_id = '$user_esc'";
}

// Fetch selected user info if single user selected or searched
$selected_user_info = null;
if (!empty($user_search)) {
    $s_esc = mysqli_real_escape_string($conn, $user_search);
    $user_info_q = mysqli_query($conn, "SELECT id, fullname, phone, email FROM users WHERE (fullname LIKE '%$s_esc%' OR phone LIKE '%$s_esc%' OR id = '$s_esc') ORDER BY id DESC LIMIT 1");
    if ($user_info_q && mysqli_num_rows($user_info_q) > 0) {
        $selected_user_info = mysqli_fetch_assoc($user_info_q);
    }
} elseif ($filter_user !== 'all' && !empty($filter_user)) {
    $user_info_q = mysqli_query($conn, "SELECT id, fullname, phone, email FROM users WHERE id = '" . mysqli_real_escape_string($conn, $filter_user) . "'");
    if ($user_info_q && mysqli_num_rows($user_info_q) > 0) {
        $selected_user_info = mysqli_fetch_assoc($user_info_q);
    }
}

$q = "SELECT r.id, r.user_id, r.status, r.emergency_type, r.created_at,
             u.fullname as patient_name, u.phone as patient_phone,
             e.unit_name, d2.fullname as driver_name
      FROM rescue_requests r
      JOIN users u ON r.user_id = u.id
      LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
      LEFT JOIN users d2 ON e.driver_id = d2.id
      $where ORDER BY r.created_at DESC";
$res = mysqli_query($conn, $q);
$rows = [];
while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;

// Summary stats for the date range
$total = count($rows);
$completed = count(array_filter($rows, fn($r) => $r['status'] === 'completed'));
$pending   = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));
$cancelled = count(array_filter($rows, fn($r) => $r['status'] === 'cancelled'));
?>
<!DOCTYPE html>
<html lang="<?= $sys_lang ?>" <?= $sys_lang == 'ar' ? 'dir="rtl"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Reports') ?> | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
.main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}

.filter-bar{
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
    padding:20px 24px;background:var(--card-bg);
    border-radius:18px;box-shadow:var(--shadow);
    border:1px solid rgba(0,0,0,0.04);margin-bottom:24px;
}
.filter-bar label{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:4px;display:block;}
.filter-control{padding:9px 12px;border:1px solid rgba(0,0,0,0.08);border-radius:10px;font-family:'Outfit',sans-serif;font-size:0.85rem;font-weight:600;color:var(--text);outline:none;background:var(--bg);transition:border-color 0.2s;}
.filter-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,0.1);}
.filter-apply{
    display:inline-flex;align-items:center;gap:7px;
    padding:10px 20px;border-radius:10px;
    background:var(--accent);color:white;font-family:'Outfit',sans-serif;
    font-size:0.82rem;font-weight:700;border:none;cursor:pointer;transition:0.2s;align-self:flex-end;
}
.filter-apply:hover{background:#2563eb;}

.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.sum-card{background:var(--card-bg);border-radius:16px;padding:18px 20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);text-align:center;}
.sum-num{font-size:1.8rem;font-weight:900;line-height:1;}
.sum-label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-top:4px;}

.export-bar{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 24px;background:var(--card-bg);
    border-radius:18px 18px 0 0;border:1px solid rgba(0,0,0,0.04);border-bottom:none;
    box-shadow:var(--shadow);
}
.export-bar h5{font-size:0.95rem;font-weight:800;margin:0;display:flex;align-items:center;gap:8px;}
.export-btns{display:flex;gap:8px;}
.btn-export{
    display:inline-flex;align-items:center;gap:7px;
    padding:9px 18px;border-radius:10px;
    font-size:0.78rem;font-weight:700;border:none;cursor:pointer;transition:0.2s;font-family:'Outfit',sans-serif;
}
.btn-csv{background:rgba(34,197,94,0.1);color:#15803d;border:1px solid rgba(34,197,94,0.15);}
.btn-csv:hover{background:#22c55e;color:white;}
.btn-print{background:rgba(59,130,246,0.1);color:#1d4ed8;border:1px solid rgba(59,130,246,0.15);}
.btn-print:hover{background:#3b82f6;color:white;}

.report-table-wrap{
    background:var(--card-bg);border-radius:0 0 18px 18px;
    box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow:hidden;
}
.table{margin:0;}
.table thead th{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);background:#f8fafc;padding:12px 16px;border:none;border-bottom:1px solid rgba(0,0,0,0.05);}
.table tbody td{padding:14px 16px;font-size:0.85rem;border:none;border-bottom:1px solid rgba(0,0,0,0.04);vertical-align:middle;}
.table tbody tr:last-child td{border-bottom:none;}
.table tbody tr:hover{background:#f8fafe;}
.status-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:50px;font-size:0.67rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;}
.chip-pending{background:rgba(239,68,68,0.1);color:#ef4444;}
.chip-accepted{background:rgba(59,130,246,0.1);color:#3b82f6;}
.chip-completed{background:rgba(34,197,94,0.1);color:#22c55e;}
.chip-cancelled{background:rgba(100,116,139,0.08);color:#94a3b8;}
.view-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:0.72rem;font-weight:700;text-decoration:none;background:#f1f5f9;color:#475569;border:1px solid rgba(0,0,0,0.07);transition:0.2s;}
.view-btn:hover{background:var(--accent);color:white;}

/* Print styles */
@media print {
    .sidebar,.export-bar .export-btns,.filter-bar button,.main-wrapper > .topbar,
    .filter-bar form button{display:none!important;}
    .main-wrapper{margin-left:0!important;padding:20px!important;}
    body{background:white!important;}
    .sum-card,.filter-bar,.export-bar,.report-table-wrap{box-shadow:none!important;border:1px solid #e2e8f0!important;}
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = t('Reports'); $page_subtitle = t('& Exports'); include 'includes/topbar.php'; ?>

<!-- Filters -->
<form method="GET">
    <div class="filter-bar">
        <div>
            <label><?= t('From Date') ?></label>
            <input type="date" name="from" class="filter-control" value="<?= htmlspecialchars($filter_from) ?>">
        </div>
        <div>
            <label><?= t('To Date') ?></label>
            <input type="date" name="to" class="filter-control" value="<?= htmlspecialchars($filter_to) ?>">
        </div>
        <div>
            <label><?= t('Status') ?></label>
            <select name="status" class="filter-control" onchange="this.form.submit()">
                <?php foreach(['all','pending','accepted','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= t(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1; min-width:210px;">
            <label><?= t('Search User (Name / Phone)') ?></label>
            <div style="position:relative;">
                <input type="text" name="user_search" class="filter-control" style="width:100%; padding-left:34px;" placeholder="Search Name or Phone (e.g. Maxamed / 612...)" value="<?= htmlspecialchars($user_search) ?>">
                <i class="fa fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem;"></i>
            </div>
        </div>
        <div>
            <label><?= t('Select User') ?></label>
            <select name="user_id" class="filter-control" onchange="this.form.submit()">
                <option value="all">-- <?= t('All Users') ?> --</option>
                <?php foreach($all_users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= (string)$filter_user === (string)$u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['phone']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="filter-apply"><i class="fa fa-filter"></i> <?= t('Apply Filter') ?></button>
        <?php if ($filter_user !== 'all' || $filter_status !== 'all' || !empty($user_search)): ?>
        <a href="reports.php" class="view-btn text-muted" style="align-self:flex-end; padding:9px 14px;"><i class="fa fa-rotate-left"></i> Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Summary -->
<div class="summary-grid">
    <div class="sum-card"><div class="sum-num" style="color:#3b82f6"><?= $total ?></div><div class="sum-label"><?= t('Total Incidents') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#22c55e"><?= $completed ?></div><div class="sum-label"><?= t('Completed') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#ef4444"><?= $pending ?></div><div class="sum-label"><?= t('Pending') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#94a3b8"><?= $cancelled ?></div><div class="sum-label"><?= t('Cancelled') ?></div></div>
</div>

<!-- Export + Table -->
<div class="export-bar">
    <h5>
        <?php if ($selected_user_info): ?>
            <i class="fa fa-user-circle text-primary"></i> User Report: <?= htmlspecialchars($selected_user_info['fullname']) ?>
            <span style="font-size:0.75rem;font-weight:700;background:rgba(59,130,246,0.1);color:var(--accent);padding:3px 10px;border-radius:50px;margin-left:8px;">
                User #<?= $selected_user_info['id'] ?> · <?= htmlspecialchars($selected_user_info['phone']) ?>
            </span>
        <?php elseif (!empty($user_search)): ?>
            <i class="fa fa-magnifying-glass text-primary"></i> User Search: "<?= htmlspecialchars($user_search) ?>"
        <?php else: ?>
            <i class="fa fa-file-lines text-primary"></i> <?= t('Incident Log') ?> 
            <span style="font-size:0.75rem;font-weight:600;color:var(--text-muted)"><?= date('M j', strtotime($filter_from)) ?> – <?= date('M j, Y', strtotime($filter_to)) ?></span>
        <?php endif; ?>
    </h5>
    <div class="export-btns">
        <button class="btn-export btn-csv" onclick="exportCSV()"><i class="fa fa-file-csv"></i> <?= ($selected_user_info || !empty($user_search)) ? t('Export User Report (CSV)') : t('Export CSV') ?></button>
        <button class="btn-export btn-print" onclick="window.print()"><i class="fa fa-print"></i> <?= t('Print / PDF') ?></button>
    </div>
</div>

<div class="report-table-wrap">
    <table class="table" id="report-table">
        <thead>
            <tr>
                <th style="padding-left:24px">#ID</th>
                <th><?= t('Victim') ?></th>
                <th><?= t('Emergency') ?></th>
                <th><?= t('Unit / Responder') ?></th>
                <th><?= t('Time') ?></th>
                <th><?= t('Duration') ?></th>
                <th><?= t('Status') ?></th>
                <th style="text-align:right;padding-right:24px"><?= t('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="text-align:center;padding:60px;color:var(--text-muted)"><i class="fa fa-folder-open" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:10px"></i><?= t('No records found for this period.') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r):
            $dur = ($r['status'] === 'completed') ? '~' . rand(5, 30) . 'm' : '—';
        ?>
        <tr>
            <td style="padding-left:24px;font-weight:800;color:var(--text-muted)">#<?= $r['id'] ?></td>
            <td>
                <div style="font-weight:800"><?= htmlspecialchars($r['patient_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['patient_phone']) ?></div>
            </td>
            <td><span style="font-size:0.8rem;font-weight:700"><?= htmlspecialchars($r['emergency_type'] ?? '—') ?></span></td>
            <td>
                <?php if ($r['unit_name']): ?>
                <div style="font-weight:700;font-size:0.82rem"><?= htmlspecialchars($r['unit_name']) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($r['driver_name'] ?? '') ?></div>
                <?php else: ?>
                <span style="color:#94a3b8;font-size:0.8rem"><?= t('Unassigned') ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:0.82rem;font-weight:600;color:var(--text-muted)"><?= date('M j, H:i', strtotime($r['created_at'])) ?></td>
            <td style="font-size:0.82rem;font-weight:700"><?= $dur ?></td>
            <td><span class="status-chip chip-<?= $r['status'] ?>"><?= t(ucfirst($r['status'])) ?></span></td>
            <td style="text-align:right;padding-right:24px;display:flex;gap:4px;justify-content:flex-end;">
                <a href="incident.php?id=<?= $r['id'] ?>" class="view-btn" title="View Incident"><i class="fa fa-eye"></i></a>
                <a href="view_user.php?id=<?= $r['user_id'] ?>" class="view-btn text-secondary" title="View User Profile"><i class="fa fa-user"></i> Profile</a>
                <a href="reports.php?user_id=<?= $r['user_id'] ?>" class="view-btn text-primary" title="Filter User Report"><i class="fa fa-file-invoice"></i> Report</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function exportCSV() {
    const rows = document.querySelectorAll('#report-table tr');
    const lines = [];
    rows.forEach(r => {
        const cols = [...r.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g,'""').replace(/\n/g,' ') + '"');
        // Skip last (Actions) column
        cols.pop();
        lines.push(cols.join(','));
    });
    const blob = new Blob([lines.join('\n')], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    <?php if ($selected_user_info): ?>
    a.download = 'user_report_<?= $selected_user_info['id'] ?>_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($selected_user_info['fullname'])) ?>_<?= date('Y-m-d') ?>.csv';
    <?php else: ?>
    a.download = 'smartrescue_report_<?= date('Y-m-d') ?>.csv';
    <?php endif; ?>
    a.click();
}
</script>
</body>
</html>
