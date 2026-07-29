<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'driver') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../includes/session_guard.php';

$driver_id   = $_SESSION['user_id'];
$user_q      = mysqli_query($conn, "SELECT * FROM users WHERE id = '$driver_id' LIMIT 1");
$driver_data = mysqli_fetch_assoc($user_q);
$fullname    = $driver_data['fullname']  ?? 'Driver';
$language    = $driver_data['language']  ?? 'en';
$dark_mode   = (int)($driver_data['dark_mode'] ?? 1);
$notif_on    = (int)($driver_data['notifications_enabled'] ?? 1);
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

$type_icons  = ['medical'=>'fa-truck-medical','fire'=>'fa-fire-extinguisher','police'=>'fa-shield-halved','accident'=>'fa-car-burst'];
$type_colors = ['medical'=>'#3b82f6','fire'=>'#ef4444','police'=>'#6366f1','accident'=>'#f59e0b'];
$unit_icon   = $type_icons[$unit_type]  ?? 'fa-truck-medical';
$unit_color  = $type_colors[$unit_type] ?? '#3b82f6';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Settings | SmartRescue Driver</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#f0f4ff;--surface:#ffffff;--surface2:#f8faff;--border:#e4e9f2;--text:#0f172a;--muted:#64748b;--blue:#2563eb;--green:#10b981;--red:#ef4444;--amber:#f59e0b;--purple:#6366f1;--shadow-sm:0 2px 8px rgba(0,0,0,0.06);--shadow:0 4px 24px rgba(0,0,0,0.08);--radius:16px;--sidebar-w:260px}
[data-theme="dark"]{--bg:#080f1e;--surface:#0f1c30;--surface2:#121f35;--border:#1a2d47;--text:#f1f5f9;--muted:#7a8fac;--shadow-sm:0 2px 8px rgba(0,0,0,0.3);--shadow:0 4px 24px rgba(0,0,0,0.4)}
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
.page-content{padding:28px 32px;max-width:820px}

/* PAGE HEADER */
.page-header{display:flex;align-items:center;gap:16px;margin-bottom:24px}
.page-icon{width:52px;height:52px;border-radius:16px;background:rgba(99,102,241,.1);color:var(--purple);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}

/* SETTINGS SECTION */
.settings-section{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px}
.section-head{padding:16px 20px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.section-head h3{font-weight:800;font-size:13px;text-transform:uppercase;letter-spacing:.5px;flex:1}
.sh-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}

/* SETTING ROW */
.setting-row{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;border-bottom:1px solid var(--border);gap:12px;transition:background .15s}
.setting-row:last-child{border-bottom:none}
.setting-row:hover{background:var(--bg)}
.sr-left{display:flex;align-items:center;gap:14px;min-width:0;flex:1}
.sr-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.sr-label{font-weight:700;font-size:14px;line-height:1.3}
.sr-sub{font-size:12px;color:var(--muted);margin-top:2px;font-weight:500}
.sr-right{flex-shrink:0}

/* TOGGLE */
.toggle-wrap{position:relative;display:inline-block}
.toggle-inp{position:absolute;opacity:0;width:0;height:0}
.toggle-track{display:block;width:50px;height:28px;border-radius:14px;background:#d1d5db;cursor:pointer;transition:background .25s;position:relative}
.toggle-inp:checked + .toggle-track{background:var(--track-color,#10b981)}
.toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.18);transition:transform .25s cubic-bezier(.4,0,.2,1)}
.toggle-inp:checked + .toggle-track::after{transform:translateX(22px)}

/* SELECT */
.lang-select{appearance:none;-webkit-appearance:none;background:var(--bg);border:1.5px solid var(--border);border-radius:12px;color:var(--text);font-family:'Inter',sans-serif;font-weight:600;font-size:14px;padding:10px 36px 10px 14px;cursor:pointer;outline:none;transition:border-color .2s;min-width:200px}
.lang-select:focus{border-color:var(--blue)}
.lang-select-wrap{position:relative}
.lang-select-wrap::after{content:'\f107';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none;font-size:13px}

/* DANGER ZONE */
.danger-zone{background:rgba(239,68,68,.05);border:1.5px solid rgba(239,68,68,.2);border-radius:var(--radius);padding:20px 24px;margin-bottom:16px}
.danger-title{font-weight:800;font-size:13px;color:var(--red);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.signout-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;height:48px;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 6px 16px rgba(239,68,68,.35);transition:all .2s;text-decoration:none}
.signout-btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(239,68,68,.4)}

/* TOAST */
.toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--text);color:var(--bg);padding:12px 20px;border-radius:30px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:1000;opacity:0;transition:all .3s;pointer-events:none}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

/* MOBILE */
.mob-toggle{display:none;position:fixed;top:14px;left:14px;z-index:500;width:40px;height:40px;border-radius:12px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow);align-items:center;justify-content:center;cursor:pointer;font-size:16px;color:var(--text)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:399}
@media(max-width:768px){.mob-toggle{display:flex}.sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}.topbar{left:0;padding-left:60px}.main{margin-left:0}.page-content{padding:20px 16px}}
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
        <a href="profile.php"  class="nav-item"><i class="fa-solid fa-user-shield"></i> Profile</a>
        <a href="settings.php" class="nav-item active"><i class="fa-solid fa-gear"></i> Settings</a>
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
        <span class="page-title">Settings</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($unit_name); ?></span>
    </div>
    <div class="topbar-actions">
        <label class="toggle-switch" onclick="toggleDispatch()">
            <div id="onlinePill" class="toggle-track <?php echo $is_avail ? 'on' : 'off'; ?>">
                <div class="toggle-knob"></div>
            </div>
            <span id="onlineText" class="toggle-label"><?php echo $is_avail ? 'Online' : 'Offline'; ?></span>
        </label>
        <button class="icon-btn" onclick="toggleDarkBtn()" id="themeBtn">
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
            <div><div class="avatar-name"><?php echo htmlspecialchars(explode(' ',$fullname)[0]); ?></div><div class="avatar-role">Driver</div></div>
        </a>
    </div>
</header>

<!-- MAIN -->
<main class="main">
<div class="page-content">

    <div class="page-header">
        <div class="page-icon"><i class="fa-solid fa-gear"></i></div>
        <div>
            <h1 style="font-weight:900;font-size:22px">Settings</h1>
            <p style="font-size:13px;color:var(--muted);margin-top:3px">Manage your dispatch preferences and account settings</p>
        </div>
    </div>

    <!-- ── UNIT & DISPATCH ─────────────────────────────── -->
    <div class="settings-section">
        <div class="section-head">
            <div class="sh-icon" style="background:<?php echo $unit_color; ?>20;color:<?php echo $unit_color; ?>">
                <i class="fa-solid <?php echo $unit_icon; ?>"></i>
            </div>
            <h3>Unit & Dispatch</h3>
        </div>

        <!-- Unit Info -->
        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:<?php echo $unit_color; ?>20;color:<?php echo $unit_color; ?>">
                    <i class="fa-solid <?php echo $unit_icon; ?>"></i>
                </div>
                <div>
                    <div class="sr-label"><?php echo htmlspecialchars(strtoupper($unit_name)); ?></div>
                    <div class="sr-sub"><?php echo ucfirst($unit_type); ?> · <?php echo htmlspecialchars($plate_num); ?></div>
                </div>
            </div>
            <div class="sr-right">
                <span style="font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;background:<?php echo $is_avail ? 'rgba(16,185,129,.12)' : 'rgba(239,68,68,.1)'; ?>;color:<?php echo $is_avail ? 'var(--green)' : 'var(--red)'; ?>">
                    <?php echo $is_avail ? '● Active' : '● Offline'; ?>
                </span>
            </div>
        </div>

        <!-- Available toggle -->
        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(16,185,129,.12);color:var(--green)">
                    <i class="fa-solid fa-circle-dot"></i>
                </div>
                <div>
                    <div class="sr-label">Available for Dispatches</div>
                    <div class="sr-sub">Toggle your online availability for incoming SOS calls</div>
                </div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" class="toggle-inp" id="dispatchToggle"
                    <?php echo $is_avail ? 'checked' : ''; ?>
                    onchange="toggleDispatch(this.checked)">
                <span class="toggle-track" style="--track-color:#10b981"></span>
            </label>
        </div>
    </div>

    <!-- ── APPEARANCE ──────────────────────────────────── -->
    <div class="settings-section">
        <div class="section-head">
            <div class="sh-icon" style="background:rgba(99,102,241,.12);color:var(--purple)">
                <i class="fa-solid fa-palette"></i>
            </div>
            <h3>Appearance</h3>
        </div>

        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(234,179,8,.12);color:#eab308">
                    <i class="fa-solid fa-moon"></i>
                </div>
                <div>
                    <div class="sr-label">Dark Mode</div>
                    <div class="sr-sub" id="themeSubtxt"><?php echo $dark_mode ? 'Dark theme active' : 'Light theme active'; ?></div>
                </div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" class="toggle-inp" id="darkModeToggle"
                    <?php echo $dark_mode ? 'checked' : ''; ?>
                    onchange="toggleDark(this.checked)">
                <span class="toggle-track" style="--track-color:#6366f1"></span>
            </label>
        </div>
    </div>

    <!-- ── NOTIFICATIONS ───────────────────────────────── -->
    <div class="settings-section">
        <div class="section-head">
            <div class="sh-icon" style="background:rgba(245,158,11,.12);color:var(--amber)">
                <i class="fa-solid fa-bell"></i>
            </div>
            <h3>Notifications</h3>
        </div>

        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(245,158,11,.12);color:var(--amber)">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <div class="sr-label">System Notifications</div>
                    <div class="sr-sub">Push alerts for incoming emergency dispatches</div>
                </div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" class="toggle-inp" id="notifToggle"
                    <?php echo $notif_on ? 'checked' : ''; ?>
                    onchange="savePref('notifications_enabled', this.checked ? 1 : 0)">
                <span class="toggle-track" style="--track-color:#f59e0b"></span>
            </label>
        </div>

        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(99,102,241,.12);color:var(--purple)">
                    <i class="fa-solid fa-volume-high"></i>
                </div>
                <div>
                    <div class="sr-label">Sound Alarm</div>
                    <div class="sr-sub">Audible alert on new emergency SOS</div>
                </div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" class="toggle-inp" id="soundToggle" checked
                    onchange="savePref('sound_enabled', this.checked ? 1 : 0)">
                <span class="toggle-track" style="--track-color:#6366f1"></span>
            </label>
        </div>

        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(236,72,153,.12);color:#ec4899">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div>
                    <div class="sr-label">Vibration</div>
                    <div class="sr-sub">Haptic feedback on new emergency</div>
                </div>
            </div>
            <label class="toggle-wrap">
                <input type="checkbox" class="toggle-inp" id="hapticToggle" checked
                    onchange="savePref('vibration_enabled', this.checked ? 1 : 0)">
                <span class="toggle-track" style="--track-color:#ec4899"></span>
            </label>
        </div>
    </div>

    <!-- ── LANGUAGE ────────────────────────────────────── -->
    <div class="settings-section">
        <div class="section-head">
            <div class="sh-icon" style="background:rgba(6,182,212,.12);color:#06b6d4">
                <i class="fa-solid fa-language"></i>
            </div>
            <h3>Language & Region</h3>
        </div>
        <div class="setting-row">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(6,182,212,.12);color:#06b6d4">
                    <i class="fa-solid fa-earth-africa"></i>
                </div>
                <div>
                    <div class="sr-label">App Language</div>
                    <div class="sr-sub">Choose your preferred language</div>
                </div>
            </div>
            <div class="lang-select-wrap">
                <select class="lang-select" id="langSelect" onchange="changeLang(this.value)">
                    <option value="en" <?php echo $language==='en'?'selected':''; ?>>🇬🇧 English</option>
                    <option value="so" <?php echo $language==='so'?'selected':''; ?>>🇸🇴 Somali</option>
                    <option value="ar" <?php echo $language==='ar'?'selected':''; ?>>🇸🇦 Arabic</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── ACCOUNT LINKS ───────────────────────────────── -->
    <div class="settings-section">
        <div class="section-head">
            <div class="sh-icon" style="background:rgba(37,99,235,.12);color:var(--blue)">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <h3>Account</h3>
        </div>
        <a href="profile.php" class="setting-row" style="text-decoration:none;color:inherit">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(37,99,235,.12);color:var(--blue)">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <div class="sr-label">Edit Profile</div>
                    <div class="sr-sub">Update name, phone, and email</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--muted);font-size:13px"></i>
        </a>
        <a href="history.php" class="setting-row" style="text-decoration:none;color:inherit">
            <div class="sr-left">
                <div class="sr-icon" style="background:rgba(99,102,241,.12);color:var(--purple)">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="sr-label">Mission History</div>
                    <div class="sr-sub">View your completed rescue missions</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--muted);font-size:13px"></i>
        </a>
    </div>

    <!-- ── DANGER ZONE ─────────────────────────────────── -->
    <div class="danger-zone">
        <div class="danger-title"><i class="fa-solid fa-power-off"></i> Sign Out</div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px;line-height:1.6">You will be logged out of the SmartRescue Driver Portal. Make sure you are offline before logging out.</p>
        <a href="../auth/logout.php" class="signout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Sign Out of Driver Portal
        </a>
    </div>

</div>
</main>

<!-- Toast -->
<div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i> <span id="toastMsg">Saved</span></div>

<script>
const DRV_ID = <?php echo (int)$driver_id; ?>;
let isOnline = <?php echo $is_avail ? 'true' : 'false'; ?>;

function showToast(msg = 'Saved') {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function toggleDispatch(val) {
    if (val === undefined) val = !isOnline;
    const newStatus = val ? 'available' : 'offline';
    fetch('../api/driver/update_unit_status.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`status=${newStatus}&driver_id=${DRV_ID}`
    }).then(r=>r.json()).then(d=>{
        if(d.status==='success'){
            isOnline = val;
            const pill = document.getElementById('onlinePill');
            pill.className = 'toggle-track ' + (isOnline ? 'on' : 'off');
            document.getElementById('onlineText').textContent = isOnline ? 'Online' : 'Offline';
            document.getElementById('dispatchToggle').checked = isOnline;
            showToast(isOnline ? 'You are now Online' : 'You are now Offline');
        }
    }).catch(()=>{});
}

function toggleDark(isDark) {
    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
    document.getElementById('themeSubtxt').textContent = isDark ? 'Dark theme active' : 'Light theme active';
    document.getElementById('themeIcon').className = 'fa-solid ' + (isDark ? 'fa-sun' : 'fa-moon');
    savePref('dark_mode', isDark ? 1 : 0);
    showToast(isDark ? 'Dark mode enabled' : 'Light mode enabled');
}

function toggleDarkBtn() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.getElementById('darkModeToggle').checked = !isDark;
    toggleDark(!isDark);
}

function savePref(key, value) {
    fetch('../api/driver/save_setting.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`key=${encodeURIComponent(key)}&value=${value}&driver_id=${DRV_ID}`
    }).then(()=>showToast('Setting saved')).catch(()=>{});
}

function changeLang(lang) {
    savePref('language', lang);
}

function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}

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
