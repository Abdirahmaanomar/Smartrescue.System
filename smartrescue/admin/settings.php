<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}
require_once '../includes/functions.php';
require_once 'includes/lang.php';

$user_id = $_SESSION['user_id'];

// 1. Fetch current logged-in admin user profile details
$admin_stmt = mysqli_query($conn, "SELECT fullname, email, phone FROM users WHERE id = '$user_id'");
$admin_user = mysqli_fetch_assoc($admin_stmt) ?: ['fullname' => 'Administrator', 'email' => 'admin@smartrescue.so', 'phone' => '+252 61 000 0000'];

// 2. Fetch all current settings from database
$settings = [];
$res = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
while($row = mysqli_fetch_assoc($res)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Ensure system_logs table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(255) NOT NULL,
    `details` text DEFAULT NULL,
    `type` varchar(50) DEFAULT 'info',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

$logs_res = mysqli_query($conn, "SELECT l.*, u.fullname FROM system_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 10");
$logs = mysqli_fetch_all($logs_res, MYSQLI_ASSOC);

// Count total users
$user_cnt_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
$user_cnt = ($user_cnt_res && $row = mysqli_fetch_assoc($user_cnt_res)) ? $row['cnt'] : 0;

// Ensure defaults for missing keys
$defaults = [
    'site_name' => 'SmartRescue',
    'emergency_hotline' => '999',
    'contact_email' => 'admin@smartrescue.so',
    'contact_phone' => '+252 61 000 0000',
    'dispatch_radius' => '15',
    'map_zoom' => '13',
    'default_lat' => '2.0469',
    'default_lng' => '45.3182',
    'refresh_rate' => '4',

    'user_reg_status' => 'active',
    'allow_self_reg' => '1',
    'auto_approve_driver' => '0',
    'pass_min_length' => '6',
    'session_timeout' => '30',
    'max_login_attempts' => '5',
    'two_factor_auth' => '0',

    'sos_timeout_warn' => '10',
    'auto_assign_closest' => '1',
    'max_missions_per_driver' => '1',
    'allow_multi_responders' => '0',
    'incident_auto_archive_days' => '30',

    'notif_sound' => '1',
    'notif_screen_flash' => '1',
    'notif_sound_repeat' => '10',
    'notif_high_priority_popup' => '1',

    'language' => 'en',
    'maintenance_mode' => '0',
    'maintenance_message' => 'System is under scheduled maintenance. Please contact emergency hotline directly.',
    'debug_mode' => '0',

    'auto_backup' => '0',
    'retention_days' => '90'
];

foreach($defaults as $key => $val) {
    if(!isset($settings[$key])) $settings[$key] = $val;
}

// 3. Save Settings Handler (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        
        $excluded = ['ajax', 'new_password', 'admin_fullname', 'admin_email', 'admin_phone'];
        $checkboxes = [
            'allow_self_reg',
            'auto_approve_driver',
            'two_factor_auth',
            'auto_assign_closest',
            'allow_multi_responders',
            'notif_sound',
            'notif_screen_flash',
            'notif_high_priority_popup',
            'maintenance_mode',
            'debug_mode',
            'auto_backup'
        ];

        // Save key-value settings
        foreach($_POST as $key => $value) {
            if(!in_array($key, $excluded)) {
                update_setting($conn, $key, clean_input($value, $conn));
            }
        }

        // Handle Checkboxes
        foreach($checkboxes as $cb) {
            if(!isset($_POST[$cb])) {
                update_setting($conn, $cb, '0');
            } else {
                update_setting($conn, $cb, '1');
            }
        }

        // Handle Admin Account Updates
        if (isset($_POST['admin_fullname']) || isset($_POST['admin_email']) || isset($_POST['admin_phone'])) {
            $afullname = clean_input($_POST['admin_fullname'], $conn);
            $aemail    = clean_input($_POST['admin_email'], $conn);
            $aphone    = clean_input($_POST['admin_phone'], $conn);
            
            if(!empty($afullname) && !empty($aemail)) {
                mysqli_query($conn, "UPDATE users SET fullname='$afullname', email='$aemail', phone='$aphone' WHERE id='$user_id'");
                $_SESSION['fullname'] = $afullname;
            }
        }

        // Handle Password Change if provided
        if(!empty($_POST['new_password'])) {
            $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password = '$new_pass' WHERE id = '$user_id'");
        }

        // Log activity
        log_activity($conn, $_SESSION['user_id'], 'Settings Updated', 'Admin updated comprehensive system configuration.', 'success');

        echo json_encode(['status' => 'success', 'message' => t('System & admin settings synchronized successfully.'), 'reload' => true]);
        exit();
    }
}
$sys_lang = get_setting($conn, 'language', 'en');
?>
<!DOCTYPE html>
<html lang="<?= $sys_lang ?>" <?= $sys_lang == 'ar' ? 'dir="rtl"' : '' ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── CSS RESET & VARIABLES ── */
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg-color: #f1f5f9;
    --surface: #ffffff;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --text-light: #94a3b8;
    
    --primary: #2563eb;
    --primary-light: rgba(37, 99, 235, 0.1);
    --primary-dark: #1d4ed8;
    --success: #10b981;
    --success-bg: rgba(16, 185, 129, 0.1);
    --danger: #ef4444;
    --danger-bg: rgba(239, 68, 68, 0.1);
    --warning: #f59e0b;
    
    --border: #e2e8f0;
    --focus: rgba(37, 99, 235, 0.25);
    
    --radius-sm: 8px;
    --radius-md: 14px;
    --radius-lg: 20px;
    --radius-xl: 24px;
    
    --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
    --shadow-md: 0 8px 24px rgba(0,0,0,0.06);
    --shadow-lg: 0 16px 40px rgba(0,0,0,0.08);
    --sidebar-width: 268px;
}

body {
    font-family: 'Outfit', sans-serif;
    background: var(--bg-color);
    color: var(--text-main);
    overflow-x: hidden;
    padding-bottom: 90px; /* Room for sticky save bar */
}
.main-wrapper {
    margin-left: var(--sidebar-width);
    padding: 32px 40px;
    max-width: 1240px;
}

/* ── STATUS DASHBOARD ── */
.status-dashboard {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
    margin-bottom: 24px;
}
.status-pill {
    background: var(--surface); border-radius: var(--radius-md);
    padding: 18px 22px; border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    display: flex; align-items: center; gap: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.status-pill:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.status-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.si-up { background: var(--success-bg); color: var(--success); }
.si-api { background: var(--primary-light); color: var(--primary); }
.si-db { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }

.status-info h4 { font-size: 1.25rem; font-weight: 800; margin: 0; display: flex; align-items: baseline; gap: 4px; }
.status-info h4 small { font-size: 0.8rem; font-weight: 500; color: var(--text-muted); }
.status-info p { font-size: 0.75rem; color: var(--text-muted); margin: 2px 0 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── LAYOUT GRID ── */
.settings-grid {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 32px;
    align-items: start;
}

/* ── VERTICAL NAV ── */
.settings-nav {
    background: var(--surface); border-radius: var(--radius-lg);
    padding: 16px; box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    position: sticky; top: 100px;
}
.s-nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; border-radius: var(--radius-md);
    color: var(--text-muted); font-size: 0.88rem; font-weight: 700;
    text-decoration: none; transition: all 0.2s;
    margin-bottom: 4px; border: 1px solid transparent; cursor: pointer;
}
.s-nav-item i { width: 20px; text-align: center; font-size: 1.1rem; }
.s-nav-item:hover { background: var(--bg-color); color: var(--text-main); }
.s-nav-item.active {
    background: var(--surface);
    border-color: var(--border);
    color: var(--primary);
    box-shadow: var(--shadow-sm);
}

/* ── SETTING PANELS ── */
.settings-panel { display: none; animation: fadeIn 0.3s ease forwards; }
.settings-panel.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.s-card {
    background: var(--surface); border-radius: var(--radius-xl);
    padding: 32px; box-shadow: var(--shadow-sm);
    border: 1px solid var(--border); margin-bottom: 24px;
}
.s-card-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 28px; padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.sc-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--bg-color); color: var(--text-main);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; border: 1px solid var(--border);
}
.sc-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.sc-desc { font-size: 0.8rem; color: var(--text-muted); font-weight: 500; margin-top: 3px; }

/* ── FORMS ── */
.form-row { margin-bottom: 22px; }
.form-label {
    display: block; font-size: 0.8rem; font-weight: 700;
    color: var(--text-main); margin-bottom: 8px;
}
.input-group-custom {
    position: relative; display: flex; align-items: center;
}
.input-icon-left {
    position: absolute; left: 16px; color: var(--text-light); font-size: 0.95rem; pointer-events: none;
}
.s-input {
    width: 100%; padding: 12px 16px;
    padding-left: 44px; /* Space for icon */
    background: #fafafa; border: 1.5px solid var(--border);
    border-radius: var(--radius-md); color: var(--text-main);
    font-size: 0.9rem; font-weight: 600; font-family: 'Outfit', sans-serif;
    transition: all 0.2s; outline: none;
}
.s-input:focus {
    background: var(--surface); border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--focus);
}
.s-input[readonly] { background: var(--bg-color); cursor: not-allowed; opacity: 0.8; }
.s-input.no-icon { padding-left: 16px; }

.form-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; font-weight: 500; }

/* ── TOGGLES ── */
.setting-toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 0; border-bottom: 1px solid var(--border);
}
.setting-toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
.str-info h6 { font-size: 0.95rem; font-weight: 700; margin: 0 0 4px 0; }
.str-info p { font-size: 0.8rem; color: var(--text-muted); margin: 0; font-weight: 500; }

.s-toggle { position: relative; width: 48px; height: 26px; display: inline-block; flex-shrink: 0; cursor: pointer; }
.s-toggle input { opacity: 0; width: 0; height: 0; }
.s-toggle-slider {
    position: absolute; inset: 0; background: #cbd5e1; border-radius: 50px; transition: 0.3s;
}
.s-toggle-slider::before {
    content: ''; position: absolute; height: 20px; width: 20px;
    left: 3px; bottom: 3px; background: white; border-radius: 50%;
    transition: 0.3s cubic-bezier(0.4, 0.0, 0.2, 1); box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.s-toggle input:checked + .s-toggle-slider { background: var(--primary); }
.s-toggle input:checked + .s-toggle-slider::before { transform: translateX(22px); }

/* ── STICKY SAVE BAR ── */
.sticky-save-bar {
    position: fixed; bottom: -80px; left: var(--sidebar-width); right: 0;
    background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border-top: 1px solid var(--border); padding: 16px 40px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 -10px 40px rgba(0,0,0,0.05); z-index: 100;
    transition: bottom 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.sticky-save-bar.visible { bottom: 0; }
.ssb-info { display: flex; align-items: center; gap: 12px; }
.ssb-dot { width: 10px; height: 10px; background: var(--warning); border-radius: 50%; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); animation: pulseDot 2s infinite; }
@keyframes pulseDot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(0.8); opacity: 0.5; } }
.ssb-text h5 { margin: 0; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.ssb-text p { margin: 0; font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }

.ssb-actions { display: flex; gap: 14px; }
.btn-outline {
    background: transparent; border: 1.5px solid var(--border); color: var(--text-main);
    padding: 10px 20px; border-radius: var(--radius-md); font-weight: 700; font-size: 0.85rem;
    cursor: pointer; transition: all 0.2s; font-family: 'Outfit', sans-serif;
}
.btn-outline:hover { background: var(--bg-color); color: var(--danger); border-color: rgba(239, 68, 68, 0.3); }

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border: none; padding: 10px 28px; border-radius: var(--radius-md);
    font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s;
    font-family: 'Outfit', sans-serif; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    display: inline-flex; align-items: center; gap: 8px; min-width: 140px; justify-content: center;
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4); }

/* ── ACTION LISTS / LOGS ── */
.activity-list { list-style: none; padding: 0; margin: 0; }
.activity-item {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 16px 0; border-bottom: 1px solid var(--border);
}
.activity-item:last-child { border: none; padding-bottom: 0; }
.act-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--bg-color); color: var(--text-muted);
    display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; border: 1px solid var(--border);
}
.act-icon.info { color: var(--primary); background: var(--primary-light); border-color: rgba(37, 99, 235, 0.2); }
.act-icon.safe { color: var(--success); background: var(--success-bg); border-color: rgba(16, 185, 129, 0.2); }
.act-details h6 { font-size: 0.88rem; font-weight: 700; margin: 0 0 2px 0; color: var(--text-main); }
.act-details p { font-size: 0.8rem; color: var(--text-muted); margin: 0; line-height: 1.4; }
.act-time { font-size: 0.75rem; font-weight: 700; color: var(--text-light); text-align: right; flex-shrink: 0; }

/* ── TOAST ── */
.toast-container { position: fixed; top: 24px; right: 24px; z-index: 1000; pointer-events: none; }
.toast-msg {
    background: var(--surface); border-left: 4px solid var(--success);
    border-radius: var(--radius-md); padding: 16px 24px; box-shadow: var(--shadow-lg);
    display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
    animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; min-width: 300px; border: 1px solid var(--border);
}
.toast-msg h6 { margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }
.toast-msg p  { margin: 2px 0 0; font-size: 0.78rem; font-weight: 500; color: var(--text-muted); }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { to { transform: translateX(100%); opacity: 0; } }

@media(max-width: 991px) {
    .settings-grid { grid-template-columns: 1fr; }
    .settings-nav { position: static; display: flex; overflow-x: auto; padding: 10px; border-radius: var(--radius-md); }
    .s-nav-item { white-space: nowrap; }
    .status-dashboard { grid-template-columns: 1fr; }
    .main-wrapper { margin-left: 0; padding: 20px; }
    .sticky-save-bar { left: 0; padding: 16px 20px; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-wrapper">
    <?php $page_title = t('System Configuration'); $page_subtitle = t('Admin Dashboard Settings'); include 'includes/topbar.php'; ?>

    <!-- STATUS PANEL -->
    <div class="status-dashboard">
        <div class="status-pill">
            <div class="status-icon si-up"><i class="fa fa-server"></i></div>
            <div class="status-info">
                <h4>99.9% <small>Uptime</small></h4>
                <p><?= t('System Online') ?></p>
            </div>
        </div>
        <div class="status-pill">
            <div class="status-icon si-api"><i class="fa fa-users"></i></div>
            <div class="status-info">
                <h4><?= $user_cnt ?> <small>Total</small></h4>
                <p><?= t('Registered Users') ?></p>
            </div>
        </div>
        <div class="status-pill">
            <div class="status-icon si-db"><i class="fa fa-database"></i></div>
            <div class="status-info">
                <h4>24.5 MB <small>Healthy</small></h4>
                <p><?= t('Database Scope') ?></p>
            </div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="settings-grid">
        
        <!-- SIDE NAVIGATION -->
        <nav class="settings-nav">
            <a class="s-nav-item active" data-target="panel-general"><i class="fa fa-sliders"></i> <?= t('General & Branding') ?></a>
            <a class="s-nav-item" data-target="panel-user-settings"><i class="fa fa-users-gear"></i> <?= t('User & Account Settings') ?></a>
            <a class="s-nav-item" data-target="panel-dispatch"><i class="fa fa-truck-medical"></i> <?= t('Emergency & Dispatch') ?></a>
            <a class="s-nav-item" data-target="panel-notify"><i class="fa fa-bell"></i> <?= t('Notifications & Alerts') ?></a>
            <a class="s-nav-item" data-target="panel-system"><i class="fa fa-shield-halved"></i> <?= t('System & Security') ?></a>
            <a class="s-nav-item" data-target="panel-backup"><i class="fa fa-database"></i> <?= t('Backups & Logs') ?></a>
        </nav>

        <!-- CONTENT PANELS -->
        <div class="settings-content">
            <form id="settingsForm">
                
                <!-- 1. GENERAL & BRANDING -->
                <div class="settings-panel active" id="panel-general">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-layer-group"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('General Settings & Platform Details') ?></h3>
                                <p class="sc-desc"><?= t('Core application details, branding, and contact numbers.') ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Platform Name') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-globe input-icon-left"></i>
                                    <input type="text" name="site_name" class="s-input track-change" value="<?= htmlspecialchars($settings['site_name']) ?>">
                                </div>
                                <p class="form-hint"><?= t('Displayed in the header and outgoing emails.') ?></p>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Emergency Hotline Number') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-phone-volume input-icon-left"></i>
                                    <input type="text" name="emergency_hotline" class="s-input track-change" value="<?= htmlspecialchars($settings['emergency_hotline']) ?>">
                                </div>
                                <p class="form-hint"><?= t('Direct 24/7 emergency dispatch call center number.') ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Support Email Contact') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-envelope input-icon-left"></i>
                                    <input type="email" name="contact_email" class="s-input track-change" value="<?= htmlspecialchars($settings['contact_email']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Support Phone Number') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-phone input-icon-left"></i>
                                    <input type="text" name="contact_phone" class="s-input track-change" value="<?= htmlspecialchars($settings['contact_phone']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-map-location-dot"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Live Map & GPS Defaults') ?></h3>
                                <p class="sc-desc"><?= t('Configure fleet tracking bounds and default search radius.') ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Dispatch Search Radius (KM)') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-compass input-icon-left"></i>
                                    <input type="number" step="0.5" name="dispatch_radius" class="s-input track-change" value="<?= htmlspecialchars($settings['dispatch_radius']) ?>">
                                </div>
                                <p class="form-hint"><?= t('Search radius to locate nearby ambulances for emergency calls.') ?></p>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Live Map Polling Rate') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-stopwatch input-icon-left"></i>
                                    <select name="refresh_rate" class="s-input track-change">
                                        <option value="2" <?= $settings['refresh_rate']=='2'?'selected':'' ?>>Aggressive (Every 2 seconds)</option>
                                        <option value="4" <?= $settings['refresh_rate']=='4'?'selected':'' ?>>Optimal (Every 4 seconds)</option>
                                        <option value="10" <?= $settings['refresh_rate']=='10'?'selected':'' ?>>Economy (Every 10 seconds)</option>
                                    </select>
                                </div>
                                <p class="form-hint"><?= t('Frequent polling provides smoother fleet tracking but increases server load.') ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-row">
                                <label class="form-label"><?= t('Map Default Zoom Level') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-magnifying-glass-location input-icon-left"></i>
                                    <input type="number" name="map_zoom" class="s-input track-change" value="<?= htmlspecialchars($settings['map_zoom']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4 form-row">
                                <label class="form-label"><?= t('Default Latitude') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-location-dot input-icon-left"></i>
                                    <input type="text" name="default_lat" class="s-input track-change" value="<?= htmlspecialchars($settings['default_lat']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4 form-row">
                                <label class="form-label"><?= t('Default Longitude') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-location-dot input-icon-left"></i>
                                    <input type="text" name="default_lng" class="s-input track-change" value="<?= htmlspecialchars($settings['default_lng']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. USER & ACCOUNT SETTINGS (Explicitly Requested) -->
                <div class="settings-panel" id="panel-user-settings">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-user-gear"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('User Registration & Access Controls') ?></h3>
                                <p class="sc-desc"><?= t('Set rules for user signups, driver approval, and account defaults.') ?></p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Default New Account Status') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-user-check input-icon-left"></i>
                                    <select name="user_reg_status" class="s-input track-change">
                                        <option value="active" <?= $settings['user_reg_status']=='active'?'selected':'' ?>>Instant Active (No Review Needed)</option>
                                        <option value="pending" <?= $settings['user_reg_status']=='pending'?'selected':'' ?>>Pending Admin Approval</option>
                                    </select>
                                </div>
                                <p class="form-hint"><?= t('Controls whether newly registered victims/users can submit SOS instantly.') ?></p>
                            </div>

                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Password Minimum Length') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-key input-icon-left"></i>
                                    <select name="pass_min_length" class="s-input track-change">
                                        <option value="6" <?= $settings['pass_min_length']=='6'?'selected':'' ?>>6 Characters (Standard)</option>
                                        <option value="8" <?= $settings['pass_min_length']=='8'?'selected':'' ?>>8 Characters (Recommended)</option>
                                        <option value="10" <?= $settings['pass_min_length']=='10'?'selected':'' ?>>10 Characters (High Security)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Allow Public User Self-Registration') ?></h6>
                                <p><?= t('Permit new victims/citizens to create accounts from the mobile app.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="allow_self_reg" class="track-change" <?= ($settings['allow_self_reg'] == '1' || $settings['allow_self_reg'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Auto-Approve Ambulance Driver Signups') ?></h6>
                                <p><?= t('Automatically activate newly registered drivers without manual verification.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="auto_approve_driver" class="track-change" <?= ($settings['auto_approve_driver'] == '1' || $settings['auto_approve_driver'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-user-shield"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Admin Account Credentials & Session') ?></h3>
                                <p class="sc-desc"><?= t('Manage master administrator account credentials and session timeouts.') ?></p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Admin Full Name') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-user input-icon-left"></i>
                                    <input type="text" name="admin_fullname" class="s-input track-change" value="<?= htmlspecialchars($admin_user['fullname']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Master User Email') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-envelope input-icon-left"></i>
                                    <input type="email" name="admin_email" class="s-input track-change" value="<?= htmlspecialchars($admin_user['email']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Admin Phone Number') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-phone input-icon-left"></i>
                                    <input type="text" name="admin_phone" class="s-input track-change" value="<?= htmlspecialchars($admin_user['phone']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Session Inactivity Timeout') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-clock input-icon-left"></i>
                                    <select name="session_timeout" class="s-input track-change">
                                        <option value="15" <?= $settings['session_timeout']=='15'?'selected':'' ?>>15 Minutes</option>
                                        <option value="30" <?= $settings['session_timeout']=='30'?'selected':'' ?>>30 Minutes</option>
                                        <option value="60" <?= $settings['session_timeout']=='60'?'selected':'' ?>>1 Hour</option>
                                        <option value="0" <?= $settings['session_timeout']=='0'?'selected':'' ?>>Never (Session Persists)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label"><?= t('Update Master Admin Password') ?></label>
                            <div class="input-group-custom">
                                <i class="fa fa-lock input-icon-left"></i>
                                <input type="password" name="new_password" class="s-input track-change" placeholder="<?= t('Leave empty to keep current password.') ?>">
                            </div>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Require Two-Factor Authentication (2FA) for Admins') ?></h6>
                                <p><?= t('Enforce security code verification upon login for administrative roles.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="two_factor_auth" class="track-change" <?= ($settings['two_factor_auth'] == '1' || $settings['two_factor_auth'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. EMERGENCY & DISPATCH -->
                <div class="settings-panel" id="panel-dispatch">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-truck-medical"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Emergency Dispatch & Response Rules') ?></h3>
                                <p class="sc-desc"><?= t('Configure automated assignment rules and response timeouts.') ?></p>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('SOS Delay Warning Threshold (Minutes)') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-hourglass-half input-icon-left"></i>
                                    <select name="sos_timeout_warn" class="s-input track-change">
                                        <option value="5" <?= $settings['sos_timeout_warn']=='5'?'selected':'' ?>>5 Minutes (Strict Response)</option>
                                        <option value="10" <?= $settings['sos_timeout_warn']=='10'?'selected':'' ?>>10 Minutes (Standard)</option>
                                        <option value="15" <?= $settings['sos_timeout_warn']=='15'?'selected':'' ?>>15 Minutes (Relaxed)</option>
                                    </select>
                                </div>
                                <p class="form-hint"><?= t('Time before unassigned emergency calls flag red alert warnings.') ?></p>
                            </div>

                            <div class="col-md-6 form-row">
                                <label class="form-label"><?= t('Max Active Missions Per Driver') ?></label>
                                <div class="input-group-custom">
                                    <i class="fa fa-ambulance input-icon-left"></i>
                                    <select name="max_missions_per_driver" class="s-input track-change">
                                        <option value="1" <?= $settings['max_missions_per_driver']=='1'?'selected':'' ?>>1 Mission at a time (Recommended)</option>
                                        <option value="2" <?= $settings['max_missions_per_driver']=='2'?'selected':'' ?>>Up to 2 Concurrent Missions</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Auto-Assign Closest Available Ambulance Unit') ?></h6>
                                <p><?= t('Automatically dispatch nearest active driver upon receiving emergency SOS.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="auto_assign_closest" class="track-change" <?= ($settings['auto_assign_closest'] == '1' || $settings['auto_assign_closest'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Allow Multiple Responders per Emergency SOS') ?></h6>
                                <p><?= t('Enable dispatching multiple units to a single high-priority incident.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="allow_multi_responders" class="track-change" <?= ($settings['allow_multi_responders'] == '1' || $settings['allow_multi_responders'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 4. NOTIFICATIONS & ALERTS (Email Dispatch & Unit SMS Removed!) -->
                <div class="settings-panel" id="panel-notify">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-bell"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Notification & Audio Siren Alerts') ?></h3>
                                <p class="sc-desc"><?= t('Control real-time browser sounds and dispatch emergency notifications.') ?></p>
                            </div>
                        </div>
                        
                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Admin Browser Emergency Siren Sounds') ?></h6>
                                <p><?= t('Play acute siren ringtone locally when new SOS drops.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="notif_sound" class="track-change" <?= ($settings['notif_sound'] == '1' || $settings['notif_sound'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Screen Flash & Urgent Popups') ?></h6>
                                <p><?= t('Flash screen borders and pop up high-priority red alerts for incoming SOS calls.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="notif_screen_flash" class="track-change" <?= ($settings['notif_screen_flash'] == '1' || $settings['notif_screen_flash'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Auto-Center Live Map on New Incident') ?></h6>
                                <p><?= t('Immediately pan and zoom the live dispatch map to location of new SOS.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="notif_high_priority_popup" class="track-change" <?= ($settings['notif_high_priority_popup'] == '1' || $settings['notif_high_priority_popup'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="form-row mt-4">
                            <label class="form-label"><?= t('Siren Sound Repeat Interval') ?></label>
                            <div class="input-group-custom">
                                <i class="fa fa-volume-high input-icon-left"></i>
                                <select name="notif_sound_repeat" class="s-input track-change">
                                    <option value="5" <?= $settings['notif_sound_repeat']=='5'?'selected':'' ?>>Every 5 Seconds (Continuous Siren)</option>
                                    <option value="10" <?= $settings['notif_sound_repeat']=='10'?'selected':'' ?>>Every 10 Seconds</option>
                                    <option value="30" <?= $settings['notif_sound_repeat']=='30'?'selected':'' ?>>Every 30 Seconds</option>
                                    <option value="0" <?= $settings['notif_sound_repeat']=='0'?'selected':'' ?>>Play Once Only</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 5. SYSTEM & SECURITY -->
                <div class="settings-panel" id="panel-system">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-sliders"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Instance Configuration & Maintenance') ?></h3>
                                <p class="sc-desc"><?= t('Server-level parameters, system language, and maintenance modes.') ?></p>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label"><?= t('System Default Language') ?></label>
                            <div class="input-group-custom">
                                <i class="fa fa-language input-icon-left"></i>
                                <select name="language" class="s-input track-change">
                                    <option value="en" <?= $settings['language']=='en'?'selected':'' ?>>English (US)</option>
                                    <option value="so" <?= $settings['language']=='so'?'selected':'' ?>>Somali (Soomaali)</option>
                                    <option value="ar" <?= $settings['language']=='ar'?'selected':'' ?>>Arabic (العربية)</option>
                                    <option value="fr" <?= $settings['language']=='fr'?'selected':'' ?>>French (Français)</option>
                                </select>
                            </div>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('System Maintenance Mode') ?></h6>
                                <p><?= t('Restrict non-admin mobile and web access during system upgrades.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="maintenance_mode" class="track-change" <?= ($settings['maintenance_mode'] == '1' || $settings['maintenance_mode'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="form-row mt-3">
                            <label class="form-label"><?= t('Maintenance Display Notice') ?></label>
                            <textarea name="maintenance_message" class="s-input no-icon track-change" rows="3"><?= htmlspecialchars($settings['maintenance_message']) ?></textarea>
                            <p class="form-hint"><?= t('Displayed to users on the mobile app when maintenance mode is active.') ?></p>
                        </div>

                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Verbose Debug Logging') ?></h6>
                                <p><?= t('Record detailed API diagnostic payloads in system audit logs.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="debug_mode" class="track-change" <?= ($settings['debug_mode'] == '1' || $settings['debug_mode'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 6. BACKUPS & LOGS -->
                <div class="settings-panel" id="panel-backup">
                    <div class="s-card mb-4">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-database"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Data Retention & Integrity') ?></h3>
                                <p class="sc-desc"><?= t('Manage data lifecycle, automated backups, and archival.') ?></p>
                            </div>
                        </div>
                        
                        <div class="setting-toggle-row">
                            <div class="str-info">
                                <h6><?= t('Automated Daily Backup') ?></h6>
                                <p><?= t('Dumps full MySQL database into /backups directory every night.') ?></p>
                            </div>
                            <label class="s-toggle">
                                <input type="checkbox" name="auto_backup" class="track-change" <?= ($settings['auto_backup'] == '1' || $settings['auto_backup'] == 'on') ? 'checked' : '' ?>>
                                <span class="s-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="form-row mt-4">
                            <label class="form-label"><?= t('Data Retention Policy') ?></label>
                            <div class="input-group-custom">
                                <i class="fa fa-trash-clock input-icon-left"></i>
                                <select name="retention_days" class="s-input track-change">
                                    <option value="30" <?= $settings['retention_days']=='30'?'selected':'' ?>>30 Days</option>
                                    <option value="90" <?= $settings['retention_days']=='90'?'selected':'' ?>>90 Days</option>
                                    <option value="365" <?= $settings['retention_days']=='365'?'selected':'' ?>>1 Year</option>
                                    <option value="0" <?= $settings['retention_days']=='0'?'selected':'' ?>>Keep Forever</option>
                                </select>
                            </div>
                            <p class="form-hint"><?= t('Delete old incidents/tracking logs older than specified threshold.') ?></p>
                        </div>

                        <div style="margin-top:20px; padding:20px; background:#f8fafc; border:1px dashed var(--border); border-radius:var(--radius-md); text-align:center;">
                            <i class="fa fa-file-zipper" style="font-size:2.2rem; color:var(--primary); margin-bottom:12px;"></i>
                            <p style="font-size:0.85rem; font-weight:700; color:var(--text-main); margin-bottom:14px;"><?= t('Hot Snapshot: Manual Restore Point') ?></p>
                            <a href="../api/admin/generate_backup.php" class="btn-primary" style="text-decoration:none;"><i class="fa fa-download me-2"></i> <?= t('Download Latest SQL Backup') ?></a>
                        </div>
                    </div>

                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="sc-icon"><i class="fa fa-list"></i></div>
                            <div>
                                <h3 class="sc-title"><?= t('Recent System Activity & Audit Trail') ?></h3>
                                <p class="sc-desc"><?= t('Audit log entries of administrative actions and settings updates.') ?></p>
                            </div>
                        </div>
                        <ul class="activity-list">
                            <?php if (empty($logs)): ?>
                                <li style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.85rem;"><?= t('No recent activities logged.') ?></li>
                            <?php endif; ?>
                            <?php foreach($logs as $log): 
                                $icon = 'fa-check';
                                if($log['type'] === 'warning') $icon = 'fa-triangle-exclamation';
                                if($log['type'] === 'danger') $icon = 'fa-bolt';
                                if($log['type'] === 'safe') $icon = 'fa-shield-halved';
                                if($log['action'] === 'Login') $icon = 'fa-key';
                            ?>
                            <li class="activity-item">
                                <div class="act-icon <?= $log['type'] ?>"><i class="fa <?= $icon ?>"></i></div>
                                <div class="act-details">
                                    <h6><?= htmlspecialchars($log['action']) ?></h6>
                                    <p><?= htmlspecialchars($log['details']) ?> <?= $log['fullname'] ? '<br><small>by '.htmlspecialchars($log['fullname']).'</small>' : '' ?></p>
                                </div>
                                <div class="act-time"><?php 
                                    $time = strtotime($log['created_at']);
                                    $diff = time() - $time;
                                    if ($diff < 60) echo "Just now";
                                    elseif ($diff < 3600) echo round($diff/60)." min ago";
                                    elseif ($diff < 86400) echo round($diff/3600)." hrs ago";
                                    else echo date('M j', $time);
                                ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </form>
        </div>
    </div>
</main>

<!-- STICKY SAVE BAR -->
<div class="sticky-save-bar" id="saveBar">
    <div class="ssb-info">
        <div class="ssb-dot"></div>
        <div class="ssb-text">
            <h5><?= t('Unsaved Changes Detected') ?></h5>
            <p><?= t('You have modified system parameters. Save to apply changes broadly.') ?></p>
        </div>
    </div>
    <div class="ssb-actions">
        <button class="btn-outline" onclick="discardChanges()"><?= t('Discard') ?></button>
        <button class="btn-primary" id="saveBtn" onclick="saveSettings()">
            <i class="fa fa-cloud-arrow-up"></i> <?= t('Apply Settings') ?>
        </button>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="tc"></div>

<script>
// ── TAB SWITCHING LOGIC ──
const navItems = document.querySelectorAll('.s-nav-item');
const panels = document.querySelectorAll('.settings-panel');

navItems.forEach(item => {
    item.addEventListener('click', () => {
        navItems.forEach(nav => nav.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));
        
        item.classList.add('active');
        const target = document.getElementById(item.dataset.target);
        if(target) target.classList.add('active');
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});

// ── UNSAVED CHANGES TRACKER ──
const inputs = document.querySelectorAll('.track-change');
const saveBar = document.getElementById('saveBar');
let hasChanges = false;

inputs.forEach(input => {
    input.addEventListener('change', () => {
        if(!hasChanges) {
            hasChanges = true;
            saveBar.classList.add('visible');
        }
    });
    input.addEventListener('input', () => {
        if(!hasChanges) {
            hasChanges = true;
            saveBar.classList.add('visible');
        }
    });
});

function discardChanges() {
    document.getElementById('settingsForm').reset();
    hasChanges = false;
    saveBar.classList.remove('visible');
    showCustomToast('Discarded', 'All unsaved modifications were reverted.', 'info');
}

// ── AJAX SAVE LOGIC ──
function saveSettings() {
    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Saving...';
    btn.style.pointerEvents = 'none';

    const fd = new FormData(document.getElementById('settingsForm'));
    fd.append('ajax', '1');

    fetch('settings.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                hasChanges = false;
                saveBar.classList.remove('visible');
                showCustomToast('System Secured', data.message, 'success');
                if (data.reload) {
                    setTimeout(() => { window.location.reload(); }, 1200);
                }
            } else {
                showCustomToast('Configuration Error', data.message || 'Operation failed', 'warning');
            }
        })
        .catch(err => {
            console.error(err);
            showCustomToast('Connection Failed', 'Server error or timeout. Please check logs.', 'warning');
        })
        .finally(() => {
            btn.innerHTML = '<i class="fa fa-cloud-arrow-up"></i> <?= t('Apply Settings') ?>';
            btn.style.pointerEvents = 'auto';
        });
}

// ── TOAST NOTIFICATION ──
function showCustomToast(title, desc, type) {
    const container = document.getElementById('tc');
    const toast = document.createElement('div');
    
    let icon = 'fa-info-circle', color = 'var(--primary)';
    if(type==='success') { icon = 'fa-check-circle'; color = 'var(--success)'; }
    if(type==='warning') { icon = 'fa-triangle-exclamation'; color = 'var(--warning)'; }

    toast.className = 'toast-msg';
    toast.style.borderLeftColor = color;
    toast.innerHTML = `
        <div style="font-size:1.4rem; color:${color};"><i class="fa ${icon}"></i></div>
        <div>
            <h6>${title}</h6>
            <p>${desc}</p>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}
</script>
</body>
</html>
