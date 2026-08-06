<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Load translation system
require_once 'includes/lang.php';

// Parameters & Filters
$report_type   = $_GET['type']        ?? 'all_incidents'; // 'all_incidents', 'individual', 'responder', 'services'
$all_dates     = (isset($_GET['all_dates']) && $_GET['all_dates'] == '1') ? 1 : 0;
$filter_status = $_GET['status']      ?? 'all';
$service_filter= $_GET['service']     ?? 'all'; // 'all', 'Medical', 'Fire', 'Police', 'Accident'
$search        = trim($_GET['search'] ?? '');
$selected_id   = trim($_GET['id']     ?? '');
$filter_from   = $_GET['from']        ?? date('Y-m-01');
$filter_to     = $_GET['to']          ?? date('Y-m-d');

// Construct Date Where Condition
if ($all_dates) {
    $date_where = "1=1";
} else {
    $date_where = "DATE(r.created_at) BETWEEN '$filter_from' AND '$filter_to'";
}

// Global Summary Stats (for header overview cards)
$total_incidents_q   = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests");
$total_incidents_val = ($total_incidents_q && $r = mysqli_fetch_assoc($total_incidents_q)) ? (int)$r['c'] : 0;

$completed_q         = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE status='completed'");
$completed_val       = ($completed_q && $r = mysqli_fetch_assoc($completed_q)) ? (int)$r['c'] : 0;

$pending_q           = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE status='pending'");
$pending_val         = ($pending_q && $r = mysqli_fetch_assoc($pending_q)) ? (int)$r['c'] : 0;

$responders_q        = mysqli_query($conn, "SELECT COUNT(*) as c FROM emergency_units");
$responders_val      = ($responders_q && $r = mysqli_fetch_assoc($responders_q)) ? (int)$r['c'] : 0;

// Data structures for each report type
$report_data = [];
$single_info = null;
$service_summary = [];

if ($report_type === 'all_incidents') {
    $where = "WHERE $date_where";
    if ($filter_status !== 'all') {
        $st_esc = mysqli_real_escape_string($conn, $filter_status);
        $where .= " AND r.status = '$st_esc'";
    }
    if (!empty($search)) {
        $s_esc = mysqli_real_escape_string($conn, $search);
        $where .= " AND (u.fullname LIKE '%$s_esc%' OR u.phone LIKE '%$s_esc%' OR e.unit_name LIKE '%$s_esc%' OR r.emergency_type LIKE '%$s_esc%' OR r.id = '$s_esc')";
    }

    $q = "SELECT r.id, r.user_id, r.status, r.emergency_type, r.created_at,
                 u.fullname as patient_name, u.phone as patient_phone,
                 e.id as unit_id, e.unit_name, d2.fullname as driver_name
          FROM rescue_requests r
          JOIN users u ON r.user_id = u.id
          LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
          LEFT JOIN users d2 ON e.driver_id = d2.id
          $where ORDER BY r.created_at DESC";
    $res = mysqli_query($conn, $q);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $report_data[] = $row;
        }
    }

} elseif ($report_type === 'individual') {
    // If a specific individual victim is selected or searched
    if (!empty($selected_id) || !empty($search)) {
        $user_where = !empty($selected_id) ? "id = '" . mysqli_real_escape_string($conn, $selected_id) . "'" : "(fullname LIKE '%".mysqli_real_escape_string($conn, $search)."%' OR phone LIKE '%".mysqli_real_escape_string($conn, $search)."%')";
        $user_info_q = mysqli_query($conn, "SELECT id, fullname, phone, email, medical_info, emergency_contacts, created_at FROM users WHERE role='user' AND $user_where ORDER BY id DESC LIMIT 1");
        if ($user_info_q && mysqli_num_rows($user_info_q) > 0) {
            $single_info = mysqli_fetch_assoc($user_info_q);
            $u_id = (int)$single_info['id'];

            // Individual stats summary
            $indiv_stats_q = mysqli_query($conn, "SELECT 
                COUNT(id) as total_sent,
                COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END), 0) as completed_sent,
                COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as pending_sent,
                COALESCE(SUM(CASE WHEN status IN ('cancelled','rejected') THEN 1 ELSE 0 END), 0) as cancelled_sent
                FROM rescue_requests WHERE user_id = '$u_id'");
            if ($indiv_stats_q && $st_r = mysqli_fetch_assoc($indiv_stats_q)) {
                $single_info['stats'] = $st_r;
            }

            $where = "WHERE r.user_id = '$u_id' AND $date_where";
            if ($filter_status !== 'all') {
                $st_esc = mysqli_real_escape_string($conn, $filter_status);
                $where .= " AND r.status = '$st_esc'";
            }
            $q = "SELECT r.id, r.status, r.emergency_type, r.created_at, r.lat, r.lng,
                         e.unit_name, d2.fullname as driver_name
                  FROM rescue_requests r
                  LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
                  LEFT JOIN users d2 ON e.driver_id = d2.id
                  $where ORDER BY r.created_at DESC";
            $res = mysqli_query($conn, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $report_data[] = $row;
                }
            }
        }
    }
    
    // Overview of all individuals if no single individual selected
    if (empty($single_info)) {
        $search_where = "";
        if (!empty($search)) {
            $s_esc = mysqli_real_escape_string($conn, $search);
            $search_where = "AND (u.fullname LIKE '%$s_esc%' OR u.phone LIKE '%$s_esc%' OR u.email LIKE '%$s_esc%')";
        }
        $q = "SELECT u.id, u.fullname, u.phone, u.email, u.created_at as registered_date,
                     COUNT(r.id) as total_requests,
                     COALESCE(SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END), 0) as completed_count,
                     COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END), 0) as pending_count,
                     COALESCE(SUM(CASE WHEN r.status IN ('cancelled','rejected') THEN 1 ELSE 0 END), 0) as cancelled_count,
                     MAX(r.created_at) as last_request
              FROM users u
              LEFT JOIN rescue_requests r ON u.id = r.user_id " . ($all_dates ? "" : "AND DATE(r.created_at) BETWEEN '$filter_from' AND '$filter_to'") . "
              WHERE u.role = 'user' $search_where
              GROUP BY u.id
              ORDER BY total_requests DESC, u.fullname ASC";
        $res = mysqli_query($conn, $q);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $report_data[] = $row;
            }
        }
    }

} elseif ($report_type === 'responder') {
    // If a specific responder unit / driver is selected or searched
    if (!empty($selected_id) || !empty($search)) {
        $resp_where = !empty($selected_id) ? "e.id = '" . mysqli_real_escape_string($conn, $selected_id) . "'" : "(e.unit_name LIKE '%".mysqli_real_escape_string($conn, $search)."%' OR d.fullname LIKE '%".mysqli_real_escape_string($conn, $search)."%')";
        $resp_info_q = mysqli_query($conn, "SELECT e.id, e.unit_name, e.unit_type, e.status as unit_status, e.plate_number, d.fullname as driver_name, d.phone as driver_phone, d.email as driver_email 
                                            FROM emergency_units e 
                                            LEFT JOIN users d ON e.driver_id = d.id 
                                            WHERE $resp_where LIMIT 1");
        if ($resp_info_q && mysqli_num_rows($resp_info_q) > 0) {
            $single_info = mysqli_fetch_assoc($resp_info_q);
            $unit_id = (int)$single_info['id'];

            // Responder stats summary
            $resp_stats_q = mysqli_query($conn, "SELECT 
                COUNT(id) as total_handled,
                COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END), 0) as completed_handled,
                COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as pending_handled,
                COALESCE(SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END), 0) as cancelled_handled,
                COALESCE(SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END), 0) as rejected_handled
                FROM rescue_requests WHERE assigned_unit_id = '$unit_id'");
            if ($resp_stats_q && $st_r = mysqli_fetch_assoc($resp_stats_q)) {
                $single_info['stats'] = $st_r;
            }

            $where = "WHERE r.assigned_unit_id = '$unit_id' AND $date_where";
            if ($filter_status !== 'all') {
                $st_esc = mysqli_real_escape_string($conn, $filter_status);
                $where .= " AND r.status = '$st_esc'";
            }
            $q = "SELECT r.id, r.status, r.emergency_type, r.created_at,
                         u.fullname as patient_name, u.phone as patient_phone
                  FROM rescue_requests r
                  JOIN users u ON r.user_id = u.id
                  $where ORDER BY r.created_at DESC";
            $res = mysqli_query($conn, $q);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $report_data[] = $row;
                }
            }
        }
    }

    // Overview of all responders if no single responder selected
    if (empty($single_info)) {
        $search_where = "";
        if (!empty($search)) {
            $s_esc = mysqli_real_escape_string($conn, $search);
            $search_where = "WHERE (e.unit_name LIKE '%$s_esc%' OR d.fullname LIKE '%$s_esc%' OR e.unit_type LIKE '%$s_esc%')";
        }
        $q = "SELECT e.id as unit_id, e.unit_name, e.unit_type, e.status as unit_status, e.plate_number,
                     d.fullname as driver_name, d.phone as driver_phone,
                     COUNT(r.id) as total_missions,
                     COALESCE(SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END), 0) as completed_missions,
                     COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END), 0) as pending_missions,
                     COALESCE(SUM(CASE WHEN r.status='cancelled' THEN 1 ELSE 0 END), 0) as cancelled_missions,
                     COALESCE(SUM(CASE WHEN r.status='rejected' THEN 1 ELSE 0 END), 0) as rejected_missions
              FROM emergency_units e
              LEFT JOIN users d ON e.driver_id = d.id
              LEFT JOIN rescue_requests r ON e.id = r.assigned_unit_id " . ($all_dates ? "" : "AND DATE(r.created_at) BETWEEN '$filter_from' AND '$filter_to'") . "
              $search_where
              GROUP BY e.id
              ORDER BY total_missions DESC, e.unit_name ASC";
        $res = mysqli_query($conn, $q);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $report_data[] = $row;
            }
        }
    }

} elseif ($report_type === 'services') {
    // 4 System Primary Emergency Services Breakdown
    $services_list = ['Medical', 'Fire', 'Police', 'Accident'];

    // Overall summary for each of the 4 services
    foreach ($services_list as $svc) {
        $svc_esc = mysqli_real_escape_string($conn, $svc);
        $svc_q = mysqli_query($conn, "SELECT 
            COUNT(r.id) as total_calls,
            COALESCE(SUM(CASE WHEN r.status='completed' THEN 1 ELSE 0 END), 0) as completed_calls,
            COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END), 0) as pending_calls,
            COALESCE(SUM(CASE WHEN r.status='cancelled' THEN 1 ELSE 0 END), 0) as cancelled_calls,
            COALESCE(SUM(CASE WHEN r.status='rejected' THEN 1 ELSE 0 END), 0) as rejected_calls
            FROM rescue_requests r 
            WHERE (r.emergency_type LIKE '%$svc_esc%' OR r.emergency_type = '$svc_esc') " . ($all_dates ? "" : "AND DATE(r.created_at) BETWEEN '$filter_from' AND '$filter_to'"));
        if ($svc_q && $r_s = mysqli_fetch_assoc($svc_q)) {
            $service_summary[$svc] = $r_s;
        } else {
            $service_summary[$svc] = ['total_calls' => 0, 'completed_calls' => 0, 'pending_calls' => 0, 'cancelled_calls' => 0, 'rejected_calls' => 0];
        }
    }

    // Detailed requests query for selected service filter or all services
    $svc_where = "WHERE $date_where";
    if ($service_filter !== 'all') {
        $sf_esc = mysqli_real_escape_string($conn, $service_filter);
        $svc_where .= " AND (r.emergency_type LIKE '%$sf_esc%' OR r.emergency_type = '$sf_esc')";
    }
    if ($filter_status !== 'all') {
        $st_esc = mysqli_real_escape_string($conn, $filter_status);
        $svc_where .= " AND r.status = '$st_esc'";
    }
    if (!empty($search)) {
        $s_esc = mysqli_real_escape_string($conn, $search);
        $svc_where .= " AND (u.fullname LIKE '%$s_esc%' OR u.phone LIKE '%$s_esc%' OR r.emergency_type LIKE '%$s_esc%')";
    }

    $q = "SELECT r.id, r.emergency_type, r.status, r.created_at,
                 u.fullname as patient_name, u.phone as patient_phone,
                 e.unit_name, d2.fullname as driver_name
          FROM rescue_requests r
          JOIN users u ON r.user_id = u.id
          LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
          LEFT JOIN users d2 ON e.driver_id = d2.id
          $svc_where ORDER BY r.created_at DESC";
    $res = mysqli_query($conn, $q);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $report_data[] = $row;
        }
    }
}
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

/* Nav Tabs */
.report-tabs{
    display:flex; gap:10px; margin-bottom:20px; border-bottom:2px solid #e2e8f0; padding-bottom:12px; flex-wrap:wrap;
}
.report-tab-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding:11px 22px; border-radius:12px; font-weight:700; font-size:0.88rem;
    text-decoration:none; color:var(--text-muted); background:var(--card-bg);
    border:1px solid rgba(0,0,0,0.06); transition:all 0.2s ease;
    box-shadow:0 2px 8px rgba(0,0,0,0.03);
}
.report-tab-btn:hover{ color:var(--accent); background:#f8fafe; transform:translateY(-1px); }
.report-tab-btn.active{
    background:var(--accent); color:white; border-color:var(--accent);
    box-shadow:0 4px 14px rgba(59,130,246,0.35);
}

/* Filter Bar */
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

.btn-all-dates{
    display:inline-flex; align-items:center; gap:6px;
    padding:9px 14px; border-radius:10px; font-size:0.8rem; font-weight:700;
    text-decoration:none; transition:0.2s; align-self:flex-end;
    background:rgba(59,130,246,0.1); color:var(--accent); border:1px solid rgba(59,130,246,0.2);
}
.btn-all-dates.active{
    background:#10b981; color:white; border-color:#10b981;
    box-shadow:0 3px 10px rgba(16,185,129,0.3);
}

.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.sum-card{background:var(--card-bg);border-radius:16px;padding:18px 20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);text-align:center;}
.sum-num{font-size:1.8rem;font-weight:900;line-height:1;}
.sum-label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-top:4px;}

/* Services Grid */
.services-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.svc-card{background:var(--card-bg);border-radius:16px;padding:20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);transition:0.2s;text-decoration:none;color:inherit;display:block;}
.svc-card:hover{transform:translateY(-3px);box-shadow:0 8px 30px rgba(0,0,0,0.08);}
.svc-card.active{border:2px solid var(--accent);background:#f8fafe;}

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
    box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow-x:auto;
}
.table{margin:0;width:100%;border-collapse:collapse;}
.table thead th{
    font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:1.4px;
    color:var(--text-muted);background:#f8fafc;padding:11px 14px;
    border:none;border-bottom:2px solid rgba(0,0,0,0.06);white-space:nowrap;
}
.table tbody td{padding:13px 14px;font-size:0.84rem;border:none;border-bottom:1px solid rgba(0,0,0,0.04);vertical-align:middle;}
.table tbody tr:last-child td{border-bottom:none;}
.table tbody tr{transition:background 0.15s;}
.table tbody tr:hover{background:rgba(59,130,246,0.03);}
/* stat mini-badge in overview rows */
.stat-mini{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:26px;padding:0 8px;border-radius:8px;font-size:0.78rem;font-weight:800;}
.stat-mini.s-total{background:#f1f5f9;color:#334155;}
.stat-mini.s-ok{background:rgba(34,197,94,0.1);color:#15803d;}
.stat-mini.s-pend{background:rgba(59,130,246,0.1);color:#1d4ed8;}
.stat-mini.s-canc{background:rgba(245,158,11,0.1);color:#b45309;}
.stat-mini.s-rej{background:rgba(239,68,68,0.1);color:#dc2626;}
/* rate badge */
.rate-badge{display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border-radius:50px;font-size:0.72rem;font-weight:800;}
.rate-badge.rate-hi{background:rgba(34,197,94,0.12);color:#15803d;}
.rate-badge.rate-md{background:rgba(245,158,11,0.12);color:#b45309;}
.rate-badge.rate-lo{background:rgba(239,68,68,0.12);color:#dc2626;}
/* status chip */
.status-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:50px;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap;}
.chip-pending{background:rgba(59,130,246,0.1);color:#1d4ed8;}
.chip-accepted{background:rgba(59,130,246,0.1);color:#3b82f6;}
.chip-completed{background:rgba(34,197,94,0.1);color:#15803d;}
.chip-cancelled{background:rgba(245,158,11,0.1);color:#b45309;}
.chip-rejected{background:rgba(239,68,68,0.1);color:#dc2626;}
/* action buttons */
.view-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:9px;font-size:0.72rem;font-weight:700;text-decoration:none;border:1.5px solid transparent;transition:all 0.18s;white-space:nowrap;}
.view-btn:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,0,0,0.12);}
.view-btn.btn-eye{background:#f0f9ff;color:#0369a1;border-color:rgba(3,105,161,0.18);}
.view-btn.btn-eye:hover{background:#0ea5e9;color:white;border-color:#0ea5e9;}
.view-btn.btn-user{background:#f0fdf4;color:#15803d;border-color:rgba(21,128,61,0.18);}
.view-btn.btn-user:hover{background:#22c55e;color:white;border-color:#22c55e;}
.view-btn.btn-unit{background:#fffbeb;color:#b45309;border-color:rgba(180,83,9,0.18);}
.view-btn.btn-unit:hover{background:#f59e0b;color:white;border-color:#f59e0b;}
.view-btn.btn-detail{background:#f5f3ff;color:#6d28d9;border-color:rgba(109,40,217,0.18);}
.view-btn.btn-detail:hover{background:#7c3aed;color:white;border-color:#7c3aed;}
.actions-cell{display:flex;gap:6px;justify-content:flex-end;align-items:center;flex-wrap:nowrap;}
/* unit status chip */
.unit-chip-available{background:rgba(34,197,94,0.1);color:#15803d;}
.unit-chip-busy{background:rgba(245,158,11,0.1);color:#b45309;}
.unit-chip-offline{background:rgba(100,116,139,0.1);color:#475569;}

/* Status Quick Filter Tabs */
.status-filter-tabs{
    display:flex; gap:8px; padding:14px 24px; background:#f8fafc;
    border-bottom:1px solid rgba(0,0,0,0.05); flex-wrap:wrap;
}
.status-filter-tab{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 14px; border-radius:50px; font-size:0.75rem; font-weight:700;
    text-decoration:none; transition:0.2s; cursor:pointer;
    background:white; color:var(--text-muted); border:1.5px solid rgba(0,0,0,0.07);
}
.status-filter-tab:hover{ transform:translateY(-1px); color:var(--text); }
.status-filter-tab.tab-all.active{background:#0f172a;color:white;border-color:#0f172a;}
.status-filter-tab.tab-completed.active{background:#22c55e;color:white;border-color:#22c55e;}
.status-filter-tab.tab-cancelled.active{background:#f59e0b;color:white;border-color:#f59e0b;}
.status-filter-tab.tab-rejected.active{background:#ef4444;color:white;border-color:#ef4444;}
.status-filter-tab.tab-pending.active{background:#3b82f6;color:white;border-color:#3b82f6;}

/* Single detail header card */
.detail-header-card{
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white; border-radius: 16px; padding: 24px; margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; align-items: flex-start; justify-content: space-between; gap: 20px;
}

.printable-badge-card{
    background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.12);
    border-radius:12px; padding:12px 18px; text-align:center; min-width:110px;
}
.printable-badge-card .num{ font-size:1.6rem; font-weight:900; line-height:1; }
.printable-badge-card .lbl{ font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; opacity:0.8; margin-top:3px; }

/* Print styles */
@media print {
    .sidebar,.export-bar .export-btns,.filter-bar button,.main-wrapper > .topbar,
    .filter-bar form button,.report-tabs,.btn-all-dates{display:none!important;}
    .main-wrapper{margin-left:0!important;padding:20px!important;}
    body{background:white!important; color:black!important;}
    .sum-card,.filter-bar,.export-bar,.report-table-wrap,.svc-card{box-shadow:none!important;border:1px solid #cbd5e1!important;}
    .detail-header-card{background:#0f172a!important; color:white!important; -webkit-print-color-adjust: exact;}
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = t('Reports'); $page_subtitle = t('& Exports'); include 'includes/topbar.php'; ?>

<!-- Navigation Tabs -->
<div class="report-tabs">
    <a href="reports.php?type=all_incidents<?= $all_dates ? '&all_dates=1' : '' ?>" class="report-tab-btn <?= $report_type === 'all_incidents' ? 'active' : '' ?>">
        <i class="fa fa-list-check"></i> <?= t('All Incidents Reports') ?>
    </a>
    <a href="reports.php?type=individual<?= $all_dates ? '&all_dates=1' : '' ?>" class="report-tab-btn <?= $report_type === 'individual' ? 'active' : '' ?>">
        <i class="fa fa-user"></i> <?= t('Individual Reports') ?>
    </a>
    <a href="reports.php?type=responder<?= $all_dates ? '&all_dates=1' : '' ?>" class="report-tab-btn <?= $report_type === 'responder' ? 'active' : '' ?>">
        <i class="fa fa-truck-medical"></i> <?= t('Responder Reports') ?>
    </a>
    <a href="reports.php?type=services<?= $all_dates ? '&all_dates=1' : '' ?>" class="report-tab-btn <?= $report_type === 'services' ? 'active' : '' ?>">
        <i class="fa fa-hand-holding-medical"></i> <?= t('Service Reports') ?>
    </a>
</div>

<!-- Filters Bar -->
<form method="GET" action="reports.php">
    <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
    <?php if($all_dates): ?>
    <input type="hidden" name="all_dates" value="1">
    <?php endif; ?>

    <div class="filter-bar">
        <div>
            <label><?= t('From Date') ?></label>
            <input type="date" name="from" class="filter-control" value="<?= htmlspecialchars($filter_from) ?>" <?= $all_dates ? 'disabled style="opacity:0.5;"' : '' ?>>
        </div>
        <div>
            <label><?= t('To Date') ?></label>
            <input type="date" name="to" class="filter-control" value="<?= htmlspecialchars($filter_to) ?>" <?= $all_dates ? 'disabled style="opacity:0.5;"' : '' ?>>
        </div>

        <a href="reports.php?type=<?= htmlspecialchars($report_type) ?>&all_dates=<?= $all_dates ? '0' : '1' ?>&status=<?= htmlspecialchars($filter_status) ?>&service=<?= htmlspecialchars($service_filter) ?>&search=<?= urlencode($search) ?>" 
           class="btn-all-dates <?= $all_dates ? 'active' : '' ?>" title="Toggle all historical dates">
            <i class="fa <?= $all_dates ? 'fa-check-circle' : 'fa-calendar-days' ?>"></i>
            <?= t('All Dates') ?>
        </a>

        <?php if($report_type === 'services'): ?>
        <div>
            <label><?= t('Emergency Service') ?></label>
            <select name="service" class="filter-control" onchange="this.form.submit()">
                <option value="all" <?= $service_filter === 'all' ? 'selected' : '' ?>>-- All Services --</option>
                <option value="Medical" <?= $service_filter === 'Medical' ? 'selected' : '' ?>>🚑 Medical (Caafimaad)</option>
                <option value="Fire" <?= $service_filter === 'Fire' ? 'selected' : '' ?>>🚒 Firefighter (Dabdamis)</option>
                <option value="Police" <?= $service_filter === 'Police' ? 'selected' : '' ?>>🚓 Police (Boliis)</option>
                <option value="Accident" <?= $service_filter === 'Accident' ? 'selected' : '' ?>>🚙 Road Accident (Shilal)</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if($report_type === 'all_incidents' || $report_type === 'services' || !empty($single_info)): ?>
        <div>
            <label><?= t('Status') ?></label>
            <select name="status" class="filter-control" onchange="this.form.submit()">
                <?php foreach(['all','pending','accepted','completed','cancelled','rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= t(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div style="flex:1; min-width:210px;">
            <label>
                <?php 
                if ($report_type === 'individual') echo t('Search Victim (Name / Phone / Email)');
                elseif ($report_type === 'responder') echo t('Search Responder / Unit');
                elseif ($report_type === 'services') echo t('Search Emergency Service');
                else echo t('Search Incident / Patient / Unit');
                ?>
            </label>
            <div style="position:relative;">
                <input type="text" name="search" class="filter-control" style="width:100%; padding-left:34px;" 
                       placeholder="<?= $report_type==='individual' ? 'Name, Phone...' : ($report_type==='responder' ? 'Unit Name, Driver...' : 'Search...') ?>" 
                       value="<?= htmlspecialchars($search) ?>">
                <i class="fa fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem;"></i>
            </div>
        </div>

        <button type="submit" class="filter-apply"><i class="fa fa-filter"></i> <?= t('Apply Filter') ?></button>

        <?php if ($filter_status !== 'all' || $service_filter !== 'all' || !empty($search) || !empty($selected_id) || $all_dates): ?>
        <a href="reports.php?type=<?= htmlspecialchars($report_type) ?>" class="view-btn text-muted" style="align-self:flex-end; padding:9px 14px;">
            <i class="fa fa-rotate-left"></i> Reset
        </a>
        <?php endif; ?>
    </div>
</form>

<!-- Global Summary Overview Cards -->
<?php if ($report_type !== 'services'): ?>
<div class="summary-grid">
    <div class="sum-card"><div class="sum-num" style="color:#3b82f6"><?= $total_incidents_val ?></div><div class="sum-label"><?= t('Total Incidents') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#22c55e"><?= $completed_val ?></div><div class="sum-label"><?= t('Completed') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#ef4444"><?= $pending_val ?></div><div class="sum-label"><?= t('Pending') ?></div></div>
    <div class="sum-card"><div class="sum-num" style="color:#8b5cf6"><?= $responders_val ?></div><div class="sum-label"><?= t('Responders Units') ?></div></div>
</div>
<?php endif; ?>

<!-- Single Emergency Service Detailed Summary Header Card (Only when a service is selected) -->
<?php if ($report_type === 'services' && $service_filter !== 'all' && isset($service_summary[$service_filter])): 
    $svc_icons = [
        'Medical'  => ['icon' => 'fa-truck-medical', 'color' => '#ef4444', 'title' => t('Medical Services')],
        'Fire'     => ['icon' => 'fa-fire-extinguisher', 'color' => '#f97316', 'title' => t('Firefighter Services')],
        'Police'   => ['icon' => 'fa-shield-halved', 'color' => '#3b82f6', 'title' => t('Police Services')],
        'Accident' => ['icon' => 'fa-car-burst', 'color' => '#eab308', 'title' => t('Accident Services')],
    ];
    $s_info = $service_summary[$service_filter];
    $s_meta = $svc_icons[$service_filter] ?? ['icon' => 'fa-hand-holding-medical', 'color' => '#3b82f6', 'title' => $service_filter . ' Services'];
    $rate   = ($s_info['total_calls'] > 0) ? round(($s_info['completed_calls'] / $s_info['total_calls']) * 100) : 0;
?>
<div class="detail-header-card" style="border-left: 6px solid <?= $s_meta['color'] ?>;">
    <div>
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; opacity:0.7; font-weight:800; margin-bottom:4px;">
            <i class="fa <?= $s_meta['icon'] ?>" style="color:<?= $s_meta['color'] ?>"></i> <?= t('Selected Emergency Service Summary') ?>
        </div>
        <h2 style="margin:0; font-weight:900;"><?= $s_meta['title'] ?></h2>
        <div style="font-size:0.88rem; opacity:0.85; margin-top:6px;">
            <?= t('Detailed performance summary for') ?> <strong><?= htmlspecialchars($service_filter) ?></strong>
        </div>
    </div>

    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <div class="printable-badge-card" style="border-color:#3b82f6;">
            <div class="num" style="color:#60a5fa;"><?= $s_info['total_calls'] ?></div>
            <div class="lbl"><?= t('Total Requests') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#22c55e;">
            <div class="num" style="color:#4ade80;"><?= $s_info['completed_calls'] ?></div>
            <div class="lbl"><?= t('Aqbalay / Completed') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#ef4444;">
            <div class="num" style="color:#f87171;"><?= $s_info['rejected_calls'] ?></div>
            <div class="lbl"><?= t('Diiday / Rejected') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#f59e0b;">
            <div class="num" style="color:#fbbf24;"><?= $s_info['cancelled_calls'] ?></div>
            <div class="lbl"><?= t('Cancelled') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#8b5cf6;">
            <div class="num" style="color:#c084fc;"><?= $rate ?>%</div>
            <div class="lbl"><?= t('Success Rate') ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Single Victim Detailed Profile Header Card -->
<?php if ($single_info && $report_type === 'individual'): 
    $st = $single_info['stats'] ?? ['total_sent'=>0,'completed_sent'=>0,'pending_sent'=>0,'cancelled_sent'=>0];
?>
<div class="detail-header-card">
    <div>
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; opacity:0.7; font-weight:800; margin-bottom:4px;">
            <i class="fa fa-user-circle text-primary"></i> <?= t('Individual Victim Detailed Report') ?>
        </div>
        <h2 style="margin:0; font-weight:900;"><?= htmlspecialchars($single_info['fullname']) ?></h2>
        <div style="font-size:0.88rem; opacity:0.9; margin-top:6px; display:flex; gap:16px; flex-wrap:wrap;">
            <span><i class="fa fa-phone"></i> <strong>Phone:</strong> <?= htmlspecialchars($single_info['phone']) ?></span>
            <span><i class="fa fa-envelope"></i> <strong>Email:</strong> <?= htmlspecialchars($single_info['email'] ?: 'N/A') ?></span>
            <span><i class="fa fa-calendar-check"></i> <strong>Registered:</strong> <?= date('M j, Y', strtotime($single_info['created_at'])) ?></span>
        </div>

        <!-- Extra Profile Info: Medical & Emergency Contacts -->
        <div style="margin-top:14px; padding-top:12px; border-top:1px solid rgba(255,255,255,0.1); display:flex; gap:24px; flex-wrap:wrap; font-size:0.82rem;">
            <div>
                <strong style="color:#60a5fa;"><i class="fa fa-notes-medical"></i> <?= t('Medical Info') ?>:</strong>
                <span style="opacity:0.85; margin-left:4px;"><?= htmlspecialchars($single_info['medical_info'] ?: 'No recorded pre-existing conditions') ?></span>
            </div>
            <div>
                <strong style="color:#34d399;"><i class="fa fa-address-book"></i> <?= t('Emergency Contacts') ?>:</strong>
                <span style="opacity:0.85; margin-left:4px;"><?= htmlspecialchars($single_info['emergency_contacts'] ?: 'None listed') ?></span>
            </div>
        </div>
    </div>

    <div style="display:flex; gap:10px; align-items:center;">
        <div class="printable-badge-card" style="border-color:#3b82f6;">
            <div class="num" style="color:#60a5fa;"><?= $st['total_sent'] ?></div>
            <div class="lbl"><?= t('Total Requests') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#22c55e;">
            <div class="num" style="color:#4ade80;"><?= $st['completed_sent'] ?></div>
            <div class="lbl"><?= t('Completed') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#ef4444;">
            <div class="num" style="color:#f87171;"><?= $st['pending_sent'] ?></div>
            <div class="lbl"><?= t('Pending') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#94a3b8;">
            <div class="num" style="color:#cbd5e1;"><?= $st['cancelled_sent'] ?></div>
            <div class="lbl"><?= t('Cancelled / Rejected') ?></div>
        </div>
    </div>
</div>

<!-- Single Responder Detailed Profile Header Card -->
<?php elseif ($single_info && $report_type === 'responder'): 
    $st = $single_info['stats'] ?? ['total_handled'=>0,'completed_handled'=>0,'pending_handled'=>0,'cancelled_handled'=>0,'rejected_handled'=>0];
    $rate = ($st['total_handled'] > 0) ? round(($st['completed_handled'] / $st['total_handled']) * 100) : 0;
?>
<div class="detail-header-card">
    <div>
        <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1.5px; opacity:0.7; font-weight:800; margin-bottom:4px;">
            <i class="fa fa-truck-medical text-warning"></i> <?= t('Responder Unit Detailed Report') ?>
        </div>
        <h2 style="margin:0; font-weight:900;"><?= htmlspecialchars($single_info['unit_name']) ?> <span style="font-size:1rem; font-weight:700; opacity:0.8;">(<?= htmlspecialchars($single_info['unit_type'] ?: 'Emergency Unit') ?>)</span></h2>
        <div style="font-size:0.88rem; opacity:0.9; margin-top:6px; display:flex; gap:16px; flex-wrap:wrap;">
            <span><i class="fa fa-user-shield"></i> <strong>Driver:</strong> <?= htmlspecialchars($single_info['driver_name'] ?: 'Unassigned') ?></span>
            <span><i class="fa fa-phone"></i> <strong>Phone:</strong> <?= htmlspecialchars($single_info['driver_phone'] ?: 'N/A') ?></span>
            <span><i class="fa fa-id-card"></i> <strong>Plate ID:</strong> <?= htmlspecialchars($single_info['plate_number'] ?: 'N/A') ?></span>
            <span><i class="fa fa-signal"></i> <strong>Status:</strong> <?= ucfirst($single_info['unit_status'] ?: 'offline') ?></span>
        </div>
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
        <div class="printable-badge-card" style="border-color:#3b82f6;">
            <div class="num" style="color:#60a5fa;"><?= $st['total_handled'] ?></div>
            <div class="lbl"><?= t('Total Handled') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#22c55e;">
            <div class="num" style="color:#4ade80;"><?= $st['completed_handled'] ?></div>
            <div class="lbl"><?= t('Completed') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#eab308;">
            <div class="num" style="color:#facc15;"><?= $st['cancelled_handled'] ?></div>
            <div class="lbl"><?= t('Cancelled') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#ef4444;">
            <div class="num" style="color:#f87171;"><?= $st['rejected_handled'] ?></div>
            <div class="lbl"><?= t('Rejected') ?></div>
        </div>
        <div class="printable-badge-card" style="border-color:#8b5cf6;">
            <div class="num" style="color:#c084fc;"><?= $rate ?>%</div>
            <div class="lbl"><?= t('Success Rate') ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Status Quick Filter Tabs (shown when a specific responder or individual is selected) -->
<?php if ($single_info && ($report_type === 'responder' || $report_type === 'individual')): 
    $base_url = 'reports.php?type=' . $report_type . '&id=' . $selected_id . ($all_dates ? '&all_dates=1' : '') . '&from=' . $filter_from . '&to=' . $filter_to;
    $statuses_detail = [
        'all'       => ['label' => '&#9654; All', 'cls' => 'tab-all'],
        'completed' => ['label' => '&#10003; Completed', 'cls' => 'tab-completed'],
        'cancelled' => ['label' => '&#8855; Cancelled', 'cls' => 'tab-cancelled'],
        'rejected'  => ['label' => '&#215; Rejected', 'cls' => 'tab-rejected'],
        'pending'   => ['label' => '&#9679; Pending', 'cls' => 'tab-pending'],
    ];
?>
<div class="status-filter-tabs">
    <?php foreach ($statuses_detail as $sv => $smeta): ?>
    <a href="<?= $base_url ?>&status=<?= $sv ?>" class="status-filter-tab <?= $smeta['cls'] ?> <?= $filter_status === $sv ? 'active' : '' ?>">
        <?= $smeta['label'] ?>
    </a>
    <?php endforeach; ?>
    <span style="margin-left:auto; font-size:0.78rem; font-weight:700; color:var(--text-muted); display:flex; align-items:center; gap:4px;">
        <i class="fa fa-list"></i> <?= count($report_data) ?> record(s)
    </span>
</div>
<?php endif; ?>

<!-- Export Bar -->
<div class="export-bar">
    <h5>
        <?php if ($report_type === 'all_incidents'): ?>
            <i class="fa fa-file-lines text-primary"></i> <?= t('All Incidents Reports') ?>
        <?php elseif ($report_type === 'individual'): ?>
            <i class="fa fa-user text-primary"></i> <?= $single_info ? t('Individual Victim Detailed Log') : t('Individual Victims Overview') ?>
        <?php elseif ($report_type === 'responder'): ?>
            <i class="fa fa-truck-medical text-primary"></i> <?= $single_info ? t('Responder Unit Mission Log') : t('Responder Units Performance Report') ?>
        <?php elseif ($report_type === 'services'): ?>
            <i class="fa fa-hand-holding-medical text-primary"></i> <?= $service_filter !== 'all' ? htmlspecialchars($service_filter) . ' ' . t('Service Log') : t('All Emergency Service Logs') ?>
        <?php endif; ?>
        
        <span style="font-size:0.75rem;font-weight:600;color:var(--text-muted); margin-left:8px;">
            <?= $all_dates ? t('All Dates (Whole History)') : date('M j, Y', strtotime($filter_from)) . ' – ' . date('M j, Y', strtotime($filter_to)) ?>
        </span>
    </h5>
    <div class="export-btns">
        <button class="btn-export btn-csv" onclick="exportCSV()"><i class="fa fa-file-csv"></i> <?= t('Export CSV') ?></button>
        <button class="btn-export btn-print" onclick="window.print()"><i class="fa fa-print"></i> <?= t('Print / PDF') ?></button>
    </div>
</div>

<!-- Table Wrap -->
<div class="report-table-wrap">
    <table class="table" id="report-table">

        <?php if ($report_type === 'all_incidents' || $report_type === 'services' || ($report_type==='individual' && $single_info) || ($report_type==='responder' && $single_info)): ?>
        <!-- Incident Log Table View -->
        <thead>
            <tr>
                <th style="padding-left:24px">#ID</th>
                <?php if ($report_type !== 'individual' || empty($single_info)): ?>
                <th><?= t('Victim') ?></th>
                <?php endif; ?>
                <th><?= t('Emergency') ?></th>
                <?php if ($report_type !== 'responder' || empty($single_info)): ?>
                <th><?= t('Unit / Responder') ?></th>
                <?php endif; ?>
                <th><?= t('Time') ?></th>
                <th><?= t('Status') ?></th>
                <th style="text-align:right;padding-right:24px"><?= t('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($report_data)): ?>
            <tr><td colspan="7" style="text-align:center;padding:50px;color:var(--text-muted)"><i class="fa fa-folder-open" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:10px"></i><?= t('No records found for this period.') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($report_data as $r): ?>
        <tr>
            <td style="padding-left:24px;font-weight:800;color:var(--text-muted)">#<?= $r['id'] ?></td>
            <?php if ($report_type !== 'individual' || empty($single_info)): ?>
            <td>
                <div style="font-weight:800"><?= htmlspecialchars($r['patient_name'] ?? '—') ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['patient_phone'] ?? '') ?></div>
            </td>
            <?php endif; ?>
            <td><span style="font-size:0.8rem;font-weight:700"><?= htmlspecialchars($r['emergency_type'] ?? '—') ?></span></td>
            <?php if ($report_type !== 'responder' || empty($single_info)): ?>
            <td>
                <?php if (!empty($r['unit_name'])): ?>
                <div style="font-weight:700;font-size:0.82rem"><?= htmlspecialchars($r['unit_name']) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($r['driver_name'] ?? '') ?></div>
                <?php else: ?>
                <span style="color:#94a3b8;font-size:0.8rem"><?= t('Unassigned') ?></span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
            <td style="font-size:0.82rem;font-weight:600;color:var(--text-muted)"><?= date('M j, Y H:i', strtotime($r['created_at'])) ?></td>
            <td><span class="status-chip chip-<?= $r['status'] ?>"><?= t(ucfirst($r['status'])) ?></span></td>
            <td style="text-align:right;padding-right:16px;">
                <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center;">
                    <!-- View Incident -->
                    <a href="incident.php?id=<?= $r['id'] ?>" class="view-btn btn-eye" title="View Full Incident Details">
                        <i class="fa fa-eye"></i> View
                    </a>
                    <!-- Victim Profile (not shown in individual detail) -->
                    <?php if (!empty($r['user_id']) && $report_type !== 'individual'): ?>
                    <a href="view_user.php?id=<?= $r['user_id'] ?>" class="view-btn btn-user" title="View Victim Profile">
                        <i class="fa fa-user"></i> Victim
                    </a>
                    <?php endif; ?>
                    <!-- Unit Report (only in all_incidents / services views) -->
                    <?php if (!empty($r['unit_id']) && in_array($report_type, ['all_incidents','services','individual'])): ?>
                    <a href="reports.php?type=responder&id=<?= $r['unit_id'] ?>" class="view-btn btn-unit" title="View Responder Unit Report">
                        <i class="fa fa-truck-medical"></i> Unit
                    </a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>

        <?php elseif ($report_type === 'individual' && empty($single_info)): ?>
        <!-- Individual Victims Overview Table -->
        <thead>
            <tr>
                <th style="padding-left:18px;width:60px">#ID</th>
                <th style="min-width:160px"><?= t('Name') ?></th>
                <th><?= t('Phone') ?></th>
                <th style="text-align:center"><?= t('Total') ?></th>
                <th style="text-align:center"><?= t('Completed') ?></th>
                <th style="text-align:center"><?= t('Pending') ?></th>
                <th style="text-align:center"><?= t('Cancelled / Rejected') ?></th>
                <th><?= t('Last SOS Date') ?></th>
                <th style="text-align:right;padding-right:18px;width:90px"><?= t('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($report_data)): ?>
            <tr><td colspan="9" style="text-align:center;padding:50px;color:var(--text-muted)"><i class="fa fa-users-slash" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:10px"></i><?= t('No individual victim records found.') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($report_data as $u): ?>
        <tr>
            <td style="padding-left:18px;font-weight:800;color:var(--text-muted);font-size:0.78rem">#<?= $u['id'] ?></td>
            <td>
                <div style="font-weight:800;font-size:0.88rem"><?= htmlspecialchars($u['fullname']) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($u['phone']) ?></div>
            </td>
            <td style="font-size:0.82rem;color:var(--text-muted)"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
            <td style="text-align:center"><span class="stat-mini s-total"><?= (int)$u['total_requests'] ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-ok"><?= (int)$u['completed_count'] ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-pend"><?= (int)$u['pending_count'] ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-canc"><?= (int)$u['cancelled_count'] ?></span></td>
            <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap"><?= $u['last_request'] ? date('M j, Y', strtotime($u['last_request'])) : '<span style="color:#cbd5e1">Never</span>' ?></td>
            <td style="text-align:right;padding-right:14px">
                <a href="reports.php?type=individual&id=<?= $u['id'] ?>" class="view-btn btn-detail" title="View Full Profile & Report">
                    <i class="fa fa-chart-line"></i> Report
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>

        <?php elseif ($report_type === 'responder' && empty($single_info)): ?>
        <!-- Responders Units Performance Overview Table -->
        <thead>
            <tr>
                <th style="padding-left:18px;width:60px">#ID</th>
                <th style="min-width:140px"><?= t('Unit Name') ?></th>
                <th style="min-width:140px"><?= t('Driver / Responder') ?></th>
                <th style="text-align:center"><?= t('Total') ?></th>
                <th style="text-align:center"><?= t('Done') ?></th>
                <th style="text-align:center"><?= t('Pending') ?></th>
                <th style="text-align:center"><?= t('Cancelled') ?></th>
                <th style="text-align:center"><?= t('Rejected') ?></th>
                <th style="text-align:center;min-width:90px"><?= t('Rate') ?></th>
                <th style="text-align:center"><?= t('Status') ?></th>
                <th style="text-align:right;padding-right:18px;width:90px"><?= t('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($report_data)): ?>
            <tr><td colspan="11" style="text-align:center;padding:50px;color:var(--text-muted)"><i class="fa fa-truck-monster" style="font-size:2rem;opacity:0.2;display:block;margin-bottom:10px"></i><?= t('No responder records found.') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($report_data as $resp): 
            $missions = (int)($resp['total_missions'] ?? 0);
            $comp     = (int)($resp['completed_missions'] ?? 0);
            $pend     = (int)($resp['pending_missions'] ?? 0);
            $canc     = (int)($resp['cancelled_missions'] ?? 0);
            $rej      = (int)($resp['rejected_missions'] ?? 0);
            $rate     = ($missions > 0) ? round(($comp / $missions) * 100) : 0;
            $rate_cls = $rate >= 70 ? 'rate-hi' : ($rate >= 40 ? 'rate-md' : 'rate-lo');
            $ustat    = strtolower($resp['unit_status'] ?? 'offline');
            $ustat_cls = $ustat === 'available' ? 'unit-chip-available' : ($ustat === 'busy' ? 'unit-chip-busy' : 'unit-chip-offline');
        ?>
        <tr>
            <td style="padding-left:18px;font-weight:800;color:var(--text-muted);font-size:0.78rem">#<?= $resp['unit_id'] ?></td>
            <td>
                <div style="font-weight:800;font-size:0.88rem"><?= htmlspecialchars($resp['unit_name']) ?></div>
                <div style="font-size:0.71rem;color:var(--text-muted);margin-top:2px;text-transform:capitalize"><?= htmlspecialchars($resp['unit_type'] ?: 'Unit') ?></div>
            </td>
            <td>
                <div style="font-weight:700;font-size:0.85rem"><?= htmlspecialchars($resp['driver_name'] ?: '—') ?></div>
                <div style="font-size:0.71rem;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($resp['driver_phone'] ?: '') ?></div>
            </td>
            <td style="text-align:center"><span class="stat-mini s-total"><?= $missions ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-ok"><?= $comp ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-pend"><?= $pend ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-canc"><?= $canc ?></span></td>
            <td style="text-align:center"><span class="stat-mini s-rej"><?= $rej ?></span></td>
            <td style="text-align:center">
                <span class="rate-badge <?= $rate_cls ?>"><?= $rate ?>%</span>
            </td>
            <td style="text-align:center">
                <span class="status-chip <?= $ustat_cls ?>">
                    <?= ucfirst($ustat) ?>
                </span>
            </td>
            <td style="text-align:right;padding-right:14px">
                <a href="reports.php?type=responder&id=<?= $resp['unit_id'] ?>" class="view-btn btn-detail" title="View Mission Log & Full Report">
                    <i class="fa fa-chart-bar"></i> Details
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <?php endif; ?>

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
        if(cols.length > 0) cols.pop();
        lines.push(cols.join(','));
    });
    const blob = new Blob([lines.join('\n')], {type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'smartrescue_report_<?= $report_type ?>_<?= date('Y-m-d') ?>.csv';
    a.click();
}
</script>
</body>
</html>
