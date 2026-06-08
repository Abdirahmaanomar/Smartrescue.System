<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
  header("Location: ../auth/login.php");
  exit();
}
require_once '../includes/session_guard.php';
$driver_id = $_SESSION['user_id'];
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE id='$driver_id'");
$user = mysqli_fetch_assoc($user_res);
$unit_res = mysqli_query($conn, "SELECT * FROM emergency_units WHERE driver_id='$driver_id' LIMIT 1");
$unit = mysqli_fetch_assoc($unit_res);
$unit_id = $unit ? $unit['id'] : 0;
$job_res = mysqli_query($conn, "SELECT r.*,u.fullname as pname,u.phone as pphone FROM rescue_requests r JOIN users u ON r.user_id=u.id WHERE r.assigned_unit_id='$unit_id' AND r.status IN('pending','accepted','en_route','arrived') LIMIT 1");
$job = mysqli_fetch_assoc($job_res);
$hist_res = mysqli_query($conn, "SELECT r.*,u.fullname as pname FROM rescue_requests r JOIN users u ON r.user_id=u.id WHERE r.assigned_unit_id='$unit_id' ORDER BY r.created_at DESC");
$saves = 0;
$missions = [];
while ($r = mysqli_fetch_assoc($hist_res)) {
  if ($r['status'] === 'completed')
    $saves++;
  $missions[] = $r;
}
$initials = strtoupper(substr($user['fullname'], 0, 1));

$time_format_24h = $user['time_format_24h'] ?? 1;
$notifications_enabled = $user['notifications_enabled'] ?? 1;
$sound_alerts = $user['sound_alerts'] ?? 1;
$emergency_updates = $user['emergency_updates'] ?? 1;
$location_sharing = $user['location_sharing'] ?? 1;
$auto_gps_tracking = $user['auto_gps_tracking'] ?? 1;
$session_timeout = $user['session_timeout'] ?? '30';
$app_language = $user['language'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $user['dark_mode'] ? 'dark' : 'light' ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Driver Command Center | SmartRescue</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    :root {
      --bg: #f1f5f9;
      --card: #fff;
      --text: #0f172a;
      --sub: #64748b;
      --border: #e2e8f0;
      --p: #1e40af;
      --pa: #3b82f6;
      --danger: #ef4444;
      --success: #10b981;
      --warn: #f59e0b;
      --sidebar: 240px;
      --nav: 64px
    }

    [data-theme=dark] {
      --bg: #0f172a;
      --card: #1e293b;
      --text: #f1f5f9;
      --sub: #94a3b8;
      --border: #334155
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column
    }

    /* NAV */
    .topnav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: var(--nav);
      background: var(--card);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 20px;
      gap: 16px;
      z-index: 1000;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .06)
    }

    .brand {
      font-weight: 800;
      font-size: 1.2rem;
      color: var(--text)
    }

    .brand span {
      color: var(--pa)
    }

    .nav-spacer {
      flex: 1
    }

    .gps-status {
      font-size: .8rem;
      font-weight: 700;
      color: var(--success);
      display: flex;
      align-items: center;
      gap: 6px
    }

    .bell-btn {
      position: relative;
      background: none;
      border: none;
      color: var(--text);
      font-size: 1.2rem;
      cursor: pointer;
      padding: 8px
    }

    .bell-badge {
      position: absolute;
      top: 4px;
      right: 4px;
      background: var(--danger);
      color: #fff;
      font-size: .6rem;
      font-weight: 800;
      border-radius: 50%;
      width: 16px;
      height: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      display: none
    }

    .avatar-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--p), var(--pa));
      color: #fff;
      font-weight: 800;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden
    }

    .status-toggle {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: .8rem;
      font-weight: 700
    }

    .tog {
      position: relative;
      width: 44px;
      height: 24px
    }

    .tog input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .tog-slider {
      position: absolute;
      inset: 0;
      background: #e2e8f0;
      border-radius: 12px;
      cursor: pointer;
      transition: .3s
    }

    .tog-slider:before {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      left: 3px;
      bottom: 3px;
      background: #fff;
      border-radius: 50%;
      transition: .3s;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1)
    }

    .tog input:checked+.tog-slider {
      background: var(--success)
    }

    .tog input:checked+.tog-slider:before {
      transform: translateX(20px)
    }

    /* LAYOUT */
    .layout {
      display: flex;
      margin-top: var(--nav);
      min-height: calc(100vh - var(--nav))
    }

    /* SIDEBAR */
    .sidebar {
      width: var(--sidebar);
      background: var(--card);
      border-right: 1px solid var(--border);
      position: fixed;
      top: var(--nav);
      bottom: 0;
      overflow-y: auto;
      padding: 24px 0;
      display: flex;
      flex-direction: column;
      gap: 4px;
      z-index: 900
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 13px 24px;
      font-weight: 600;
      font-size: .9rem;
      color: var(--sub);
      cursor: pointer;
      border-radius: 0;
      transition: .2s;
      border-left: 3px solid transparent;
      text-decoration: none
    }

    .nav-item i {
      width: 18px;
      text-align: center;
      font-size: 1rem
    }

    .nav-item:hover {
      color: var(--pa);
      background: rgba(59, 130, 246, .06)
    }

    .nav-item.active {
      color: var(--pa);
      background: rgba(59, 130, 246, .1);
      border-left-color: var(--pa)
    }

    .sidebar-footer {
      margin-top: auto;
      padding: 16px 24px;
      border-top: 1px solid var(--border)
    }

    /* MAIN */
    .main {
      margin-left: var(--sidebar);
      flex: 1;
      padding: 32px;
      overflow-y: auto
    }

    .page {
      display: none
    }

    .page.active {
      display: block;
      animation: fadeUp .4s ease
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(12px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    /* CARDS */
    .card {
      background: var(--card);
      border-radius: 16px;
      padding: 24px;
      border: 1px solid var(--border);
      box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
      transition: .3s
    }

    .card:hover {
      box-shadow: 0 8px 32px rgba(0, 0, 0, .06)
    }

    .card-sm {
      padding: 20px
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px
    }

    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px
    }

    .kpi {
      position: relative;
      overflow: hidden
    }

    .kpi-icon {
      position: absolute;
      right: -10px;
      bottom: -10px;
      font-size: 4rem;
      opacity: .06
    }

    .kpi h6 {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: var(--sub);
      margin-bottom: 10px
    }

    .kpi h2 {
      font-size: 2.2rem;
      font-weight: 900;
      line-height: 1
    }

    .kpi .trend {
      font-size: .8rem;
      font-weight: 600;
      color: var(--success);
      margin-top: 8px
    }

    /* STATUS BADGE */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 50px;
      font-size: .72rem;
      font-weight: 800;
      text-transform: uppercase
    }

    .b-pending {
      background: rgba(245, 158, 11, .12);
      color: var(--warn)
    }

    .b-accepted {
      background: rgba(59, 130, 246, .12);
      color: var(--pa)
    }

    .b-en_route {
      background: rgba(16, 185, 129, .12);
      color: var(--success)
    }

    .b-arrived {
      background: rgba(30, 64, 175, .12);
      color: var(--p)
    }

    .b-completed {
      background: rgba(16, 185, 129, .12);
      color: var(--success)
    }

    .b-rejected {
      background: rgba(239, 68, 68, .12);
      color: var(--danger)
    }

    /* MISSION HERO */
    .mission-hero {
      background: linear-gradient(135deg, #0f172a 0%, #1e40af 60%, #3b82f6 100%);
      border-radius: 24px;
      padding: 36px;
      color: #fff;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden
    }

    .mission-hero::before {
      content: '';
      position: absolute;
      top: -60px;
      right: -60px;
      width: 220px;
      height: 220px;
      background: rgba(255, 255, 255, .05);
      border-radius: 50%
    }

    .mission-title {
      font-size: 1.6rem;
      font-weight: 900;
      margin-bottom: 20px
    }

    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      background: rgba(255, 255, 255, .08);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 20px
    }

    .info-item label {
      font-size: .72rem;
      opacity: .6;
      text-transform: uppercase;
      font-weight: 700;
      display: block;
      margin-bottom: 4px
    }

    .info-item span {
      font-weight: 800;
      font-size: .95rem
    }

    .eta-bar {
      background: rgba(255, 255, 255, .1);
      border-radius: 12px;
      padding: 14px 20px;
      display: flex;
      gap: 24px;
      margin-bottom: 20px
    }

    .eta-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      font-size: .9rem
    }

    .action-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap
    }

    /* BUTTONS */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 700;
      font-size: .88rem;
      border: none;
      cursor: pointer;
      transition: .2s;
      text-decoration: none
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, .08)
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--p), var(--pa));
      color: #fff;
      box-shadow: 0 4px 16px rgba(30, 64, 175, .3)
    }

    .btn-success {
      background: linear-gradient(135deg, #059669, var(--success));
      color: #fff;
      box-shadow: 0 4px 16px rgba(16, 185, 129, .3)
    }

    .btn-danger {
      background: linear-gradient(135deg, #dc2626, var(--danger));
      color: #fff;
      box-shadow: 0 4px 16px rgba(239, 68, 68, .3)
    }

    .btn-warn {
      background: linear-gradient(135deg, #d97706, var(--warn));
      color: #fff
    }

    .btn-light {
      background: rgba(255, 255, 255, .9);
      color: var(--p);
      border: 1px solid rgba(255, 255, 255, .3)
    }

    .btn-outline {
      background: none;
      border: 2px solid var(--border);
      color: var(--text)
    }

    .btn-sm {
      padding: 8px 16px;
      font-size: .8rem;
      border-radius: 8px
    }

    .btn-full {
      width: 100%;
      justify-content: center
    }

    /* STANDBY */
    .standby-card {
      text-align: center;
      padding: 60px 40px
    }

    .standby-icon {
      font-size: 4rem;
      color: var(--pa);
      opacity: .4;
      animation: pulse 2s infinite
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: .4
      }

      50% {
        opacity: .8
      }
    }

    /* PROFILE */
    .profile-card {
      display: flex;
      align-items: center;
      gap: 20px;
      background: var(--card);
      border-radius: 20px;
      padding: 24px;
      border: 1px solid var(--border);
      margin-bottom: 24px
    }

    .big-avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--p), var(--pa));
      color: #fff;
      font-weight: 900;
      font-size: 1.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden
    }

    .profile-info h3 {
      font-weight: 800;
      font-size: 1.2rem;
      margin-bottom: 4px
    }

    .profile-info p {
      color: var(--sub);
      font-size: .85rem;
      margin-bottom: 8px
    }

    /* MAP */
    #map {
      height: calc(100vh - 140px);
      border-radius: 20px;
      border: 1px solid var(--border)
    }

    .map-overlay {
      position: absolute;
      top: 16px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--card);
      border-radius: 50px;
      padding: 10px 24px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
      display: flex;
      gap: 20px;
      z-index: 999;
      font-weight: 700;
      font-size: .85rem;
      border: 1px solid var(--border)
    }

    .map-wrap {
      position: relative
    }

    .follow-btn {
      position: absolute;
      bottom: 20px;
      left: 20px;
      z-index: 999;
      background: var(--card);
      border: 2px solid var(--border);
      border-radius: 50px;
      padding: 10px 20px;
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: .82rem;
      color: var(--text);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, .12);
      transition: .2s
    }

    .follow-btn.active {
      background: linear-gradient(135deg, var(--p), var(--pa));
      color: #fff;
      border-color: transparent;
      box-shadow: 0 4px 20px rgba(30, 64, 175, .4)
    }

    .follow-btn:hover {
      transform: translateY(-2px)
    }

    /* HISTORY */
    .mission-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 16px
    }

    .mcard {
      background: var(--card);
      border-radius: 16px;
      padding: 20px;
      border: 1px solid var(--border);
      transition: .2s
    }

    .mcard:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, .08)
    }

    .mcard-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px
    }

    .mcard h4 {
      font-weight: 800;
      font-size: .95rem
    }

    .mcard .meta {
      font-size: .8rem;
      color: var(--sub);
      margin-top: 4px
    }

    .mcard .type-tag {
      background: rgba(59, 130, 246, .1);
      color: var(--pa);
      padding: 3px 10px;
      border-radius: 50px;
      font-size: .72rem;
      font-weight: 800
    }

    /* SETTINGS */
    .settings-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px
    }

    .form-group {
      margin-bottom: 18px
    }

    .form-group label {
      display: block;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-bottom: 8px;
      color: var(--sub)
    }

    .form-control {
      width: 100%;
      padding: 12px 16px;
      border-radius: 12px;
      border: 2px solid var(--border);
      background: var(--bg);
      color: var(--text);
      font-family: 'Outfit', sans-serif;
      font-size: .9rem;
      font-weight: 600;
      transition: .2s
    }

    .form-control:focus {
      outline: none;
      border-color: var(--pa);
      background: var(--card)
    }

    /* SOS POPUP */
    .sos-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .5);
      z-index: 9000;
      display: none;
      align-items: flex-end;
      justify-content: flex-end;
      padding: 24px
    }

    .sos-overlay.show {
      display: flex
    }

    .sos-popup {
      background: var(--card);
      border-radius: 24px;
      padding: 28px;
      max-width: 380px;
      width: 100%;
      box-shadow: 0 24px 60px rgba(0, 0, 0, .3);
      border: 2px solid var(--danger);
      animation: slideUp .4s ease
    }

    @keyframes slideUp {
      from {
        transform: translateY(60px);
        opacity: 0
      }

      to {
        transform: translateY(0);
        opacity: 1
      }
    }

    .sos-pulse {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(239, 68, 68, .1);
      color: var(--danger);
      border-radius: 50px;
      padding: 6px 14px;
      font-size: .8rem;
      font-weight: 800;
      margin-bottom: 16px;
      animation: blink 1s infinite
    }

    @keyframes blink {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .5
      }
    }

    .sos-popup h3 {
      font-weight: 900;
      font-size: 1.2rem;
      margin-bottom: 16px
    }

    .sos-info {
      background: var(--bg);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 16px
    }

    .sos-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 0;
      font-size: .88rem;
      font-weight: 600;
      border-bottom: 1px solid var(--border)
    }

    .sos-row:last-child {
      border: none
    }

    .sos-row i {
      width: 18px;
      color: var(--pa)
    }

    .sos-actions {
      display: flex;
      gap: 10px
    }

    /* NOTIF DROPDOWN */
    .notif-wrap {
      position: relative
    }

    .notif-dropdown {
      position: absolute;
      top: calc(100% + 8px);
      right: 0;
      width: 320px;
      background: var(--card);
      border-radius: 16px;
      border: 1px solid var(--border);
      box-shadow: 0 12px 40px rgba(0, 0, 0, .15);
      z-index: 2000;
      display: none
    }

    .notif-dropdown.open {
      display: block
    }

    .notif-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      font-weight: 800;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .notif-item {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border);
      cursor: pointer;
      transition: .2s
    }

    .notif-item:hover {
      background: var(--bg)
    }

    .notif-item:last-child {
      border: none
    }

    .notif-item h6 {
      font-weight: 700;
      font-size: .85rem;
      margin-bottom: 2px
    }

    .notif-item p {
      font-size: .78rem;
      color: var(--sub)
    }

    .notif-empty {
      padding: 24px;
      text-align: center;
      color: var(--sub);
      font-size: .85rem
    }

    .section-title {
      font-size: 1.4rem;
      font-weight: 900;
      margin-bottom: 6px
    }

    .section-sub {
      color: var(--sub);
      font-size: .9rem;
      margin-bottom: 24px
    }

    /* ROUTE UI */
    .map-page-layout {
      display: grid;
      grid-template-columns: 1fr 280px;
      gap: 20px;
      align-items: start
    }

    .map-main {
      min-width: 0
    }

    .route-panel {
      background: var(--card);
      border-radius: 20px;
      padding: 20px;
      border: 1px solid var(--border);
      position: sticky;
      top: calc(var(--nav) + 16px)
    }

    .route-panel-header {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 800;
      font-size: .95rem;
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border)
    }

    .route-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-left: auto;
      flex-shrink: 0
    }

    .route-dot-idle {
      background: var(--border)
    }

    .route-dot-loading {
      background: var(--warn);
      animation: blink 1s infinite
    }

    .route-dot-live {
      background: var(--success);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, .2)
    }

    .route-dot-err {
      background: var(--danger)
    }

    .route-stats {
      display: flex;
      flex-direction: column;
      gap: 12px
    }

    .route-stat-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: var(--bg);
      border-radius: 12px
    }

    .route-stat-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0
    }

    .route-stat-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px;
      color: var(--sub);
      margin-bottom: 2px
    }

    .route-stat-val {
      font-weight: 900;
      font-size: 1rem;
      color: var(--text)
    }

    /* Route line animation */
    @keyframes routeDash {
      to {
        stroke-dashoffset: -40
      }
    }

    .leaflet-overlay-pane path.route-animated {
      stroke-dasharray: 12 8;
      animation: routeDash 1.2s linear infinite
    }

    /* TURN-BY-TURN UI */
    .route-steps-wrap {
      margin-top: 16px;
      max-height: 220px;
      overflow-y: auto;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: var(--bg);
      padding: 8px
    }

    .route-step-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px;
      border-bottom: 1px solid rgba(0, 0, 0, .05)
    }

    .route-step-item:last-child {
      border-bottom: none
    }

    .step-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: rgba(59, 130, 246, .15);
      color: var(--pa);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: .85rem
    }

    .step-text {
      font-size: .82rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 2px
    }

    .step-dist {
      font-size: .7rem;
      color: var(--sub);
      font-weight: 700
    }

    /* MAP LAYER CONTROLS */
    .map-layer-controls {
      position: absolute;
      top: 16px;
      right: 16px;
      z-index: 800;
      display: flex;
      background: var(--card);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      border: 1px solid var(--border);
    }

    .layer-btn {
      background: transparent;
      border: none;
      padding: 8px 12px;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--sub);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .layer-btn:not(:last-child) {
      border-right: 1px solid var(--border);
    }

    .layer-btn:hover {
      background: rgba(59, 130, 246, 0.05);
      color: var(--text);
    }

    .layer-btn.active {
      background: var(--p);
      color: #fff;
    }

    /* RESPONSIVE */
    @media(max-width:1024px) {
      .map-page-layout {
        grid-template-columns: 1fr
      }
    }

    @media(max-width:768px) {
      .sidebar {
        width: 60px;
        padding: 16px 0
      }

      .nav-item span,
      .sidebar-footer {
        display: none
      }

      .nav-item {
        justify-content: center;
        padding: 14px
      }

      .main {
        margin-left: 60px;
        padding: 20px
      }

      .grid-3,
      .grid-2 {
        grid-template-columns: 1fr
      }

      .settings-grid {
        grid-template-columns: 1fr
      }

      .info-grid {
        grid-template-columns: 1fr
      }

      .action-row {
        flex-direction: column
      }

      .sos-overlay {
        align-items: center;
        justify-content: center;
        padding: 16px
      }
    }

    /* DARK FIXES */
    [data-theme=dark] .topnav,
    [data-theme=dark] .sidebar {
      border-color: var(--border)
    }

    [data-theme=dark] .form-control {
      background: var(--bg);
      border-color: var(--border)
    }

    [data-theme=dark] .form-control:focus {
      background: var(--card)
    }
  </style>
</head>

<body>

  <!-- TOP NAV -->
  <nav class="topnav">
    <div
      style="background:linear-gradient(135deg,var(--p),var(--pa));width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0">
      <i class="fa fa-truck-medical"></i>
    </div>
    <div class="brand">Smart<span>Rescue</span> <span style="font-weight:300;opacity:.4;font-size:.9rem">DRIVER</span>
    </div>
    <div class="nav-spacer"></div>
    <div class="gps-status d-none d-md-flex" id="gps-status"><i class="fa fa-circle-notch fa-spin"></i> Acquiring GPS…
    </div>
    <div class="status-toggle">
      <span id="status-label"
        style="color:<?= ($unit && $unit['status'] === 'available') ? 'var(--success)' : 'var(--sub)' ?>"><?= ($unit && $unit['status'] === 'available') ? 'Online' : 'Offline' ?></span>
      <label class="tog"><input type="checkbox" id="unitToggle" <?= ($unit && $unit['status'] === 'available') ? 'checked' : '' ?> onchange="toggleUnit('unitToggle')"><span class="tog-slider"></span></label>
    </div>
    <div class="notif-wrap">
      <button class="bell-btn" onclick="toggleNotif()"><i class="fa fa-bell"></i><span class="bell-badge"
          id="bell-badge">0</span></button>
      <div class="notif-dropdown" id="notif-dropdown">
        <div class="notif-header"><span>Notifications</span><button onclick="clearNotifs()"
            style="background:none;border:none;font-size:.75rem;color:var(--sub);cursor:pointer;font-weight:600">Clear
            all</button></div>
        <div id="notif-list">
          <div class="notif-empty">No notifications</div>
        </div>
      </div>
    </div>
    <button class="avatar-btn" id="topbar-avatar-btn" title="<?= htmlspecialchars($user['fullname']) ?>"
      onclick="showPage('profile', null)">
      <?php if (!empty($user['profile_image'])): ?>
        <img src="../<?= htmlspecialchars($user['profile_image']) ?>"
          style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="avatar"
          onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=1e40af&color=fff'">
      <?php else: ?>
        <?= $initials ?>
      <?php endif; ?>
    </button>
    <button class="btn btn-outline btn-sm" onclick="toggleDark()"><i class="fa fa-moon"></i></button>
    <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
  </nav>

  <div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="nav-item active" onclick="showPage('dashboard',this)"><i
          class="fa fa-gauge-high"></i><span>Dashboard</span></div>
      <div class="nav-item" onclick="showPage('map',this)"><i class="fa fa-map-location-dot"></i><span>Live Map</span>
      </div>
      <div class="nav-item" onclick="showPage('history',this)"><i
          class="fa fa-clock-rotate-left"></i><span>History</span></div>
      <div class="nav-item" id="nav-settings" onclick="showPage('settings',this)"><i
          class="fa fa-gear"></i><span>Settings</span></div>
      <div class="sidebar-footer">
        <div style="font-size:.78rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px">Unit
        </div>
        <div style="font-weight:800;font-size:.9rem;margin-top:4px">
          <?= $unit ? htmlspecialchars($unit['unit_name']) : 'Unassigned' ?>
        </div>
        <div style="font-size:.78rem;color:var(--sub);margin-top:2px">
          <?= $unit ? htmlspecialchars($unit['plate_number']) : '' ?>
        </div>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">

      <!-- DASHBOARD PAGE -->
      <div class="page active" id="page-dashboard">
        <div class="profile-card">
          <div class="big-avatar" id="dash-big-avatar">
            <?php if (!empty($user['profile_image'])): ?>
              <img src="../<?= htmlspecialchars($user['profile_image']) ?>"
                style="width:100%;height:100%;object-fit:cover;" alt="avatar"
                onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=1e40af&color=fff'">
            <?php else: ?>
              <?= $initials ?>
            <?php endif; ?>
          </div>
          <div class="profile-info">
            <h3><?= htmlspecialchars($user['fullname']) ?></h3>
            <p><i class="fa fa-envelope"
                style="margin-right:6px;color:var(--pa)"></i><?= htmlspecialchars($user['email']) ?> &nbsp;·&nbsp; <i
                class="fa fa-phone"
                style="margin-right:4px;color:var(--pa)"></i><?= htmlspecialchars($user['phone'] ?? '') ?></p>
            <span class="badge <?= ($unit && $unit['status'] === 'available') ? 'b-en_route' : 'b-rejected' ?>"><i
                class="fa fa-circle"
                style="font-size:.5rem"></i><?= ($unit && $unit['status'] === 'available') ? 'Online — Ready' : 'Offline' ?></span>
          </div>
          <div style="margin-left:auto;text-align:right">
            <?php if ($unit): ?>
              <div style="font-size:.8rem;color:var(--sub);font-weight:700">UNIT</div>
              <div style="font-weight:900;font-size:1rem"><?= htmlspecialchars($unit['unit_name']) ?></div>
              <div style="font-size:.78rem;color:var(--sub)"><?= htmlspecialchars($unit['plate_number']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- KPI CARDS -->
        <div class="grid-3" style="margin-bottom:24px">
          <div class="card kpi card-sm"><i class="fa fa-heart-pulse kpi-icon"></i>
            <h6>Lives Saved</h6>
            <h2><?= $saves ?></h2>
            <div class="trend"><i class="fa fa-arrow-up"></i> Expert Responder</div>
          </div>
          <div class="card kpi card-sm"><i class="fa fa-list-check kpi-icon"></i>
            <h6>Total Missions</h6>
            <h2><?= count($missions) ?></h2>
            <div class="trend" style="color:var(--pa)">All time</div>
          </div>
          <div class="card kpi card-sm"><i class="fa fa-ambulance kpi-icon"></i>
            <h6>Unit Status</h6>
            <h2 style="font-size:1.3rem;margin-top:4px"><?= $unit ? htmlspecialchars($unit['status']) : 'N/A' ?></h2>
            <div class="trend" style="color:var(--sub)">
              <?= $unit ? htmlspecialchars($unit['unit_type'] ?? '') : 'Unassigned' ?>
            </div>
          </div>
        </div>

        <!-- ACTIVE MISSION -->
        <?php if ($job): ?>
          <?php $pc = ['pending' => 'b-pending', 'accepted' => 'b-accepted', 'en_route' => 'b-en_route', 'arrived' => 'b-arrived', 'completed' => 'b-completed'][$job['status']] ?? 'b-pending'; ?>
          <div class="mission-hero">
            <div
              style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px">
              <div class="mission-title"><i class="fa fa-siren" style="margin-right:10px"></i>Active Mission</div>
              <span class="badge <?= $pc ?>"><?= strtoupper(str_replace('_', ' ', $job['status'])) ?></span>
            </div>
            <div class="info-grid">
              <div class="info-item"><label>Patient</label><span><?= htmlspecialchars($job['pname']) ?></span></div>
              <div class="info-item"><label>Emergency</label><span><?= htmlspecialchars($job['emergency_type']) ?></span>
              </div>
              <div class="info-item"><label>Contact</label><span
                  style="color:#fbbf24"><?= htmlspecialchars($job['pphone']) ?></span></div>
              <div class="info-item">
                <label>Priority</label><span><?= htmlspecialchars($job['priority'] ?? 'Standard') ?></span>
              </div>
              <?php if (!empty($job['description'])): ?>
                <div class="info-item" style="grid-column:1/-1">
                  <label>Description</label><span><?= htmlspecialchars($job['description']) ?></span>
                </div><?php endif; ?>
            </div>
            <div class="eta-bar">
              <div class="eta-item"><i class="fa fa-route" style="color:#60a5fa"></i><span
                  id="dash-dist">Calculating…</span></div>
              <div class="eta-item"><i class="fa fa-clock" style="color:#fbbf24"></i><span id="dash-eta">ETA: …</span>
              </div>
            </div>
            <div class="action-row" id="action-row">
              <a href="tel:<?= $job['pphone'] ?>" class="btn btn-light"><i class="fa fa-phone-volume"></i>Call Patient</a>
              <?php if ($job['status'] === 'pending'): ?>
                <button onclick="updateMission('reject')" class="btn btn-danger"><i class="fa fa-xmark"></i>Reject</button>
                <button onclick="updateMission('accept')" class="btn btn-success"><i class="fa fa-check"></i>Accept
                  Mission</button>
              <?php elseif ($job['status'] === 'accepted'): ?>
                <button onclick="updateMission('en_route')" class="btn btn-warn"><i class="fa fa-truck-fast"></i>Start
                  Trip</button>
              <?php elseif ($job['status'] === 'en_route'): ?>
                <button onclick="updateMission('arrived')" class="btn btn-primary"><i class="fa fa-location-dot"></i>Mark
                  Arrived</button>
              <?php elseif ($job['status'] === 'arrived'): ?>
                <button onclick="updateMission('complete')" class="btn btn-success"><i
                    class="fa fa-circle-check"></i>Complete Mission</button>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="card standby-card">
            <div class="standby-icon"><i class="fa fa-tower-broadcast"></i></div>
            <h2 style="font-weight:900;margin-top:20px;margin-bottom:8px">On Standby</h2>
            <p style="color:var(--sub)">Waiting for dispatch. New assignments will appear here and trigger an alert.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- MAP PAGE -->
      <div class="page" id="page-map">
        <div class="map-page-layout">
          <div class="map-main">
            <div class="section-title"><i class="fa fa-map-location-dot"
                style="color:var(--pa);margin-right:10px"></i>Live Map</div>
            <div class="section-sub">Real-time OSRM road routing — auto-updates every 12 seconds</div>
            <div class="map-wrap">
              <div class="map-overlay" id="map-overlay" style="display:none">
                <span id="mo-dist"><i class="fa fa-route" style="color:var(--pa);margin-right:6px"></i>--</span>
                <span id="mo-eta"><i class="fa fa-clock" style="color:var(--warn);margin-right:6px"></i>ETA --</span>
                <span id="mo-type" style="font-size:.72rem;color:var(--sub)"></span>
              </div>
              <div id="map"></div>
              <!-- Map Layer Controls -->
              <div class="map-layer-controls">
                <button class="layer-btn active" id="btn-layer-std" onclick="setMapLayer('std')"><i
                    class="fa fa-map"></i> Map</button>
                <button class="layer-btn" id="btn-layer-sat" onclick="setMapLayer('sat')"><i
                    class="fa fa-satellite"></i> Satellite</button>
                <button class="layer-btn" id="btn-layer-3d" onclick="setMapLayer('3d')"><i
                    class="fa fa-mountain"></i> 3D</button>
              </div>
              <button class="follow-btn active" id="follow-btn" onclick="toggleFollow()" title="Toggle map follow mode">
                <i class="fa fa-location-crosshairs"></i><span id="follow-label">Following You</span>
              </button>
            </div>
          </div>
          <div class="route-panel" id="route-panel">
            <div class="route-panel-header">
              <i class="fa fa-route" style="color:var(--pa)"></i>
              <span>Route Info</span>
              <span id="route-status-dot" class="route-dot route-dot-idle"></span>
            </div>
            <div id="route-stats" class="route-stats">
              <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(59,130,246,.1);color:var(--pa)"><i
                    class="fa fa-road"></i></div>
                <div>
                  <div class="route-stat-label">Road Distance</div>
                  <div class="route-stat-val" id="rp-dist">—</div>
                </div>
              </div>
              <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(245,158,11,.1);color:var(--warn)"><i
                    class="fa fa-clock"></i></div>
                <div>
                  <div class="route-stat-label">Estimated Time</div>
                  <div class="route-stat-val" id="rp-eta">—</div>
                </div>
              </div>
              <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(16,185,129,.1);color:var(--success)"><i
                    class="fa fa-gauge-high"></i></div>
                <div>
                  <div class="route-stat-label">Avg Speed</div>
                  <div class="route-stat-val" id="rp-speed">40 km/h</div>
                </div>
              </div>
              <div class="route-stat-item">
                <div class="route-stat-icon" style="background:rgba(239,68,68,.1);color:var(--danger)"><i
                    class="fa fa-location-dot"></i></div>
                <div>
                  <div class="route-stat-label">Last Updated</div>
                  <div class="route-stat-val" id="rp-updated">—</div>
                </div>
              </div>
            </div>
            <div id="route-no-job" style="text-align:center;padding:32px 16px;color:var(--sub)">
              <i class="fa fa-map-pin" style="font-size:2rem;opacity:.3;display:block;margin-bottom:12px"></i>
              <div style="font-weight:700;font-size:.88rem">No Active Mission</div>
              <div style="font-size:.78rem;margin-top:4px">Accept an SOS to see the route</div>
            </div>
            <div id="route-steps-container" style="display:none">
              <div
                style="font-weight:800;font-size:.85rem;margin-top:16px;color:var(--sub);text-transform:uppercase;letter-spacing:1px">
                Turn-by-turn</div>
              <div class="route-steps-wrap" id="route-steps-list"></div>
            </div>
            <button onclick="refreshRoute()" class="btn btn-primary btn-full" style="margin-top:16px" id="refresh-btn"
              <?= $job ? '' : 'style="display:none"' ?>>
              <i class="fa fa-rotate"></i>Refresh Route
            </button>
          </div>
        </div>
      </div>

      <!-- HISTORY PAGE -->
      <div class="page" id="page-history">
        <div class="section-title"><i class="fa fa-clock-rotate-left"
            style="color:var(--pa);margin-right:10px"></i>Mission History</div>
        <div class="section-sub"><?= count($missions) ?> total missions on record</div>
        <?php if (count($missions) > 0): ?>
          <div class="mission-cards">
            <?php foreach ($missions as $m): ?>
              <?php $bc = (['completed' => 'b-completed', 'accepted' => 'b-accepted', 'en_route' => 'b-en_route', 'arrived' => 'b-arrived', 'pending' => 'b-pending', 'rejected' => 'b-rejected'][$m['status']] ?? 'b-pending'); ?>
              <div class="mcard">
                <div class="mcard-header">
                  <div>
                    <div class="mcard h4"><?= htmlspecialchars($m['pname']) ?></div>
                    <div class="meta"><i class="fa fa-calendar"
                        style="margin-right:4px"></i><?= date('M d, Y  H:i', strtotime($m['created_at'])) ?></div>
                  </div>
                  <span class="badge <?= $bc ?>"><?= strtoupper(str_replace('_', ' ', $m['status'])) ?></span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                  <span class="type-tag"><?= htmlspecialchars($m['emergency_type']) ?></span>
                </div>
                <?php if (!empty($m['description'])): ?>
                  <p style="font-size:.82rem;color:var(--sub);margin-bottom:12px">
                    <?= htmlspecialchars(substr($m['description'], 0, 80)) ?>       <?= strlen($m['description']) > 80 ? '…' : '' ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($m['lat']) && !empty($m['lng'])): ?>
                  <div style="font-size:.78rem;color:var(--sub)"><i class="fa fa-location-dot"
                      style="color:var(--danger);margin-right:4px"></i><?= number_format($m['lat'], 4) ?>,
                    <?= number_format($m['lng'], 4) ?>
                  </div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="card standby-card">
            <div class="standby-icon"><i class="fa fa-folder-open"></i></div>
            <h2 style="font-weight:900;margin-top:20px;margin-bottom:8px">No Missions Yet</h2>
            <p style="color:var(--sub)">Your completed missions will appear here.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- PROFILE PAGE (NEW) -->
      <div class="page" id="page-profile">
        <div class="section-title"><i class="fa fa-user" style="color:var(--pa);margin-right:10px"></i>My Profile</div>
        <div class="section-sub">Manage your personal information and account settings</div>
        <div class="card" style="max-width: 600px; margin: 0 auto; padding: 32px; border-radius: 16px;">
          <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:28px;">
            <div style="position:relative; margin-bottom:16px;">
              <div class="big-avatar" id="profile-big-avatar"
                style="width:96px; height:96px; font-size:2.2rem; box-shadow:0 8px 24px rgba(59,130,246,.2); border: 4px solid var(--card); cursor:pointer;"
                onclick="document.getElementById('avatarFileInput').click()">
                <?php if (!empty($user['profile_image'])): ?>
                  <img src="../<?= htmlspecialchars($user['profile_image']) ?>" id="avatar-img-preview"
                    style="width:100%;height:100%;object-fit:cover;" alt="avatar"
                    onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=1e40af&color=fff'">
                <?php else: ?>
                  <span id="avatar-initials-preview"><?= $initials ?></span>
                  <img id="avatar-img-preview" src="" style="display:none;width:100%;height:100%;object-fit:cover;"
                    alt="avatar"
                    onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=1e40af&color=fff'">
                <?php endif; ?>
              </div>
              <input type="file" id="avatarFileInput" accept="image/*" style="display:none;"
                onchange="uploadDriverAvatar(this)">
              <button type="button" onclick="document.getElementById('avatarFileInput').click()"
                style="position:absolute; bottom:-4px; right:-4px; border-radius:50px; padding:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border:2px solid var(--card); background:var(--pa); color:white; cursor:pointer;"><i
                  class="fa fa-camera" style="font-size:0.7rem;"></i></button>
            </div>
            <h3 style="font-weight:800;font-size:1.4rem;margin-bottom:4px"><?= htmlspecialchars($user['fullname']) ?>
            </h3>
            <p style="color:var(--sub);font-size:.9rem"><i class="fa fa-envelope"
                style="margin-right:6px"></i><?= htmlspecialchars($user['email']) ?></p>
          </div>
          <form onsubmit="updateProfile(event)">
            <div class="form-group"><label>Full Name</label><input name="fullname" class="form-control"
                value="<?= htmlspecialchars($user['fullname']) ?>" required></div>
            <div class="form-group"><label>Email Address</label><input name="email" type="email" class="form-control"
                value="<?= htmlspecialchars($user['email']) ?>" required></div>
            <div class="form-group" style="display:flex;gap:15px">
              <div style="flex:1"><label>Birth Date</label><input type="date" name="birth_date" class="form-control"
                  value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>"></div>
              <div style="flex:1"><label>Gender</label>
                <select name="gender" class="form-control">
                  <option value="" <?= empty($user['gender']) ? 'selected' : '' ?>>Select...</option>
                  <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                  <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
              </div>
            </div>
            <div class="form-group"><label>Phone Number</label><input name="phone" class="form-control"
                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
            <div style="margin-top:28px;">
              <button type="submit" class="btn btn-primary btn-full"><i class="fa fa-save"></i>Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <!-- SETTINGS PAGE (REVAMPED) -->
      <div class="page" id="page-settings">
        <div class="section-title"><i class="fa fa-sliders" style="color:var(--pa);margin-right:10px"></i>System
          Settings</div>
        <div class="section-sub">Customize your app experience, notifications, and privacy</div>

        <div class="settings-grid">
          <div>
            <div class="card" style="margin-bottom:24px">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-truck-medical" style="color:var(--pa);font-size:1.2rem"></i>Unit Status</h4>
              <div
                style="background:var(--bg);border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center;margin-bottom:12px; border:1px solid var(--border)">
                <div>
                  <div style="font-weight:800;font-size:1rem">
                    <?= $unit ? htmlspecialchars($unit['unit_name']) : 'No Unit' ?>
                  </div>
                  <div style="font-size:.85rem;color:var(--sub)"><?= $unit ? ucfirst($unit['status']) : 'Unassigned' ?>
                  </div>
                </div>
                <label class="tog"><input type="checkbox" id="unitToggle2" <?= ($unit && $unit['status'] === 'available') ? 'checked' : '' ?> onchange="toggleUnit('unitToggle2')"><span class="tog-slider"></span></label>
              </div>
              <p style="font-size:.8rem;color:var(--sub);line-height:1.4"><i class="fa fa-info-circle"
                  style="margin-right:4px;color:var(--pa)"></i>Mark yourself available in dispatch system to receive
                emergency missions.</p>
            </div>

            <div class="card" style="margin-bottom:24px">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-earth-americas" style="color:var(--success);font-size:1.2rem"></i>Language & Region</h4>
              <div class="form-group" style="margin-bottom:16px">
                <label>App Language</label>
                <select class="form-control" onchange="changeLanguage(this.value)">
                  <option value="en" <?= $app_language === 'en' ? 'selected' : '' ?>>English</option>
                  <option value="so" <?= $app_language === 'so' ? 'selected' : '' ?>>Somali</option>
                  <option value="ar" <?= $app_language === 'ar' ? 'selected' : '' ?>>Arabic</option>
                </select>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0">
                <div>
                  <div style="font-weight:700">24-Hour Time Format</div>
                  <div style="font-size:.8rem;color:var(--sub)">Use 24h format instead of am/pm</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $time_format_24h ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'time_format_24h')"><span class="tog-slider"></span></label>
              </div>
            </div>

            <div class="card">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-shield-halved" style="color:var(--danger);font-size:1.2rem"></i>Security</h4>
              <form onsubmit="changePassword(event)">
                <div class="form-group"><label>Current Password</label><input type="password" name="old_password"
                    class="form-control" placeholder="Enter current password"></div>
                <div class="form-group"><label>New Password</label><input type="password" name="new_password"
                    class="form-control" placeholder="Enter new password"></div>
                <button type="submit" class="btn btn-outline" style="margin-top:8px; width:100%"><i
                    class="fa fa-key"></i>Update Password</button>
              </form>
            </div>
          </div>

          <div>
            <div class="card" style="margin-bottom:24px">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-bell" style="color:#fbbf24;font-size:1.2rem"></i>Notifications Setup</h4>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                <div>
                  <div style="font-weight:700">Push Notifications</div>
                  <div style="font-size:.8rem;color:var(--sub)">Receive alerts on your device</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $notifications_enabled ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'notifications_enabled')"><span class="tog-slider"></span></label>
              </div>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                <div>
                  <div style="font-weight:700">SOS Sound Alerts</div>
                  <div style="font-size:.8rem;color:var(--sub)">Loud alarm for new missions</div>
                </div>
                <label class="tog"><input type="checkbox" id="soundToggle" <?= $sound_alerts ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'sound_alerts')"><span class="tog-slider"></span></label>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0">
                <div>
                  <div style="font-weight:700">Emergency Updates</div>
                  <div style="font-size:.8rem;color:var(--sub)">System broadcast messages</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $emergency_updates ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'emergency_updates')"><span class="tog-slider"></span></label>
              </div>
            </div>

            <div class="card" style="margin-bottom:24px">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-lock" style="color:var(--p);font-size:1.2rem"></i>Privacy & Safety</h4>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                <div>
                  <div style="font-weight:700">Location Sharing</div>
                  <div style="font-size:.8rem;color:var(--sub)">Share location with dispatch</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $location_sharing ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'location_sharing')"><span class="tog-slider"></span></label>
              </div>
              <div
                style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                <div>
                  <div style="font-weight:700">Auto GPS Tracking</div>
                  <div style="font-size:.8rem;color:var(--sub)">Start tracking on shift start</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $auto_gps_tracking ? 'checked' : '' ?>
                    onchange="toggleSetting(this, 'auto_gps_tracking')"><span class="tog-slider"></span></label>
              </div>
              <div class="form-group" style="margin-top:16px; margin-bottom:0">
                <label>Session Timeout</label>
                <select class="form-control" onchange="saveSetting('session_timeout', this.value)">
                  <option value="5" <?= $session_timeout === '5' ? 'selected' : '' ?>>5 Minutes</option>
                  <option value="10" <?= $session_timeout === '10' ? 'selected' : '' ?>>10 Minutes</option>
                  <option value="30" <?= $session_timeout === '30' ? 'selected' : '' ?>>30 Minutes</option>
                  <option value="never" <?= $session_timeout === 'never' ? 'selected' : '' ?>>Never</option>
                </select>
              </div>
            </div>

            <div class="card">
              <h4 style="font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px"><i
                  class="fa fa-palette" style="color:var(--p);font-size:1.2rem"></i>Preferences</h4>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0">
                <div>
                  <div style="font-weight:700">Dark Mode</div>
                  <div style="font-size:.8rem;color:var(--sub)">Easier on the eyes at night</div>
                </div>
                <label class="tog"><input type="checkbox" <?= $user['dark_mode'] ? 'checked' : '' ?>
                    onchange="toggleDark()"><span class="tog-slider"></span></label>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- SOS POPUP OVERLAY -->
  <div class="sos-overlay" id="sos-overlay" onclick="dismissSOS(event)">
    <div class="sos-popup" onclick="event.stopPropagation()">
      <div class="sos-pulse"><i class="fa fa-siren-on"></i> EMERGENCY SOS</div>
      <h3 id="sos-title">New Emergency Request</h3>
      <div class="sos-info">
        <div class="sos-row"><i class="fa fa-user"></i><span id="sos-name">—</span></div>
        <div class="sos-row"><i class="fa fa-phone"></i><a id="sos-phone" href="#">—</a></div>
        <div class="sos-row"><i class="fa fa-triangle-exclamation"></i><span id="sos-type">—</span></div>
        <div class="sos-row"><i class="fa fa-route"></i><span id="sos-dist">Calculating distance…</span></div>
        <div class="sos-row"><i class="fa fa-clock"></i><span id="sos-time">—</span></div>
      </div>
      <div id="sos-desc"
        style="font-size:.83rem;color:var(--sub);background:var(--bg);border-radius:10px;padding:12px;margin-bottom:16px;display:none">
      </div>
      <div class="sos-actions">
        <button onclick="rejectSOS()" class="btn btn-danger" style="flex:1;justify-content:center"><i
            class="fa fa-xmark"></i>Reject</button>
        <button onclick="acceptSOS()" class="btn btn-success" style="flex:2;justify-content:center"><i
            class="fa fa-check"></i>Accept Mission</button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div id="toast"
    style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:#1e293b;color:#fff;padding:12px 24px;border-radius:50px;font-weight:700;font-size:.88rem;z-index:9999;transition:.4s;opacity:0">
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-polylinedecorator/dist/leaflet.polylineDecorator.js"></script>
  <script>
    const JOB_LAT = <?= $job ? $job['lat'] : 'null' ?>, JOB_LNG = <?= $job ? $job['lng'] : 'null' ?>;
    const JOB_ID = <?= $job ? $job['id'] : 'null' ?>, UNIT_ID = <?= $unit_id ?: 'null' ?>;
    const HAS_JOB = <?= $job ? 'true' : 'false' ?>;
    let userPrefs = {
      location_sharing: <?= (int) $location_sharing ?>,
      auto_gps_tracking: <?= (int) $auto_gps_tracking ?>,
      sound_alerts: <?= (int) $sound_alerts ?>,
      notifications_enabled: <?= (int) $notifications_enabled ?>
    };

    // PAGE NAVIGATION
    function showPage(id, el) {
      document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
      document.getElementById('page-' + id).classList.add('active');
      if (el) el.classList.add('active');
      if (id === 'map' && mapObj) setTimeout(() => mapObj.invalidateSize(), 100);
    }

    // TOAST
    function toast(msg, color = '#10b981') {
      const t = document.getElementById('toast');
      t.textContent = msg; t.style.background = color;
      t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)';
      setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(80px)' }, 3000);
    }

    // DARK MODE
    function toggleDark() {
      const h = document.documentElement;
      const next = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      h.setAttribute('data-theme', next);
      const f = new FormData(); f.append('action', 'toggle_preference'); f.append('preference', 'dark_mode'); f.append('value', next === 'dark' ? 1 : 0);
      fetch('../api/user/user_settings.php', { method: 'POST', body: f });
    }

    // UNIT TOGGLE
    function toggleUnit(triggerId) {
      const t1 = document.getElementById('unitToggle');
      const t2 = document.getElementById('unitToggle2');

      // Decide which one was flipped and sync the other
      const isOn = (triggerId === 'unitToggle2') ? t2.checked : t1.checked;
      if (t1) t1.checked = isOn;
      if (t2) t2.checked = isOn;

      const lbl = document.getElementById('status-label');
      if (lbl) {
        lbl.textContent = isOn ? 'Online' : 'Offline';
        lbl.style.color = isOn ? 'var(--success)' : 'var(--sub)';
      }

      const f = new FormData(); f.append('status', isOn ? 'available' : 'busy');
      fetch('../api/driver/update_unit_status.php', { method: 'POST', body: f }).then(r => r.json()).then(d => {
        if (d.status === 'success') toast(isOn ? 'You are now Online' : 'You are now Offline', isOn ? '#10b981' : '#64748b');
      });
    }

    // PROFILE + PASSWORD
    function updateProfile(e) {
      e.preventDefault();
      const f = new FormData(e.target); f.append('action', 'update_profile');
      fetch('../api/user/user_settings.php', { method: 'POST', body: f }).then(r => r.json()).then(d => {
        toast(d.message, d.status === 'success' ? '#10b981' : '#ef4444');
      });
    }

    async function uploadDriverAvatar(input) {
      if (!input.files || !input.files[0]) return;
      toast('Uploading photo…', '#3b82f6');
      const fd = new FormData();
      fd.append('avatar', input.files[0]);
      try {
        const res = await fetch('../api/user/upload_avatar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
          const src = '../' + data.file_path;
          const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(<?= json_encode($user['fullname']) ?>)}&background=1e40af&color=fff`;

          // Update profile page avatar
          const previewImg = document.getElementById('avatar-img-preview');
          const previewInitials = document.getElementById('avatar-initials-preview');
          if (previewImg) {
            previewImg.src = src;
            previewImg.style.display = 'block';
            previewImg.onerror = function () { this.src = fallback; this.onerror = null; };
          }
          if (previewInitials) previewInitials.style.display = 'none';

          // Update topbar button
          const btn = document.getElementById('topbar-avatar-btn');
          if (btn) btn.innerHTML = `<img src="${src}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="avatar" onerror="this.src='${fallback}';this.onerror=null;">`;

          // Update dashboard card
          const dash = document.getElementById('dash-big-avatar');
          if (dash) dash.innerHTML = `<img src="${src}" style="width:100%;height:100%;object-fit:cover;" alt="avatar" onerror="this.src='${fallback}';this.onerror=null;">`;

          toast('Photo updated!', '#10b981');
        } else {
          toast(data.message || 'Upload failed', '#ef4444');
        }
      } catch (err) {
        toast('Upload failed', '#ef4444');
      }
    }
    function changePassword(e) {
      e.preventDefault();
      const f = new FormData(e.target); f.append('action', 'change_password');
      fetch('../api/user/user_settings.php', { method: 'POST', body: f }).then(r => r.json()).then(d => {
        toast(d.message, d.status === 'success' ? '#10b981' : '#ef4444');
        if (d.status === 'success') e.target.reset();
      });
    }

    function toggleSetting(el, key) {
      const isOn = el.checked;
      userPrefs[key] = isOn ? 1 : 0; // Update local state immediately
      const f = new FormData();
      f.append('action', 'toggle_preference'); f.append('preference', key); f.append('value', isOn ? 1 : 0);
      fetch('../api/user/user_settings.php', { method: 'POST', body: f }).catch(() => { });
      toast('Setting updated', '#10b981');
    }

    function saveSetting(key, val) {
      userPrefs[key] = val; // Update local state immediately
      const f = new FormData();
      f.append('action', 'toggle_preference'); f.append('preference', key); f.append('value', val);
      fetch('../api/user/user_settings.php', { method: 'POST', body: f }).catch(() => { });
      toast('Setting saved', '#10b981');
    }

    function changeLanguage(langCode) {
      saveSetting('language', langCode);
      if (langCode === 'en') {
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=.' + location.hostname + '; path=/;';
      } else {
        document.cookie = `googtrans=/en/${langCode}; path=/`;
        document.cookie = `googtrans=/en/${langCode}; domain=.${location.hostname}; path=/`;
      }
      setTimeout(() => location.reload(), 600);
    }

    // MISSION UPDATE
    function updateMission(action) {
      if (!JOB_ID || !UNIT_ID) return;
      const f = new FormData(); f.append('request_id', JOB_ID); f.append('unit_id', UNIT_ID); f.append('action', action);
      fetch('../api/driver/update_status.php', { method: 'POST', body: f }).then(r => r.json()).then(d => {
        if (d.status === 'success') location.reload();
        else toast(d.message || 'Action failed', '#ef4444');
      });
    }

    // NOTIFICATIONS
    let notifList = [];
    function toggleNotif() { document.getElementById('notif-dropdown').classList.toggle('open') }
    function clearNotifs() { notifList = []; renderNotifs() }
    function renderNotifs() {
      const el = document.getElementById('notif-list'), badge = document.getElementById('bell-badge');
      if (!notifList.length) { el.innerHTML = '<div class="notif-empty">No notifications</div>'; badge.style.display = 'none'; return; }
      badge.style.display = 'flex'; badge.textContent = notifList.length;
      el.innerHTML = notifList.map(n => `<div class="notif-item"><h6><i class="fa fa-triangle-exclamation" style="color:var(--danger);margin-right:6px"></i>${n.emergency_type}</h6><p>${n.fullname} · ${n.time_ago}</p></div>`).join('');
    }
    document.addEventListener('click', e => { if (!e.target.closest('.notif-wrap')) document.getElementById('notif-dropdown').classList.remove('open') });

    // SOUND ALERT
    function playAlert() {
      if (!userPrefs.sound_alerts) return;
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [0, 0.4, 0.8].forEach(t => { const o = ctx.createOscillator(), g = ctx.createGain(); o.connect(g); g.connect(ctx.destination); o.frequency.value = 880; g.gain.setValueAtTime(0.3, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.3); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.3); });
      } catch (e) { }
      if (window.speechSynthesis) { const u = new SpeechSynthesisUtterance('New emergency request received'); u.rate = 1.1; window.speechSynthesis.speak(u); }
    }

    // SOS POPUP
    let currentSOS = null, seenIds = new Set();
<?php if ($job): ?> seenIds.add(<?= (int) $job['id'] ?>); <?php endif; ?>

    function showSOS(req) {
      currentSOS = req;
      document.getElementById('sos-title').textContent = `${req.emergency_type} Emergency`;
      document.getElementById('sos-name').textContent = req.fullname;
      const ph = document.getElementById('sos-phone'); ph.textContent = req.phone; ph.href = 'tel:' + req.phone;
      document.getElementById('sos-type').textContent = req.emergency_type;
      document.getElementById('sos-time').textContent = req.time_ago;
      const desc = document.getElementById('sos-desc');
      if (req.description) { desc.textContent = req.description; desc.style.display = 'block'; } else { desc.style.display = 'none'; }
      document.getElementById('sos-dist').textContent = 'Calculating…';
      document.getElementById('sos-overlay').classList.add('show');
      playAlert();
      // Distance calc if driver has GPS
      if (driverLat && req.lat && req.lng) {
        const d = haversine(driverLat, driverLng, parseFloat(req.lat), parseFloat(req.lng));
        const eta = Math.ceil(d / 40 * 60);
        document.getElementById('sos-dist').textContent = `${d.toFixed(1)} km away · ETA ${eta} min`;
      }
      // Add to notifs
      notifList.unshift(req);
      renderNotifs();
    }
    function dismissSOS(e) { if (e.target === document.getElementById('sos-overlay')) { document.getElementById('sos-overlay').classList.remove('show'); currentSOS = null; } }
    function rejectSOS() { document.getElementById('sos-overlay').classList.remove('show'); currentSOS = null; toast('Request dismissed', '#64748b'); }
    function acceptSOS() {
      if (!currentSOS) return;
      const f = new FormData(); f.append('request_id', currentSOS.id); f.append('unit_id', UNIT_ID); f.append('action', 'accept');
      fetch('../api/driver/update_status.php', { method: 'POST', body: f }).then(r => r.json()).then(d => {
        if (d.status === 'success') { toast('Mission Accepted! Loading…', '#10b981'); setTimeout(() => location.reload(), 1000); }
        else toast(d.message || 'Failed', '#ef4444');
      });
    }

    // Poll for new SOS
    function pollSOS() {
      fetch('../api/driver/get_pending_sos.php').then(r => r.json()).then(d => {
        if (d.status === 'success' && d.requests) {
          d.requests.forEach(req => {
            if (!seenIds.has(parseInt(req.id))) {
              seenIds.add(parseInt(req.id));
              if (!document.getElementById('sos-overlay').classList.contains('show')) showSOS(req);
            }
          });
        }
      }).catch(() => { });
    }
    <!---only poll if no active job--->
    <?php if (!$job): ?>
      setInterval(pollSOS, 5000);
      pollSOS();
    <?php endif; ?>

    // ══════════════════════════════════════════════════
    // MAP ENGINE — OSRM Smart Routing
    // ══════════════════════════════════════════════════
    let mapObj, driverMarker, victimMarker, routeLayer, driverLat = null, driverLng = null;
    let lastRouteFetch = 0, routeRefreshTimer = null;
    const ROUTE_THROTTLE = 12000; // Refresh route every 12 seconds max
    const driverIcon = L.divIcon({ className: '', html: `<div style="background:linear-gradient(135deg,#1e40af,#3b82f6);width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;box-shadow:0 4px 16px rgba(30,64,175,.5);border:3px solid #fff"><i class="fa fa-ambulance"></i></div>`, iconSize: [42, 42], iconAnchor: [21, 21] });
    const victimIcon = L.divIcon({ className: '', html: `<div style="background:#ef4444;width:20px;height:20px;border-radius:50%;border:4px solid #fff;box-shadow:0 0 0 4px rgba(239,68,68,.3),0 4px 12px rgba(239,68,68,.5)"></div>`, iconSize: [20, 20], iconAnchor: [10, 10] });

    const mapCenter = HAS_JOB ? [JOB_LAT, JOB_LNG] : [2.0469, 45.3182];
    mapObj = L.map('map', { zoomControl: false }).setView(mapCenter, 14);

    const mapLayers = {
      std: L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: '0123', maxZoom: 20, attribution: '© Google Maps' }),
      sat: L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: '0123', maxZoom: 20, attribution: '© Google Maps' }),
      '3d': L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: '0123', maxZoom: 20, attribution: '© Google Maps' })
    };

    mapLayers.std.addTo(mapObj); // Default layer

    function setMapLayer(type) {
      Object.keys(mapLayers).forEach(k => mapObj.removeLayer(mapLayers[k]));
      mapObj.addLayer(mapLayers[type]);
      document.querySelectorAll('.layer-btn').forEach(btn => btn.classList.remove('active'));
      document.getElementById('btn-layer-' + type).classList.add('active');
    }

    L.control.zoom({ position: 'bottomright' }).addTo(mapObj);

    if (HAS_JOB) {
      victimMarker = L.marker([JOB_LAT, JOB_LNG], { icon: victimIcon }).addTo(mapObj).bindPopup('<b>Patient Location</b>').openPopup();
    }

    function haversine(la1, lo1, la2, lo2) {
      const R = 6371, dL = (la2 - la1) * Math.PI / 180, dO = (lo2 - lo1) * Math.PI / 180;
      const a = Math.sin(dL / 2) ** 2 + Math.cos(la1 * Math.PI / 180) * Math.cos(la2 * Math.PI / 180) * Math.sin(dO / 2) ** 2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    // ── OSRM Routing ──────────────────────────────────
    let routeDecorator = null;

    function getManeuverIcon(type, modifier) {
      if (type === 'arrive') return '<i class="fa fa-location-dot" style="color:var(--danger)"></i>';
      if (type === 'depart') return '<i class="fa fa-flag"></i>';
      if (!modifier) return '<i class="fa fa-arrow-up"></i>';
      if (modifier.includes('left')) return '<i class="fa fa-turn-down" style="transform:rotate(90deg)"></i>';
      if (modifier.includes('right')) return '<i class="fa fa-turn-up" style="transform:rotate(90deg)"></i>';
      if (modifier.includes('uturn')) return '<i class="fa fa-arrow-rotate-left"></i>';
      return '<i class="fa fa-arrow-up"></i>';
    }

    function fetchRoute(dLat, dLng, vLat, vLng, force) {
      const now = Date.now();
      if (!force && (now - lastRouteFetch) < ROUTE_THROTTLE) return;
      lastRouteFetch = now;
      setRouteDot('loading');
      const url = `https://router.project-osrm.org/route/v1/driving/${dLng},${dLat};${vLng},${vLat}?overview=full&geometries=geojson&steps=true`;
      fetch(url, { signal: AbortSignal.timeout(9000) })
        .then(r => r.json())
        .then(data => {
          if (data.code !== 'Ok' || !data.routes || !data.routes.length) { fallbackRoute(dLat, dLng, vLat, vLng); return; }
          const route = data.routes[0];
          const distKm = (route.distance / 1000).toFixed(1);
          const durSec = route.duration;
          const durMin = Math.ceil(durSec / 60);
          const speedKmh = durSec > 0 ? ((route.distance / 1000) / (durSec / 3600)).toFixed(0) : '40';
          if (routeLayer) { mapObj.removeLayer(routeLayer); }
          if (routeDecorator) { mapObj.removeLayer(routeDecorator); }

          routeLayer = L.geoJSON(route.geometry, {
            style: { color: '#3b82f6', weight: 6, opacity: .88, lineCap: 'round', lineJoin: 'round' }
          }).addTo(mapObj);

          // Arrows on the line
          routeDecorator = L.polylineDecorator(routeLayer, {
            patterns: [{
              offset: 25, repeat: 60,
              symbol: L.Symbol.arrowHead({ pixelSize: 15, polygon: false, pathOptions: { color: '#fff', weight: 3, opacity: 0.9 } })
            }]
          }).addTo(mapObj);

          routeLayer._decorator = routeDecorator;

          // Turn-by-Turn Steps
          if (route.legs && route.legs[0] && route.legs[0].steps) {
            const steps = route.legs[0].steps;
            let html = '';
            steps.forEach(s => {
              if (s.distance > 0 || s.maneuver.type === 'arrive') {
                const inst = s.maneuver.instruction || (s.name && s.name !== '' ? `Turn ${s.maneuver.modifier || ''} onto ${s.name}` : `Proceed ${s.maneuver.modifier || 'straight'}`);
                html += `<div class="route-step-item">
              <div class="step-icon">${getManeuverIcon(s.maneuver.type, s.maneuver.modifier)}</div>
              <div><div class="step-text">${inst}</div>
              <div class="step-dist">${s.distance > 0 ? (s.distance >= 1000 ? (s.distance / 1000).toFixed(1) + ' km' : s.distance + ' m') : ''}</div></div>
            </div>`;
              }
            });
            document.getElementById('route-steps-container').style.display = 'block';
            document.getElementById('route-steps-list').innerHTML = html;
          }

          fitMapToBounds();
          updateETADisplay(distKm, durMin, true, speedKmh);
          setRouteDot('live');
        })
        .catch(() => fallbackRoute(dLat, dLng, vLat, vLng));
    }

    function fallbackRoute(dLat, dLng, vLat, vLng) {
      const d = haversine(dLat, dLng, vLat, vLng), eta = Math.ceil(d / 40 * 60);
      if (routeLayer) { if (routeLayer._decorator) mapObj.removeLayer(routeLayer._decorator); mapObj.removeLayer(routeLayer); }
      if (routeDecorator) { mapObj.removeLayer(routeDecorator); }

      routeLayer = L.polyline([[dLat, dLng], [vLat, vLng]], { color: '#3b82f6', weight: 4, dashArray: '10,10', opacity: .8 }).addTo(mapObj);

      document.getElementById('route-steps-container').style.display = 'none';

      fitMapToBounds();
      updateETADisplay(d.toFixed(1), eta, false, '40');
      setRouteDot('err');
    }

    function fitMapToBounds() {
      if (!window._fitted && driverMarker && victimMarker) {
        const g = new L.featureGroup([driverMarker, victimMarker]);
        mapObj.fitBounds(g.getBounds().pad(.35));
        window._fitted = true;
      }
    }

    function updateETADisplay(distKm, durMin, isReal, speedKmh) {
      const pfx = isReal ? '' : ' ~';
      const routeType = isReal ? 'Road Route' : 'Straight Line';
      // Map overlay
      document.getElementById('mo-dist').innerHTML = `<i class="fa fa-road" style="color:var(--pa);margin-right:6px"></i>${pfx}${distKm} km`;
      document.getElementById('mo-eta').innerHTML = `<i class="fa fa-clock" style="color:var(--warn);margin-right:6px"></i>ETA${pfx} ${durMin} min`;
      const moType = document.getElementById('mo-type');
      if (moType) moType.textContent = routeType;
      document.getElementById('map-overlay').style.display = 'flex';
      // Dashboard mission card
      const dd = document.getElementById('dash-dist'), de = document.getElementById('dash-eta');
      if (dd) dd.textContent = `${pfx}${distKm} km`;
      if (de) de.textContent = `ETA:${pfx} ${durMin} min`;
      // Route panel
      const rpd = document.getElementById('rp-dist'), rpe = document.getElementById('rp-eta'), rps = document.getElementById('rp-speed'), rpu = document.getElementById('rp-updated'), rn = document.getElementById('route-no-job');
      if (rpd) rpd.textContent = `${pfx}${distKm} km`;
      if (rpe) rpe.textContent = `${pfx}${durMin} min`;
      if (rps) rps.textContent = `${speedKmh} km/h`;
      if (rpu) rpu.textContent = new Date().toLocaleTimeString();
      if (rn) rn.style.display = 'none';
    }

    function setRouteDot(state) {
      const el = document.getElementById('route-status-dot');
      if (!el) return;
      el.className = 'route-dot route-dot-' + state;
    }

    function refreshRoute() {
      if (HAS_JOB && driverLat && victimMarker) {
        const vp = victimMarker.getLatLng();
        fetchRoute(driverLat, driverLng, vp.lat, vp.lng, true);
        toast('Refreshing route…', '#3b82f6');
      } else toast('No active mission', '#64748b');
    }

    // Auto-refresh route timer (every 30s)
    function startRouteAutoRefresh() {
      if (routeRefreshTimer) clearInterval(routeRefreshTimer);
      routeRefreshTimer = setInterval(() => {
        if (HAS_JOB && driverLat && victimMarker) {
          const vp = victimMarker.getLatLng();
          if (routeLayer && routeLayer._decorator) mapObj.removeLayer(routeLayer._decorator);
          if (routeLayer) mapObj.removeLayer(routeLayer);
          if (routeDecorator) mapObj.removeLayer(routeDecorator);
          lastRouteFetch = 0;
          fetchRoute(driverLat, driverLng, vp.lat, vp.lng, true);
        }
      }, 30000);
    }
    if (HAS_JOB) startRouteAutoRefresh();
    // ── End OSRM Routing ──────────────────────────────

    // ── Follow-Me Mode ──────────────────────────────────
    let followMode = true;
    function toggleFollow() {
      followMode = !followMode;
      const btn = document.getElementById('follow-btn');
      const lbl = document.getElementById('follow-label');
      if (followMode) {
        btn.classList.add('active');
        lbl.textContent = 'Following You';
        if (driverLat) mapObj.panTo([driverLat, driverLng], { animate: true, duration: 0.6 });
      } else {
        btn.classList.remove('active');
        lbl.textContent = 'Follow Off';
      }
    }

    function updateMap(lat, lng) {
      driverLat = lat; driverLng = lng;
      if (!driverMarker) {
        driverMarker = L.marker([lat, lng], { icon: driverIcon }).addTo(mapObj).bindPopup('<b>You are here</b>');
      } else {
        driverMarker.setLatLng([lat, lng]);
      }
      if (HAS_JOB && victimMarker) {
        const vp = victimMarker.getLatLng();
        fetchRoute(lat, lng, vp.lat, vp.lng, false); // throttled — only calls OSRM every 12s
        fitMapToBounds();
      } else {
        // No active job — pan/follow driver
        if (followMode) {
          mapObj.panTo([lat, lng], { animate: true, duration: 0.6 });
        } else if (!window._fitted) {
          mapObj.setView([lat, lng], 15);
        }
        window._fitted = true;
      }
    }

    // GPS ENGINE — Best-Fix Strategy
    // Accumulate multiple readings, always keep the most accurate (lowest accuracy value).
    // This prevents the first coarse IP-based fix from becoming the permanent "wrong" location.
    let watchId = null, lastSend = 0;
    let driverBestFix = null; // { lat, lng, accuracy }
    let driverAttempts = 0;

    function startGPS() {
      if (!navigator.geolocation) { setGPS('error'); return; }
      setGPS('acquiring');
      driverBestFix = null;
      driverAttempts = 0;

      watchId = navigator.geolocation.watchPosition(pos => {
        const { latitude: lat, longitude: lng, accuracy: acc } = pos.coords;
        driverAttempts++;

        // GUARD: Ignore extremely bad IP-location (over 60km)
        if (acc > 60000) {
          console.warn(`Driver GPS reading ignored (too inaccurate: ±${Math.round(acc)}m)`);
          setGPS('acquiring', Math.round(acc));
          return;
        }

        // ── Movement & Accuracy Strategy ──
        let distMoved = 0;
        if (driverLat && driverLng && driverBestFix) {
          distMoved = haversine(driverLat, driverLng, lat, lng) * 1000;
        }

        // Update strategy:
        // 1. Initial session fix
        // 2. Better accuracy found
        // 3. Movement detected (>30m) AND accuracy is reasonable (<150m)
        if (!driverBestFix || acc < driverBestFix.accuracy || (distMoved > 30 && acc < 150)) {
          driverBestFix = { lat, lng, accuracy: acc };
        }

        // Use best-fix coordinates for all map/DB updates
        const bLat = driverBestFix.lat;
        const bLng = driverBestFix.lng;
        const bAcc = driverBestFix.accuracy;

        driverLat = bLat; driverLng = bLng;

        // Update marker position
        if (driverMarker) driverMarker.setLatLng([bLat, bLng]);
        else driverMarker = L.marker([bLat, bLng], { icon: driverIcon }).addTo(mapObj).bindPopup('<b>Your Location</b>');

        // First fix: fly to location with accuracy-appropriate zoom
        if (!window._fitted && mapObj) {
          const zoomLevel = bAcc <= 100 ? 18 : (bAcc <= 500 ? 16 : 14);
          if (followMode) {
            mapObj.flyTo([bLat, bLng], zoomLevel, { duration: 1.5 });
          } else {
            mapObj.setView([bLat, bLng], zoomLevel);
          }
          window._fitted = true;
        } else if (followMode && mapObj) {
          mapObj.panTo([bLat, bLng], { animate: true, duration: 0.6 });
        }

        const now = Date.now();
        // Push to DB every 3s if location sharing is enabled or if they have an active job, using best-fix coords
        if (now - lastSend >= 3000 && (userPrefs.location_sharing || HAS_JOB)) {
          fetch('../api/driver/update_driver_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `lat=${bLat}&lng=${bLng}`
          }).catch(() => { });
          lastSend = now;
        }

        if (bAcc <= 30) setGPS('great', Math.round(bAcc), bLat, bLng);
        else if (bAcc <= 100) setGPS('good', Math.round(bAcc), bLat, bLng);
        else setGPS('acquiring', Math.round(bAcc));
      }, err => {
        setGPS('error');
        if (err.code === err.TIMEOUT || err.code === 3) {
          if (watchId) navigator.geolocation.clearWatch(watchId);
          watchId = null;
          setTimeout(startGPS, 3000);
        }
      }, { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 });
    }

    function setGPS(state, acc, lat, lng) {
      const el = document.getElementById('gps-status');
      if (!el) return;
      const states = {
        'acquiring': `<i class="fa fa-circle-notch fa-spin"></i> GPS Acquiring${acc ? ` (±${acc}m)` : '…'}`,
        'great': `<i class="fa fa-satellite-dish" style="color:var(--success)"></i> GPS Locked ±${acc}m`,
        'good': `<i class="fa fa-satellite" style="color:var(--warn)"></i> GPS Active ±${acc}m`,
        'error': `<i class="fa fa-circle-exclamation" style="color:var(--danger)"></i> GPS Failed`
      };
      el.innerHTML = states[state] || states.acquiring;

      if (lat && lng) {
        reverseGeocode(lat, lng).then(addr => {
          if (addr) {
            el.innerHTML = `<i class="fa fa-map-pin" style="color:var(--success)"></i> <span style="font-weight:700;">${addr}</span> <span style="opacity:0.6;font-size:0.75rem;margin-left:4px;">(±${acc}m)</span>`;
          }
        });
      }
    }

    // ── Reverse Geocoding ──
    const geoCache = {};
    async function reverseGeocode(lat, lng) {
      const k = lat.toFixed(4) + ',' + lng.toFixed(4);
      if (geoCache[k] !== undefined) return geoCache[k];
      geoCache[k] = null;
      try {
        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
        const data = await res.json();
        if (data && data.address) {
          const a = data.address;
          let p = [];
          if (a.building || a.amenity) p.push(a.building || a.amenity);
          if (a.road) p.push(a.road);
          if (a.neighbourhood || a.suburb) p.push(a.neighbourhood || a.suburb);
          const output = p.length > 0 ? p.join(', ') : (data.display_name ? data.display_name.split(',')[0] : '');
          if (output) geoCache[k] = output;
          return output;
        }
      } catch (e) { }
      return null;
    }

    startGPS();

    // Disable follow mode if user manually drags/zooms the map
    mapObj.on('dragstart', () => {
      if (followMode) {
        followMode = false;
        const btn = document.getElementById('follow-btn');
        const lbl = document.getElementById('follow-label');
        if (btn) btn.classList.remove('active');
        if (lbl) lbl.textContent = 'Follow Off';
      }
    });

    // Poll victim location during active mission + re-route when they move
    <?php if ($job): ?>
      let lastVictimLat = JOB_LAT, lastVictimLng = JOB_LNG;
      setInterval(() => {
        fetch('../api/driver/get_victim_location.php').then(r => r.json()).then(d => {
          if (d.status === 'success' && d.victim_lat && d.victim_lng) {
            const vLat = parseFloat(d.victim_lat), vLng = parseFloat(d.victim_lng);
            if (victimMarker) victimMarker.setLatLng([vLat, vLng]);
            // Re-route if victim moved more than 30m
            const moved = haversine(lastVictimLat, lastVictimLng, vLat, vLng) * 1000;
            if (moved > 30 && driverLat) {
              if (routeLayer && routeLayer._decorator) mapObj.removeLayer(routeLayer._decorator);
              if (routeLayer) mapObj.removeLayer(routeLayer);
              if (routeDecorator) mapObj.removeLayer(routeDecorator);
              lastRouteFetch = 0;
              window._fitted = false; // allow re-fit to new positions
              fetchRoute(driverLat, driverLng, vLat, vLng, true);
              lastVictimLat = vLat; lastVictimLng = vLng;
            }
            // Follow driver while on a job: pan map to keep driver visible
            if (followMode && driverLat) {
              mapObj.panTo([driverLat, driverLng], { animate: true, duration: 0.8 });
            }
          }
        }).catch(() => { });
      }, 4000);
    <?php endif; ?>
  </script>

  <!-- Google Translate Widget (Hidden) -->
  <div id="google_translate_element" style="display:none;"></div>
  <script type="text/javascript">
    function googleTranslateElementInit() {
      new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
    }

    // Ensure the cookie matches the saved user preference
    <?php if ($app_language !== 'en'): ?>
      if (document.cookie.indexOf('googtrans=') === -1) {
        document.cookie = "googtrans=/en/<?php echo $app_language; ?>; path=/";
        document.cookie = "googtrans=/en/<?php echo $app_language; ?>; domain=." + location.hostname + "; path=/";
        location.reload();
      }
    <?php else: ?>
      if (document.cookie.indexOf('googtrans=') !== -1) {
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=.' + location.hostname + '; path=/;';
        location.reload();
      }
    <?php endif; ?>
  </script>
  <script type="text/javascript"
    src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

  <style>
    /* Clean up the Google Translate UI leftovers */
    body {
      top: 0 !important;
    }

    .skiptranslate {
      display: none !important;
    }

    iframe.goog-te-banner-frame {
      display: none !important;
    }

    .goog-te-balloon-frame {
      display: none !important;
    }
  </style>

</body>

</html>