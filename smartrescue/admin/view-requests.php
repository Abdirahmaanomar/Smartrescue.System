<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['fullname'];

// Load translation system if not already loaded
if (!function_exists('t')) {
    require_once 'includes/lang.php';
}

// Filters & Search logic
$filter_search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$filter_from   = $_GET['from']   ?? '';
$filter_to     = $_GET['to']     ?? '';

$conditions = [];

if (!empty($filter_search)) {
    $search = mysqli_real_escape_string($conn, $filter_search);
    $conditions[] = "(u.fullname LIKE '%$search%' OR u.phone LIKE '%$search%' OR r.emergency_type LIKE '%$search%' OR d.unit_name LIKE '%$search%' OR r.id LIKE '%$search%')";
}

if ($filter_status !== 'all') {
    $status = mysqli_real_escape_string($conn, $filter_status);
    $conditions[] = "r.status = '$status'";
}

if (!empty($filter_from)) {
    $from = mysqli_real_escape_string($conn, $filter_from);
    $conditions[] = "DATE(r.created_at) >= '$from'";
}

if (!empty($filter_to)) {
    $to = mysqli_real_escape_string($conn, $filter_to);
    $conditions[] = "DATE(r.created_at) <= '$to'";
}

$where_clause = "";
if (count($conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch requests with filters
$query = "SELECT r.*, u.fullname as patient_name, u.phone as patient_phone, d.unit_name 
          FROM rescue_requests r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN emergency_units d ON r.assigned_unit_id = d.id
          $where_clause
          ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mission Logs | SmartRescue</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
        body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
        .main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}
        .log-card{background:var(--card-bg);border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow:hidden;}
        
        /* Filter Bar Styles */
        .filter-bar {
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
            padding: 20px 24px; background: var(--card-bg);
            border-radius: 20px; box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.04); margin-bottom: 24px;
        }
        .filter-bar label {
            font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--text-muted); display: block; margin-bottom: 6px;
        }
        .filter-control {
            padding: 10px 14px; border: 1.5px solid rgba(0,0,0,0.06); border-radius: 10px;
            font-family: 'Outfit', sans-serif; font-size: 0.85rem; font-weight: 600;
            color: var(--text); outline: none; background: var(--bg); transition: border-color 0.2s, box-shadow 0.2s;
        }
        .filter-control:focus {
            border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); background: #fff;
        }
        .filter-apply {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 22px; border-radius: 10px;
            background: var(--accent); color: white; font-family: 'Outfit', sans-serif;
            font-size: 0.82rem; font-weight: 800; border: none; cursor: pointer; transition: 0.2s;
            align-self: flex-end; box-shadow: 0 4px 12px rgba(59,130,246,0.2);
        }
        .filter-apply:hover {
            background: #2563eb; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59,130,246,0.3); color: white;
        }
        .filter-clear {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 11px 18px; border-radius: 10px;
            background: #f1f5f9; color: #475569; font-family: 'Outfit', sans-serif;
            font-size: 0.82rem; font-weight: 800; border: 1px solid rgba(0,0,0,0.07); cursor: pointer; transition: 0.2s;
            align-self: flex-end; text-decoration: none;
        }
        .filter-clear:hover {
            background: #e2e8f0; color: #0f172a;
        }
        
        .table{margin:0;}
        .table thead th{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);background:#f8fafc;padding:13px 16px;border:none;border-bottom:1px solid rgba(0,0,0,0.05);}
        .table tbody td{padding:14px 16px;font-size:0.87rem;border:none;border-bottom:1px solid rgba(0,0,0,0.04);vertical-align:middle;}
        .table tbody tr:last-child td{border-bottom:none;}
        .table tbody tr:hover{background:#f8fafe;}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:50px;font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;}
        .status-pending{background:rgba(239,68,68,0.1);color:#ef4444;}
        .status-accepted{background:rgba(59,130,246,0.1);color:#3b82f6;}
        .status-completed{background:rgba(34,197,94,0.1);color:#22c55e;}
        .status-cancelled{background:rgba(100,116,139,0.08);color:#94a3b8;}
        .panel-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid rgba(0,0,0,0.04);}
        .panel-header h5{font-size:0.95rem;font-weight:800;margin:0;display:flex;align-items:center;gap:8px;}
        .view-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;font-size:0.75rem;font-weight:700;text-decoration:none;background:#f1f5f9;color:#475569;border:1px solid rgba(0,0,0,0.07);transition:0.2s;}
        .view-btn:hover{background:var(--accent);color:white;}
        .del-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:0.75rem;font-weight:700;text-decoration:none;background:rgba(239,68,68,0.06);color:#ef4444;border:1px solid rgba(239,68,68,0.1);transition:0.2s;}
        .del-btn:hover{background:#ef4444;color:white;}
        .et-badge{display:inline-block;padding:4px 10px;border-radius:6px;background:rgba(59,130,246,0.08);color:#3b82f6;border:1px solid rgba(59,130,246,0.15);font-size:0.72rem;font-weight:700;}
        
        /* Custom Modal */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 9999; display: none; align-items: center; justify-content: center;
        }
        .modal-overlay.show { display: flex; animation: fadeIn 0.2s ease; }
        .custom-modal {
            background: var(--card-bg); border-radius: 20px; width: 100%; max-width: 380px;
            padding: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); transform: scale(0.95);
            transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1); text-align: center;
        }
        .modal-overlay.show .custom-modal { transform: scale(1); }
        .modal-icon {
            width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.1);
            color: #ef4444; font-size: 1.8rem; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .modal-title { font-size: 1.25rem; font-weight: 900; margin-bottom: 8px; color: var(--text); }
        .modal-desc { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 28px; line-height: 1.5; font-weight: 500; }
        .modal-actions { display: flex; gap: 12px; }
        .modal-btn { 
            flex: 1; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.9rem;
            border: none; cursor: pointer; transition: 0.2s; text-decoration: none; display: flex;
            justify-content: center; align-items: center; gap: 8px; font-family: 'Outfit', sans-serif;
        }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-cancel:hover { background: #e2e8f0; color: #1e293b; }
        .modal-btn-danger { background: #ef4444; color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }
        .modal-btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = 'Mission'; $page_subtitle = 'Logs'; include 'includes/topbar.php'; ?>

    <!-- Filters & Search Form -->
    <form method="GET" style="margin-bottom: 24px;">
        <div class="filter-bar">
            <!-- Text Search Input -->
            <div style="flex: 2; min-width: 240px;">
                <label><?= t('Search') ?></label>
                <div style="position: relative;">
                    <i class="fa fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem; pointer-events: none;"></i>
                    <input type="text" name="search" class="filter-control" style="padding-left: 38px; width: 100%;" placeholder="<?= t('Search victim, phone, type...') ?>" value="<?= htmlspecialchars($filter_search) ?>">
                </div>
            </div>
            
            <!-- From Date Picker -->
            <div style="flex: 1; min-width: 150px;">
                <label><?= t('From Date') ?></label>
                <input type="date" name="from" class="filter-control" style="width: 100%;" value="<?= htmlspecialchars($filter_from) ?>">
            </div>
            
            <!-- To Date Picker -->
            <div style="flex: 1; min-width: 150px;">
                <label><?= t('To Date') ?></label>
                <input type="date" name="to" class="filter-control" style="width: 100%;" value="<?= htmlspecialchars($filter_to) ?>">
            </div>
            
            <!-- Status Dropdown Selector -->
            <div style="flex: 1; min-width: 150px;">
                <label><?= t('Status') ?></label>
                <select name="status" class="filter-control" style="width: 100%; cursor: pointer;" onchange="this.form.submit()">
                    <?php foreach(['all', 'pending', 'accepted', 'completed', 'cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= t(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filter Actions Buttons -->
            <div style="display: flex; gap: 8px; align-self: flex-end;">
                <button type="submit" class="filter-apply"><i class="fa fa-filter"></i> <?= t('Apply Filter') ?></button>
                <?php if (!empty($filter_search) || $filter_status !== 'all' || !empty($filter_from) || !empty($filter_to)): ?>
                    <a href="view-requests.php" class="filter-clear"><i class="fa fa-rotate-left"></i> <?= t('Clear') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="log-card">
        <div class="panel-header">
            <h5><i class="fa fa-list-check" style="color:var(--accent)"></i> Mission Archive</h5>
            <a href="reports.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:10px;background:var(--accent);color:white;font-size:0.8rem;font-weight:700;text-decoration:none"><i class="fa fa-file-lines"></i> Export Report</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="padding-left:24px">#</th>
                        <th>Date &amp; Time</th>
                        <th>Victim</th>
                        <th>Emergency Type</th>
                        <th>Details Sent</th>
                        <th>Assigned Unit</th>
                        <th>Status</th>
                        <th style="text-align:right;padding-right:24px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) == 0): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:60px;color:var(--text-muted)">
                            <i class="fa fa-folder-open" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:12px"></i>
                            <?= t('No missions match your search/filter criteria.') ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td style="padding-left:24px;font-weight:800;color:var(--text-muted);font-size:0.8rem">#<?php echo $row['id']; ?></td>
                        <td style="font-size:0.82rem;font-weight:600;color:var(--text-muted)"><?php echo date('M j, H:i', strtotime($row['created_at'])); ?></td>
                        <td>
                            <div style="font-weight:800"><?php echo htmlspecialchars($row['patient_name']); ?></div>
                            <?php if (!empty($row['patient_phone'])): ?>
                                <div style="font-size:0.75rem;color:var(--text-muted)"><?php echo htmlspecialchars($row['patient_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="et-badge"><?php echo htmlspecialchars($row['emergency_type'] ?? '—'); ?></span></td>
                        <td>
                            <?php 
                            $has_details = false;
                            if (!empty($row['description'])) {
                                $has_details = true;
                                echo '<div style="font-size:0.78rem;color:#475569;font-style:italic;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . htmlspecialchars($row['description']) . '">"' . htmlspecialchars($row['description']) . '"</div>';
                            }
                            if (!empty($row['evidence_image'])) {
                                $has_details = true;
                                echo '<div style="display:flex;gap:4px;margin-top:4px;">';
                                $imgs = explode(',', $row['evidence_image']);
                                foreach ($imgs as $img) {
                                    $img = trim($img);
                                    if (empty($img)) continue;
                                    if (strpos($img, 'uploads/') === 0) {
                                        $webPath = '../' . $img;
                                        $fsPath  = dirname(__DIR__) . '/' . $img;
                                        $apiFsPath = dirname(__DIR__) . '/api/' . $img;
                                        $apiWebPath = '../api/' . $img;
                                    } else {
                                        $webPath = '../uploads/' . $img;
                                        $fsPath  = dirname(__DIR__) . '/uploads/' . $img;
                                        $apiFsPath = dirname(__DIR__) . '/api/uploads/' . $img;
                                        $apiWebPath = '../api/uploads/' . $img;
                                    }
                                    
                                    if (file_exists($fsPath)) {
                                        $finalWebPath = $webPath;
                                    } elseif (file_exists($apiFsPath)) {
                                        $finalWebPath = $apiWebPath;
                                    } else {
                                        $finalWebPath = $webPath;
                                    }
                                    
                                    echo '<img src="' . htmlspecialchars($finalWebPath) . '" style="width:28px;height:28px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.1);cursor:zoom-in;" onclick="window.open(this.src,\'_blank\')" title="Click to view">';
                                }
                                echo '</div>';
                            }
                            if (!$has_details) {
                                echo '<span style="color:#94a3b8;font-size:0.8rem;">None</span>';
                            }
                            ?>
                        </td>
                        <td><div style="font-weight:700;font-size:0.85rem"><?php echo $row['unit_name'] ? htmlspecialchars($row['unit_name']) : '<span style="color:#94a3b8">Unassigned</span>'; ?></div></td>
                        <td>
                            <?php $status = strtolower($row['status']); ?>
                            <span class="status-pill status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                        </td>
                        <td style="text-align:right;padding-right:24px;display:flex;gap:6px;justify-content:flex-end">
                            <a href="incident.php?id=<?php echo $row['id']; ?>" class="view-btn"><i class="fa fa-eye"></i> View</a>
                            <a href="#" onclick="openDeleteModal(event, 'delete_request.php?id=<?php echo $row['id']; ?>')" class="del-btn" title="Delete Mission Log"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal" onclick="closeDeleteModal(event)">
    <div class="custom-modal">
        <div class="modal-icon"><i class="fa fa-trash-can"></i></div>
        <h3 class="modal-title">Delete Log</h3>
        <p class="modal-desc">Are you sure you want to delete this mission record? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal(event, true)">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="modal-btn modal-btn-danger">Delete Log</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openDeleteModal(e, url) {
        e.preventDefault();
        document.getElementById('confirmDeleteBtn').href = url;
        document.getElementById('deleteModal').classList.add('show');
    }
    function closeDeleteModal(e, force = false) {
        if (force || e.target.id === 'deleteModal') {
            document.getElementById('deleteModal').classList.remove('show');
        }
    }
</script>
</body>
</html>