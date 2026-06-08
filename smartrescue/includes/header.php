<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user preferences if DB connection exists and session is active
$dark_mode = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $u_res = mysqli_query($conn, "SELECT dark_mode FROM users WHERE id = '$uid'");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $u_data = mysqli_fetch_assoc($u_res);
        $dark_mode = $u_data['dark_mode'];
    }
}

$theme = $dark_mode ? 'dark' : 'light';
$page_title = $page_title ?? 'SmartRescue';
$sys_lang = get_setting($conn, 'language', 'en');
?>
<!DOCTYPE html>
<html lang="<?= $sys_lang ?>" data-theme="<?= $theme; ?>" <?= $sys_lang == 'ar' ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Leaflet default CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>

<!-- Standalone Top Navigation -->
<?php
// Sync profile_image from DB into session every page load
if (isset($conn) && isset($_SESSION['user_id'])) {
    $_h_uid = $_SESSION['user_id'];
    $_h_res = mysqli_query($conn, "SELECT profile_image FROM users WHERE id='$_h_uid' LIMIT 1");
    if ($_h_res && $_h_row = mysqli_fetch_assoc($_h_res)) {
        $_SESSION['profile_image'] = $_h_row['profile_image'] ?? '';
    }
}
$_nav_img  = $_SESSION['profile_image'] ?? '';
$_nav_init = strtoupper(substr($_SESSION['fullname'] ?? 'U', 0, 1));
?>
<nav class="topnav">
    <a href="../user/index.php" class="topnav-brand" style="text-decoration:none;">
        <div class="brand-icon"><i class="fa-solid fa-truck-medical"></i></div>
        <span>SmartRescue</span>
    </a>
    <div style="display:flex; gap:12px; align-items:center;">
        <button onclick="SmartRescue.toggleTheme()" class="btn-primary" style="background:transparent; color:var(--text); box-shadow:none; border:1px solid var(--border); border-radius:12px; cursor:pointer;"><i class="fa-solid fa-moon"></i></button>
        <button onclick="SmartRescue.goBack()" class="btn-primary" style="background:var(--surface-solid); color:var(--text); border:1px solid var(--border); box-shadow:var(--shadow-sm);"><i class="fa fa-arrow-left" style="margin-right:6px;"></i> Back</button>
        <a href="../user/profile.php" id="nav-user-avatar" title="My Profile" style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--primary,#3b82f6),#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1rem;overflow:hidden;text-decoration:none;box-shadow:0 4px 12px rgba(59,130,246,0.3);flex-shrink:0;">
            <?php if ($_nav_img): ?>
                <img src="../<?= htmlspecialchars($_nav_img) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= $_nav_init ?>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- Main Container -->
<main style="padding: 32px 24px; max-width: 1000px; margin: 0 auto; width: 100%;">
