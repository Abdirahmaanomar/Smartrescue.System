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
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = clean_input($_POST['fullname'], $conn);
    $phone    = clean_input($_POST['phone'], $conn);
    $email    = clean_input($_POST['email'], $conn);
    
    $image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['profile_image']['tmp_name'];
        $file_name = $_FILES['profile_image']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed)) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_name = "driver_" . $driver_id . "_" . time() . "." . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $image_path = "uploads/" . $new_name;
            }
        }
    }

    if ($image_path) {
        mysqli_query($conn, "UPDATE users SET fullname='$fullname', phone='$phone', email='$email', profile_image='$image_path' WHERE id='$driver_id'");
    } else {
        mysqli_query($conn, "UPDATE users SET fullname='$fullname', phone='$phone', email='$email' WHERE id='$driver_id'");
    }
    $_SESSION['fullname'] = $fullname;
    $msg = 'Profile updated successfully!';
}

$user_q      = mysqli_query($conn, "SELECT * FROM users WHERE id = '$driver_id' LIMIT 1");
$driver_data = mysqli_fetch_assoc($user_q);
$fullname    = $driver_data['fullname'] ?? 'Driver';
$email       = $driver_data['email']    ?? '';
$phone       = $driver_data['phone']    ?? '';
$dark_mode   = (int)($driver_data['dark_mode'] ?? 1);
$avatar_raw  = $driver_data['profile_image'] ?? '';
$avatar = '';
if (!empty($avatar_raw)) {
    if (strpos($avatar_raw, 'http') === 0 || strpos($avatar_raw, '../') === 0) {
        $avatar = htmlspecialchars($avatar_raw);
    } else {
        $avatar = '../' . htmlspecialchars(ltrim($avatar_raw, '/'));
    }
}

$unit_q     = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id = '$driver_id' LIMIT 1");
$unit       = mysqli_fetch_assoc($unit_q);
$unit_id    = (int)($unit['id'] ?? 0);
$unit_name  = $unit['unit_name']   ?? 'Rescue Unit';
$unit_type  = strtolower($unit['unit_type'] ?? 'medical');
$plate_num  = $unit['plate_number'] ?? 'SOM-000-DRV';
$unit_status= strtolower($unit['status'] ?? 'available');
$is_avail   = ($unit_status === 'available');

$type_map = [
    'medical'  => ['icon' => 'fa-truck-medical',     'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.12)', 'grad' => 'linear-gradient(135deg,#1e40af,#3b82f6)'],
    'fire'     => ['icon' => 'fa-fire-extinguisher', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.12)',  'grad' => 'linear-gradient(135deg,#991b1b,#ef4444)'],
    'police'   => ['icon' => 'fa-shield-halved',     'color' => '#6366f1', 'bg' => 'rgba(99,102,241,0.12)', 'grad' => 'linear-gradient(135deg,#3730a3,#6366f1)'],
    'accident' => ['icon' => 'fa-car-burst',         'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.12)', 'grad' => 'linear-gradient(135deg,#92400e,#f59e0b)'],
];
$tm = $type_map[$unit_type] ?? $type_map['medical'];

// Stats
$saves_q   = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id' AND status='completed'");
$total_saves = (int)(mysqli_fetch_assoc($saves_q)['c'] ?? 0);
$missions_q  = mysqli_query($conn, "SELECT COUNT(*) c FROM rescue_requests WHERE assigned_unit_id='$unit_id'");
$total_missions = (int)(mysqli_fetch_assoc($missions_q)['c'] ?? 0);
$rank = 'Rookie Responder';
if ($total_saves >= 50) $rank = 'Elite Responder';
elseif ($total_saves >= 20) $rank = 'Senior Responder';
elseif ($total_saves >= 10) $rank = 'Expert Responder';
elseif ($total_saves >= 5)  $rank = 'Skilled Responder';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Driver Profile | SmartRescue</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#f0f4ff;--surface:#ffffff;--surface2:#f8faff;--border:#e4e9f2;--text:#0f172a;--muted:#64748b;--blue:#2563eb;--green:#10b981;--red:#ef4444;--amber:#f59e0b;--purple:#6366f1;--shadow-sm:0 2px 8px rgba(0,0,0,0.06);--shadow:0 4px 24px rgba(0,0,0,0.08);--shadow-lg:0 8px 40px rgba(0,0,0,0.12);--radius:16px;--sidebar-w:260px}
[data-theme="dark"]{--bg:#080f1e;--surface:#0f1c30;--surface2:#121f35;--border:#1a2d47;--text:#f1f5f9;--muted:#7a8fac;--shadow-sm:0 2px 8px rgba(0,0,0,0.3);--shadow:0 4px 24px rgba(0,0,0,0.4);--shadow-lg:0 8px 40px rgba(0,0,0,0.5)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;overflow-x:hidden}

/* SIDEBAR */
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:400;box-shadow:var(--shadow);transition:transform .3s ease}
.sidebar-logo{padding:22px 20px 18px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.logo-icon{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#1d4ed8,#2563eb);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;box-shadow:0 4px 12px rgba(37,99,235,.4);flex-shrink:0}
.logo-text{font-weight:900;font-size:17px;color:var(--blue);letter-spacing:-0.5px;line-height:1.1}
.logo-sub{font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:1px}
.sidebar-nav{flex:1;padding:16px 12px;overflow-y:auto;display:flex;flex-direction:column;gap:2px}
.nav-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.2px;padding:10px 10px 6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:12px;text-decoration:none;color:var(--muted);font-weight:600;font-size:14px;transition:all .2s;border:none;background:none;width:100%;text-align:left;cursor:pointer}
.nav-item:hover{background:var(--bg);color:var(--text)}
.nav-item.active{background:rgba(37,99,235,.1);color:var(--blue);font-weight:700}
.nav-item i{width:20px;text-align:center;font-size:16px;flex-shrink:0}
.sidebar-unit{margin:0 12px 12px;padding:14px;background:var(--bg);border-radius:14px;border:1px solid var(--border)}
.sidebar-unit-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.sidebar-unit-name{font-weight:800;font-size:13px;color:var(--text)}
.sidebar-unit-meta{font-size:11px;color:var(--muted);margin-top:2px}
.sidebar-footer{padding:14px 12px;border-top:1px solid var(--border)}
.logout-btn{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;color:var(--red);font-weight:700;font-size:14px;text-decoration:none;transition:all .2s;background:none;border:none;width:100%;cursor:pointer}
.logout-btn:hover{background:rgba(239,68,68,.08)}

/* TOPBAR */
.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:64px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 28px;gap:16px;z-index:300;box-shadow:var(--shadow-sm)}
.topbar-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted);font-weight:600;flex:1}
.page-title{font-size:16px;font-weight:800;color:var(--text)}
.topbar-actions{display:flex;align-items:center;gap:12px}
/* ── iOS Toggle Switch */
.toggle-switch{display:inline-flex;align-items:center;gap:9px;cursor:pointer;user-select:none}
.toggle-track{position:relative;width:44px;height:26px;border-radius:30px;transition:background .25s,box-shadow .25s;flex-shrink:0}
.toggle-track.on{background:var(--green);box-shadow:0 0 10px rgba(16,185,129,.4)}
.toggle-track.off{background:#cbd5e1}
[data-theme="dark"] .toggle-track.off{background:#334155}
.toggle-knob{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);transition:transform .25s cubic-bezier(.34,1.56,.64,1)}
.toggle-track.on .toggle-knob{transform:translateX(18px)}
.toggle-track.off .toggle-knob{transform:translateX(0)}
.toggle-label{font-weight:700;font-size:12px;letter-spacing:.4px;transition:color .2s}
.toggle-switch:has(.toggle-track.on) .toggle-label{color:var(--green)}
.toggle-switch:has(.toggle-track.off) .toggle-label{color:var(--muted)}
.icon-btn{width:40px;height:40px;border-radius:12px;border:1px solid var(--border);background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:all .2s;font-size:15px;text-decoration:none}
.icon-btn:hover{border-color:var(--blue);color:var(--blue)}
.avatar-wrap{display:flex;align-items:center;gap:10px;padding:6px 12px;border-radius:30px;border:1px solid var(--border);background:var(--bg);cursor:pointer;transition:all .2s;text-decoration:none}
.avatar-wrap:hover{border-color:var(--blue)}
.avatar-circle{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--blue),#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px;flex-shrink:0}
.avatar-circle img{width:32px;height:32px;border-radius:50%;object-fit:cover}
.avatar-name{font-weight:700;font-size:13px;color:var(--text)}
.avatar-role{font-size:10px;color:var(--muted);font-weight:600}

/* MAIN */
.main{margin-left:var(--sidebar-w);padding-top:64px;min-height:100vh}
.page-content{padding:28px 32px;max-width:900px}

/* PROFILE HEADER */
.profile-hero{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:24px;box-shadow:var(--shadow);display:flex;align-items:center;gap:24px;position:relative;overflow:hidden}
.profile-hero::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:<?php echo $tm['grad']; ?>;opacity:.04;pointer-events:none}
.profile-avatar-big{width:88px;height:88px;border-radius:24px;background:<?php echo $tm['grad']; ?>;display:flex;align-items:center;justify-content:center;font-size:34px;color:#fff;flex-shrink:0;box-shadow:0 8px 24px rgba(0,0,0,.2);position:relative;overflow:hidden;cursor:pointer}
.avatar-hover-overlay{position:absolute;inset:0;background:rgba(15,23,42,0.7);border-radius:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;opacity:0;transition:opacity 0.25s ease;backdrop-filter:blur(2px)}
.profile-avatar-big:hover .avatar-hover-overlay{opacity:1}
.profile-info{flex:1;min-width:0}
.profile-name{font-weight:900;font-size:24px}
.profile-meta{font-size:13px;color:var(--muted);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap}
.profile-meta i{font-size:11px;color:var(--blue)}
.profile-rank{display:inline-flex;align-items:center;gap:6px;background:rgba(245,158,11,.1);color:#d97706;border:1px solid rgba(245,158,11,.3);border-radius:20px;padding:5px 14px;font-size:11px;font-weight:800;margin-top:10px}
.profile-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0}

/* STATS */
.profile-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:24px}
.ps-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow-sm);text-align:center;transition:transform .2s}
.ps-card:hover{transform:translateY(-2px)}
.ps-val{font-weight:900;font-size:28px}
.ps-label{font-size:11px;color:var(--muted);font-weight:700;margin-top:3px}

/* FORM CARD */
.form-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px}
.form-card h3{font-weight:800;font-size:15px;margin-bottom:18px;display:flex;align-items:center;gap:10px}
.form-card h3 .fh-icon{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.field-group{margin-bottom:16px}
.field-label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.field-input{width:100%;height:48px;border-radius:12px;border:1.5px solid var(--border);background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;font-weight:600;font-size:14px;padding:0 16px;outline:none;transition:border-color .2s}
.field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.save-btn{width:100%;height:48px;border-radius:12px;border:none;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;font-family:'Inter',sans-serif;font-weight:800;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all .2s;box-shadow:0 6px 16px rgba(37,99,235,.3)}
.save-btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(37,99,235,.4)}

/* UNIT DETAIL CARD */
.unit-detail-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.udc-header{padding:20px;background:<?php echo $tm['grad']; ?>;position:relative;overflow:hidden}
.udc-header::before{content:'';position:absolute;top:-30px;right:-30px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.1)}
.udc-icon{font-size:32px;color:rgba(255,255,255,.9);margin-bottom:10px}
.udc-name{font-weight:900;font-size:17px;color:#fff}
.udc-meta{font-size:12px;color:rgba(255,255,255,.75);margin-top:3px}
.udc-plate{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;color:#fff;margin-top:10px}
.udc-body{padding:16px 20px}
.udc-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.udc-row:last-child{border-bottom:none;padding-bottom:0}
.udc-key{color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px}
.udc-key i{width:16px;text-align:center;color:var(--blue);font-size:12px}
.udc-val{font-weight:700}

/* ALERT */
.alert-msg{padding:12px 16px;border-radius:12px;font-weight:700;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-msg.success{background:rgba(16,185,129,.1);color:#059669;border:1px solid rgba(16,185,129,.25)}

/* MOBILE */
.mob-toggle{display:none;position:fixed;top:14px;left:14px;z-index:500;width:40px;height:40px;border-radius:12px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);align-items:center;justify-content:center;cursor:pointer;font-size:16px;color:var(--text)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:399}
@media(max-width:768px){.mob-toggle{display:flex}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}.topbar{left:0;padding-left:60px}.main{margin-left:0}.page-content{padding:20px 16px}.profile-hero{flex-direction:column;align-items:flex-start}.profile-right{align-items:flex-start}.profile-stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<button class="mob-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
        <div><div class="logo-text">SmartRescue</div><div class="logo-sub">Driver Portal</div></div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="index.php"   class="nav-item"><i class="fa-solid fa-house-chimney"></i> Dashboard</a>
        <a href="map.php"     class="nav-item"><i class="fa-solid fa-map-location-dot"></i> Live Map</a>
        <a href="history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
        <div class="nav-label" style="margin-top:8px">Account</div>
        <a href="profile.php"  class="nav-item active"><i class="fa-solid fa-user-shield"></i> Profile</a>
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

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-breadcrumb">
        <span class="page-title">Profile</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($fullname); ?></span>
    </div>
    <div class="topbar-actions">
        <label class="toggle-switch" onclick="toggleDispatch()">
            <div id="onlinePill" class="toggle-track <?php echo $is_avail ? 'on' : 'off'; ?>">
                <div class="toggle-knob"></div>
            </div>
            <span id="onlineText" class="toggle-label"><?php echo $is_avail ? 'Online' : 'Offline'; ?></span>
        </label>
        <button class="icon-btn" onclick="toggleTheme()"><i class="fa-solid <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>" id="themeIcon"></i></button>
        <a href="profile.php" class="avatar-wrap">
            <div class="avatar-circle">
                <?php if ($avatar): ?>
                    <img src="<?php echo $avatar; ?>" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;"><?php echo strtoupper(mb_substr($fullname, 0, 1)); ?></span>
                <?php else: ?>
                    <span><?php echo strtoupper(mb_substr($fullname, 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div><div class="avatar-name"><?php echo htmlspecialchars(explode(' ',$fullname)[0]); ?></div><div class="avatar-role">Driver</div></div>
        </a>
    </div>
</header>

<!-- MAIN -->
<main class="main">
<div class="page-content">

    <?php if ($msg): ?>
    <div class="alert-msg success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- PROFILE HERO -->
    <div class="profile-hero">
        <div class="profile-avatar-big" onclick="document.getElementById('avatarInputDirect').click()" title="Click to change profile picture">
            <?php if ($avatar): ?>
                <img src="<?php echo $avatar; ?>" style="width:100%;height:100%;border-radius:24px;object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="fa-solid <?php echo $tm['icon']; ?>" style="display:none"></i>
            <?php else: ?>
                <i class="fa-solid <?php echo $tm['icon']; ?>"></i>
            <?php endif; ?>
            <div class="avatar-hover-overlay">
                <i class="fa-solid fa-camera" style="font-size:20px;margin-bottom:3px"></i>
                <span style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.5px">Change</span>
            </div>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo htmlspecialchars($fullname); ?></div>
            <div class="profile-meta">
                <?php if ($email): ?><span><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($email); ?></span><?php endif; ?>
                <?php if ($phone): ?><span><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($phone); ?></span><?php endif; ?>
            </div>
            <div class="profile-rank"><i class="fa-solid fa-trophy"></i> <?php echo $rank; ?></div>
        </div>
        <div class="profile-right">
            <div style="text-align:right">
                <div style="font-size:10px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:1px">Unit</div>
                <div style="font-weight:900;font-size:15px;margin-top:2px"><?php echo htmlspecialchars($unit_name); ?></div>
                <div style="font-size:11px;color:var(--muted);margin-top:1px"><?php echo htmlspecialchars($plate_num); ?></div>
            </div>
        </div>
    </div>

    <!-- QUICK STATS -->
    <div class="profile-stats">
        <div class="ps-card">
            <div class="ps-val" style="color:var(--green)"><?php echo $total_saves; ?></div>
            <div class="ps-label">Lives Saved</div>
        </div>
        <div class="ps-card">
            <div class="ps-val" style="color:var(--blue)"><?php echo $total_missions; ?></div>
            <div class="ps-label">Total Missions</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px" class="two-col-profile">

        <!-- EDIT FORM -->
        <div class="form-card">
            <h3>
                <span class="fh-icon" style="background:rgba(37,99,235,.1);color:var(--blue)"><i class="fa-solid fa-user-pen"></i></span>
                Personal Information
            </h3>
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="file" id="avatarInputDirect" name="profile_image" accept="image/*" style="display:none" onchange="document.getElementById('profileFormSubmitBtn').click()">
                <div class="field-group">
                    <div class="field-label">Full Name</div>
                    <input type="text" name="fullname" class="field-input" value="<?php echo htmlspecialchars($fullname); ?>" required>
                </div>
                <div class="field-group">
                    <div class="field-label">Phone Number</div>
                    <input type="text" name="phone" class="field-input" value="<?php echo htmlspecialchars($phone); ?>">
                </div>
                <div class="field-group">
                    <div class="field-label">Email Address</div>
                    <input type="email" name="email" class="field-input" value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <button type="submit" name="update_profile" id="profileFormSubmitBtn" class="save-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- UNIT CARD -->
        <div class="unit-detail-card">
            <div class="udc-header">
                <div class="udc-icon"><i class="fa-solid <?php echo $tm['icon']; ?>"></i></div>
                <div class="udc-name"><?php echo htmlspecialchars($unit_name); ?></div>
                <div class="udc-meta"><?php echo ucfirst($unit_type); ?> Response Unit</div>
                <div class="udc-plate"><i class="fa-solid fa-id-card"></i> <?php echo htmlspecialchars($plate_num); ?></div>
            </div>
            <div class="udc-body">
                <div class="udc-row">
                    <span class="udc-key"><i class="fa-solid fa-circle-dot"></i> Status</span>
                    <span class="udc-val" style="color:<?php echo $is_avail ? 'var(--green)' : 'var(--red)'; ?>">
                        <?php echo $is_avail ? '● Available' : '● Offline'; ?>
                    </span>
                </div>
                <div class="udc-row">
                    <span class="udc-key"><i class="fa-solid fa-tag"></i> Type</span>
                    <span class="udc-val"><?php echo ucfirst($unit_type); ?></span>
                </div>
                <div class="udc-row">
                    <span class="udc-key"><i class="fa-solid fa-shield-heart"></i> Lives Saved</span>
                    <span class="udc-val" style="color:var(--green)"><?php echo $total_saves; ?></span>
                </div>
                <div class="udc-row">
                    <span class="udc-key"><i class="fa-solid fa-flag-checkered"></i> Total Missions</span>
                    <span class="udc-val"><?php echo $total_missions; ?></span>
                </div>
                <div class="udc-row">
                    <span class="udc-key"><i class="fa-solid fa-trophy"></i> Rank</span>
                    <span class="udc-val" style="color:var(--amber)"><?php echo $rank; ?></span>
                </div>
            </div>
        </div>

    </div><!-- /two-col-profile -->

</div>
</main>

<style>
@media(max-width:900px){.two-col-profile{grid-template-columns:1fr !important}}
</style>

<script>
const DRV_ID = <?php echo (int)$driver_id; ?>;
const UNIT_ID = <?php echo (int)$unit_id; ?>;
let isOnline = <?php echo $is_avail ? 'true' : 'false'; ?>;
let lastAlertId = null, lastAudio = 0;

function toggleDispatch(){const s=isOnline?'offline':'available';fetch('../api/driver/update_unit_status.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`status=${s}&driver_id=${DRV_ID}`}).then(r=>r.json()).then(d=>{if(d.status==='success'){isOnline=!isOnline;const p=document.getElementById('onlinePill');p.className='toggle-track '+(isOnline?'on':'off');document.getElementById('onlineText').textContent=isOnline?'Online':'Offline'}}).catch(()=>{})}
function toggleTheme(){const h=document.documentElement;const d=h.getAttribute('data-theme')==='dark';h.setAttribute('data-theme',d?'light':'dark');document.getElementById('themeIcon').className='fa-solid '+(d?'fa-moon':'fa-sun');fetch('../api/driver/save_setting.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`key=dark_mode&value=${d?0:1}&driver_id=${DRV_ID}`}).catch(()=>{})}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}

function doAction(rid, action) {
    fetch('../api/driver/update_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `request_id=${rid}&unit_id=${UNIT_ID}&action=${action}&driver_id=${DRV_ID}`
    }).then(r => r.json()).then(() => pollModal()).catch(() => {});
}

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

function pollModal() {
    fetch(`../api/driver/get_active_job.php?driver_id=${DRV_ID}`)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.request) {
                checkAndShowDispatchModal(data.request);
            } else {
                hideDispatchModal();
            }
        }).catch(() => {});
}
document.addEventListener('DOMContentLoaded', () => {
    pollModal();
    setInterval(pollModal, 3500);
});
</script>
<?php require_once 'dispatch_modal.php'; ?>
</body>
</html>
