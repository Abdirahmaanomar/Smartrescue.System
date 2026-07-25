<?php
$start_time = microtime(true);
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../includes/functions.php';
require_once '../includes/session_guard.php';
require_once 'includes/lang.php';

$refresh_rate = get_setting($conn, 'refresh_rate', '7');
$sys_lang = get_setting($conn, 'language', 'en');
$page_title = 'Dashboard';
$page_subtitle = '';
?>
<!DOCTYPE html>
<html lang="<?= $sys_lang ?>" <?= $sys_lang == 'ar' ? 'dir="rtl"' : '' ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Dashboard') ?> | SmartRescue Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        :root {
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #0f172a;
            --text-muted: #64748b;
            --accent: #3b82f6;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
            --sidebar-width: 268px;
            --radius: 16px;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
        }

        .leaflet-control-attribution { display: none !important; }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            padding: 36px 44px;
            min-height: 100vh;
        }

        /* KPI CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .kpi-card {
            border-radius: 20px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: default;
            position: relative;
            overflow: hidden;
            background: var(--card-bg);
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--text);
            box-shadow: var(--shadow);
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.02);
            pointer-events: none;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        }

        .kpi-card.danger {
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.05);
        }
        .kpi-card.danger::before {
            background: rgba(239, 68, 68, 0.06);
        }
        .kpi-card.danger .kpi-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444 !important;
        }

        .kpi-card.success {
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.05);
        }
        .kpi-card.success::before {
            background: rgba(34, 197, 94, 0.06);
        }
        .kpi-card.success .kpi-icon {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e !important;
        }

        .kpi-card.primary {
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.05);
        }
        .kpi-card.primary::before {
            background: rgba(59, 130, 246, 0.06);
        }
        .kpi-card.primary .kpi-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6 !important;
        }

        .kpi-card.warning {
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.05);
        }
        .kpi-card.warning::before {
            background: rgba(245, 158, 11, 0.06);
        }
        .kpi-card.warning .kpi-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b !important;
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(0, 0, 0, 0.05);
            color: var(--text);
        }

        .kpi-icon svg {
            display: block;
            width: 22px;
            height: 22px;
        }

        .kpi-num {
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        .kpi-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .kpi-trend {
            font-size: 0.66rem;
            font-weight: 600;
            margin-top: 4px;
        }

        .kpi-trend.up {
            color: #ef4444;
        }

        .kpi-trend.down {
            color: #22c55e;
        }

        /* MAP */
        .map-card {
            background: var(--card-bg);
            border-radius: 22px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 28px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .map-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        .map-header h5 {
            font-size: 1rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .map-legend {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        #master-map {
            height: 480px;
            width: 100%;
        }

        /* INCIDENT TABLE */
        .panel-card {
            background: var(--card-bg);
            border-radius: 22px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.04);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 28px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        .panel-header h5 {
            font-size: 0.98rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            background: #f8fafc;
            padding: 12px 16px;
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .table tbody tr {
            transition: background 0.15s;
        }

        .table tbody tr:hover {
            background: #f8fafe;
        }

        .table tbody td {
            padding: 14px 16px;
            font-size: 0.88rem;
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* BADGES */
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .status-chip::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .chip-pending {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .chip-pending::before {
            background: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
            animation: chip-blink 1.5s infinite;
        }

        .chip-accepted {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .chip-accepted::before {
            background: #3b82f6;
        }

        .chip-completed {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .chip-completed::before {
            background: #22c55e;
        }

        .chip-dispatched {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .chip-dispatched::before {
            background: #f59e0b;
        }

        @keyframes chip-blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.4;
            }
        }

        @keyframes map-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }

        .marker-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .marker-init {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.8rem;
        }

        .emergency-tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgba(59, 130, 246, 0.08);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.15);
        }

        .victim-name {
            font-weight: 800;
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .victim-phone {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .btn-cmd {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-assign {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-assign:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.4);
            color: white;
        }

        .btn-view {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .btn-view:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-dispatched {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: default;
            font-size: 0.72rem;
        }

        /* SOUND ALERT TOGGLE */
        .sound-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.06);
            cursor: pointer;
            transition: 0.2s;
            user-select: none;
        }

        .sound-toggle.on {
            color: #22c55e;
            background: rgba(34, 197, 94, 0.06);
            border-color: rgba(34, 197, 94, 0.15);
        }

        .sound-toggle i {
            font-size: 0.9rem;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.2;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0;
        }

        /* MAP LAYER CONTROLS */
        .map-layer-controls {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 800;
            display: flex;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .layer-btn {
            background: transparent;
            border: none;
            padding: 8px 12px;
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .layer-btn:not(:last-child) {
            border-right: 1px solid rgba(0, 0, 0, 0.08);
        }

        .layer-btn:hover {
            background: rgba(59, 130, 246, 0.05);
            color: var(--text);
        }

        .layer-btn.active {
            background: var(--accent);
            color: #fff;
        }

        /* Modal */
        .assign-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .assign-modal-backdrop.show {
            display: flex;
            animation: fadeIn 0.2s;
        }

        .assign-modal {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 32px;
            width: 90%;
            max-width: 460px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-wrapper">
        <?php include 'includes/topbar.php'; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card danger">
                <div class="kpi-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="kpi-num" id="stat-pending">—</div>
                    <div class="kpi-label"><?= t('Pending SOS') ?></div>
                    <div class="kpi-trend up" id="stat-pending-sub"></div>
                </div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-icon"><i class="fa fa-person-running"></i></div>
                <div>
                    <div class="kpi-num" id="stat-active">—</div>
                    <div class="kpi-label"><?= t('Active Missions') ?></div>
                </div>
            </div>
            <div class="kpi-card primary">
                <div class="kpi-icon"><i class="fa fa-truck-medical"></i></div>
                <div>
                    <div class="kpi-num" id="stat-fleet">—</div>
                    <div class="kpi-label"><?= t('Available Units') ?></div>
                </div>
            </div>
            <div class="kpi-card warning">
                <div class="kpi-icon"><i class="fa fa-chart-line"></i></div>
                <div>
                    <div class="kpi-num" id="stat-total">—</div>
                    <div class="kpi-label"><?= t('Total Today') ?></div>
                </div>
            </div>
        </div>

        <!-- Live Map -->
        <div class="map-card">
            <div class="map-header">
                <h5><i class="fa fa-map-location-dot text-primary"></i> Tactical Live Map</h5>
                <div class="d-flex align-items-center gap-3">
                    <div class="map-legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background:#ef4444;box-shadow:0 0 6px rgba(239,68,68,0.5)">
                            </div> Incident
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:#3b82f6"></div> Unit
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:#f59e0b"></div> Dispatched
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:#10b981;box-shadow:0 0 6px rgba(16,185,129,0.5)">
                            </div> Users
                        </div>
                    </div>
                    <div class="sound-toggle" id="sound-toggle" onclick="toggleSound()">
                        <i class="fa fa-volume-high"></i> <span id="sound-label">Sound On</span>
                    </div>
                </div>
            </div>
            <div style="position:relative;">
                <div id="master-map"></div>
                <div class="map-layer-controls">
                    <button class="layer-btn active" id="btn-layer-std" onclick="setMapLayer('std')"><i
                            class="fa fa-map"></i> Map</button>
                    <button class="layer-btn" id="btn-layer-sat" onclick="setMapLayer('sat')"><i
                            class="fa fa-satellite"></i> Satellite</button>
                </div>
            </div>
        </div>

        <!-- Incident Queue -->
        <div class="panel-card">
            <div class="panel-header">
                <h5><i class="fa fa-list-ul text-primary"></i> Real-time Incident Triage</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="status-chip chip-pending" id="queue-count">Loading...</span>
                    <a href="view-requests.php" class="btn-cmd btn-view"><i class="fa fa-history"></i> Full Logs</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="padding-left:28px">Time</th>
                            <th>Victim</th>
                            <th>Emergency Type</th>
                            <th>Details Sent</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:28px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="incident-queue-body">
                        <tr>
                            <td colspan="5">
                                <div class="empty-state"><i class="fa fa-satellite-dish"></i>
                                    <p>Connecting to live feed...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php include 'includes/assign_modal.php'; ?>

    <!-- Hidden audio for SOS alert -->
    <audio id="sos-alert" preload="auto">
        <source
            src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJiVkFVEPVuLk5BuS0BPfZSVjGZHQlN/lpOKYUZIXI6VkYVdS09okZSRhFtXXnWPlZOKal5pcpGVlI9rbXF1jJOTlXF1dHKHkJKWe3x7c4KMj5SAgX13e4aKjJCGhoV8eYGGiYuPhYiIf3x/g4eIjIWHiIB+f4GEhoqGhomDgoGBg4WIhoaJhYSFhISFiIaGh4aFhoaGhoaHhYWGhoeHhoWFhYaHiIiGhYWFhYaHiYiGhYaGhYWGh4iIhoWFhoaGh4eJiIaFhYWFhoeJiIaFhYaGhoeHiYiFhYWGh4iIiIaFhYWGh4iJiIaGhYWGh4mJiIaFhYWGh4iJiIaFhYaGh4eJiIaFhYaGh4iIiIaFhYaHiIiJh4WFhYaHiIiIhoWFhYaHiYmIhoWFhoaHiIiIhoWFhoeIiImIhYWFhoeIiYmHhoWFhoiIiImHhYaFhoiJiYiHhYaFhoeJiYmIhoaFhoeJiYqJh4aGhYeIiYqJiIeGhoiJiouKiYeHiIiKi4uLioiIiYqKjI2Mi4mJioqMjY2Mi4qKi4yNjo2Mi4uLjI2OjoyLi4yNjo+OjYyMjY6Pj46NjI2Oj4+PjY2Njo+QkI+OjY6Pj5CPj46Ojw=="
            type="audio/wav">
    </audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // ─── Sound System ───────────────────────────────────────────
        let soundEnabled = localStorage.getItem('sos_sound') !== 'off';
        let lastPendingCount = 0;

        function toggleSound() {
            soundEnabled = !soundEnabled;
            localStorage.setItem('sos_sound', soundEnabled ? 'on' : 'off');
            updateSoundUI();
        }
        function updateSoundUI() {
            const el = document.getElementById('sound-toggle');
            const lbl = document.getElementById('sound-label');
            if (soundEnabled) {
                el.classList.add('on'); lbl.textContent = 'Sound On';
                el.querySelector('i').className = 'fa fa-volume-high';
            } else {
                el.classList.remove('on'); lbl.textContent = 'Sound Off';
                el.querySelector('i').className = 'fa fa-volume-xmark';
            }
        }
        updateSoundUI();

        function playSosAlert() {
            if (!soundEnabled) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [0, 0.3, 0.6].forEach(t => {
                    const o = ctx.createOscillator();
                    const g = ctx.createGain();
                    o.connect(g); g.connect(ctx.destination);
                    o.frequency.value = 880;
                    o.type = 'sine';
                    g.gain.setValueAtTime(0.5, ctx.currentTime + t);
                    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.25);
                    o.start(ctx.currentTime + t);
                    o.stop(ctx.currentTime + t + 0.25);
                });
            } catch (e) { }
        }

        // ─── Reverse Geocoding Helper ───────────────────────────────
        const geoCache = {};
        async function getAddress(lat, lng, callback) {
            const k = parseFloat(lat).toFixed(4) + ',' + parseFloat(lng).toFixed(4);
            if (geoCache[k]) { callback(geoCache[k]); return; }
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                const data = await res.json();
                if (data && data.address) {
                    const a = data.address;
                    
                    // 1. Get neighborhood/suburb
                    const neighborhoodName = a.neighbourhood || a.suburb || a.quarter || a.district || a.city_district || a.city || a.town || a.village || '';
                    
                    // 2. Get specific landmark/amenity (any key not in the ignore list)
                    const ignoreKeys = [
                      'road', 'street', 'house_number', 'house_name', 'postcode', 'country', 
                      'country_code', 'state', 'county', 'city', 'town', 'village', 'municipality', 
                      'city_district', 'district', 'quarter', 'suburb', 'neighbourhood', 'subdivision', 
                      'region', 'state_district', 'ISO3166-2-lvl4'
                    ];
                    
                    let landmarkName = '';
                    for (const key in a) {
                      if (ignoreKeys.includes(key)) continue;
                      const val = a[key];
                      if (val && val.toString().toLowerCase() !== 'yes' && val.toString().toLowerCase() !== 'no') {
                        landmarkName = val.toString();
                        break; // Use the first specific landmark
                      }
                    }
                    
                    let output = '';
                    if (neighborhoodName && landmarkName) {
                      output = `${neighborhoodName} (U dhow ${landmarkName})`;
                    } else if (neighborhoodName) {
                      output = neighborhoodName;
                    } else if (landmarkName) {
                      output = landmarkName;
                    } else {
                      output = data.display_name ? data.display_name.split(',')[0].trim() : 'Unknown';
                    }
                    
                    geoCache[k] = output;
                    callback(output);
                }
            } catch (e) { callback('Location details unavailable'); }
        }

        // ─── Map Engine ─────────────────────────────────────────────
        let map, markers = {}, routeLines = {};
        let activeUnits = []; // Store globally for Assign Modal

        const victimIcon = L.divIcon({
            className: '',
            html: `<div style="background:#ef4444;color:#ffffff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 3px rgba(239,68,68,0.2), 0 4px 12px rgba(239,68,68,0.3);border:2px solid #ffffff;animation:map-pulse 2s infinite;"></div>`,
            iconSize: [28, 28], iconAnchor: [14, 14]
        });

        const unitIcon = L.divIcon({
            className: '',
            html: `<div style="background:#ffffff;color:#3b82f6;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);border:2px solid #3b82f6;"><i class="fa fa-truck-medical" style="font-size:11px"></i></div>`,
            iconSize: [28, 28], iconAnchor: [14, 14]
        });

        const unitIconActive = L.divIcon({
            className: '',
            html: `<div style="background:#f59e0b;color:#ffffff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(245,158,11,0.2), 0 4px 12px rgba(245,158,11,0.4);border:2.5px solid #ffffff;animation:map-pulse 2s infinite;"><i class="fa fa-truck-fast" style="font-size:12px"></i></div>`,
            iconSize: [32, 32], iconAnchor: [16, 16]
        });

        const normalUserIcon = L.divIcon({
            className: '',
            html: `<div style="background:#10b981;color:#ffffff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.15);border:2px solid #ffffff;"><i class="fa fa-user" style="font-size:11px"></i></div>`,
            iconSize: [24, 24], iconAnchor: [12, 12]
        });

        const mapLayers = {
            std: L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }),
            sat: L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }),
            '3d': L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 })
        };

        function initMap() {
            map = L.map('master-map', { zoomControl: false, attributionControl: false, wheelPxPerZoomLevel: 60, layers: [mapLayers.std] }).setView([2.0469, 45.3182], 13);
            L.control.zoom({ position: 'bottomright' }).addTo(map);
        }

        function setMapLayer(type) {
            Object.keys(mapLayers).forEach(k => map.removeLayer(mapLayers[k]));
            map.addLayer(mapLayers[type]);
            document.querySelectorAll('.layer-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-layer-' + type).classList.add('active');
        }

        // ─── Data Polling ────────────────────────────────────────────
        function pollData() {
            fetch('../api/admin/get_fleet_data.php')
                .then(r => r.json())
                .then(d => {
                    if (d.status !== 'success') return;
                    activeUnits = d.units;
                    updateKPIs(d.stats, d.incidents);
                    updateQueue(d.incidents);
                    updateMapMarkers(d.incidents, d.units, d.all_users);
                    // Sound on new SOS
                    if (d.stats.pending > lastPendingCount && lastPendingCount !== null) {
                        playSosAlert();
                    }
                    lastPendingCount = d.stats.pending;
                }).catch(() => { });
        }

        function updateKPIs(s, incidents) {
            document.getElementById('stat-pending').textContent = s.pending;
            document.getElementById('stat-active').textContent = s.active;
            document.getElementById('stat-fleet').textContent = s.units_available;
            document.getElementById('stat-total').textContent = incidents.length;
            const qc = document.getElementById('queue-count');
            qc.textContent = s.pending > 0 ? s.pending + ' Urgent' : 'All Clear';
            qc.className = 'status-chip ' + (s.pending > 0 ? 'chip-pending' : 'chip-completed');
        }

        function updateQueue(incidents) {
            const tbody = document.getElementById('incident-queue-body');
            if (!incidents.length) {
                tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="fa fa-shield-halved"></i><p>No active incidents — All clear</p></div></td></tr>`;
                return;
            }
            tbody.innerHTML = incidents.map(i => {
                const time = new Date(i.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const chipClass = `chip-${i.status}`;
                
                let detailsHtml = '';
                if (i.description) {
                    detailsHtml += `<div style="font-size:0.78rem;color:#475569;font-style:italic;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${escHtml(i.description)}">"${escHtml(i.description)}"</div>`;
                }
                if (i.evidence_image) {
                    const imgs = i.evidence_image.split(',');
                    detailsHtml += `<div style="display:flex;gap:4px;margin-top:4px;">`;
                    imgs.forEach(img => {
                        img = img.trim();
                        if (img) {
                            const relPath = img.startsWith('uploads/') ? '../' + img : '../uploads/' + img;
                            detailsHtml += `<img src="${relPath}" onerror="if(!this.dataset.tried){this.dataset.tried=true;this.src=this.src.replace('/uploads/','/api/uploads/');}" style="width:28px;height:28px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.1);cursor:zoom-in;" onclick="window.open(this.src,'_blank')" title="Click to view">`;
                        }
                    });
                    detailsHtml += `</div>`;
                }
                if (!detailsHtml) {
                    detailsHtml = `<span style="color:#94a3b8;font-size:0.8rem;">None</span>`;
                }

                const action = i.status === 'pending'
                    ? `<div style="display:flex;gap:6px;justify-content:flex-end;">
                        <a href="incident.php?id=${i.id}" class="btn-cmd btn-view"><i class="fa fa-eye"></i> Details</a>
                        <button onclick="openAssignModal(${i.id}, ${i.lat || i.user_lat}, ${i.lng || i.user_lng}, '${escHtml(i.emergency_type)}')" class="btn-cmd btn-assign"><i class="fa fa-truck-medical"></i> Assign</button>
                       </div>`
                    : i.status === 'accepted'
                        ? `<a href="incident.php?id=${i.id}" class="btn-cmd btn-view"><i class="fa fa-eye"></i> Details</a>`
                        : `<div style="display:flex;gap:6px;justify-content:flex-end;">
                            <a href="incident.php?id=${i.id}" class="btn-cmd btn-view"><i class="fa fa-eye"></i> Details</a>
                            <span class="btn-cmd btn-dispatched"><i class="fa fa-check"></i> Dispatched</span>
                           </div>`;

                return `<tr>
            <td style="padding-left:28px"><strong style="font-size:0.9rem">${time}</strong></td>
            <td>
                <div class="victim-name">${escHtml(i.patient_name)}</div>
                <div class="victim-phone"><i class="fa fa-phone" style="margin-right:4px;opacity:0.5"></i>${escHtml(i.patient_phone)}</div>
            </td>
            <td><span class="emergency-tag">${escHtml(i.emergency_type)}</span></td>
            <td>${detailsHtml}</td>
            <td><span class="status-chip ${chipClass}">${i.status}</span></td>
            <td style="text-align:right;padding-right:28px">${action}</td>
        </tr>`;
            }).join('');
        }

        function escHtml(s) {
            if (!s) return '';
            return s.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function updateMapMarkers(incidents, units, all_users) {
            const activeIds = new Set();
            const seenUsers = new Set();



            incidents.forEach(i => {
                if (i.status === 'completed' || i.status === 'cancelled') return;
                if (seenUsers.has(i.user_id)) return;
                seenUsers.add(i.user_id);
                const vLat = i.user_lat || i.lat;
                const vLng = i.user_lng || i.lng;
                if (!vLat || !vLng) return;
                const mid = 'vic_' + i.user_id;
                activeIds.add(mid);

                let popupDetails = '';
                if (i.description) {
                    popupDetails += `<div style="font-size:11px;color:#475569;margin-top:4px;font-style:italic;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${escHtml(i.description)}">"${escHtml(i.description)}"</div>`;
                }
                if (i.evidence_image) {
                    const firstImg = i.evidence_image.split(',')[0].trim();
                    const relPath = firstImg.startsWith('uploads/') ? '../' + firstImg : '../uploads/' + firstImg;
                    popupDetails += `<img src="${relPath}" onerror="if(!this.dataset.tried){this.dataset.tried=true;this.src=this.src.replace('/uploads/','/api/uploads/');}" style="width:100%;height:60px;object-fit:cover;border-radius:6px;margin-top:6px;border:1px solid rgba(0,0,0,0.1);cursor:zoom-in;" onclick="window.open(this.src,'_blank')">`;
                }

                const popupHtml = (addr = 'Locating...') => `
            <div style="min-width:180px">
                <b style="color:#ef4444">⚠ ${escHtml(i.patient_name)}</b><br>
                <span style="font-size:12px;font-weight:700">${escHtml(i.emergency_type)}</span><br>
                <div style="font-size:11px;color:#3b82f6;margin:4px 0;line-height:1.3" id="addr_${mid}"><i class="fa fa-map-pin me-1"></i>${addr}</div>
                ${popupDetails}
                <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:10px;color:#94a3b8">${new Date(i.created_at).toLocaleTimeString()}</span>
                    <a href="incident.php?id=${i.id}" style="font-size:11px;font-weight:700;color:#3b82f6;text-decoration:none;">View Details →</a>
                </div>
            </div>`;

                if (markers[mid]) {
                    const currentPos = markers[mid].getLatLng();
                    if (currentPos.lat !== vLat || currentPos.lng !== vLng) {
                        markers[mid].setLatLng([vLat, vLng]);
                        getAddress(vLat, vLng, (addr) => {
                            const el = document.getElementById(`addr_${mid}`);
                            if (el) el.innerHTML = `<i class="fa fa-map-pin me-1"></i>${addr}`;
                        });
                    }
                } else {
                    markers[mid] = L.marker([vLat, vLng], { icon: victimIcon }).addTo(map)
                        .bindPopup(popupHtml());

                    markers[mid].on('popupopen', () => {
                        getAddress(vLat, vLng, (addr) => {
                            const el = document.getElementById(`addr_${mid}`);
                            if (el) el.innerHTML = `<i class="fa fa-map-pin me-1"></i>${addr}`;
                        });
                    });
                }
            });

            units.forEach(u => {
                if (!u.current_lat || u.status === 'offline') return;
                const mid = 'unit_' + u.id;
                activeIds.add(mid);

                const isBusy = (u.status !== 'available');
                const iconToUse = isBusy ? unitIconActive : unitIcon;
                const statusColor = isBusy ? '#f59e0b' : '#3b82f6';

                const targetPos = [parseFloat(u.current_lat), parseFloat(u.current_lng)];

                if (markers[mid]) {
                    // Smoothly slide the marker if it's already on the map
                    const currentPos = markers[mid].getLatLng();
                    if (currentPos.lat !== targetPos[0] || currentPos.lng !== targetPos[1]) {
                        // A simple CSS transition on the leaflet-marker-icon class usually works,
                        // but we'll use Leaflet's setLatLng.
                        markers[mid].setLatLng(targetPos);
                    }
                    markers[mid].setIcon(iconToUse);
                } else {
                    let avatarHtml = `<div class="marker-init">${(u.driver_name || '?').charAt(0).toUpperCase()}</div>`;
                    if (u.driver_image) {
                        avatarHtml = `<img src="../${escHtml(u.driver_image)}" class="marker-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.driver_name)}&background=3b82f6&color=fff'">`;
                    }

                    markers[mid] = L.marker(targetPos, { icon: iconToUse }).addTo(map)
                        .bindPopup(`
                    <div style="display:flex; align-items:center; gap:10px; min-width:160px;">
                        ${avatarHtml}
                        <div>
                            <b style="color:${statusColor}">${isBusy ? '🚀' : '🚑'} ${escHtml(u.unit_name)}</b><br>
                            <span style="font-size:11px;color:#64748b">${escHtml(u.driver_name)}</span><br>
                            <span style="font-size:10px;font-weight:700;color:${statusColor}">${
                                u.status === 'available' ? '🟢 ONLINE' :
                                u.status === 'offline'   ? '⚫ OFFLINE' :
                                                          '🟡 ON MISSION'
                            }</span>
                        </div>
                    </div>`);
                }
            });

            // Draw mission lines
            const activeLines = new Set();
            incidents.forEach(i => {
                if (i.status === 'accepted' && i.assigned_unit_id) {
                    const unit = units.find(u => u.id == i.assigned_unit_id);
                    if (unit && unit.current_lat) {
                        const lid = 'line_' + i.id;
                        activeLines.add(lid);
                        const vLat = i.user_lat || i.lat;
                        const vLng = i.user_lng || i.lng;
                        const pts = [[parseFloat(unit.current_lat), parseFloat(unit.current_lng)], [parseFloat(vLat), parseFloat(vLng)]];
                        if (routeLines[lid]) { routeLines[lid].setLatLngs(pts); }
                        else { routeLines[lid] = L.polyline(pts, { color: '#3b82f6', weight: 2, dashArray: '6,10', opacity: 0.5 }).addTo(map); }
                    }
                }
            });

            // Prune stale
            Object.keys(markers).forEach(k => { if (!activeIds.has(k)) { map.removeLayer(markers[k]); delete markers[k]; } });
            Object.keys(routeLines).forEach(k => { if (!activeLines.has(k)) { map.removeLayer(routeLines[k]); delete routeLines[k]; } });
        }

        initMap();
        pollData(); // Initial load

        // Configurable refresh rate (default 7s). Admin can change in Settings.
        const refreshSeconds = <?= (int) $refresh_rate ?>;
        setInterval(pollData, refreshSeconds * 1000);

        // Removed local logic in favor of global assign_modal.php

        // CSS injection for map pulse
        const style = document.createElement('style');
        style.textContent = `@keyframes map-pulse {0%,100%{box-shadow:0 0 0 4px rgba(239,68,68,0.25),0 4px 12px rgba(239,68,68,0.4);}50%{box-shadow:0 0 0 10px rgba(239,68,68,0),0 4px 12px rgba(239,68,68,0.2);}}`;
        document.head.appendChild(style);
    </script>
</body>

</html>