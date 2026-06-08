<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

// Mark all read action
if (isset($_POST['mark_all_read'])) {
    // In a real system you'd update a notifications table. 
    // Here we just redirect back with a flash message.
    $_SESSION['notif_flash'] = 'All notifications marked as read.';
    header("Location: notifications.php"); exit();
}

// Fetch recent SOS alerts (last 50)
$q = "SELECT r.id, r.status, r.emergency_type, r.created_at, r.lat, r.lng,
             u.fullname as patient_name, u.phone as patient_phone
      FROM rescue_requests r
      JOIN users u ON r.user_id = u.id
      ORDER BY r.created_at DESC LIMIT 50";
$res = mysqli_query($conn, $q);
$alerts = [];
while ($row = mysqli_fetch_assoc($res)) $alerts[] = $row;

$new_count = count(array_filter($alerts, fn($a) => $a['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
.main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}

.notif-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;}
.notif-count-badge{
    display:inline-flex;align-items:center;gap:8px;
    padding:8px 18px;border-radius:50px;
    background:rgba(239,68,68,0.1);color:#ef4444;
    font-size:0.8rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;
}
.notif-actions{display:flex;align-items:center;gap:10px;}
.btn-mark-read{
    display:inline-flex;align-items:center;gap:7px;
    padding:10px 20px;border-radius:10px;
    border:none;cursor:pointer;font-family:'Outfit',sans-serif;
    font-size:0.82rem;font-weight:700;
    background:var(--card-bg);color:var(--text-muted);
    border:1px solid rgba(0,0,0,0.08);transition:0.2s;
}
.btn-mark-read:hover{background:#e2e8f0;color:var(--text);}

.sound-toggle-row{
    display:flex;align-items:center;gap:10px;
    padding:12px 18px;border-radius:12px;
    background:var(--card-bg);border:1px solid rgba(0,0,0,0.06);
    font-size:0.82rem;font-weight:700;color:var(--text-muted);
    box-shadow:var(--shadow);
}
.toggle-switch{position:relative;width:42px;height:24px;cursor:pointer;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{
    position:absolute;inset:0;background:#cbd5e1;border-radius:50px;transition:0.3s;
}
.toggle-slider::before{
    content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;
    background:white;border-radius:50%;transition:0.3s;box-shadow:0 1px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-slider{background:#22c55e;}
.toggle-switch input:checked + .toggle-slider::before{transform:translateX(18px);}

.panel{background:var(--card-bg);border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow:hidden;}

.notif-item{
    display:flex;align-items:flex-start;gap:16px;
    padding:18px 24px;border-bottom:1px solid rgba(0,0,0,0.04);
    transition:background 0.15s;
    position:relative;
}
.notif-item:last-child{border-bottom:none;}
.notif-item:hover{background:#f8fafc;}
.notif-item.unread::before{
    content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
    background:var(--accent);border-radius:0 2px 2px 0;
}
.notif-icon{
    width:44px;height:44px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;flex-shrink:0;
}
.notif-icon.pending{background:rgba(239,68,68,0.1);color:#ef4444;}
.notif-icon.accepted{background:rgba(59,130,246,0.1);color:#3b82f6;}
.notif-icon.completed{background:rgba(34,197,94,0.1);color:#22c55e;}
.notif-icon.cancelled{background:rgba(100,116,139,0.1);color:#94a3b8;}
.notif-body{flex:1;}
.notif-title{font-size:0.9rem;font-weight:800;margin-bottom:3px;}
.notif-sub{font-size:0.78rem;color:var(--text-muted);}
.notif-time{font-size:0.72rem;font-weight:700;color:var(--text-muted);white-space:nowrap;margin-top:3px;}
.notif-action-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 14px;border-radius:8px;
    font-size:0.75rem;font-weight:700;text-decoration:none;
    background:#f1f5f9;color:#475569;border:1px solid rgba(0,0,0,0.08);
    transition:0.2s;white-space:nowrap;
}
.notif-action-btn:hover{background:var(--accent);color:white;border-color:var(--accent);}
.notif-action-btn.urgent{background:rgba(239,68,68,0.08);color:#ef4444;border-color:rgba(239,68,68,0.15);}
.notif-action-btn.urgent:hover{background:#ef4444;color:white;}

.flash-msg{
    display:flex;align-items:center;gap:10px;
    padding:14px 20px;border-radius:12px;
    background:rgba(34,197,94,0.1);color:#15803d;
    border:1px solid rgba(34,197,94,0.15);
    font-size:0.85rem;font-weight:700;margin-bottom:20px;
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = 'Notifications'; $page_subtitle = 'Center'; include 'includes/topbar.php'; ?>

<?php if (isset($_SESSION['notif_flash'])): ?>
<div class="flash-msg"><i class="fa fa-circle-check"></i><?= $_SESSION['notif_flash'] ?></div>
<?php unset($_SESSION['notif_flash']); endif; ?>

<div class="notif-toolbar">
    <div style="display:flex;align-items:center;gap:12px">
        <?php if ($new_count > 0): ?>
        <div class="notif-count-badge">
            <span style="width:8px;height:8px;background:#ef4444;border-radius:50%;animation:pulse-badge 1.5s infinite"></span>
            <?= $new_count ?> Urgent Alert<?= $new_count > 1 ? 's' : '' ?>
        </div>
        <?php endif; ?>
        <span style="font-size:0.8rem;font-weight:600;color:var(--text-muted)"><?= count($alerts) ?> total alerts</span>
    </div>
    <div class="notif-actions">
        <div class="sound-toggle-row">
            <i class="fa fa-bell" style="color:var(--accent)"></i>
            <span>Sound Alerts</span>
            <label class="toggle-switch">
                <input type="checkbox" id="sound-check" onchange="toggleSoundPref(this)" <?= (localStorage_read() ? 'checked' : '') ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <form method="POST">
            <button type="submit" name="mark_all_read" class="btn-mark-read">
                <i class="fa fa-check-double"></i> Mark All Read
            </button>
        </form>
    </div>
</div>

<div class="panel">
<?php if (empty($alerts)): ?>
    <div style="text-align:center;padding:80px 24px;color:var(--text-muted)">
        <i class="fa fa-bell-slash" style="font-size:3rem;opacity:0.2;display:block;margin-bottom:12px"></i>
        <p style="font-size:0.9rem;font-weight:600">No alerts yet. SOS activations will appear here.</p>
    </div>
<?php endif; ?>
<?php foreach ($alerts as $a):
    $isPending = $a['status'] === 'pending';
    $iconClass = $a['status'];
    $icons = ['pending'=>'fa-triangle-exclamation','accepted'=>'fa-truck-medical','completed'=>'fa-circle-check','cancelled'=>'fa-ban'];
    $icon = $icons[$a['status']] ?? 'fa-bell';
    $timeAgo = human_time_diff(strtotime($a['created_at']));
?>
<div class="notif-item <?= $isPending ? 'unread' : '' ?>">
    <div class="notif-icon <?= $iconClass ?>"><i class="fa <?= $icon ?>"></i></div>
    <div class="notif-body">
        <div class="notif-title">
            <?php if ($isPending): ?>⚠ New SOS from <?= htmlspecialchars($a['patient_name']) ?>
            <?php elseif ($a['status'] === 'accepted'): ?>Mission Accepted — <?= htmlspecialchars($a['patient_name']) ?>
            <?php elseif ($a['status'] === 'completed'): ?>Mission Completed — <?= htmlspecialchars($a['patient_name']) ?>
            <?php else: ?>Cancelled — <?= htmlspecialchars($a['patient_name']) ?>
            <?php endif; ?>
        </div>
        <div class="notif-sub">
            <i class="fa fa-tag" style="margin-right:4px;opacity:0.5"></i><?= htmlspecialchars($a['emergency_type'] ?? 'Unknown') ?>
            &nbsp;·&nbsp;
            <i class="fa fa-phone" style="margin-right:4px;opacity:0.5"></i><?= htmlspecialchars($a['patient_phone']) ?>
        </div>
        <div class="notif-time"><i class="fa fa-clock" style="margin-right:4px"></i><?= $timeAgo ?></div>
    </div>
    <?php if ($isPending): ?>
    <a href="javascript:void(0)" onclick="openAssignModal(<?= $a['id'] ?>, <?= floatval($a['lat']) ?>, <?= floatval($a['lng']) ?>, '<?= addslashes(htmlspecialchars($a['emergency_type'])) ?>')" class="notif-action-btn urgent"><i class="fa fa-truck-medical"></i> Assign</a>
    <?php else: ?>
    <a href="incident.php?id=<?= $a['id'] ?>" class="notif-action-btn"><i class="fa fa-eye"></i> View</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sound preference
document.addEventListener('DOMContentLoaded', () => {
    const cb = document.getElementById('sound-check');
    if (cb) cb.checked = localStorage.getItem('sos_sound') !== 'off';
});
function toggleSoundPref(el) {
    localStorage.setItem('sos_sound', el.checked ? 'on' : 'off');
}
// Real-time new alert polling
let lastCount = <?= $new_count ?>;
function pollAlerts() {
    fetch('../api/admin/get_fleet_data.php').then(r=>r.json()).then(d=>{
        if(d.status==='success' && d.stats.pending > lastCount) {
            if(localStorage.getItem('sos_sound')!=='off') {
                try{const ctx=new AudioContext();const o=ctx.createOscillator();const g=ctx.createGain();o.connect(g);g.connect(ctx.destination);o.frequency.value=880;g.gain.setValueAtTime(0.4,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.3);o.start();o.stop(ctx.currentTime+0.3);}catch(e){}
            }
            lastCount = d.stats.pending;
            setTimeout(()=>location.reload(),1500);
        }
    }).catch(()=>{});
}
setInterval(pollAlerts, 5000);
@keyframes pulse-badge{0%,100%{opacity:1;}50%{opacity:0.5;}}
</script>
<?php
function human_time_diff($time) {
    $diff = time() - $time;
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}
function localStorage_read() { return true; } // default on
?>
<?php include 'includes/assign_modal.php'; ?>
</body>
</html>
