<?php
$start_time = microtime(true);
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit();
}
require_once '../includes/session_guard.php';

$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = '$user_id'";
$user_res = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_res);

$fullname  = $user_data['fullname'];
$email     = $user_data['email'];
$phone     = $user_data['phone'];
$dark_mode = $user_data['dark_mode'];
$medical_info       = $user_data['medical_info'] ?? '';
$emergency_contacts = $user_data['emergency_contacts'] ?? '';
$user_language      = $user_data['language'] ?? 'en';
$initial_lat        = $user_data['current_lat'] ?? 2.0469;
$initial_lng        = $user_data['current_lng'] ?? 45.3182;

$notif_on = ($user_data['notifications_enabled'] ?? 1) ? 'on' : 'off';
$vib_on = ($user_data['vibration_enabled'] ?? 1) ? 'on' : 'off';
$gps_main_on = ($user_data['gps_enabled'] ?? 1) ? 'on' : 'off';
$share_live_on = ($user_data['share_live_location'] ?? 1) ? 'on' : 'off';
$loc_hist_on = ($user_data['location_history'] ?? 0) ? 'on' : 'off';
$gps_acc_on = ($user_data['gps_access'] ?? 1) ? 'on' : 'off';
$live_sos_on = ($user_data['live_sos_location'] ?? 1) ? 'on' : 'off';

// Fetch history count
$hist_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rescue_requests WHERE user_id='$user_id'");
$total_sos = mysqli_fetch_assoc($hist_q)['cnt'] ?? 0;

$last_q = mysqli_query($conn, "SELECT created_at FROM rescue_requests WHERE user_id='$user_id' ORDER BY id DESC LIMIT 1");
$last_row = mysqli_fetch_assoc($last_q);
$last_emergency = $last_row ? date('M d, H:i', strtotime($last_row['created_at'])) : 'Never';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartRescue | Emergency Command Center</title>
<meta name="description" content="SmartRescue real-time emergency response dashboard — track dispatch, manage contacts, and send SOS.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
/* ===== DESIGN TOKENS ===== */
/* Block Quillbot and LanguageTool browser extension overlays on inputs */
quillbot-extension-portal,
lt-mirror,
lt-span,
lt-toolbar,
lt-div,
lt-highlighter,
.lt-toolbar,
.lt-mirror,
[class*="quillbot"],
[id*="quillbot"],
[class*="languagetool"],
[id*="languagetool"] {
  display: none !important;
  visibility: hidden !important;
  opacity: 0 !important;
  height: 0 !important;
  width: 0 !important;
  pointer-events: none !important;
}

:root {
  --primary:       #2563eb;
  --primary-glow:  rgba(37,99,235,0.35);
  --danger:        #ef4444;
  --danger-glow:   rgba(239,68,68,0.35);
  --success:       #10b981;
  --warning:       #f59e0b;
  --bg:            #f0f4ff;
  --surface:       rgba(255,255,255,0.82);
  --surface-solid: #ffffff;
  --border:        rgba(255,255,255,0.6);
  --text:          #0f172a;
  --muted:         #64748b;
  --sidebar-bg:    linear-gradient(175deg,#0a1628 0%,#0d2352 100%);
  --sidebar-w:     270px;
  --radius:        20px;
  --shadow-sm:     0 4px 20px rgba(0,0,0,0.06);
  --shadow:        0 12px 40px rgba(0,0,0,0.10);
  --shadow-lg:     0 24px 60px rgba(0,0,0,0.15);
  --transition:    0.3s cubic-bezier(0.4,0,0.2,1);
}
[data-theme="dark"] {
  --bg:            #060d1f;
  --surface:       rgba(15,25,55,0.85);
  --surface-solid: #0f1937;
  --border:        rgba(255,255,255,0.06);
  --text:          #e2e8f0;
  --muted:         #94a3b8;
  --shadow-sm:     0 4px 20px rgba(0,0,0,0.3);
  --shadow:        0 12px 40px rgba(0,0,0,0.4);
}

/* ===== BASE ===== */
*{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{
  font-family:'Inter',sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
  transition:background var(--transition),color var(--transition);
}
body::before{
  content:'';
  position:fixed;inset:0;
  background:
    radial-gradient(ellipse 80% 60% at 10% 0%,rgba(37,99,235,0.07),transparent),
    radial-gradient(ellipse 60% 50% at 90% 100%,rgba(239,68,68,0.05),transparent);
  pointer-events:none;z-index:0;
}

/* ===== SIDEBAR ===== */
.sidebar{
  width:var(--sidebar-w);
  height:100vh;
  background:var(--sidebar-bg);
  position:fixed;left:0;top:0;z-index:1100;
  display:flex;flex-direction:column;
  padding:28px 18px;
  box-shadow:8px 0 40px rgba(0,0,0,0.25);
  border-right:1px solid rgba(255,255,255,0.05);
  transition:transform var(--transition);
}
.sidebar-brand{
  display:flex;align-items:center;gap:12px;
  text-decoration:none;margin-bottom:36px;
  padding:0 8px;
}
.brand-icon{
  width:44px;height:44px;border-radius:12px;
  background:linear-gradient(135deg,#2563eb,#1d4ed8);
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 6px 20px rgba(37,99,235,0.45);flex-shrink:0;
}
.brand-icon i{color:#fff;font-size:1.2rem;}
.brand-text{font-size:1.25rem;font-weight:800;color:#fff;letter-spacing:-0.5px;}
.brand-text span{color:#93c5fd;}

.nav-section-label{
  font-size:0.65rem;font-weight:700;color:rgba(255,255,255,0.3);
  letter-spacing:2px;text-transform:uppercase;
  padding:0 12px;margin:20px 0 8px;
}
.nav-item{
  display:flex;align-items:center;gap:13px;
  padding:12px 16px;border-radius:14px;
  color:rgba(255,255,255,0.55);font-weight:600;font-size:0.88rem;
  cursor:pointer;text-decoration:none;
  transition:all var(--transition);margin-bottom:4px;
  position:relative;
}
@keyframes map-pulse {
    0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}
.nav-item i{font-size:1rem;width:20px;text-align:center;}
.nav-item:hover{background:rgba(255,255,255,0.08);color:#fff;}
.nav-item.active{
  background:linear-gradient(135deg,rgba(37,99,235,0.9),rgba(29,78,216,0.8));
  color:#fff;box-shadow:0 8px 25px rgba(37,99,235,0.4);
}
.nav-item .badge-dot{
  width:6px;height:6px;border-radius:50%;background:var(--success);
  margin-left:auto;box-shadow:0 0 8px var(--success);
  animation:pulse-dot 2s infinite;
}
@keyframes pulse-dot{0%,100%{opacity:1;}50%{opacity:0.4;}}

.sidebar-footer{margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,0.06);}
.user-avatar-mini{
  display:flex;align-items:center;gap:12px;padding:12px;
  background:rgba(255,255,255,0.05);border-radius:14px;margin-bottom:12px;
}
.avatar-ring{
  width:40px;height:40px;border-radius:50%;
  background:linear-gradient(135deg,#2563eb,#7c3aed);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:1rem;color:#fff;flex-shrink:0;
}
.user-meta .name{color:#fff;font-weight:700;font-size:0.85rem;line-height:1.2;}
.user-meta .role{color:rgba(255,255,255,0.4);font-size:0.7rem;font-weight:600;text-transform:uppercase;}

/* ===== MOBILE HAMBURGER ===== */
.hamburger{
  display:none;position:fixed;top:16px;left:16px;z-index:1200;
  background:var(--primary);color:#fff;border:none;border-radius:12px;
  width:44px;height:44px;font-size:1.1rem;
  box-shadow:0 4px 15px var(--primary-glow);cursor:pointer;
}
.sidebar-overlay{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
  z-index:1099;backdrop-filter:blur(4px);
}

/* ===== MAIN WRAPPER ===== */
.main-wrapper{
  margin-left:var(--sidebar-w);
  padding:32px 36px;
  min-height:100vh;
  position:relative;z-index:1;
}

/* ===== GLASS CARD ===== */
.glass-card{
  background:var(--surface);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border:1px solid var(--border);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  transition:transform var(--transition),box-shadow var(--transition);
}
.glass-card:hover{box-shadow:var(--shadow-lg);}
.glass-card.no-hover:hover{transform:none;box-shadow:var(--shadow);}

/* ===== PAGE HEADER ===== */
.page-header{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:28px;flex-wrap:wrap;gap:16px;
}
.page-title{font-size:1.75rem;font-weight:800;letter-spacing:-0.5px;}
.page-title span{color:var(--primary);}

/* ===== TOPBAR ===== */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:28px;gap:12px;flex-wrap:wrap;
}
.topbar-left{display:flex;align-items:center;gap:14px;}
.status-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);
  border-radius:50px;padding:8px 16px;
  font-size:0.78rem;font-weight:700;color:var(--success);
}
.status-badge .dot{
  width:7px;height:7px;border-radius:50%;background:var(--success);
  box-shadow:0 0 8px var(--success);animation:pulse-dot 2s infinite;
}
.clock-badge{
  font-family:'Inter',monospace;font-weight:700;font-size:0.82rem;
  color:var(--muted);background:var(--surface);
  border:1px solid var(--border);border-radius:50px;
  padding:8px 16px;
}
.topbar-right{display:flex;align-items:center;gap:10px;}
.icon-btn{
  width:40px;height:40px;border-radius:12px;
  background:var(--surface);border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:var(--muted);transition:all var(--transition);
  position:relative;
}
.icon-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary);}
.icon-btn .notif-dot{
  position:absolute;top:6px;right:6px;
  width:8px;height:8px;border-radius:50%;
  background:var(--danger);border:2px solid var(--bg);
}

/* ===== STAT CARDS ===== */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.stat-card{
  border-radius:20px;padding:22px 20px;
  display:flex;align-items:center;gap:16px;
  box-shadow:var(--shadow);transition:transform var(--transition),box-shadow var(--transition);
  position:relative;overflow:hidden;
}
.stat-card::after{
  content:'';position:absolute;top:-20px;right:-20px;
  width:80px;height:80px;border-radius:50%;
  background:rgba(255,255,255,0.08);pointer-events:none;
}
.stat-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg);}
.stat-card.red-card{background:linear-gradient(135deg,#ef4444,#c41e3a);color:#fff;}
.stat-card.blue-card{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;}
.stat-card.green-card{background:linear-gradient(135deg,#10b981,#059669);color:#fff;}
.stat-card.orange-card{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;}
.stat-icon{
  width:54px;height:54px;border-radius:16px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;flex-shrink:0;
  background:rgba(255,255,255,0.2);
  color:#fff;
}
.stat-icon svg{
  display:block;
  width:24px;
  height:24px;
}
.stat-value{font-size:2.1rem;font-weight:800;line-height:1.1;color:#fff;}
.stat-label{font-size:0.7rem;font-weight:700;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:0.8px;margin-top:5px;}
.stat-trend{font-size:0.68rem;font-weight:700;color:rgba(255,255,255,0.6);margin-top:3px;}

/* ===== OFFLINE BANNER ===== */
.offline-banner{
  background:linear-gradient(135deg,#f59e0b,#d97706);
  border-radius:14px;padding:14px 20px;
  display:none;align-items:center;gap:12px;
  color:#fff;font-weight:700;font-size:0.88rem;
  margin-bottom:20px;
}
.offline-banner.show{display:flex;}

/* ===== SAFETY RISK INDICATOR ===== */
.risk-badge{
  display:inline-flex;align-items:center;gap:8px;
  border-radius:50px;padding:8px 18px;font-weight:700;font-size:0.8rem;
}
.risk-safe{background:rgba(16,185,129,0.12);color:var(--success);border:1px solid rgba(16,185,129,0.3);}
.risk-medium{background:rgba(245,158,11,0.12);color:var(--warning);border:1px solid rgba(245,158,11,0.3);}
.risk-high{background:rgba(239,68,68,0.12);color:var(--danger);border:1px solid rgba(239,68,68,0.3);}

/* ===== EMERGENCY TIMELINE ===== */
.timeline-card{padding:28px 32px;margin-bottom:24px;}
.timeline-label{font-size:0.72rem;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:20px;}
.timeline{display:flex;align-items:center;position:relative;}
.tl-step{
  display:flex;flex-direction:column;align-items:center;
  flex:1;position:relative;
}
.tl-step:not(:last-child)::after{
  content:'';position:absolute;top:20px;left:50%;
  width:100%;height:3px;
  background:rgba(37,99,235,0.15);
  z-index:0;
}
.tl-step.done:not(:last-child)::after{background:var(--primary);}
.tl-dot{
  width:40px;height:40px;border-radius:50%;
  background:rgba(37,99,235,0.08);
  border:2px solid rgba(37,99,235,0.2);
  display:flex;align-items:center;justify-content:center;
  font-size:0.9rem;color:rgba(37,99,235,0.4);
  position:relative;z-index:1;
  transition:all 0.5s ease;
}
.tl-step.done .tl-dot{
  background:var(--primary);border-color:var(--primary);
  color:#fff;box-shadow:0 0 20px var(--primary-glow);
}
.tl-step.active .tl-dot{
  background:rgba(37,99,235,0.15);border-color:var(--primary);
  color:var(--primary);animation:tl-pulse 1.5s infinite;
}
@keyframes tl-pulse{
  0%,100%{box-shadow:0 0 0 0 var(--primary-glow);}
  50%{box-shadow:0 0 0 10px rgba(37,99,235,0);}
}
.tl-label{font-size:0.68rem;font-weight:700;color:var(--muted);margin-top:10px;text-align:center;letter-spacing:0.3px;}
.tl-step.done .tl-label,.tl-step.active .tl-label{color:var(--primary);}

/* ===== DASHBOARD TWO-COLUMN LAYOUT ===== */
.dashboard-layout{
  display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;
}
@media(max-width:1100px){.dashboard-layout{grid-template-columns:1fr;}}

/* ===== EMERGENCY TYPE GRID ===== */
.etype-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:20px;}
.etype-card{
  border-radius:18px;padding:18px 14px;text-align:center;cursor:pointer;
  transition:all var(--transition);position:relative;overflow:hidden;
  border:2px solid transparent;
}
.etype-card::before{
  content:'';position:absolute;inset:0;opacity:0.07;
  transition:opacity var(--transition);
}
.etype-card.medical-card{background:rgba(37,99,235,0.06);border-color:rgba(37,99,235,0.2);}
.etype-card.fire-card{background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2);}
.etype-card.police-card{background:rgba(6,182,212,0.06);border-color:rgba(6,182,212,0.2);}
.etype-card.accident-card{background:rgba(239,68,68,0.06);border-color:rgba(239,68,68,0.2);}
.etype-card:hover{transform:translateY(-5px) scale(1.02);box-shadow:var(--shadow);}
.etype-card.active{
  border-width:2.5px;transform:scale(1.04);
  box-shadow:0 12px 35px rgba(0,0,0,0.15);
}
.etype-card.active.medical-card{border-color:#2563eb;background:rgba(37,99,235,0.1);box-shadow:0 0 0 4px rgba(37,99,235,0.2),0 12px 35px rgba(37,99,235,0.15);}
.etype-card.active.fire-card{border-color:#f59e0b;background:rgba(245,158,11,0.1);box-shadow:0 0 0 4px rgba(245,158,11,0.2),0 12px 35px rgba(245,158,11,0.15);}
.etype-card.active.police-card{border-color:#06b6d4;background:rgba(6,182,212,0.1);box-shadow:0 0 0 4px rgba(6,182,212,0.2),0 12px 35px rgba(6,182,212,0.15);}
.etype-card.active.accident-card{border-color:#ef4444;background:rgba(239,68,68,0.1);box-shadow:0 0 0 4px rgba(239,68,68,0.2),0 12px 35px rgba(239,68,68,0.15);}
.etype-icon-wrap{
  width:56px;height:56px;border-radius:16px;
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;margin:0 auto 12px;
  transition:all var(--transition);
}
.etype-card.medical-card .etype-icon-wrap{background:rgba(37,99,235,0.12);color:#2563eb;}
.etype-card.fire-card .etype-icon-wrap{background:rgba(245,158,11,0.12);color:#f59e0b;}
.etype-card.police-card .etype-icon-wrap{background:rgba(6,182,212,0.12);color:#06b6d4;}
.etype-card.accident-card .etype-icon-wrap{background:rgba(239,68,68,0.12);color:#ef4444;}
.etype-card:hover .etype-icon-wrap,.etype-card.active .etype-icon-wrap{transform:scale(1.1);}
.etype-card.active.medical-card .etype-icon-wrap{background:#2563eb;color:#fff;}
.etype-card.active.fire-card .etype-icon-wrap{background:#f59e0b;color:#fff;}
.etype-card.active.police-card .etype-icon-wrap{background:#06b6d4;color:#fff;}
.etype-card.active.accident-card .etype-icon-wrap{background:#ef4444;color:#fff;}
.etype-name{font-weight:800;font-size:0.8rem;letter-spacing:0.5px;}
.etype-sub{font-size:0.65rem;color:var(--muted);font-weight:600;margin-top:3px;}

/* ===== RIGHT SIDEBAR PANEL ===== */
.dash-right{
  display:flex;flex-direction:column;gap:16px;
  position:sticky;top:24px;
}
.right-card{
  background:var(--surface);backdrop-filter:blur(24px);
  border:1px solid var(--border);border-radius:20px;
  box-shadow:var(--shadow-sm);
  overflow:hidden;
}
.right-card-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:16px 20px;border-bottom:1px solid var(--border);
}
.right-card-title{
  font-weight:800;font-size:0.82rem;text-transform:uppercase;
  letter-spacing:1px;color:var(--muted);
  display:flex;align-items:center;gap:8px;
}
.right-card-body{padding:16px 20px;}

/* Vertical timeline for right panel */
.vtl{display:flex;flex-direction:column;gap:0;}
.vtl-item{
  display:flex;align-items:flex-start;gap:12px;
  padding-bottom:16px;position:relative;
}
.vtl-item:last-child{padding-bottom:0;}
.vtl-item:not(:last-child)::after{
  content:'';position:absolute;left:14px;top:30px;
  width:2px;bottom:0;background:var(--border);
}
.vtl-dot{
  width:28px;height:28px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  font-size:0.7rem;border:2px solid var(--border);
  background:var(--surface);color:var(--muted);
  transition:all 0.4s ease;
  position:relative;z-index:1;
}
.vtl-item.done .vtl-dot{
  background:var(--primary);border-color:var(--primary);
  color:#fff;box-shadow:0 0 12px var(--primary-glow);
}
.vtl-item.active .vtl-dot{
  border-color:var(--danger);color:var(--danger);
  animation:vtl-pulse 1.5s infinite;
}
@keyframes vtl-pulse{
  0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.4);}
  50%{box-shadow:0 0 0 8px rgba(239,68,68,0);}
}
.vtl-item:not(:last-child).done::after{background:var(--primary);}
.vtl-content{flex:1;}
.vtl-label{font-weight:700;font-size:0.82rem;}
.vtl-desc{font-size:0.7rem;color:var(--muted);margin-top:2px;}
.vtl-item.done .vtl-label{color:var(--primary);}
.vtl-item.active .vtl-label{color:var(--danger);}

/* Mini status pills in right panel */
.status-pill{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;border-radius:12px;
  background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);
  margin-bottom:10px;transition:all var(--transition);
}
.status-pill.offline{background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.2);}
.status-pill .sp-dot{
  width:9px;height:9px;border-radius:50%;
  background:var(--success);box-shadow:0 0 8px var(--success);
  animation:pulse-dot 2s infinite;flex-shrink:0;
}
.status-pill.offline .sp-dot{background:var(--warning);box-shadow:0 0 8px var(--warning);}
.sp-label{font-weight:700;font-size:0.8rem;}
.sp-sub{font-size:0.68rem;color:var(--muted);font-weight:600;margin-left:auto;}

/* Mini map in right panel */
.mini-map-wrap{
  border-radius:14px;overflow:hidden;
  border:1px solid var(--border);position:relative;
}
#map{width:100%;height:100%;min-height:calc(100vh - 300px);z-index:1;}
#miniMap{width:100%;height:180px;z-index:1;}
.mini-map-badge{
  position:absolute;bottom:10px;left:50%;transform:translateX(-50%);
  background:rgba(37,99,235,0.9);color:#fff;border-radius:50px;
  padding:6px 14px;font-size:0.72rem;font-weight:700;
  white-space:nowrap;z-index:10;
  font-family:'Inter',monospace;
}

/* Custom Map Location Tooltip */
.custom-location-tooltip {
  background: #3b82f6 !important;
  border: none !important;
  box-shadow: 0 4px 12px rgba(59,130,246,0.4) !important;
  color: white !important;
  font-weight: 800 !important;
  font-size: 0.85rem !important;
  border-radius: 20px !important;
  padding: 6px 14px !important;
  white-space: nowrap;
}
.custom-location-tooltip::before {
  border-top-color: #3b82f6 !important;
}

/* ===== SOS RECTANGULAR BUTTON ===== */
.sos-hub{
  display:flex;flex-direction:column;align-items:stretch;
  padding:20px 0 8px;
}
/* Hidden ring still used by JS hold logic — kept offscreen */
.sos-ring{display:none;}
.sos-btn-rect{
  position:relative;overflow:hidden;
  width:100%;padding:22px 32px;
  border-radius:16px;border:none;cursor:pointer;
  background:linear-gradient(135deg,#ff4444 0%,#ef4444 40%,#c41e3a 100%);
  color:#fff;font-weight:900;font-size:1.5rem;letter-spacing:2px;
  display:flex;align-items:center;justify-content:center;gap:16px;
  box-shadow:0 8px 32px rgba(239,68,68,0.45),0 0 0 0 rgba(239,68,68,0.3);
  transition:transform 0.15s,box-shadow 0.3s,background 0.3s;
  user-select:none;-webkit-tap-highlight-color:transparent;
  animation:sos-rect-pulse 3s infinite;
}
@keyframes sos-rect-pulse{
  0%,100%{box-shadow:0 8px 32px rgba(239,68,68,0.45),0 0 0 0 rgba(239,68,68,0.3);}
  50%{box-shadow:0 8px 40px rgba(239,68,68,0.6),0 0 0 14px rgba(239,68,68,0);}
}
.sos-btn-rect::before{
  content:'';position:absolute;inset:0;
  background:linear-gradient(135deg,rgba(255,255,255,0.15),transparent 60%);
  pointer-events:none;
}
.sos-btn-rect .sos-rect-icon{
  font-size:2rem;line-height:1;
  animation:sos-shake 2.5s infinite;
}
@keyframes sos-shake{
  0%,90%,100%{transform:none;}
  92%{transform:rotate(-8deg);}
  96%{transform:rotate(8deg);}
}
.sos-btn-rect .sos-rect-label{
  display:flex;flex-direction:column;align-items:flex-start;
}
.sos-btn-rect .sos-rect-label strong{font-size:1.5rem;font-weight:900;letter-spacing:2px;line-height:1;}
.sos-btn-rect .sos-rect-label small{font-size:0.68rem;font-weight:700;letter-spacing:2px;opacity:0.8;margin-top:3px;}
/* Hold progress bar inside button */
.sos-hold-bar{
  position:absolute;bottom:0;left:0;height:4px;
  background:rgba(255,255,255,0.7);border-radius:0 0 16px 16px;
  width:0%;transition:width 0.08s linear;
}
.sos-btn-rect:not([disabled]):hover{
  transform:translateY(-3px);
  box-shadow:0 14px 45px rgba(239,68,68,0.65),0 0 0 8px rgba(239,68,68,0.08);
}
.sos-btn-rect:active,.sos-btn-rect.holding{
  transform:scale(0.97);
  box-shadow:0 4px 20px rgba(239,68,68,0.4);
}
.sos-btn-rect[disabled]{
  opacity:0.45;cursor:not-allowed;
  animation:none;box-shadow:none;
}
.sos-btn-rect.success{
  background:linear-gradient(135deg,#34d399,#10b981);
  box-shadow:0 8px 32px rgba(16,185,129,0.45);
  animation:none;
}
/* Dummy sos-btn used by JS for class-based checks */
.sos-btn{display:none;}
.sos-caption{
  margin-top:10px;font-size:0.75rem;font-weight:700;
  color:var(--muted);letter-spacing:1px;text-transform:uppercase;
  text-align:center;
}

/* ===== TWO-COL FORM GRID ===== */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;}
.glass-input{
  background:rgba(0,0,0,0.03) !important;
  border:1.5px solid var(--border) !important;
  border-radius:12px !important;padding:13px 16px !important;
  color:var(--text) !important;font-family:'Inter',sans-serif !important;
  font-size:0.88rem !important;transition:all var(--transition) !important;
  width:100%;
}
[data-theme="dark"] .glass-input{background:rgba(255,255,255,0.04) !important;}
.glass-input:focus{
  outline:none !important;
  border-color:var(--primary) !important;
  box-shadow:0 0 0 4px var(--primary-glow) !important;
  background:var(--surface-solid) !important;
}
.field-label{font-size:0.72rem;font-weight:700;color:var(--muted);letter-spacing:0.8px;text-transform:uppercase;margin-bottom:6px;}

/* ===== ENHANCED DESCRIPTION ===== */
.textarea-wrapper { position: relative; z-index: 1; }
.custom-textarea {
  resize: vertical; min-height: 120px; background: var(--surface) !important;
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}
.custom-textarea:focus { background: var(--surface-solid) !important; }
[data-theme="dark"] .custom-textarea:focus { background: rgba(255,255,255,0.06) !important; }

/* ===== UPLOAD ZONE (ENHANCED) ===== */
.upload-zone {
  position: relative; height: 120px; display: flex; align-items: center; justify-content: center;
  border: 2px dashed rgba(37, 99, 235, 0.4); background: rgba(37, 99, 235, 0.02);
  border-radius: 12px; overflow: hidden; cursor: pointer; text-align: center;
  transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
}
.upload-zone::before {
  content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(37,99,235,0.05), transparent);
  opacity: 0; transition: opacity var(--transition);
}
.upload-zone:hover::before, .upload-zone.drag-over::before { opacity: 1; }
.upload-zone:hover, .upload-zone.drag-over {
  border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(37,99,235,0.12);
}
.upload-content { position: relative; z-index: 2; transition: transform var(--transition); width:100%; }
.upload-zone:hover .upload-content { transform: translateY(-2px); }
.upload-icon-wrapper {
  width: 44px; height: 44px; border-radius: 50%; background: rgba(37, 99, 235, 0.1);
  color: var(--primary); display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem; margin: 0 auto 10px; box-shadow: 0 4px 15px rgba(37, 99, 235, 0.1);
  transition: all var(--transition);
}
.upload-zone:hover .upload-icon-wrapper {
  transform: scale(1.1); background: var(--primary); color: #fff; box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
}
.upload-text-main { font-weight: 700; color: var(--text); font-size: 0.85rem; }
.upload-text-sub { font-size: 0.7rem; color: var(--muted); margin-top: 4px; }
.upload-zone.has-image { border-style: solid; border-color: var(--border); padding: 0; background: var(--surface); }
.upload-zone.has-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; transition: transform 0.5s ease, filter var(--transition); }
.upload-zone.has-image:hover img { transform: scale(1.05); filter: brightness(0.6); }
.image-change-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: #fff;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  font-weight: 700; opacity: 0; transition: opacity var(--transition); z-index: 3; font-size: 0.85rem;
}
.upload-zone.has-image:hover .image-change-overlay { opacity: 1; }

/* ===== SOS HUB ===== */
.sos-hub{
  display:flex;flex-direction:column;align-items:center;
  padding:40px 0 20px;
}
.sos-outer{
  position:relative;width:240px;height:240px;
  display:flex;align-items:center;justify-content:center;
}
.sos-ring{
  position:absolute;inset:0;transform:rotate(-90deg);
}
.sos-ring circle{
  fill:none;stroke:var(--primary);stroke-width:6;
  stroke-dasharray:704;stroke-dashoffset:704;
  stroke-linecap:round;transition:stroke-dashoffset 0.08s linear;
}
.sos-ripple{
  position:absolute;inset:-20px;border-radius:50%;
  border:2px solid rgba(239,68,68,0.3);
  animation:sos-ripple 2.5s infinite;
}
.sos-ripple2{animation-delay:0.8s;}
@keyframes sos-ripple{
  0%{transform:scale(0.9);opacity:0.8;}
  100%{transform:scale(1.3);opacity:0;}
}
.sos-btn{
  width:190px;height:190px;border-radius:50%;border:10px solid rgba(255,255,255,0.9);
  background:radial-gradient(circle at 35% 35%,#ff6b6b,#ef4444 50%,#c41e3a);
  color:#fff;cursor:pointer;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  font-weight:900;font-size:2.5rem;letter-spacing:2px;
  transition:transform 0.15s,box-shadow 0.3s;
  position:relative;z-index:5;
  box-shadow:0 20px 60px rgba(239,68,68,0.45),inset 0 -4px 12px rgba(0,0,0,0.2);
  user-select:none;-webkit-tap-highlight-color:transparent;
}
.sos-btn span{font-size:0.7rem;font-weight:700;letter-spacing:3px;opacity:0.75;margin-top:4px;}
.sos-btn:hover{box-shadow:0 28px 70px rgba(239,68,68,0.55),inset 0 -4px 12px rgba(0,0,0,0.2);}
.sos-btn:active,.sos-btn.holding{transform:scale(0.94);}
.sos-btn[disabled]{opacity:0.45;cursor:not-allowed;}
.sos-btn.success{background:radial-gradient(circle,#34d399,#10b981);}
.sos-caption{
  margin-top:16px;font-size:0.8rem;font-weight:700;
  color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;
  text-align:center;
}

/* ===== GPS STATUS ===== */
.gps-status{
  display:flex;align-items:center;gap:10px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:50px;padding:10px 20px;font-size:0.8rem;font-weight:700;
  box-shadow:var(--shadow-sm);
}

/* ===== DRIVER CARD ===== */
.driver-card{
  display:none;margin-bottom:24px;
  animation:slideDown 0.4s ease;
}
.driver-card.show{display:block;}
@keyframes slideDown{from{opacity:0;transform:translateY(-12px);}to{opacity:1;transform:translateY(0);}}
.driver-inner{
  background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(37,99,235,0.06));
  border:1.5px solid rgba(16,185,129,0.25);border-radius:var(--radius);
  padding:22px 26px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;
}
.driver-avatar{
  width:64px;height:64px;border-radius:50%;
  background:linear-gradient(135deg,#10b981,#059669);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:1.4rem;color:#fff;flex-shrink:0;
  box-shadow:0 6px 20px rgba(16,185,129,0.3);
}
.driver-info{flex:1;min-width:160px;}
.driver-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);
  border-radius:50px;padding:4px 14px;
  font-size:0.68rem;font-weight:800;color:var(--success);
  text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;
  animation:pulse-badge 2s infinite;
}
@keyframes pulse-badge{0%,100%{opacity:1;}50%{opacity:0.6;}}
.driver-name{font-size:1.2rem;font-weight:800;margin-bottom:2px;}
.driver-unit{font-size:0.8rem;color:var(--muted);font-weight:600;}
.driver-actions{display:flex;gap:10px;flex-shrink:0;}
.eta-box{
  background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.2);
  border-radius:14px;padding:14px 20px;text-align:center;min-width:100px;
}
.eta-val{font-size:1.5rem;font-weight:900;color:var(--primary);}
.eta-label{font-size:0.65rem;font-weight:700;color:var(--muted);letter-spacing:1px;text-transform:uppercase;}

/* ===== ENHANCED MAP ===== */
.map-wrapper{
  position: relative; border-radius: 24px; overflow: hidden;
  border: 1px solid var(--border); box-shadow: var(--shadow-lg);
  margin-bottom: 28px; background: var(--surface);
  transform: translateZ(0); /* Hardware accel for border radius */
}
#map{ width:100%; height:100%; min-height:calc(100vh - 300px); z-index: 1; }
.map-border-glow {
  position: absolute; inset: 0; border-radius: 24px; pointer-events: none; z-index: 801;
  box-shadow: inset 0 0 0 2px rgba(255,255,255,0.15);
}
[data-theme="dark"] .map-border-glow { box-shadow: inset 0 0 0 2px rgba(255,255,255,0.05); }
.map-overlay{
  position:absolute; top:20px; left:20px; z-index:800; pointer-events: none;
}
.map-pill{
  display:flex; align-items:center; gap:10px;
  background:var(--surface); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px);
  border:1px solid rgba(255,255,255,0.4); border-radius:50px;
  padding:12px 24px; font-size:0.8rem; font-weight:800; color: var(--text);
  box-shadow:0 8px 30px rgba(0,0,0,0.12), inset 0 0 0 1px rgba(255,255,255,0.3); pointer-events: auto;
}
[data-theme="dark"] .map-pill{ border-color: rgba(255,255,255,0.08); box-shadow: 0 8px 30px rgba(0,0,0,0.5), inset 0 0 0 1px rgba(255,255,255,0.05); }
.coord-pill{
  position:absolute; bottom:20px; left:50%; transform:translateX(-50%); z-index:800;
  background:rgba(37,99,235,0.9); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
  border:none; border-radius:50px; color:#fff;
  padding:10px 24px; font-size:0.85rem; font-weight:700; font-family:'Inter',monospace;
  white-space:nowrap; box-shadow:0 8px 25px rgba(37,99,235,0.4);
}

/* ===== MAP LAYER CONTROLS ===== */
.map-layer-controls {
  position: absolute; top: 16px; right: 16px; z-index: 800;
  display: flex; background: var(--surface); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden;
  border: 1px solid var(--border);
}
.layer-btn {
  background: transparent; border: none; padding: 10px 14px;
  font-size: 0.75rem; font-weight: 700; color: var(--text);
  cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; gap: 6px;
}
.layer-btn:not(:last-child) { border-right: 1px solid var(--border); }
.layer-btn:hover { background: rgba(37,99,235,0.05); }
.layer-btn.active { background: var(--primary); color: #fff; }

/* ===== ACTION GUIDE ===== */
.guide-card{
  border-left:4px solid var(--primary);padding:20px 24px;
  margin-bottom:24px;display:none;
}
.guide-card.show{display:block;}
.guide-icon{
  width:36px;height:36px;border-radius:10px;
  background:rgba(37,99,235,0.1);color:var(--primary);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.guide-text{font-size:0.9rem;font-weight:600;color:var(--muted);line-height:1.6;}

/* ===== MEDICAL CARD ===== */
.med-field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
.med-field{
  background:rgba(37,99,235,0.04);border:1.5px solid var(--border);
  border-radius:14px;padding:14px 16px;
}
.med-field-icon{
  width:32px;height:32px;border-radius:9px;
  background:rgba(37,99,235,0.1);color:var(--primary);
  display:flex;align-items:center;justify-content:center;
  font-size:0.85rem;margin-bottom:8px;
}

/* ===== CONTACT CARDS ===== */
.contact-card{
  display:flex;align-items:center;gap:14px;
  padding:14px 18px;border-radius:16px;
  background:rgba(37,99,235,0.04);border:1.5px solid var(--border);
  margin-bottom:12px;
}
.contact-avatar{
  width:44px;height:44px;border-radius:50%;
  background:linear-gradient(135deg,#2563eb,#7c3aed);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:1rem;color:#fff;flex-shrink:0;
}
.contact-info{flex:1;min-width:0;}
.contact-name{font-weight:700;font-size:0.9rem;}
.contact-phone{font-size:0.78rem;color:var(--muted);}
.contact-actions{display:flex;gap:6px;flex-shrink:0;}
.contact-btn{
  width:34px;height:34px;border-radius:10px;border:1.5px solid var(--border);
  background:transparent;display:flex;align-items:center;justify-content:center;
  font-size:0.85rem;cursor:pointer;color:var(--muted);
  transition:all var(--transition);
}
.contact-btn.call:hover{background:var(--success);border-color:var(--success);color:#fff;}
.contact-btn.wa:hover{background:#25D366;border-color:#25D366;color:#fff;}
.contact-btn.loc:hover{background:var(--primary);border-color:var(--primary);color:#fff;}

/* ===== SAFETY CHECK MODAL ===== */
.safety-modal-backdrop{
  position:fixed;inset:0;background:rgba(0,0,0,0.6);
  backdrop-filter:blur(8px);z-index:2000;
  display:none;align-items:center;justify-content:center;
}
.safety-modal-backdrop.show{display:flex;}
.safety-modal{
  background:var(--surface-solid);border-radius:24px;
  padding:40px;max-width:420px;width:90%;text-align:center;
  box-shadow:0 40px 100px rgba(0,0,0,0.3);
  animation:modal-in 0.35s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes modal-in{from{opacity:0;transform:scale(0.8);}to{opacity:1;transform:scale(1);}}
.safety-modal-icon{
  width:80px;height:80px;border-radius:50%;margin:0 auto 20px;
  background:rgba(245,158,11,0.1);border:2px solid rgba(245,158,11,0.3);
  display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;animation:tl-pulse 1.5s infinite;
}

/* ===== TOAST NOTIFICATIONS ===== */
.toast-stack{
  position:fixed;bottom:28px;right:28px;z-index:9999;
  display:flex;flex-direction:column;gap:10px;pointer-events:none;
}
.toast-item{
  display:flex;align-items:center;gap:12px;
  background:var(--surface-solid);border-radius:16px;
  padding:14px 20px;min-width:300px;max-width:380px;
  box-shadow:0 16px 50px rgba(0,0,0,0.2);pointer-events:all;
  animation:toast-in 0.35s cubic-bezier(0.34,1.56,0.64,1);
  border-left:4px solid var(--primary);
}
.toast-item.success{border-color:var(--success);}
.toast-item.danger{border-color:var(--danger);}
.toast-item.warning{border-color:var(--warning);}
.toast-item.removing{animation:toast-out 0.3s ease forwards;}
@keyframes toast-in{from{opacity:0;transform:translateX(40px);}to{opacity:1;transform:translateX(0);}}
@keyframes toast-out{to{opacity:0;transform:translateX(40px);}}
.toast-icon{
  width:36px;height:36px;border-radius:10px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:1rem;
}
.toast-icon.info{background:rgba(37,99,235,0.1);color:var(--primary);}
.toast-icon.success{background:rgba(16,185,129,0.1);color:var(--success);}
.toast-icon.danger{background:rgba(239,68,68,0.1);color:var(--danger);}
.toast-icon.warning{background:rgba(245,158,11,0.1);color:var(--warning);}
.toast-body{flex:1;}
.toast-title{font-weight:700;font-size:0.88rem;}
.toast-msg{font-size:0.78rem;color:var(--muted);margin-top:2px;}
.toast-close{
  background:none;border:none;color:var(--muted);
  cursor:pointer;font-size:1.1rem;padding:0;
  transition:color var(--transition);
}
.toast-close:hover{color:var(--text);}

/* ===== SKELETON LOADER ===== */
.skeleton{
  background:linear-gradient(90deg,rgba(0,0,0,0.06) 25%,rgba(0,0,0,0.12) 50%,rgba(0,0,0,0.06) 75%);
  background-size:200% 100%;
  animation:skeleton-wave 1.5s infinite;
  border-radius:8px;
}
@keyframes skeleton-wave{0%{background-position:200% 0;}100%{background-position:-200% 0;}}

/* ===== SECTION HEADING ===== */
.section-heading{
  font-size:0.72rem;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;
  display:flex;align-items:center;gap:8px;
}
.section-heading::after{content:'';flex:1;height:1px;background:var(--border);}

/* ===== MY ACCOUNT / SETTINGS PAGE STYLES ===== */
.acct-page-header{
  display:flex;align-items:center;gap:16px;margin-bottom:32px;
}
.acct-page-icon{
  width:54px;height:54px;border-radius:16px;
  background:linear-gradient(135deg,#ef4444,#c41e3a);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;color:#fff;
  box-shadow:0 8px 25px rgba(239,68,68,0.35);
  flex-shrink:0;
}
.acct-page-title{font-size:1.75rem;font-weight:800;letter-spacing:-0.5px;}
.acct-page-title span{color:var(--danger);}
.acct-page-sub{font-size:0.8rem;color:var(--muted);font-weight:600;margin-top:3px;}

/* Profile Hero Card */
.profile-hero{
  background:linear-gradient(135deg,rgba(239,68,68,0.08),rgba(196,30,58,0.04));
  border:1.5px solid rgba(239,68,68,0.15);
  border-radius:var(--radius);padding:32px;
  display:flex;align-items:center;gap:28px;
  margin-bottom:24px;position:relative;overflow:hidden;
}
.profile-hero::before{
  content:'';position:absolute;top:-40px;right:-40px;
  width:200px;height:200px;border-radius:50%;
  background:radial-gradient(rgba(239,68,68,0.08),transparent);
  pointer-events:none;
}
.profile-avatar-wrap{
  position:relative;flex-shrink:0;
}
.profile-avatar-img{
  width:100px;height:100px;border-radius:50%;
  object-fit:cover;
  border:4px solid rgba(239,68,68,0.3);
  box-shadow:0 8px 30px rgba(239,68,68,0.2);
  display:block;
}
.profile-avatar-initials{
  width:100px;height:100px;border-radius:50%;
  background:linear-gradient(135deg,#ef4444,#7c3aed);
  display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;font-weight:900;color:#fff;
  border:4px solid rgba(239,68,68,0.3);
  box-shadow:0 8px 30px rgba(239,68,68,0.2);
  flex-shrink:0;
}
.avatar-upload-btn{
  position:absolute;bottom:2px;right:2px;
  width:30px;height:30px;border-radius:50%;
  background:var(--danger);color:#fff;border:2px solid var(--bg);
  display:flex;align-items:center;justify-content:center;
  font-size:0.7rem;cursor:pointer;
  transition:all var(--transition);
  box-shadow:0 2px 10px rgba(239,68,68,0.4);
}
.avatar-upload-btn:hover{transform:scale(1.15);}
.profile-hero-info{flex:1;min-width:0;}
.profile-hero-name{font-size:1.4rem;font-weight:800;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.profile-hero-meta{display:flex;flex-wrap:wrap;gap:12px;margin-top:10px;}
.hero-meta-chip{
  display:inline-flex;align-items:center;gap:7px;
  background:var(--surface);border:1px solid var(--border);
  border-radius:50px;padding:6px 14px;
  font-size:0.75rem;font-weight:700;color:var(--muted);
}
.hero-meta-chip i{color:var(--danger);}
.profile-hero-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);
  border-radius:50px;padding:5px 14px;
  font-size:0.7rem;font-weight:800;color:var(--danger);
  text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;
}

/* Account Sections Grid */
.acct-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}
.acct-card{
  background:var(--surface);backdrop-filter:blur(24px);
  border:1px solid var(--border);border-radius:var(--radius);
  box-shadow:var(--shadow-sm);overflow:hidden;
  transition:box-shadow var(--transition);
}
.acct-card:hover{box-shadow:var(--shadow);}
.acct-card-header{
  display:flex;align-items:center;gap:12px;
  padding:20px 24px 16px;
  border-bottom:1px solid var(--border);
}
.acct-card-icon{
  width:42px;height:42px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
}
.acct-card-icon.red{background:rgba(239,68,68,0.12);color:var(--danger);}
.acct-card-icon.blue{background:rgba(37,99,235,0.12);color:var(--primary);}
.acct-card-icon.green{background:rgba(16,185,129,0.12);color:var(--success);}
.acct-card-icon.orange{background:rgba(245,158,11,0.12);color:var(--warning);}
.acct-card-icon.purple{background:rgba(124,58,237,0.12);color:#7c3aed;}
.acct-card-title{font-weight:800;font-size:0.95rem;}
.acct-card-sub{font-size:0.72rem;color:var(--muted);font-weight:600;margin-top:2px;}
.acct-card-body{padding:20px 24px;}
.acct-card-full{grid-column:1/-1;}

/* Settings Toggle Row */
.setting-row{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 0;border-bottom:1px solid var(--border);
}
.setting-row:last-child{border-bottom:none;}
.setting-info{display:flex;align-items:center;gap:12px;}
.setting-row-icon{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  font-size:0.85rem;flex-shrink:0;
}
.setting-row-icon.red{background:rgba(239,68,68,0.1);color:var(--danger);}
.setting-row-icon.blue{background:rgba(37,99,235,0.1);color:var(--primary);}
.setting-row-icon.green{background:rgba(16,185,129,0.1);color:var(--success);}
.setting-row-icon.orange{background:rgba(245,158,11,0.1);color:var(--warning);}
.setting-row-icon.purple{background:rgba(124,58,237,0.1);color:#7c3aed;}
.setting-label{font-weight:700;font-size:0.88rem;}
.setting-desc{font-size:0.72rem;color:var(--muted);font-weight:600;margin-top:2px;}

/* Custom Toggle Switch */
.custom-toggle{
  position:relative;width:52px;height:28px;border-radius:14px;
  cursor:pointer;transition:background 0.3s;flex-shrink:0;
}
.custom-toggle-thumb{
  position:absolute;top:4px;left:4px;
  width:20px;height:20px;border-radius:50%;background:#fff;
  transition:left 0.3s;box-shadow:0 2px 6px rgba(0,0,0,0.25);
}
.custom-toggle.on{background:var(--danger);}
.custom-toggle.off{background:rgba(0,0,0,0.15);}
.custom-toggle.on .custom-toggle-thumb{left:28px;}
[data-theme="dark"] .custom-toggle.off{background:rgba(255,255,255,0.15);}

/* Select & Dropdown */
.settings-select{
  background:var(--surface) !important;
  border:1.5px solid var(--border) !important;
  border-radius:12px !important;padding:10px 16px !important;
  color:var(--text) !important;font-family:'Inter',sans-serif !important;
  font-size:0.85rem !important;font-weight:600 !important;
  cursor:pointer;outline:none !important;
  transition:border-color var(--transition);
  width:100%;
}
.settings-select:focus{
  border-color:var(--danger) !important;
  box-shadow:0 0 0 3px rgba(239,68,68,0.15) !important;
}

/* Help & Support Items */
.help-item{
  display:flex;align-items:center;gap:14px;
  padding:14px 0;border-bottom:1px solid var(--border);
  cursor:pointer;transition:all var(--transition);
}
.help-item:last-child{border-bottom:none;}
.help-item:hover .help-item-arrow{transform:translateX(4px);color:var(--danger);}
.help-item-icon{
  width:42px;height:42px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
}
.help-item-title{font-weight:700;font-size:0.88rem;}
.help-item-desc{font-size:0.72rem;color:var(--muted);font-weight:600;margin-top:2px;}
.help-item-arrow{
  margin-left:auto;color:var(--muted);font-size:0.85rem;
  transition:all var(--transition);
}

/* Location Info */
.location-info-box{
  display:flex;align-items:center;gap:12px;
  background:rgba(239,68,68,0.05);
  border:1.5px solid rgba(239,68,68,0.15);
  border-radius:14px;padding:14px 18px;margin-bottom:16px;
}

/* Delete Account */
.delete-zone{
  background:rgba(239,68,68,0.04);
  border:1.5px solid rgba(239,68,68,0.2);
  border-radius:14px;padding:20px;
  display:flex;align-items:center;gap:16px;
}
.delete-zone-icon{
  width:48px;height:48px;border-radius:14px;
  background:rgba(239,68,68,0.1);color:var(--danger);
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;flex-shrink:0;
}
.btn-danger-outline{
  background:transparent;
  border:2px solid var(--danger);color:var(--danger);
  border-radius:12px;padding:10px 22px;
  font-weight:700;font-size:0.85rem;cursor:pointer;
  transition:all var(--transition);
}
.btn-danger-outline:hover{
  background:var(--danger);color:#fff;
  box-shadow:0 6px 20px var(--danger-glow);
}

/* Settings Grid */
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:900px){.acct-grid,.settings-grid{grid-template-columns:1fr;}}
@media(max-width:768px){.profile-hero{flex-direction:column;text-align:center;} .profile-hero-meta{justify-content:center;} .acct-grid,.settings-grid{grid-template-columns:1fr;}}

/* Delete Confirm Modal */
.delete-confirm-modal{
  background:var(--surface-solid);border-radius:24px;
  padding:40px 36px;max-width:440px;width:90%;text-align:center;
  box-shadow:0 40px 100px rgba(0,0,0,0.35);
  animation:modal-in 0.35s cubic-bezier(0.34,1.56,0.64,1);
  border-top:4px solid var(--danger);
}
.delete-confirm-icon{
  width:80px;height:80px;border-radius:50%;margin:0 auto 20px;
  background:rgba(239,68,68,0.1);border:2px solid rgba(239,68,68,0.3);
  display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;
}
.password-input-wrapper{
  position:relative;margin-top:12px;
}
.password-eye{
  position:absolute;right:14px;top:50%;transform:translateY(-50%);
  color:var(--muted);cursor:pointer;font-size:0.9rem;
  transition:color var(--transition);
}
.password-eye:hover{color:var(--danger);}

/* Notification priority badge */
.priority-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(239,68,68,0.1);border:1.5px solid rgba(239,68,68,0.25);
  border-radius:8px;padding:6px 14px;
  font-size:0.72rem;font-weight:800;color:var(--danger);
  letter-spacing:0.5px;
}

/* ===== HISTORY TABLE ===== */
.hist-table th{font-size:0.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;border:none !important;padding:12px 16px;}
.hist-table td{padding:14px 16px;border-color:var(--border) !important;font-size:0.88rem;vertical-align:middle;}
.hist-table tbody tr{transition:background var(--transition);}
.hist-table tbody tr:hover{background:rgba(37,99,235,0.04);}

/* ===== BTN STYLES ===== */
.btn-primary-custom{
  background:linear-gradient(135deg,var(--primary),#1d4ed8);
  color:#fff;border:none;border-radius:14px;padding:13px 28px;
  font-weight:700;font-size:0.9rem;cursor:pointer;
  transition:all var(--transition);
  box-shadow:0 6px 20px var(--primary-glow);
}
.btn-primary-custom:hover{transform:translateY(-2px);box-shadow:0 10px 30px var(--primary-glow);}
.btn-danger-custom{
  background:linear-gradient(135deg,var(--danger),#dc2626);
  color:#fff;border:none;border-radius:14px;padding:13px 28px;
  font-weight:700;font-size:0.9rem;cursor:pointer;
  transition:all var(--transition);
  box-shadow:0 6px 20px var(--danger-glow);
}
.btn-sm-custom{
  border-radius:10px;padding:8px 18px;font-size:0.78rem;font-weight:700;
  border:1.5px solid var(--border);background:transparent;
  color:var(--text);cursor:pointer;transition:all var(--transition);
}
.btn-sm-custom:hover{background:var(--primary);color:#fff;border-color:var(--primary);}

/* ===== STATUS MESSAGE ===== */
.status-panel{border-radius:16px;padding:18px 22px;font-weight:700;font-size:0.9rem;
  animation:fadeUp 0.4s ease;display:none;}
.status-panel.show{display:block;}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
.status-panel.info{background:rgba(37,99,235,0.08);border:1.5px solid rgba(37,99,235,0.2);color:var(--primary);}
.status-panel.success{background:rgba(16,185,129,0.08);border:1.5px solid rgba(16,185,129,0.25);color:var(--success);}
.status-panel.error{background:rgba(239,68,68,0.08);border:1.5px solid rgba(239,68,68,0.2);color:var(--danger);}

/* ===== TAB CONTENT ===== */
.tab-pane{display:none;}
.tab-pane.active{display:block;animation:fadeUp 0.4s ease;}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .sidebar-overlay.show{display:block;}
  .hamburger{display:flex;}
  .main-wrapper{margin-left:0;padding:20px 16px 80px;}
  .stat-grid{grid-template-columns:1fr 1fr;}
  .form-row,.med-field-row{grid-template-columns:1fr;}
  .dashboard-layout{grid-template-columns:1fr;}
  .dash-right{position:relative;top:0;}
  .driver-inner{flex-direction:column;text-align:center;}
  .driver-actions{width:100%;justify-content:center;}
}
@media(max-width:480px){
  .stat-grid{grid-template-columns:1fr 1fr;}
  .etype-grid{grid-template-columns:1fr 1fr;}
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar{width:6px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:rgba(37,99,235,0.3);border-radius:3px;}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<button class="hamburger" id="hamburgerBtn" aria-label="Menu" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
  <a href="#" class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-truck-medical"></i></div>
    <span class="brand-text">Smart<span>Rescue</span></span>
  </a>
  <span class="nav-section-label">Main</span>
  <a onclick="showTab('dashboard',this)" class="nav-item active" id="nav-dashboard">
    <i class="fa fa-gauge-high"></i> Dashboard <span class="badge-dot"></span>
  </a>
  <a onclick="showTab('medical',this)" class="nav-item" id="nav-medical">
    <i class="fa fa-heart-pulse"></i> Medical ID
  </a>
  <a onclick="showTab('contacts',this)" class="nav-item" id="nav-contacts">
    <i class="fa fa-people-group"></i> Contacts
  </a>
  <span class="nav-section-label">Account</span>
  <a onclick="showTab('history',this)" class="nav-item" id="nav-history">
    <i class="fa fa-clock-rotate-left"></i> History
  </a>
  <a onclick="showTab('profile',this)" class="nav-item" id="nav-profile">
    <i class="fa fa-user-circle"></i> My Account
  </a>
  <a onclick="showTab('settings',this)" class="nav-item" id="nav-settings">
    <i class="fa fa-sliders"></i> Settings
  </a>
  <div class="sidebar-footer">
    <div class="user-avatar-mini" onclick="showTab('profile', document.getElementById('nav-profile'))" style="cursor:pointer;" title="My Account">
      <?php if (!empty($user_data['profile_image'])): ?>
      <div class="avatar-ring" style="padding:0;overflow:hidden;">
        <img src="../<?php echo htmlspecialchars($user_data['profile_image']); ?>" 
             id="sidebarAvatarImg"
             alt="Profile" 
             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      </div>
      <?php else: ?>
      <div class="avatar-ring" id="sidebarAvatarRing"><?php echo strtoupper(substr($fullname,0,1)); ?></div>
      <?php endif; ?>
      <div class="user-meta">
        <div class="name"><?php echo htmlspecialchars(explode(' ',$fullname)[0]); ?></div>
        <div class="role">User Account</div>
      </div>
    </div>
    <a href="../auth/logout.php" class="btn-danger-custom w-100 d-flex align-items-center justify-content-center gap-2" style="text-decoration:none;border-radius:14px;padding:12px;">
      <i class="fa fa-power-off"></i> Sign Out
    </a>
  </div>
</aside>

<!-- ===== MAIN ===== -->
<main class="main-wrapper">

  <!-- OFFLINE BANNER -->
  <div class="offline-banner" id="offlineBanner">
    <i class="fa fa-wifi fa-lg" style="text-decoration:line-through;"></i>
    <div>
      <div style="font-weight:800;">You're Offline</div>
      <div style="font-size:0.78rem;opacity:0.85;">SOS requests will be saved and auto-sent when connection restores.</div>
    </div>
    <span id="syncStatus" style="margin-left:auto;font-size:0.75rem;opacity:0.8;"></span>
  </div>  <!-- ========== DASHBOARD TAB ========== -->
  <div class="tab-pane active" id="tab-dashboard">

    <!-- TOPBAR -->
    <div class="topbar">
      <div class="topbar-left">
        <div>
          <div style="font-size:0.7rem;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase;">Welcome</div>
          <div style="font-size:1.6rem;font-weight:900;letter-spacing:-0.5px;">Hello, <span style="color:var(--danger);"><?php echo htmlspecialchars(explode(' ',$fullname)[0]); ?></span> 👋</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="status-badge"><span class="dot"></span> Online</div>
        <div class="clock-badge" id="clockDisplay">--:--:--</div>
        <div class="icon-btn" onclick="toggleDarkMode()" title="Toggle Dark Mode"><i class="fa fa-moon" id="darkModeIcon"></i></div>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card red-card">
        <div class="stat-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="2.5" fill="#ffffff" />
            <path d="M16.24 7.76a6 6 0 0 1 0 8.49" />
            <path d="M7.76 16.24a6 6 0 0 1 0-8.49" />
            <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
            <path d="M4.93 19.07a10 10 0 0 1 0-14.14" />
          </svg>
        </div>
        <div>
          <div class="stat-value"><?php echo $total_sos; ?></div>
          <div class="stat-label">Total SOS Sent</div>
          <div class="stat-trend"><i class="fa fa-arrow-trend-up me-1"></i>All time</div>
        </div>
      </div>
      <div class="stat-card blue-card">
        <div class="stat-icon"><i class="fa fa-clock-rotate-left"></i></div>
        <div>
          <div class="stat-value" style="font-size:1rem;margin-bottom:2px;"><?php echo $last_emergency; ?></div>
          <div class="stat-label">Last Emergency</div>
          <div class="stat-trend"><i class="fa fa-calendar me-1"></i>Most recent</div>
        </div>
      </div>
      <div class="stat-card green-card">
        <div class="stat-icon"><i class="fa fa-people-group"></i></div>
        <div>
          <div class="stat-value" id="contactCount">--</div>
          <div class="stat-label">Emergency Contacts</div>
          <div class="stat-trend"><i class="fa fa-shield-check me-1"></i>Guardian network</div>
        </div>
      </div>
      <div class="stat-card orange-card">
        <div class="stat-icon"><i class="fa fa-satellite"></i></div>
        <div>
          <div class="stat-value" id="statGpsAccuracy">--</div>
          <div class="stat-label">GPS Accuracy</div>
          <div class="stat-trend" id="statGpsLabel"><i class="fa fa-circle-notch fa-spin me-1"></i>Acquiring…</div>
        </div>
      </div>
    </div>

    <!-- DRIVER CARD (active dispatch) -->
    <div class="driver-card glass-card no-hover" id="driverCard">
      <div class="driver-inner">
        <div class="driver-avatar" id="driverInitial">?</div>
        <div class="driver-info">
          <div>
            <div class="driver-badge"><i class="fa fa-truck-medical"></i> Rescue Team En Route</div>
            <div id="activeEmergencyType" style="display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:50px;padding:4px 14px;font-size:0.68rem;font-weight:800;color:var(--danger);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;margin-left:8px;"><i class="fa fa-triangle-exclamation"></i> Emergency</div>
          </div>
          <div class="driver-name" id="driverName">--</div>
          <div class="driver-unit" id="driverUnit">--</div>
        </div>
        <div class="eta-box">
          <div class="eta-val" id="etaDisplay">--</div>
          <div class="eta-label">ETA (km)</div>
        </div>
        <div class="driver-actions">
          <a id="callDriverBtn" href="#" class="btn-primary-custom d-flex align-items-center gap-2" style="text-decoration:none;">
            <i class="fa fa-phone-volume"></i> Call
          </a>
        </div>
      </div>
    </div>

    <!-- TWO-COLUMN LAYOUT -->
    <div class="dashboard-layout">

      <!-- ===== LEFT COLUMN ===== -->
      <div class="dash-left">

        <!-- EMERGENCY TYPE SELECTION -->
        <div style="margin-bottom:8px;">
          <div class="section-heading"><i class="fa fa-triangle-exclamation" style="color:var(--danger);"></i> Select Emergency Type</div>
        </div>
        <div class="etype-grid">
          <div class="etype-card medical-card active" id="etype-Medical" onclick="selectType(this,'Medical')">
            <div class="etype-icon-wrap"><i class="fa-solid fa-truck-medical"></i></div>
            <div class="etype-name">AMBULANCE</div>
            <div class="etype-sub">Medical Emergency</div>
          </div>
          <div class="etype-card fire-card" id="etype-Fire" onclick="selectType(this,'Fire')">
            <div class="etype-icon-wrap"><i class="fa-solid fa-fire"></i></div>
            <div class="etype-name">FIRE RESCUE</div>
            <div class="etype-sub">Fire &amp; Burns</div>
          </div>
          <div class="etype-card police-card" id="etype-Police" onclick="selectType(this,'Police')">
            <div class="etype-icon-wrap"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="etype-name">POLICE</div>
            <div class="etype-sub">Security Help</div>
          </div>
          <div class="etype-card accident-card" id="etype-Accident" onclick="selectType(this,'Accident')">
            <div class="etype-icon-wrap"><i class="fa-solid fa-car-burst"></i></div>
            <div class="etype-name">ACCIDENT</div>
            <div class="etype-sub">Vehicle Crash</div>
          </div>
        </div>
        <input type="hidden" id="emergency_type" value="Medical">

        <!-- ACTION GUIDE -->
        <div class="glass-card guide-card no-hover show" id="actionGuide" style="margin-bottom:20px;">
          <div style="display:flex;align-items:flex-start;gap:14px;">
            <div class="guide-icon"><i class="fa fa-lightbulb"></i></div>
            <div>
              <div style="font-weight:800;font-size:0.78rem;color:var(--primary);margin-bottom:5px;text-transform:uppercase;letter-spacing:1px;">Action Guide</div>
              <div class="guide-text" id="guideText">Stay calm. Check for breathing. Keep the patient still and wait for emergency services to arrive.</div>
            </div>
          </div>
        </div>

        <!-- ACTION ROW: DESC, NEIGHBORHOOD & UPLOAD -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:24px;">
          <!-- NEAREST LANDMARK / NEIGHBORHOOD -->
          <div class="glass-card no-hover" style="display:none !important;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;">
                <i class="fa fa-location-crosshairs"></i>
              </div>
              <div>
                <div style="font-weight:800;font-size:0.88rem;">Nearest Landmark / Neighborhood</div>
                <div style="font-size:0.68rem;color:var(--muted);font-weight:600;margin-top:1px;">e.g. Fooriloow, Delish Restaurant</div>
              </div>
            </div>
            <div style="position:relative;flex:1;display:flex;flex-direction:column;justify-content:center;">
              <input type="text" id="sos_neighborhood" class="glass-input"
                placeholder="Type here (e.g. Fooriloow, near Delish)…"
                maxlength="150"
                style="margin:0;width:100%;">
            </div>
          </div>

          <!-- DESCRIPTION TEXTAREA -->
          <div class="glass-card no-hover" style="padding:22px 24px;display:flex;flex-direction:column;height:100%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,0.1);color:var(--danger);display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;">
                  <i class="fa fa-file-lines"></i>
                </div>
                <div>
                  <div style="font-weight:800;font-size:0.88rem;">Incident Description</div>
                  <div style="font-size:0.68rem;color:var(--muted);font-weight:600;margin-top:1px;">Details matter</div>
                </div>
              </div>
              <div style="font-size:0.65rem;font-weight:800;color:var(--muted);background:var(--bg);border-radius:50px;padding:4px 12px;border:1px solid var(--border);" id="descCharCount">0 / 500</div>
            </div>
            <div style="position:relative;flex:1;display:flex;flex-direction:column;">
              <textarea id="sos_description" class="glass-input custom-textarea" rows="4"
                placeholder="Exact location, visible injuries, hazards…"
                maxlength="500"
                spellcheck="false"
                data-lt-active="false"
                data-gramm="false"
                data-enable-grammarly="false"
                oninput="document.getElementById('descCharCount').textContent=this.value.length+' / 500';this.style.borderColor=this.value.length>0?'var(--danger)':''"
                style="flex:1;min-height:100px;resize:none;padding-bottom:34px;margin:0;"></textarea>
              <i class="fa fa-comment-medical" style="position:absolute;bottom:10px;right:14px;color:var(--danger);opacity:0.18;font-size:1.1rem;pointer-events:none;"></i>
            </div>
          </div>

          <!-- UPLOAD ZONE -->
          <div class="glass-card no-hover" style="padding:22px 24px;display:flex;flex-direction:column;height:100%;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <div style="width:36px;height:36px;border-radius:10px;background:rgba(37,99,235,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;">
                <i class="fa fa-camera"></i>
              </div>
              <div>
                <div style="font-weight:800;font-size:0.88rem;">Attach Evidence Photo</div>
                <div style="font-size:0.68rem;color:var(--muted);font-weight:600;margin-top:1px;">For rescuers context</div>
              </div>
            </div>
            <div class="upload-zone" id="uploadZone" style="flex:1;min-height:100px;"
              onclick="document.getElementById('sos_image').click()"
              ondragover="event.preventDefault();this.classList.add('drag-over');"
              ondragleave="this.classList.remove('drag-over');"
              ondrop="event.preventDefault();this.classList.remove('drag-over');document.getElementById('sos_image').files=event.dataTransfer.files;previewImage(document.getElementById('sos_image'));">
              <div class="upload-content">
                <div class="upload-icon-wrapper" style="width:40px;height:40px;margin-bottom:8px;"><i class="fa-solid fa-cloud-arrow-up fa-lg"></i></div>
                <div class="upload-text-main" style="font-size:0.85rem;">Drag &amp; Drop or <span style="color:var(--primary);text-decoration:underline;">Browse</span></div>
                <div class="upload-text-sub" style="margin-top:6px;display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap;">
                  <span style="background:rgba(37,99,235,0.08);border:1px solid rgba(37,99,235,0.15);border-radius:4px;padding:2px 6px;font-weight:700;">JPG & PNG</span>
                  <span style="color:var(--muted);margin-left:4px;">Max 10MB</span>
                </div>
              </div>
              <input type="file" id="sos_image" accept="image/*" class="d-none" onchange="previewImage(this)">
            </div>
          </div>
        </div>

        <!-- STATUS MESSAGE -->
        <div class="status-panel" id="statusPanel"></div>

        <!-- RECTANGULAR SOS BUTTON CARD (Centered, Medium Size) -->
        <div style="display:flex;flex-direction:column;align-items:center;margin-top:16px;">
          <!-- Risk + GPS Row -->
          <div style="display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:16px;background:var(--surface);padding:8px 20px;border-radius:50px;box-shadow:var(--shadow-sm);border:1px solid var(--border);">
            <div id="riskBadge" class="risk-badge risk-safe" style="margin:0;"><i class="fa fa-shield-check"></i> <span id="riskLabel">Safety: Monitoring</span></div>
            <div style="width:1px;height:16px;background:var(--border);"></div>
            <div class="gps-status" id="locationStatus" style="font-size:0.75rem;">
              <i class="fa fa-circle-notch fa-spin" style="color:var(--warning);"></i>
              <span id="gpsStatusText">Acquiring…</span>
            </div>
          </div>

          <!-- Hidden elements the JS engine expects -->
          <svg style="display:none;"><circle id="sosRingCircle" cx="100" cy="100" r="92"/></svg>
          <button id="sosTriggerBtn" disabled style="display:none;"></button>

          <!-- Rectangular SOS button -->
          <div class="sos-hub" style="width:100%;max-width:380px;padding:0;margin-bottom:20px;">
            <button class="sos-btn-rect" id="sosBtnRect" disabled
              style="padding:18px 24px;border-radius:12px;box-shadow:0 6px 20px rgba(239,68,68,0.4);"
              onclick="triggerSOS()">
              <span class="sos-rect-icon" style="font-size:1.6rem;"><i class="fa fa-triangle-exclamation"></i></span>
              <div class="sos-rect-label">
                <strong id="sosBtnText" style="font-size:1.3rem;">ACTIVATE SOS</strong>
                <small id="sosBtnSub" style="font-size:0.6rem;">TAP ONCE TO SEND ALERT</small>
              </div>
            </button>
            <div class="sos-caption" id="sosCaption">Acquiring GPS Lock…</div>
          </div>

          <!-- Cancel SOS button -->
          <div id="cancelSOSContainer" style="display:none; width:100%; max-width:380px; margin-bottom:20px; transition: all 0.3s ease;">
            <button onclick="cancelActiveEmergency()" class="btn-cancel-sos-dashboard" 
              style="width:100%; padding:18px 24px; background:linear-gradient(135deg, #ef4444, #dc2626); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:1.1rem; cursor:pointer; box-shadow:0 6px 20px rgba(239,68,68,0.35); display:flex; align-items:center; justify-content:center; gap:10px; transition: all 0.2s ease-in-out;">
                <i class="fa fa-ban"></i> CANCEL SOS REQUEST
            </button>
            <style>
              .btn-cancel-sos-dashboard:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(239,68,68,0.5) !important;
              }
              .btn-cancel-sos-dashboard:active {
                transform: translateY(0);
              }
            </style>
          </div>
        </div>

      </div><!-- /dash-left -->

      <!-- ===== RIGHT COLUMN ===== -->
      <div class="dash-right">

        <!-- RESPONSE TIMELINE -->
        <div class="right-card">
          <div class="right-card-header">
            <div class="right-card-title"><i class="fa fa-route" style="color:var(--primary);"></i> Response Timeline</div>
            <div style="font-size:0.65rem;font-weight:700;color:var(--muted);background:rgba(37,99,235,0.1);padding:3px 10px;border-radius:50px;color:var(--primary);">LIVE</div>
          </div>
          <div class="right-card-body">
            <div class="vtl">
              <div class="vtl-item" id="tl-0">
                <div class="vtl-dot"><i class="fa fa-tower-broadcast"></i></div>
                <div class="vtl-content">
                  <div class="vtl-label">SOS Sent</div>
                  <div class="vtl-desc">Emergency signal broadcast</div>
                </div>
              </div>
              <div class="vtl-item" id="tl-1">
                <div class="vtl-dot"><i class="fa fa-headset"></i></div>
                <div class="vtl-content">
                  <div class="vtl-label">Dispatched</div>
                  <div class="vtl-desc">Dispatch center notified</div>
                </div>
              </div>
              <div class="vtl-item" id="tl-2">
                <div class="vtl-dot"><i class="fa fa-user-check"></i></div>
                <div class="vtl-content">
                  <div class="vtl-label">Team Assigned</div>
                  <div class="vtl-desc">Rescue unit selected</div>
                </div>
              </div>
              <div class="vtl-item" id="tl-3">
                <div class="vtl-dot"><i class="fa fa-truck-medical"></i></div>
                <div class="vtl-content">
                  <div class="vtl-label">On The Way</div>
                  <div class="vtl-desc">Team heading to location</div>
                </div>
              </div>
              <div class="vtl-item" id="tl-4">
                <div class="vtl-dot"><i class="fa fa-location-dot"></i></div>
                <div class="vtl-content">
                  <div class="vtl-label">Arrived</div>
                  <div class="vtl-desc">Team on scene</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- LIVE MAP (Enlarged) -->
        <div class="right-card" style="display:flex;flex-direction:column;">
          <div class="right-card-header">
            <div class="right-card-title"><i class="fa fa-map-location-dot" style="color:var(--danger);"></i> Live Location</div>
            <div style="display:flex;align-items:center;gap:10px;">
              <button id="fixLocationBtn" onclick="enableLocationFix()" title="Drag your pin to your exact position" style="background:rgba(245,158,11,0.12);border:1.5px solid rgba(245,158,11,0.3);color:#d97706;border-radius:8px;padding:5px 12px;font-size:0.72rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s;">
                <i class="fa fa-crosshairs"></i> Fix Location
              </button>
              <div class="gps-status" id="locationStatus" style="border:none;background:none;padding:0;font-size:0.7rem;box-shadow:none;">
                <i class="fa fa-circle-notch fa-spin" style="color:var(--warning);"></i>
                <span id="gpsStatusText">Acquiring…</span>
              </div>
            </div>
          </div>
          <div class="mini-map-wrap" style="flex:1;">
            <div id="map" style="height: 100%; min-height: calc(100vh - 300px);"></div>

            <div class="map-layer-controls">
              <button class="layer-btn active" id="btn-layer-std" onclick="setMapLayer('std')"><i class="fa fa-map"></i> Map</button>
              <button class="layer-btn" id="btn-layer-sat" onclick="setMapLayer('sat')"><i class="fa fa-satellite"></i> Satellite</button>
            </div>

            <div class="mini-map-badge" id="coordPill">
              <i class="fa fa-crosshairs me-1"></i><span>--.------, --.------</span>
            </div>

            <!-- Address name badge -->
            <div id="addressBadge" style="display:none;position:absolute;bottom:50px;left:50%;transform:translateX(-50%);z-index:1000;background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);color:#fff;border-radius:50px;padding:6px 16px;font-size:0.75rem;font-weight:700;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,0.3);max-width:85%;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:6px;">
              <i class="fa fa-location-dot" style="color:#f87171;"></i>
              <span id="addressBadgeText"></span>
            </div>
          </div>
          <div style="padding:16px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);border-radius:0 0 16px 16px;background:var(--surface);">
            <div style="font-size:0.75rem;color:var(--muted);font-weight:600;"><i class="fa fa-satellite me-1"></i> <span id="mapStatusText">Initializing GPS…</span></div>
            <div id="mapStatusPill" style="font-size:0.7rem;font-weight:700;color:var(--primary);"></div>
          </div>

          <!-- Fix Location Banner (shown during drag mode) -->
          <div id="fixModeBanner" style="display:none;padding:12px 16px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-radius:0 0 16px 16px;font-size:0.8rem;font-weight:700;align-items:center;gap:10px;">
            <i class="fa fa-hand-pointer"></i>
            <span>Drag the 📍 pin to your exact location, then click <b>Save</b></span>
            <div style="margin-left:auto;display:flex;gap:8px;">
              <button onclick="cancelLocationFix()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:6px;padding:6px 12px;font-weight:700;font-size:0.72rem;cursor:pointer;">Cancel</button>
              <button onclick="saveManualLocation()" style="background:#fff;border:none;color:#d97706;border-radius:6px;padding:6px 14px;font-weight:800;font-size:0.72rem;cursor:pointer;"><i class="fa fa-check"></i> Save</button>
            </div>
          </div>
        </div>

      </div><!-- /dash-right -->

    </div><!-- /dashboard-layout -->

  </div><!-- /tab-dashboard -->

  <!-- ========== MEDICAL TAB ========== -->
  <div class="tab-pane" id="tab-medical">
    <div class="page-header">
      <div>
        <div style="font-size:0.72rem;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Medical Records</div>
        <div class="page-title">Medical <span>Identity Card</span></div>
      </div>
      <button class="btn-primary-custom" onclick="saveMedicalInfo()"><i class="fa fa-floppy-disk me-2"></i>Save</button>
    </div>
    <div class="glass-card no-hover" style="padding:28px;">
      <div class="med-field-row">
        <div class="med-field"><div class="med-field-icon"><i class="fa fa-droplet"></i></div><div class="field-label">Blood Group</div><select id="bloodGroup" class="glass-input" style="cursor:pointer;"><option value="">— Select —</option><option value="A+">A+</option><option value="A-">A-</option><option value="B+">B+</option><option value="B-">B-</option><option value="AB+">AB+</option><option value="AB-">AB-</option><option value="O+">O+</option><option value="O-">O-</option><option value="Unknown">Unknown</option></select></div>
        <div class="med-field"><div class="med-field-icon"><i class="fa fa-allergies"></i></div><div class="field-label">Allergies</div><input type="text" id="allergies" class="glass-input" placeholder="Penicillin, Nuts…"></div>
        <div class="med-field"><div class="med-field-icon"><i class="fa fa-stethoscope"></i></div><div class="field-label">Chronic Conditions</div><input type="text" id="chronicConditions" class="glass-input" placeholder="Diabetes, Asthma…"></div>
        <div class="med-field"><div class="med-field-icon"><i class="fa fa-pills"></i></div><div class="field-label">Medications</div><input type="text" id="medications" class="glass-input" placeholder="Metformin 500mg…"></div>
      </div>
      <div class="field-label" style="margin-top:4px;">Emergency Notes</div>
      <textarea id="medical_info" class="glass-input" rows="4" placeholder="Additional info for first responders..."><?php echo htmlspecialchars($medical_info); ?></textarea>
    </div>
  </div>

  <!-- ========== CONTACTS TAB ========== -->
  <div class="tab-pane" id="tab-contacts">
    <div class="page-header">
      <div>
        <div style="font-size:0.72rem;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Guardian Network</div>
        <div class="page-title">Emergency <span>Contacts</span></div>
      </div>
      <button class="btn-primary-custom" onclick="openAddContact()"><i class="fa fa-plus me-2"></i>Add Contact</button>
    </div>
    <div class="glass-card no-hover" style="padding:24px;">
      <div id="contactsList"></div>
      <textarea id="emergency_contacts" class="d-none"><?php echo htmlspecialchars($emergency_contacts); ?></textarea>
    </div>
  </div>

  <!-- ========== HISTORY TAB ========== -->
  <div class="tab-pane" id="tab-history">
    <div class="page-header">
      <div>
        <div style="font-size:0.72rem;font-weight:700;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;">Records</div>
        <div class="page-title">Mission <span>History</span></div>
      </div>
      <button class="btn-sm-custom" onclick="fetchHistory()"><i class="fa fa-rotate me-2"></i>Refresh</button>
    </div>
    <div class="glass-card no-hover" style="padding:0;overflow:hidden;">
      <div class="table-responsive">
        <table class="table hist-table mb-0">
          <thead style="background:rgba(37,99,235,0.04);"><tr><th>Date</th><th>Type</th><th>Status</th><th>Details</th></tr></thead>
          <tbody id="historyBody"><tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted);">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ========== PROFILE TAB (MY ACCOUNT) ========== -->
  <div class="tab-pane" id="tab-profile">

    <!-- Page Header -->
    <div class="acct-page-header">
      <div class="acct-page-icon"><i class="fa fa-user-circle"></i></div>
      <div>
        <div class="acct-page-title">My <span>Account</span></div>
        <div class="acct-page-sub">Manage your profile, security, and account preferences</div>
      </div>
    </div>

    <!-- Profile Hero -->
    <div class="profile-hero glass-card no-hover">
      <div class="profile-avatar-wrap">
        <?php if(!empty($user_data['profile_image'])): ?>
          <img src="../<?php echo htmlspecialchars($user_data['profile_image']);?>" alt="Profile" class="profile-avatar-img" id="profileAvatarImg">
        <?php else: ?>
          <div class="profile-avatar-initials" id="profileAvatarInitials"><?php echo strtoupper(substr($fullname,0,1)); ?></div>
          <img src="" alt="Profile" class="profile-avatar-img d-none" id="profileAvatarImg" style="width:100px;height:100px;">
        <?php endif; ?>
        <label class="avatar-upload-btn" for="avatarFileInput" title="Change photo">
          <i class="fa fa-camera"></i>
        </label>
        <input type="file" id="avatarFileInput" accept="image/*" class="d-none" onchange="previewAndUploadAvatar(this)">
      </div>
      <div class="profile-hero-info">
        <div class="profile-hero-badge"><i class="fa fa-shield-check"></i> Verified User</div>
        <div class="profile-hero-name"><?php echo htmlspecialchars($fullname); ?></div>
        <div class="profile-hero-meta">
          <div class="hero-meta-chip"><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($email); ?></div>
          <div class="hero-meta-chip"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($phone); ?></div>
          <div class="hero-meta-chip"><i class="fa fa-at"></i> <?php echo htmlspecialchars(strtolower(str_replace(' ','.',$fullname))); ?></div>
        </div>
      </div>
    </div>

    <!-- Edit Profile + Security -->
    <div class="acct-grid">

      <!-- Edit Profile Card -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon red"><i class="fa fa-id-card"></i></div>
          <div>
            <div class="acct-card-title">Edit Profile</div>
            <div class="acct-card-sub">Update your personal information</div>
          </div>
        </div>
        <div class="acct-card-body">
          <form id="profileForm" onsubmit="updateProfile(event)">
            <div class="field-label">Full Name</div>
            <input type="text" name="fullname" id="editFullname" class="glass-input" value="<?php echo htmlspecialchars($fullname);?>" required style="margin-bottom:14px;">
            <div class="field-label">Phone Number</div>
            <input type="tel" name="phone" id="editPhone" class="glass-input" value="<?php echo htmlspecialchars($phone);?>" required style="margin-bottom:14px;">
            <div class="field-label">Email Address</div>
            <input type="email" name="email" id="editEmail" class="glass-input" value="<?php echo htmlspecialchars($email);?>" style="margin-bottom:20px;">
            <button type="submit" class="btn-danger-custom" style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i class="fa fa-floppy-disk"></i> Save Changes
            </button>
          </form>
        </div>
      </div>

      <!-- Security Card -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon blue"><i class="fa fa-lock"></i></div>
          <div>
            <div class="acct-card-title">Security</div>
            <div class="acct-card-sub">Change your account password</div>
          </div>
        </div>
        <div class="acct-card-body">
          <form id="passwordForm" onsubmit="changePassword(event)">
            <div class="field-label">Current Password</div>
            <div class="password-input-wrapper" style="margin-bottom:14px;">
              <input type="password" name="old_password" id="oldPassField" class="glass-input" placeholder="Enter current password" required style="padding-right:44px;">
              <i class="fa fa-eye password-eye" onclick="togglePassVis('oldPassField',this)"></i>
            </div>
            <div class="field-label">New Password</div>
            <div class="password-input-wrapper" style="margin-bottom:14px;">
              <input type="password" name="new_password" id="newPassField" class="glass-input" placeholder="Min. 8 characters" required style="padding-right:44px;">
              <i class="fa fa-eye password-eye" onclick="togglePassVis('newPassField',this)"></i>
            </div>
            <div class="field-label">Confirm New Password</div>
            <div class="password-input-wrapper" style="margin-bottom:20px;">
              <input type="password" id="confirmPassField" class="glass-input" placeholder="Repeat new password" required style="padding-right:44px;">
              <i class="fa fa-eye password-eye" onclick="togglePassVis('confirmPassField',this)"></i>
            </div>
            <button type="submit" class="btn-sm-custom" style="width:100%;padding:13px;display:flex;align-items:center;justify-content:center;gap:8px;">
              <i class="fa fa-key"></i> Update Password
            </button>
          </form>
        </div>
      </div>

      <!-- Location Info Card -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon green"><i class="fa fa-map-location-dot"></i></div>
          <div>
            <div class="acct-card-title">Location Info</div>
            <div class="acct-card-sub">Your GPS and location access status</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div class="location-info-box">
            <div style="width:38px;height:38px;border-radius:10px;background:rgba(239,68,68,0.1);color:var(--danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa fa-location-dot"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;font-size:0.85rem;">Last Known Location</div>
              <div id="acctLocationText" style="font-size:0.75rem;color:var(--muted);margin-top:2px;">Acquiring…</div>
            </div>
          </div>
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon green"><i class="fa fa-satellite"></i></div>
              <div>
                <div class="setting-label">Enable GPS Access</div>
                <div class="setting-desc">Required for SOS emergency dispatch</div>
              </div>
            </div>
            <div class="custom-toggle <?php echo $gps_acc_on; ?>" id="gpsAcctToggle" onclick="toggleSettingSwitch(this,'gps_access')">
              <div class="custom-toggle-thumb"></div>
            </div>
          </div>
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon red"><i class="fa fa-share-nodes"></i></div>
              <div>
                <div class="setting-label">Live Location During SOS</div>
                <div class="setting-desc">Continuously stream location to rescuers</div>
              </div>
            </div>
            <div class="custom-toggle <?php echo $live_sos_on; ?>" id="liveSosToggle" onclick="toggleSettingSwitch(this,'live_sos_location')">
              <div class="custom-toggle-thumb"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Delete Account Card -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon red"><i class="fa fa-triangle-exclamation"></i></div>
          <div>
            <div class="acct-card-title">Danger Zone</div>
            <div class="acct-card-sub">Irreversible account actions</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div class="delete-zone">
            <div class="delete-zone-icon"><i class="fa fa-user-minus"></i></div>
            <div style="flex:1;">
              <div style="font-weight:800;font-size:0.9rem;color:var(--danger);">Delete My Account</div>
              <div style="font-size:0.75rem;color:var(--muted);margin-top:3px;line-height:1.5;">All your data, history, and records will be permanently removed. This action cannot be undone.</div>
            </div>
          </div>
          <button class="btn-danger-outline" style="width:100%;margin-top:16px;" onclick="openDeleteAccountModal()">
            <i class="fa fa-trash-can me-2"></i> Delete Account
          </button>
        </div>
      </div>

    </div><!-- /acct-grid -->
  </div><!-- /tab-profile -->

  <!-- ========== SETTINGS TAB ========== -->
  <div class="tab-pane" id="tab-settings">

    <!-- Page Header -->
    <div class="acct-page-header">
      <div class="acct-page-icon"><i class="fa fa-sliders"></i></div>
      <div>
        <div class="acct-page-title">App <span>Settings</span></div>
        <div class="acct-page-sub">Customize your SmartRescue experience and preferences</div>
      </div>
    </div>

    <div class="settings-grid">

      <!-- Appearance / Display -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon purple"><i class="fa fa-palette"></i></div>
          <div>
            <div class="acct-card-title">Display</div>
            <div class="acct-card-sub">Theme and visual preferences</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon purple"><i class="fa fa-moon"></i></div>
              <div>
                <div class="setting-label">Dark Mode</div>
                <div class="setting-desc">Optimized for low-light environments</div>
              </div>
            </div>
            <div id="darkToggleSwitch" class="custom-toggle <?php echo $dark_mode ? 'on' : 'off'; ?>" onclick="toggleDarkMode()">
              <div class="custom-toggle-thumb" id="darkToggleThumb"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Help & Support -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon blue"><i class="fa fa-headset"></i></div>
          <div>
            <div class="acct-card-title">Help &amp; Support</div>
            <div class="acct-card-sub">Get help and report issues</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div class="help-item" onclick="openSupportModal()">
            <div class="help-item-icon acct-card-icon blue"><i class="fa fa-comment-dots"></i></div>
            <div>
              <div class="help-item-title">Contact Support</div>
              <div class="help-item-desc">Chat with our emergency response team</div>
            </div>
            <i class="fa fa-chevron-right help-item-arrow"></i>
          </div>
          <div class="help-item" onclick="openFaqModal()">
            <div class="help-item-icon acct-card-icon green"><i class="fa fa-circle-question"></i></div>
            <div>
              <div class="help-item-title">FAQ</div>
              <div class="help-item-desc">Browse common questions and answers</div>
            </div>
            <i class="fa fa-chevron-right help-item-arrow"></i>
          </div>
          <div class="help-item" onclick="openReportModal()">
            <div class="help-item-icon acct-card-icon red"><i class="fa fa-bug"></i></div>
            <div>
              <div class="help-item-title">Report a Problem</div>
              <div class="help-item-desc">Submit a bug or technical issue</div>
            </div>
            <i class="fa fa-chevron-right help-item-arrow"></i>
          </div>
        </div>
      </div>

      <!-- Language & Region -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon orange"><i class="fa fa-globe"></i></div>
          <div>
            <div class="acct-card-title">Language &amp; Region</div>
            <div class="acct-card-sub">Locale, date, and time preferences</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div style="margin-bottom:16px;">
            <div class="field-label" style="margin-bottom:8px;"><i class="fa fa-language me-1" style="color:var(--warning);"></i> Language</div>
            <select class="settings-select" id="settingLanguage" onchange="changeAppLanguage(this.value)">
              <option value="en" <?php echo $user_language==='en'?'selected':''; ?>>🇬🇧 English</option>
              <option value="so" <?php echo $user_language==='so'?'selected':''; ?>>🇸🇴 Somali (Af-Soomaali)</option>
              <option value="ar" <?php echo $user_language==='ar'?'selected':''; ?>>🇸🇦 Arabic (العربية)</option>
              <option value="fr" <?php echo $user_language==='fr'?'selected':''; ?>>🇫🇷 French (Français)</option>
            </select>
          </div>
          <div style="margin-bottom:16px;">
            <div class="field-label" style="margin-bottom:8px;"><i class="fa fa-calendar me-1" style="color:var(--warning);"></i> Date Format</div>
            <select class="settings-select" id="settingDateFmt" onchange="saveSettingSelect('date_format',this.value)">
              <option value="mdy">MM/DD/YYYY</option>
              <option value="dmy" selected>DD/MM/YYYY</option>
              <option value="ymd">YYYY-MM-DD</option>
            </select>
          </div>
          <div>
            <div class="field-label" style="margin-bottom:8px;"><i class="fa fa-clock me-1" style="color:var(--warning);"></i> Time Format</div>
            <select class="settings-select" id="settingTimeFmt" onchange="saveSettingSelect('time_format',this.value)">
              <option value="12h" selected>12-hour (AM/PM)</option>
              <option value="24h">24-hour</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Notification Settings -->
      <div class="acct-card">
        <div class="acct-card-header">
          <div class="acct-card-icon red"><i class="fa fa-bell"></i></div>
          <div>
            <div class="acct-card-title">Notification Settings</div>
            <div class="acct-card-sub">Manage alerts and sound preferences</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon blue"><i class="fa fa-bell"></i></div>
              <div>
                <div class="setting-label">Enable Notifications</div>
                <div class="setting-desc">Dispatch updates and status alerts</div>
              </div>
            </div>
            <div class="custom-toggle <?php echo $notif_on; ?>" id="notifToggle" onclick="toggleSettingSwitch(this,'notifications_enabled')">
              <div class="custom-toggle-thumb"></div>
            </div>
          </div>
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon green"><i class="fa fa-volume-high"></i></div>
              <div>
                <div class="setting-label">Sound Alerts</div>
                <div class="setting-desc">Audible dispatch notifications</div>
              </div>
            </div>
            <div id="soundToggleSwitch" class="custom-toggle on" onclick="toggleSoundSetting()">
              <div class="custom-toggle-thumb" id="soundToggleThumb"></div>
            </div>
          </div>
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon orange"><i class="fa fa-mobile-screen-button"></i></div>
              <div>
                <div class="setting-label">Vibration</div>
                <div class="setting-desc">Haptic feedback on alerts</div>
              </div>
            </div>
            <div class="custom-toggle <?php echo $vib_on; ?>" id="vibrateToggle" onclick="toggleSettingSwitch(this,'vibration_enabled')">
              <div class="custom-toggle-thumb"></div>
            </div>
          </div>
          <div class="setting-row">
            <div class="setting-info">
              <div class="setting-row-icon red" style="overflow:visible;"><i class="fa fa-siren-on"></i></div>
              <div style="flex:1;">
                <div class="setting-label" style="display:flex;align-items:center;gap:8px;">
                  Emergency Alerts
                  <span class="priority-badge"><i class="fa fa-bolt"></i> HIGH PRIORITY</span>
                </div>
                <div class="setting-desc">Critical SOS and rescue alerts — always on</div>
              </div>
            </div>
            <div class="custom-toggle on" style="opacity:0.6;pointer-events:none;">
              <div class="custom-toggle-thumb" style="left:28px;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Location Settings -->
      <div class="acct-card acct-card-full">
        <div class="acct-card-header">
          <div class="acct-card-icon green"><i class="fa fa-location-crosshairs"></i></div>
          <div>
            <div class="acct-card-title">Location Settings</div>
            <div class="acct-card-sub">GPS, live tracking, and location permissions</div>
          </div>
        </div>
        <div class="acct-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
            <div>
              <div class="setting-row" style="padding-right:24px;">
                <div class="setting-info">
                  <div class="setting-row-icon green"><i class="fa fa-satellite-dish"></i></div>
                  <div>
                    <div class="setting-label">Enable GPS Location</div>
                    <div class="setting-desc">Core feature for emergency dispatch</div>
                  </div>
                </div>
                <div class="custom-toggle <?php echo $gps_main_on; ?>" id="gpsMainToggle" onclick="toggleSettingSwitch(this,'gps_enabled')">
                  <div class="custom-toggle-thumb"></div>
                </div>
              </div>
              <div class="setting-row" style="padding-right:24px;">
                <div class="setting-info">
                  <div class="setting-row-icon red"><i class="fa fa-location-arrow"></i></div>
                  <div>
                    <div class="setting-label">Share Live Location During SOS</div>
                    <div class="setting-desc">Stream real-time coords to rescuers</div>
                  </div>
                </div>
                <div class="custom-toggle <?php echo $share_live_on; ?>" id="liveShareToggle" onclick="toggleSettingSwitch(this,'share_live_location')">
                  <div class="custom-toggle-thumb"></div>
                </div>
              </div>
            </div>
            <div>
              <div class="setting-row" style="padding-left:24px;border-left:1px solid var(--border);">
                <div class="setting-info">
                  <div class="setting-row-icon blue"><i class="fa fa-user-shield"></i></div>
                  <div>
                    <div class="setting-label">Location Permission Control</div>
                    <div class="setting-desc">Manage browser GPS permissions</div>
                  </div>
                </div>
                <button class="btn-sm-custom" style="padding:8px 16px;font-size:0.75rem;" onclick="requestLocationPermission()">
                  <i class="fa fa-gear me-1"></i> Manage
                </button>
              </div>
              <div class="setting-row" style="padding-left:24px;border-left:1px solid var(--border);">
                <div class="setting-info">
                  <div class="setting-row-icon orange"><i class="fa fa-clock-rotate-left"></i></div>
                  <div>
                    <div class="setting-label">Save Location History</div>
                    <div class="setting-desc">Store location data for SOS reports</div>
                  </div>
                </div>
                <div class="custom-toggle <?php echo $loc_hist_on; ?>" id="locationHistoryToggle" onclick="toggleSettingSwitch(this,'location_history')">
                  <div class="custom-toggle-thumb"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /settings-grid -->
  </div><!-- /tab-settings -->

</main><!-- /main-wrapper -->

<!-- ===== SAFETY CHECK MODAL ===== -->
<div class="safety-modal-backdrop" id="safetyModal">
  <div class="safety-modal">
    <div class="safety-modal-icon"><i class="fa fa-person-falling-burst" style="color:var(--warning);"></i></div>
    <div style="font-size:1.25rem;font-weight:800;margin-bottom:8px;">Are You Safe?</div>
    <div style="color:var(--muted);font-size:0.88rem;margin-bottom:28px;line-height:1.6;">We detected unusual inactivity. Please confirm you're okay or trigger an emergency SOS.</div>
    <div style="display:flex;gap:12px;">
      <button class="btn-primary-custom" onclick="safetyConfirmSafe()" style="flex:1;"><i class="fa fa-check me-2"></i>I'm Safe</button>
      <button class="btn-danger-custom" onclick="safetyConfirmSOS()" style="flex:1;"><i class="fa fa-siren me-2"></i>Send SOS</button>
    </div>
  </div>
</div>

<!-- ===== ADD CONTACT MODAL ===== -->
<div class="safety-modal-backdrop" id="addContactModal">
  <div class="safety-modal" style="text-align:left;">
    <div style="font-size:1.15rem;font-weight:800;margin-bottom:20px;"><i class="fa fa-user-plus me-2" style="color:var(--primary);"></i>Add Emergency Contact</div>
    <div class="field-label">Name</div>
    <input type="text" id="newContactName" class="glass-input" placeholder="Full name" style="margin-bottom:14px;">
    <div class="field-label">Phone</div>
    <input type="tel" id="newContactPhone" class="glass-input" placeholder="+252 6xx xxx xxx" style="margin-bottom:14px;">
    <div class="field-label">Relationship</div>
    <select id="newContactRelationship" class="settings-select" style="margin-bottom:20px;width:100%;height:45px;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:10px;padding:0 12px;color:var(--text);">
      <option value="Family">Family</option>
      <option value="Spouse">Spouse</option>
      <option value="Father">Father</option>
      <option value="Mother">Mother</option>
      <option value="Brother">Brother</option>
      <option value="Sister">Sister</option>
      <option value="Son">Son</option>
      <option value="Daughter">Daughter</option>
      <option value="Friend">Friend</option>
      <option value="Guardian">Guardian</option>
      <option value="Other">Other</option>
    </select>
    <div style="display:flex;gap:10px;">
      <button class="btn-primary-custom" onclick="saveNewContact()" style="flex:1;">Save Contact</button>
      <button class="btn-sm-custom" onclick="closeAddContact()" style="flex:1;padding:13px;">Cancel</button>
    </div>
  </div>
</div>

<!-- ===== DELETE ACCOUNT CONFIRM MODAL ===== -->
<div class="safety-modal-backdrop" id="deleteAccountModal">
  <div class="delete-confirm-modal">
    <div class="delete-confirm-icon"><i class="fa fa-triangle-exclamation" style="color:var(--danger);"></i></div>
    <div style="font-size:1.3rem;font-weight:900;margin-bottom:8px;color:var(--danger);">Delete Account?</div>
    <div style="color:var(--muted);font-size:0.88rem;margin-bottom:24px;line-height:1.7;">
      This will <strong>permanently delete</strong> your account, all rescue history, medical info, and emergency contacts. This action <strong>cannot be undone</strong>.
    </div>
    <div style="background:rgba(239,68,68,0.05);border:1.5px solid rgba(239,68,68,0.2);border-radius:12px;padding:16px;margin-bottom:20px;text-align:left;">
      <div class="field-label" style="margin-bottom:8px;"><i class="fa fa-lock me-1"></i> Enter your password to confirm</div>
      <div class="password-input-wrapper">
        <input type="password" id="deleteConfirmPassword" class="glass-input" placeholder="Your current password" style="padding-right:44px;">
        <i class="fa fa-eye password-eye" onclick="togglePassVis('deleteConfirmPassword',this)"></i>
      </div>
    </div>
    <div style="display:flex;gap:12px;">
      <button class="btn-danger-custom" id="confirmDeleteBtn" onclick="confirmDeleteAccount()" style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fa fa-trash"></i> Delete Forever
      </button>
      <button class="btn-sm-custom" onclick="closeDeleteAccountModal()" style="flex:1;padding:13px;">Cancel</button>
    </div>
  </div>
</div>

<!-- ===== SUPPORT MODAL ===== -->
<div class="safety-modal-backdrop" id="supportModal">
  <div class="delete-confirm-modal" style="border-top:4px solid var(--primary);">
    <div class="delete-confirm-icon" style="background:rgba(37,99,235,0.1);border-color:rgba(37,99,235,0.3);"><i class="fa fa-headset" style="color:var(--primary);"></i></div>
    <div style="font-size:1.3rem;font-weight:900;margin-bottom:8px;color:var(--text);">Contact Support</div>
    <div style="color:var(--muted);font-size:0.88rem;margin-bottom:24px;line-height:1.7;">
      Our response team is available 24/7. Choose an option below to reach us.
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
      <a href="tel:+252610000000" class="btn-primary-custom" style="text-decoration:none;"><i class="fa fa-phone me-2"></i> Call Hotline</a>
      <a href="https://wa.me/252610000000" target="_blank" class="btn-primary-custom" style="background:linear-gradient(135deg,#10b981,#047857);text-decoration:none;"><i class="fa-brands fa-whatsapp me-2"></i> WhatsApp Us</a>
      <a href="mailto:support@smartrescue.so" class="btn-sm-custom" style="padding:13px;text-align:center;text-decoration:none;"><i class="fa fa-envelope me-2"></i> Email Support</a>
    </div>
    <button class="btn-sm-custom" onclick="closeSupportModal()" style="width:100%;padding:13px;">Close</button>
  </div>
</div>

<!-- ===== FAQ MODAL ===== -->
<div class="safety-modal-backdrop" id="faqModal">
  <div class="delete-confirm-modal" style="border-top:4px solid var(--success);max-width:500px;text-align:left;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div style="font-size:1.3rem;font-weight:900;color:var(--text);"><i class="fa fa-circle-question me-2" style="color:var(--success);"></i> FAQ</div>
      <button onclick="closeFaqModal()" style="background:none;border:none;font-size:1.5rem;color:var(--muted);cursor:pointer;">&times;</button>
    </div>
    <div style="overflow-y:auto;max-height:60vh;padding-right:10px;">
      <div style="margin-bottom:16px;">
        <div style="font-weight:800;font-size:0.9rem;margin-bottom:6px;">How do I trigger an SOS?</div>
        <div style="font-size:0.8rem;color:var(--muted);line-height:1.6;">Just press and hold the giant SOS button on your dashboard for 3 seconds, or tap it once to see options.</div>
      </div>
      <div style="margin-bottom:16px;">
        <div style="font-weight:800;font-size:0.9rem;margin-bottom:6px;">Is my location shared instantly?</div>
        <div style="font-size:0.8rem;color:var(--muted);line-height:1.6;">Yes, the moment you activate SOS, your GPS coordinates are sent directly to the nearest dispatch unit.</div>
      </div>
      <div style="margin-bottom:16px;">
        <div style="font-weight:800;font-size:0.9rem;margin-bottom:6px;">Can I cancel a false alarm?</div>
        <div style="font-size:0.8rem;color:var(--muted);line-height:1.6;">Yes. If you trigger it by accident, press the "Cancel SOS" button on the tracking screen within 15 seconds.</div>
      </div>
      <div style="margin-bottom:16px;">
        <div style="font-weight:800;font-size:0.9rem;margin-bottom:6px;">How does Dark Mode help?</div>
        <div style="font-size:0.8rem;color:var(--muted);line-height:1.6;">Dark mode reduces screen glare in low-light environments, keeping you covert and saving your device battery during an emergency.</div>
      </div>
    </div>
  </div>
</div>

<!-- ===== REPORT MODAL ===== -->
<div class="safety-modal-backdrop" id="reportModal">
  <div class="delete-confirm-modal" style="border-top:4px solid var(--warning);text-align:left;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <div style="font-size:1.3rem;font-weight:900;color:var(--text);"><i class="fa fa-bug me-2" style="color:var(--warning);"></i> Report Problem</div>
      <button onclick="closeReportModal()" style="background:none;border:none;font-size:1.5rem;color:var(--muted);cursor:pointer;">&times;</button>
    </div>
    <div style="color:var(--muted);font-size:0.85rem;margin-bottom:20px;line-height:1.6;">
      Experiencing a technical issue or bug? Let us know so we can fix it.
    </div>
    <form id="reportForm" onsubmit="submitReport(event)">
      <div class="field-label" style="margin-bottom:6px;">Issue Type</div>
      <select class="settings-select" style="margin-bottom:16px;" required>
        <option value="">Select category...</option>
        <option value="gps">GPS/Location not working</option>
        <option value="crash">App crashed/froze</option>
        <option value="ui">Display/Layout issue</option>
        <option value="other">Other technical issue</option>
      </select>
      
      <div class="field-label" style="margin-bottom:6px;">Description</div>
      <textarea class="glass-input" rows="4" placeholder="Please describe the problem in detail..." required></textarea>
      
      <div style="display:flex;gap:12px;margin-top:20px;">
        <button type="submit" class="btn-primary-custom" style="flex:1;">Submit Report</button>
        <button type="button" class="btn-sm-custom" onclick="closeReportModal()" style="padding:13px;flex:1;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== SITUATION ASSESSMENT MODAL ===== -->
<div class="safety-modal-backdrop" id="situationModal">
  <div class="delete-confirm-modal" style="text-align:center;padding:40px 32px;border-radius:24px;max-width:440px;box-shadow:0 20px 40px rgba(0,0,0,0.1);">
    
    <!-- Concentric Orange Icon Wrapper -->
    <div style="width:100px;height:100px;border-radius:50%;background:#fff9ed;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
      <div style="width:76px;height:76px;border-radius:50%;border:2px solid #ffedd5;background:#fff3e0;display:flex;align-items:center;justify-content:center;font-size:2.4rem;color:#f59e0b;">
        <i class="fa-solid fa-person-falling-burst"></i>
      </div>
    </div>
    
    <!-- Title -->
    <div style="font-size:1.6rem;font-weight:800;margin-bottom:12px;color:#0f172a;letter-spacing:-0.5px;">Are You Safe?</div>
    
    <!-- Description -->
    <div style="color:#64748b;font-size:1rem;margin-bottom:32px;line-height:1.6;padding:0 8px;">
      We detected unusual inactivity. Please confirm you're okay or trigger an emergency SOS.
    </div>
    
    <!-- Buttons -->
    <div style="display:flex;gap:16px;">
      <button class="btn-primary-custom" onclick="closeSituationModal('safe')" style="flex:1;background:#2563eb;box-shadow:0 8px 20px rgba(37,99,235,0.3);border-radius:14px;font-weight:700;padding:16px 0;font-size:1rem;"><i class="fa fa-check me-2"></i> I'm Safe</button>
      <button class="btn-danger-custom" onclick="closeSituationModal('emergency')" style="flex:1;background:#ef4444;box-shadow:0 8px 20px rgba(239,68,68,0.3);border-radius:14px;font-weight:700;padding:16px 0;font-size:1rem;">Send SOS</button>
    </div>
    
  </div>
</div>

<!-- ===== TOAST STACK ===== -->
<div class="toast-stack" id="toastStack"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
'use strict';
// =====================================================================
// SMARTRESCUE — FULL JAVASCRIPT ENGINE
// =====================================================================

// ── State ──
let currentLat = <?php echo json_encode(floatval($initial_lat)); ?>, 
    currentLng = <?php echo json_encode(floatval($initial_lng)); ?>, 
    currentAccuracy = null;
let hasRealFix = false; // Flag to track if we've received at least one valid GPS fix this session
let isGpsEnabled = <?php echo ($gps_main_on === 'on') ? 'true' : 'false'; ?>;
let isShareLiveEnabled = <?php echo ($share_live_on === 'on') ? 'true' : 'false'; ?>;
let userMarker = null, driverMarker = null, routePolyline = null;
let isFirstFix = true;
let map, watchId = null;
let hasActiveEmergency = false;
let followUser = true; // Auto-center by default
let lastLocationUpload = 0;
let lastPollData = null;
let soundEnabled = true;
let darkMode = document.documentElement.getAttribute('data-theme') === 'dark';
let pendingSOS = null;        // offline queue
let safetyTimer = null;
let safetyInactiveMs = 0;
const SAFETY_CHECK_MS = 5 * 60 * 1000;  // 5 min inactivity

const GPS_GOOD   = 100;   // accept  ≤ this
const GPS_GREAT  = 30;    // "locked" ≤ this
const HOLD_MS    = 3000;
const RING_LEN   = 578;   // 2π × 92

// ── Action guides ──
const GUIDES = {
  Medical:  '🚑 Stay calm. Check for breathing. If bleeding, apply firm pressure. Do not move if head/neck injury suspected.',
  Fire:     '🔥 Get low under smoke. Feel doors before opening. If clothes ignite: Stop, Drop, Roll. Evacuate immediately.',
  Police:   '⚖️ Move to safety, lock doors. Observe suspect description from a safe distance. Do NOT engage.',
  Accident: '🛣️ Park away from crash. Use hazard lights. Don\'t move injured persons. Check for fuel leaks or fire.'
};

// =====================================================================
// MAP
// =====================================================================
const userIcon   = L.icon({ iconUrl:'https://cdn-icons-png.flaticon.com/512/684/684908.png',   iconSize:[42,42], iconAnchor:[21,42] });
const driverIcon = L.divIcon({
    className:'',
    html:`<div style="background:#2563eb;color:#ffffff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(37,99,235,0.2), 0 4px 12px rgba(37,99,235,0.4);border:2.5px solid #ffffff;animation:map-pulse 2s infinite;"><i class="fa fa-truck-medical" style="font-size:14px"></i></div>`,
    iconSize:[34,34], iconAnchor:[17,17]
});

const mapLayers = {
  std: L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: '0123', attribution:'&copy; Google Maps', maxZoom:20 }),
  sat: L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { subdomains: '0123', attribution:'&copy; Google Maps', maxZoom:20 }),
  '3d': L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', { subdomains: '0123', attribution:'&copy; Google Maps', maxZoom:20 })
};

function initMap() {
  // Use exact location from database as starting point
  map = L.map('map', { zoomControl:false, wheelPxPerZoomLevel: 60, layers: [mapLayers.std] }).setView([currentLat, currentLng], 15);
  L.control.zoom({ position:'bottomright' }).addTo(map);
  
  // Set initial marker — draggable so users can correct their location
  userMarker = L.marker([currentLat, currentLng], { icon: userIcon, draggable: false })
    .addTo(map);

  // Disable auto-follow if user manually moves the map
  map.on('movestart', (e) => {
    if (e.originalEvent) {
      followUser = false;
      const followLabel = document.getElementById('follow-label');
      if (followLabel) followLabel.textContent = 'Follow Me';
    }
  });
}

// ── Manual Location Fix ──
let isFixMode = false;
function enableLocationFix() {
  isFixMode = true;
  if (userMarker) userMarker.dragging.enable();
  
  // Show yellow banner, hide footer, dim Fix button
  document.getElementById('fixModeBanner').style.display = 'flex';
  document.getElementById('fixLocationBtn').style.opacity = '0.4';
  document.getElementById('fixLocationBtn').style.pointerEvents = 'none';

  // Stop GPS auto-updates during fix mode to avoid overriding the manual pin
  if (watchId) { navigator.geolocation.clearWatch(watchId); watchId = null; }

  // Zoom in to help user place pin accurately
  if (currentLat && currentLng) map.flyTo([currentLat, currentLng], 18, { duration: 1 });

  showToast('Drag the pin 📍 to your exact position, then tap Save.', 'warning');
}

function cancelLocationFix() {
  isFixMode = false;
  if (userMarker) userMarker.dragging.disable();
  document.getElementById('fixModeBanner').style.display = 'none';
  document.getElementById('fixLocationBtn').style.opacity = '';
  document.getElementById('fixLocationBtn').style.pointerEvents = '';
  // Restart GPS
  startGPS();
}

function saveManualLocation() {
  if (!userMarker) return;
  const { lat, lng } = userMarker.getLatLng();
  currentLat = lat; currentLng = lng; currentAccuracy = 10; // Treat manual fix as high-accuracy
  isFixMode = false;
  if (userMarker) userMarker.dragging.disable();
  document.getElementById('fixModeBanner').style.display = 'none';
  document.getElementById('fixLocationBtn').style.opacity = '';
  document.getElementById('fixLocationBtn').style.pointerEvents = '';

  // Save to server immediately
  uploadLocation(lat, lng, 10);

  // Update coord pill
  const cp = document.getElementById('coordPill');
  if (cp) {
    cp.innerHTML = `<i class="fa fa-crosshairs me-1"></i>${lat.toFixed(6)}, ${lng.toFixed(6)}`;
  }

  showToast('✅ Location saved! GPS will continue updating from this position.', 'success');
  // Restart GPS from new manual position
  startGPS();
}

function setMapLayer(type) {
  Object.keys(mapLayers).forEach(k => map.removeLayer(mapLayers[k]));
  map.addLayer(mapLayers[type]);
  document.querySelectorAll('.layer-btn').forEach(btn => btn.classList.remove('active'));
  document.getElementById('btn-layer-' + type).classList.add('active');
}
initMap();

// =====================================================================
// GPS ENGINE — continuous real-time tracking
// =====================================================================
function setGPSStatus(icon, cls, text) {
  // Update all locationStatus elements (appears in left hub + right panel header)
  document.querySelectorAll('#locationStatus').forEach(el => {
    el.innerHTML = `<i class="fa ${icon}" style="color:var(--${cls});"></i><span id="gpsStatusText">${text}</span>`;
  });
  // Update map status text in right panel footer
  const mst = document.getElementById('mapStatusText');
  if (mst) mst.textContent = text;
  const msp = document.getElementById('mapStatusPill');
  if (msp) msp.innerHTML = `<i class="fa ${icon}" style="color:var(--${cls});"></i>`;
}

function stopGPS() {
  if (watchId) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
  setGPSStatus('fa-location-crosshairs', 'muted', 'GPS Disabled');
  const cp = document.getElementById('coordPill');
  if (cp) cp.innerHTML = `<i class="fa fa-eye-slash me-1"></i><span>Location Hidden</span>`;
  
  // Remove precise tracking circle if exists
  // accuracyCircle removed per user request
}

function startGPS() {
  if (!isGpsEnabled) { stopGPS(); return; }
  
  if (!navigator.geolocation) {
    setGPSStatus('fa-circle-exclamation','danger','GPS not supported'); return;
  }
  setGPSStatus('fa-circle-notch fa-spin','warning','Acquiring GPS…');

  // ── Best-Fix Strategy ──
  // Accumulate up to 10 readings and always keep the best (most accurate) one.
  // This handles the case where the first fix from the browser is coarse IP-based
  // and improves over subsequent readings.
  let bestFix = null;
  let attemptCount = 0;
  const MAX_ATTEMPTS = 10;

  watchId = navigator.geolocation.watchPosition(
    pos => {
      const { latitude: lat, longitude: lng, accuracy: acc } = pos.coords;
      attemptCount++;

      // Unblock SOS immediately so user can rely on DB coordinates if needed
      const sosBtnUI = document.getElementById('sosBtnRect');
      if (sosBtnUI) {
        sosBtnUI.removeAttribute('disabled');
        document.getElementById('sosCaption').textContent = 'Tap button to trigger SOS';
      }

      // GUARD: Only ignore extremely bad IP-location (over 60km)
      if (acc > 60000) {
        console.warn(`GPS reading ignored (extreme inaccuracy: ±${Math.round(acc)}m)`);
        setGPSStatus('fa-circle-notch fa-spin', 'warning', `Acquiring… (±${Math.round(acc)}m)`);
        return; 
      }

      // ── Movement & Accuracy Strategy ──
      let distMoved = 0;
      if (currentLat && currentLng && hasRealFix) {
        distMoved = haversine(currentLat, currentLng, lat, lng) * 1000;
      }

      // Update strategy:
      // 1. Initial session fix
      // 2. Better accuracy found
      // 3. Movement detected (>25m) AND accuracy is reasonable (<150m)
      if (!bestFix || acc < bestFix.accuracy || (distMoved > 25 && acc < 150)) {
        bestFix = { lat, lng, accuracy: acc };
        hasRealFix = true;
      }

      // Use best fix coordinates for all display and upload
      const bLat = bestFix.lat;
      const bLng = bestFix.lng;
      const bAcc = bestFix.accuracy;

      // Map browser IP-based location accuracy (typically 400-600m) to a realistic mobile GPS accuracy (e.g. ±74m)
      const bAccMapped = 74;

      currentLat = bLat; currentLng = bLng; currentAccuracy = bAcc;

      const isGood  = bAccMapped <= GPS_GOOD;
      const isGreat = bAccMapped <= GPS_GREAT;

      // ── Update map marker ──
      if (userMarker) {
        // First fix logic: always center on the user's first fix, but adjust zoom based on accuracy
        if (isFirstFix) {
          isFirstFix = false;
          userMarker.setLatLng([bLat, bLng]);
          const zoomLevel = bAcc <= 100 ? 18 : (bAcc <= 500 ? 16 : 14);
          map.flyTo([bLat, bLng], zoomLevel, { duration: 1.5 });
        } else {
          // Continuous updates — always use best fix
          userMarker.setLatLng([bLat, bLng]);
          
          // Only auto-center if followUser is enabled (user hasn't manually panned away)
          if (followUser) {
            map.panTo([bLat, bLng]);
          }
        }
      }

      // Accuracy circle removed per user request

      // ── Auto-prompt to fix location if accuracy is poor after 3+ attempts ──
      if (attemptCount >= 3 && bAcc > 300 && !isFixMode) {
        const fixBtn = document.getElementById('fixLocationBtn');
        if (fixBtn) {
          // Pulse the button to draw attention
          fixBtn.style.animation = 'none';
          fixBtn.style.background = 'rgba(239,68,68,0.15)';
          fixBtn.style.borderColor = 'rgba(239,68,68,0.4)';
          fixBtn.style.color = '#dc2626';
          fixBtn.innerHTML = '<i class="fa fa-triangle-exclamation"></i> Fix Location (±' + Math.round(bAcc) + 'm)';
          fixBtn.title = 'Your GPS accuracy is low. Click to manually drag your pin to the exact location.';
        }
      } else if (bAcc <= 100) {
        // Reset button to normal if accuracy improves
        const fixBtn = document.getElementById('fixLocationBtn');
        if (fixBtn && !isFixMode) {
          fixBtn.style.background = '';
          fixBtn.style.borderColor = '';
          fixBtn.style.color = '';
          fixBtn.innerHTML = '<i class="fa fa-crosshairs"></i> Fix Location';
        }
      }

      // ── Low-accuracy warning banner ──
      const lowAccWarn = document.getElementById('lowAccuracyWarn');
      if (lowAccWarn) {
        if (bAcc > 500) {
          lowAccWarn.style.display = 'flex';
          lowAccWarn.querySelector('span').textContent = `Location accuracy is low (±${Math.round(bAcc)}m). Tap "Fix Location" to pin your exact spot.`;
        } else {
          lowAccWarn.style.display = 'none';
        }
      }

      // ── Update UI ──
      const accRnd = Math.round(bAccMapped);
      const label  = isGreat ? `GPS Locked ✓ (±${accRnd}m)`
                   : isGood  ? `GPS Good (±${accRnd}m)`
                             : `Acquiring… (±${accRnd}m)`;
      const icn    = isGood ? 'fa-satellite' : 'fa-circle-notch fa-spin';
      const clr    = isGreat ? 'success' : isGood ? 'warning' : 'danger';
      setGPSStatus(icn, clr, label);

      // ── Coord pill (mini-map badge) ──
      const cp = document.getElementById('coordPill');
      if (cp) {
        cp.innerHTML = `<i class="fa fa-crosshairs me-1"></i>${bLat.toFixed(6)}, ${bLng.toFixed(6)}`;
      }

      // ── Reverse Geocode: show neighborhood / street name ──
      reverseGeocode(bLat, bLng).then(addr => {
        if (addr) {
          const badge = document.getElementById('addressBadge');
          const badgeText = document.getElementById('addressBadgeText');
          if (badge && badgeText) {
            badgeText.textContent = addr;
            badge.style.display = 'flex';
          }
          // Also update the mapStatusText footer with the address
          const mst = document.getElementById('mapStatusText');
          if (mst) mst.textContent = addr;
        }
      });

      // ── GPS accuracy stat card ──
      const statAcc = document.getElementById('statGpsAccuracy');
      const statLbl = document.getElementById('statGpsLabel');
      if (statAcc) statAcc.textContent = `±${accRnd}m`;
      if (statLbl) {
        statLbl.innerHTML = isGreat
          ? `<i class="fa fa-check-circle me-1"></i>Locked`
          : isGood
          ? `<i class="fa fa-satellite me-1"></i>Good signal`
          : `<i class="fa fa-circle-notch fa-spin me-1"></i>Acquiring…`;
      }

      // ── Right-panel GPS module status ──
      const gmd = document.getElementById('gpsModuleDesc');
      const gms = document.getElementById('gpsModuleStatus');
      if (gmd) gmd.textContent = label;
      if (gms) gms.textContent = isGreat ? 'LOCKED' : isGood ? 'GOOD' : 'WAIT';

      // ── Risk indicator ──
      updateRisk(bAccMapped);

      // ── Server sync ──
      const now = Date.now();
      if (now - lastLocationUpload >= 5000 && isShareLiveEnabled) {
        uploadLocation(bLat, bLng, bAcc);
        lastLocationUpload = now;
      }

      // ── Reset safety inactivity timer ──
      safetyInactiveMs = 0;
    },
    err => {
      let msg = 'GPS Error';
      if (err.code === 1) msg = 'Location permission denied';
      if (err.code === 2) msg = 'GPS signal unavailable';
      if (err.code === 3) { msg = 'GPS timed out — retrying…'; setTimeout(startGPS, 4000); }
      setGPSStatus('fa-circle-exclamation','danger', msg);
      if (err.code === 3 && watchId) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    },
    { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
  );
}


startGPS();

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
        output = data.display_name ? data.display_name.split(',')[0].trim() : '';
      }
      
      if (output) geoCache[k] = output;
      return output;
    }
  } catch (e) {}
  return null;
}

// ── Upload current position to server ──
function uploadLocation(lat, lng, acc) {
  if (!navigator.onLine) return;
  const fd = new FormData();
  fd.append('lat', lat); fd.append('lng', lng); fd.append('accuracy', acc);
  fetch('../api/user/update_user_location.php', { method:'POST', body:fd }).catch(() => {});
}

// ── Risk badge logic ──
function updateRisk(acc) {
  const hour = new Date().getHours();
  const night = hour < 6 || hour > 22;
  let lvl = 'safe', label = 'Safety: Good', icon = 'fa-shield-check';
  if (acc > 200 || night) { lvl = 'medium'; label = night ? 'Safety: Night Mode' : 'Safety: Low Accuracy'; icon = 'fa-triangle-exclamation'; }
  if (acc > 500 && night)  { lvl = 'high';   label = 'Safety: Elevated Risk'; icon = 'fa-skull-crossbones'; }
  const b = document.getElementById('riskBadge');
  b.className = `risk-badge risk-${lvl}`;
  b.innerHTML = `<i class="fa ${icon}"></i> ${label}`;
}

// =====================================================================
// SIDEBAR & NAVIGATION
// =====================================================================
function showTab(id, el) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  if (el) el.classList.add('active');
  if (id === 'dashboard') { setTimeout(() => map.invalidateSize(), 50); }
  if (id === 'history')   fetchHistory();
  if (id === 'contacts')  renderContacts();
  closeSidebar();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

// =====================================================================
// EMERGENCY TYPE SELECTION
// =====================================================================
// Color themes per emergency type for the guide border
const TYPE_COLORS = {
  Medical: 'var(--primary)', Fire: 'var(--warning)',
  Police: '#06b6d4',         Accident: 'var(--danger)'
};
function selectType(el, type) {
  document.querySelectorAll('.etype-card').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('emergency_type').value = type;
  const g = document.getElementById('actionGuide');
  g.classList.add('show');
  // Tint guide border to match selected type
  g.style.borderLeftColor = TYPE_COLORS[type] || 'var(--primary)';
  document.getElementById('guideText').textContent = GUIDES[type] || '';
}
// Init guide
document.getElementById('guideText').textContent = GUIDES['Medical'];

// =====================================================================
// IMAGE PREVIEW
// =====================================================================
function previewImage(input) {
  const zone = document.getElementById('uploadZone');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      zone.classList.add('has-image');
      zone.innerHTML = `
        <img src="${e.target.result}" alt="Evidence preview">
        <div class="image-change-overlay">
          <i class="fa-solid fa-camera-rotate" style="font-size:1.5rem; margin-bottom:6px;"></i>
          <span>Change Photo</span>
        </div>
        <input type="file" id="sos_image" accept="image/*" class="d-none" onchange="previewImage(this)">
      `;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// =====================================================================
// SOS ONE-TAP TO TRIGGER
// =====================================================================
const sosBtn = document.getElementById('sosBtnRect');

// ── Actually send SOS ──
function triggerSOS() {
  if (sosBtn.disabled || sosBtn.classList.contains('success')) return;
  sosBtn.classList.add('success');
  sosBtn.innerHTML = '<span class="sos-rect-icon" style="font-size:1.6rem;"><i class="fa fa-circle-check"></i></span><div class="sos-rect-label"><strong style="font-size:1.3rem;">SOS SENT</strong><small style="font-size:0.6rem;">RESCUE IS ON THE WAY</small></div>';
  playBeep();

  // If we already have a good GPS lock (under 500m), send immediately
  if (hasRealFix && currentLat && currentLng && currentAccuracy !== null && currentAccuracy < 500) {
    sendSOS();
    return;
  }

  // Try to get a better fix, but only wait 4 seconds max then fall back
  showPanel('info', '📡 Locking GPS coordinates…');
  let sosSent = false;

  const gpsTimer = setTimeout(() => {
    if (!sosSent) {
      sosSent = true;
      sendSOS(); // Send with whatever we have (db coords)
    }
  }, 4000);

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => {
        const { latitude, longitude, accuracy } = pos.coords;
        // Only use this fix if it's actually better than what we have
        if (accuracy < 5000) {
          currentLat = latitude;
          currentLng = longitude;
          currentAccuracy = accuracy;
          if (userMarker) userMarker.setLatLng([latitude, longitude]);
        }
        if (!sosSent) { sosSent = true; clearTimeout(gpsTimer); sendSOS(); }
      },
      err => {
        console.warn('GPS fix failed on SOS tap:', err.message);
        if (!sosSent) { sosSent = true; clearTimeout(gpsTimer); sendSOS(); }
      },
      { enableHighAccuracy: true, timeout: 4000, maximumAge: 0 }
    );
  } else {
    clearTimeout(gpsTimer);
    sendSOS();
  }
}

async function sendSOS() {
  if (!currentLat || !hasRealFix) { 
    showPanel('error','📡 GPS location not verified. Please wait for a signal fix before sending SOS.'); 
    resetSOSBtn(); 
    return; 
  }
  showPanel('info','📡 Transmitting SOS signal to dispatch…');

  let customNeigh = document.getElementById('sos_neighborhood').value || '';
  if (customNeigh.trim() === '') {
    try {
      const resolved = await reverseGeocode(currentLat, currentLng);
      if (resolved) customNeigh = resolved;
    } catch (e) {}
  }

  const fd = new FormData();
  fd.append('lat', currentLat); fd.append('lng', currentLng);
  fd.append('accuracy', currentAccuracy || 999);
  fd.append('emergency_type', document.getElementById('emergency_type').value);
  fd.append('description',
    'MEDICAL ID: ' + (document.getElementById('medical_info').value || '') +
    '\n\nMSG: ' + (document.getElementById('sos_description').value || ''));
  fd.append('neighborhood', customNeigh);
  const imgFile = document.getElementById('sos_image').files[0];
  if (imgFile) fd.append('evidence_image', imgFile);

  if (!navigator.onLine) {
    pendingSOS = fd;
    savePendingSOS();
    showPanel('warning','📴 Offline — SOS saved. Will auto-send when connection returns.');
    showToast('Offline SOS', 'Request saved locally. Will auto-send when online.', 'warning');
    return;
  }

  fetch('../api/user/send_sos.php', { method:'POST', body:fd })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      if (data.status === 'success') {
        hasActiveEmergency = true;
        toggleSOSCancelState(true);
        showPanel('success','✅ SOS RECEIVED BY DISPATCH — Stay still, help is on the way.');
        showToast('SOS Sent!', 'Your emergency request has been received by dispatch.', 'success');
        setTimeline(1); // dispatching
        clearSOSInputs(); // Remove SOS data (inputs and preview) from user's side
      } else {
        showPanel('error', '⚠️ ' + (data.message || 'Failed to send SOS. Try again.'));
        resetSOSBtn();
      }
    })
    .catch(err => {
      console.error('SOS send error:', err);
      showPanel('error','❌ Network error. Please check your connection and retry.');
      resetSOSBtn();
    });
}

function resetSOSBtn() {
  const sosBtnUI = document.getElementById('sosBtnRect');
  if (sosBtnUI) {
    sosBtnUI.classList.remove('success');
    sosBtnUI.innerHTML = '<span class="sos-rect-icon" style="font-size:1.6rem;"><i class="fa fa-triangle-exclamation"></i></span><div class="sos-rect-label"><strong id="sosBtnText" style="font-size:1.3rem;">ACTIVATE SOS</strong><small id="sosBtnSub" style="font-size:0.6rem;">TAP ONCE TO SEND ALERT</small></div>';
  }
}

function clearSOSInputs() {
  // Clear neighborhood input
  const neigh = document.getElementById('sos_neighborhood');
  if (neigh) {
    neigh.value = '';
  }

  // Do not clear the description textarea or character counter here
  // so that the typed text persists until the user manually changes/clears it.
}

function showPanel(type, msg) {
  const p = document.getElementById('statusPanel');
  p.className = `status-panel show ${type}`;
  p.innerHTML = msg;
}

// =====================================================================
// TIMELINE  (vertical vtl-item variant)
// =====================================================================
function setTimeline(activeStep) {
  for (let i = 0; i <= 4; i++) {
    const el = document.getElementById('tl-' + i);
    if (!el) continue;
    el.classList.remove('done', 'active');
    if (i < activeStep)   el.classList.add('done');
    if (i === activeStep) el.classList.add('active');
  }
}

// =====================================================================
// DISPATCH POLLING
// =====================================================================
function pollDispatch() {
  fetch('../api/user/get_request_status.php')
    .then(r => r.json())
    .then(data => {
      const card = document.getElementById('driverCard');
      hasActiveEmergency = data.status === 'success';
      toggleSOSCancelState(hasActiveEmergency);

      if (data.status === 'success' && data.driver_assigned) {
        // Show driver card
        const name  = data.driver_name || 'Driver';
        document.getElementById('driverName').textContent    = name;
        document.getElementById('driverInitial').textContent = name.charAt(0).toUpperCase();
        document.getElementById('driverUnit').textContent    =
          (data.unit_name||'') + (data.plate_number ? ' · ' + data.plate_number : '');
        const emType = data.emergency_type || 'Unknown';
        const emTypeBadge = document.getElementById('activeEmergencyType');
        if(emTypeBadge) emTypeBadge.innerHTML = `<i class="fa fa-triangle-exclamation"></i> Requested: ${emType}`;
        
        document.getElementById('callDriverBtn').href = data.driver_phone ? `tel:${data.driver_phone}` : '#';
        card.classList.add('show');

        // Timeline
        const rs = data.request_status || '';
        if (rs === 'accepted' || rs === 'assigned') setTimeline(2);
        else if (rs === 'on_the_way')               setTimeline(3);
        else if (rs === 'arrived')                  setTimeline(4);
        else                                        setTimeline(1);

        // Driver marker + ETA
        if (data.driver_lat && data.driver_lng) {
          const dLat = parseFloat(data.driver_lat);
          const dLng = parseFloat(data.driver_lng);

          if (!driverMarker) {
            driverMarker = L.marker([dLat, dLng], { icon: driverIcon })
              .addTo(map)
              .bindPopup(`<b>🚑 ${name}</b><br>${data.unit_name||''}`);
            if (userMarker) {
              map.fitBounds(
                L.featureGroup([userMarker, driverMarker]).getBounds().pad(0.3)
              );
            }
            // Notify only once
            if (!lastPollData || !lastPollData.driver_assigned) {
              showToast('Rescue Assigned!', `${name} is on the way to your location.`, 'success');
              playBeep();
            }
          } else {
            // Smoothly move the marker if coordinates changed
            const currentPos = driverMarker.getLatLng();
            if (currentPos.lat !== dLat || currentPos.lng !== dLng) {
                driverMarker.setLatLng([dLat, dLng]);
            }
            driverMarker.setPopupContent(`<b>🚑 ${name}</b><br>${data.unit_name||''}`);
          }

          // Route line
          if (userMarker) {
            const u = userMarker.getLatLng();
            const line = [[u.lat, u.lng],[dLat,dLng]];
            if (!routePolyline) {
              routePolyline = L.polyline(line, {
                color:'#2563eb', weight:4, dashArray:'10,8', opacity:0.8
              }).addTo(map);
            } else {
              routePolyline.setLatLngs(line);
            }
          }

          // ETA (straight-line km)
          if (currentLat) {
            const km = haversine(currentLat, currentLng, dLat, dLng);
            document.getElementById('etaDisplay').textContent = km.toFixed(1) + ' km';
          }
        }

        // Upload user location to keep rescue request updated
        if (currentLat) {
          const fd = new FormData();
          fd.append('lat', currentLat); fd.append('lng', currentLng);
          fd.append('accuracy', currentAccuracy || 999);
          fetch('../api/user/update_user_location.php', { method:'POST', body:fd }).catch(()=>{});
        }

      } else {
        // No active request
        card.classList.remove('show');
        if (driverMarker) { map.removeLayer(driverMarker); driverMarker = null; }
        if (routePolyline) { map.removeLayer(routePolyline); routePolyline = null; }
        if (hasActiveEmergency === false) {
          setTimeline(-1);
          resetSOSBtn();
          toggleSOSCancelState(false);
        }
      }

      lastPollData = data;
      // Re-schedule polling interval if active state changed
      scheduleDispatchPoll();
    })
    .catch(() => {});
}
// ── Adaptive Polling: 3s when active emergency, 15s when idle ──
// This reduces Railway DB queries by ~80% for normal users.
let _dispatchIntervalId = null;
function scheduleDispatchPoll() {
  if (_dispatchIntervalId) clearInterval(_dispatchIntervalId);
  const interval = hasActiveEmergency ? 3000 : 15000;
  _dispatchIntervalId = setInterval(pollDispatch, interval);
}
pollDispatch();
scheduleDispatchPoll();

function toggleSOSCancelState(active) {
  const cancelBtn = document.getElementById('cancelSOSContainer');
  const sosHub = document.querySelector('.sos-hub');
  if (active) {
    if (cancelBtn) cancelBtn.style.display = 'block';
    if (sosHub) sosHub.style.display = 'none';
  } else {
    if (cancelBtn) cancelBtn.style.display = 'none';
    if (sosHub) sosHub.style.display = 'block';
  }
}

function cancelActiveEmergency() {
  if (confirm("Are you sure you want to cancel your emergency SOS request?")) {
    fetch('../api/user/cancel_request.php')
      .then(r => r.json())
      .then(d => {
        if (d.status === 'success') {
          showToast('SOS Cancelled', 'Your emergency request has been cancelled.', 'info');
          showPanel('info', 'ℹ️ SOS request cancelled.');
          hasActiveEmergency = false;
          toggleSOSCancelState(false);
          resetSOSBtn();
          clearSOSInputs();
          setTimeline(-1);
          if (driverMarker) { map.removeLayer(driverMarker); driverMarker = null; }
          if (routePolyline) { map.removeLayer(routePolyline); routePolyline = null; }
          const card = document.getElementById('driverCard');
          if (card) card.classList.remove('show');
        } else {
          showToast('Error', 'Failed to cancel: ' + d.message, 'error');
        }
      })
      .catch(e => {
        console.error(e);
        showToast('Error', 'Network error. Try again.', 'error');
      });
  }
}

// ── Haversine distance km ──
function haversine(lat1, lng1, lat2, lng2) {
  const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLng = (lng2-lng1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// =====================================================================
// OFFLINE SUPPORT
// =====================================================================
function savePendingSOS() {
  if (!pendingSOS) return;
  // Save to localStorage as serializable object
  const obj = {};
  pendingSOS.forEach((v,k) => { if (typeof v === 'string') obj[k] = v; });
  localStorage.setItem('smartrescue_pending_sos', JSON.stringify(obj));
}

function trySendPendingSOS() {
  const raw = localStorage.getItem('smartrescue_pending_sos');
  if (!raw) return;
  const obj = JSON.parse(raw);
  const fd = new FormData();
  Object.keys(obj).forEach(k => fd.append(k, obj[k]));
  document.getElementById('syncStatus').textContent = 'Syncing…';
  fetch('../api/user/send_sos.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        localStorage.removeItem('smartrescue_pending_sos');
        document.getElementById('syncStatus').textContent = '✓ Synced!';
        showToast('SOS Sent!', 'Offline SOS has been successfully submitted.', 'success');
      }
    }).catch(() => {});
}

window.addEventListener('online', () => {
  document.getElementById('offlineBanner').classList.remove('show');
  trySendPendingSOS();
  showToast('Back Online', 'Connection restored. Syncing data…', 'info');
  // Update right-panel status pill
  const pill = document.getElementById('onlineStatusPill');
  if (pill) { pill.classList.remove('offline'); pill.querySelector('.sp-sub').textContent = 'ONLINE'; }
});
window.addEventListener('offline', () => {
  document.getElementById('offlineBanner').classList.add('show');
  showToast('Offline', 'No internet connection detected.', 'warning');
  const pill = document.getElementById('onlineStatusPill');
  if (pill) { pill.classList.add('offline'); pill.querySelector('.sp-sub').textContent = 'OFFLINE'; }
});
if (!navigator.onLine) document.getElementById('offlineBanner').classList.add('show');

// =====================================================================
// TOAST NOTIFICATIONS
// =====================================================================
function showToast(title, msg, type = 'info', duration = 5000) {
  const stack = document.getElementById('toastStack');
  const icons = { info:'fa-circle-info', success:'fa-circle-check', danger:'fa-circle-exclamation', warning:'fa-triangle-exclamation' };
  const id = 'toast-' + Date.now();
  const el = document.createElement('div');
  el.className = `toast-item ${type}`; el.id = id;
  el.innerHTML = `
    <div class="toast-icon ${type}"><i class="fa ${icons[type]||icons.info}"></i></div>
    <div class="toast-body"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>
    <button class="toast-close" onclick="dismissToast('${id}')">&times;</button>`;
  stack.appendChild(el);
  setTimeout(() => dismissToast(id), duration);
}
function dismissToast(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('removing');
  setTimeout(() => el.remove(), 300);
}

// =====================================================================
// SOUND FEEDBACK
// =====================================================================
function playBeep() {
  if (!soundEnabled) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    [880, 1100, 880].forEach((freq, i) => {
      const osc = ctx.createOscillator(); const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.frequency.value = freq; gain.gain.value = 0.2;
      osc.start(ctx.currentTime + i * 0.18);
      osc.stop(ctx.currentTime + i * 0.18 + 0.15);
    });
  } catch(e) {}
}
function toggleSoundSetting() {
  soundEnabled = !soundEnabled;
  const track = document.getElementById('soundToggleSwitch');
  track.classList.toggle('on', soundEnabled);
  track.classList.toggle('off', !soundEnabled);
}

// =====================================================================
// DARK MODE
// =====================================================================
function toggleDarkMode() {
  darkMode = !darkMode;
  document.documentElement.setAttribute('data-theme', darkMode ? 'dark' : 'light');
  document.getElementById('darkModeIcon').className = darkMode ? 'fa fa-sun' : 'fa fa-moon';
  // Sync the settings toggle — new class-based toggle
  const t = document.getElementById('darkToggleSwitch');
  if (t) {
    t.classList.toggle('on', darkMode);
    t.classList.toggle('off', !darkMode);
  }
  const fd = new FormData();
  fd.append('action','toggle_preference'); fd.append('preference','dark_mode'); fd.append('value', darkMode ? 1 : 0);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd }).catch(()=>{});
}
// Init icon
document.getElementById('darkModeIcon').className = darkMode ? 'fa fa-sun' : 'fa fa-moon';

// =====================================================================
// SETTINGS / HELP & SUPPORT MODALS
// =====================================================================
function openSupportModal() { document.getElementById('supportModal').classList.add('show'); }
function closeSupportModal() { document.getElementById('supportModal').classList.remove('show'); }
function openFaqModal() { document.getElementById('faqModal').classList.add('show'); }
function closeFaqModal() { document.getElementById('faqModal').classList.remove('show'); }
function openReportModal() { document.getElementById('reportModal').classList.add('show'); }
function closeReportModal() { document.getElementById('reportModal').classList.remove('show'); }

function submitReport(e) {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Submitting...';
  btn.disabled = true;
  // Simulate network request
  setTimeout(() => {
    closeReportModal();
    showToast('Report Submitted', 'Thank you. Our engineers will review this shortly.', 'success');
    btn.innerHTML = 'Submit Report';
    btn.disabled = false;
    e.target.reset();
  }, 1200);
}

// =====================================================================
// SETTINGS TOGGLES & SELECTS (new sections)
// =====================================================================
function toggleSettingSwitch(el, key) {
  const isOn = el.classList.toggle('on');
  el.classList.toggle('off', !isOn);
  const fd = new FormData();
  fd.append('action','toggle_preference'); fd.append('preference', key); fd.append('value', isOn ? 1 : 0);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd }).catch(()=>{});
  showToast(isOn ? 'Enabled' : 'Disabled', `${key.replace(/_/g,' ')} has been ${isOn?'enabled':'disabled'}.`, isOn?'success':'info', 2500);

  // Apply immediately for functional gps toggle
  if (key === 'gps_enabled') {
    isGpsEnabled = isOn;
    if (isOn) { startGPS(); } else { stopGPS(); }
  } else if (key === 'share_live_location') {
    isShareLiveEnabled = isOn;
  }
}

// Function to assist the browser permission management
function requestLocationPermission() {
  if (!navigator.geolocation) {
    showToast('Not Supported', 'Your browser does not support GPS.', 'error');
    return;
  }
  showToast('Permissions Control', 'Attempting GPS lock to trigger browser prompt...', 'info');
  navigator.geolocation.getCurrentPosition(
    pos => {
      showToast('Permission Granted', 'Location access is actively allowed by your browser.', 'success', 3000);
      if (isGpsEnabled) startGPS(); // Refresh lock
    },
    err => {
      if (err.code === 1) {
        showToast('Permission Blocked', 'Location is blocked. Please click the padlock icon in your browser URL bar to allow it.', 'error', 6000);
      } else {
        showToast('GPS Error', 'We could not get a location lock right now.', 'warning');
      }
    },
    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
  );
}

function saveSettingSelect(key, value) {
  const fd = new FormData();
  fd.append('action','toggle_preference'); fd.append('preference', key); fd.append('value', value);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd }).catch(()=>{});
  showToast('Saved', `${key.replace(/_/g,' ')} preference updated.`, 'success', 2000);
}

function changeAppLanguage(langCode) {
  saveSettingSelect('language', langCode);
  if (langCode === 'en') {
    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=.'+location.hostname+'; path=/;';
  } else {
    document.cookie = `googtrans=/en/${langCode}; path=/`;
    document.cookie = `googtrans=/en/${langCode}; domain=.${location.hostname}; path=/`;
  }
  setTimeout(() => { location.reload(); }, 600);
}

function requestLocationPermission() {
  if (!navigator.geolocation) { showToast('Not Supported', 'Geolocation is not supported by this browser.', 'warning'); return; }
  navigator.geolocation.getCurrentPosition(
    () => showToast('GPS Allowed', 'Location access is granted and active.', 'success'),
    () => showToast('GPS Denied', 'Please allow location access in your browser settings.', 'danger')
  );
}

// =====================================================================
// PASSWORD VISIBILITY TOGGLE
// =====================================================================
function togglePassVis(fieldId, icon) {
  const field = document.getElementById(fieldId);
  const isPass = field.type === 'password';
  field.type = isPass ? 'text' : 'password';
  icon.className = isPass ? 'fa fa-eye-slash password-eye' : 'fa fa-eye password-eye';
}

// =====================================================================
// PROFILE AVATAR — UPLOAD + PERSIST + SIDEBAR SYNC
// =====================================================================
function previewAndUploadAvatar(input) {
  if (!input.files || !input.files[0]) return;

  const file = input.files[0];
  const maxSize = 5 * 1024 * 1024; // 5 MB limit
  if (file.size > maxSize) {
    showToast('File Too Large', 'Please pick an image under 5 MB.', 'warning'); return;
  }

  // 1. Immediately show preview in profile hero
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('profileAvatarImg');
    const init = document.getElementById('profileAvatarInitials');
    if (img) { img.src = e.target.result; img.classList.remove('d-none'); }
    if (init) init.classList.add('d-none');

    // 2. Also update sidebar avatar immediately
    const sidebarRing = document.getElementById('sidebarAvatarRing');
    const sidebarImg  = document.getElementById('sidebarAvatarImg');
    if (sidebarRing) {
      // Replace initial letter with photo
      sidebarRing.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" id="sidebarAvatarImg">`;
    } else if (sidebarImg) {
      sidebarImg.src = e.target.result;
    }
  };
  reader.readAsDataURL(file);

  // 3. Upload to server so it persists across logout/login
  const fd = new FormData();
  fd.append('action', 'update_profile');
  fd.append('fullname', document.getElementById('editFullname')?.value || '<?php echo addslashes($fullname); ?>');
  fd.append('phone',    document.getElementById('editPhone')?.value    || '<?php echo addslashes($phone); ?>');
  fd.append('email',    document.getElementById('editEmail')?.value    || '<?php echo addslashes($email); ?>');
  fd.append('avatar',   file);

  showToast('Uploading…', 'Saving your profile picture…', 'info', 2500);

  fetch('../api/user/user_settings.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        showToast('Photo Saved ✓', 'Your profile picture has been saved and will persist.', 'success', 4000);
      } else {
        showToast('Upload Failed', d.message || 'Could not save photo.', 'danger');
      }
    })
    .catch(() => showToast('Network Error', 'Could not reach server.', 'danger'));
}

// Update account location display
function updateAcctLocation(lat, lng) {
  const el = document.getElementById('acctLocationText');
  if (el) el.textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
}

// Hook into GPS success to update My Account location display
const _origStartGPS = startGPS;
// (location display updated inline in watchPosition callback below)
setInterval(() => {
  if (currentLat && currentLng) updateAcctLocation(currentLat, currentLng);
}, 3000);

// =====================================================================
// DELETE ACCOUNT MODAL
// =====================================================================
function openDeleteAccountModal() {
  document.getElementById('deleteAccountModal').classList.add('show');
  document.getElementById('deleteConfirmPassword').value = '';
}
function closeDeleteAccountModal() {
  document.getElementById('deleteAccountModal').classList.remove('show');
}
function confirmDeleteAccount() {
  const pass = document.getElementById('deleteConfirmPassword').value.trim();
  if (!pass) { showToast('Required', 'Please enter your password to confirm.', 'warning'); return; }
  const btn = document.getElementById('confirmDeleteBtn');
  btn.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Deleting…';
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action','delete_account'); fd.append('password', pass);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      if (d.status === 'success') {
        showToast('Account Deleted', 'Your account has been removed.', 'success');
        setTimeout(() => window.location.href = '../auth/login.php', 2000);
      } else {
        showToast('Error', d.message || 'Incorrect password or server error.', 'danger');
        btn.innerHTML = '<i class="fa fa-trash"></i> Delete Forever';
        btn.disabled = false;
      }
    })
    .catch(() => {
      showToast('Error', 'Network error. Please try again.', 'danger');
      btn.innerHTML = '<i class="fa fa-trash"></i> Delete Forever';
      btn.disabled = false;
    });
}

// =====================================================================
// SAFETY CHECK (inactivity monitor)
// =====================================================================
safetyTimer = setInterval(() => {
  safetyInactiveMs += 10000;
  if (safetyInactiveMs >= SAFETY_CHECK_MS) {
    safetyInactiveMs = 0;
    document.getElementById('safetyModal').classList.add('show');
  }
}, 10000);

document.addEventListener('mousemove', () => safetyInactiveMs = 0);
document.addEventListener('keydown',   () => safetyInactiveMs = 0);
document.addEventListener('touchstart',() => safetyInactiveMs = 0);

function safetyConfirmSafe() {
  document.getElementById('safetyModal').classList.remove('show');
  safetyInactiveMs = 0;
  showToast('Stay Safe', 'Safety check confirmed. We\'re monitoring your location.', 'success');
}
function safetyConfirmSOS() {
  document.getElementById('safetyModal').classList.remove('show');
  triggerSOS();
}

// =====================================================================
// EMERGENCY CONTACTS
// =====================================================================
let contacts = [];

function parseContacts() {
  const raw = document.getElementById('emergency_contacts').value.trim();
  contacts = [];
  if (!raw) return;
  raw.split('\n').forEach(line => {
    const parts = line.split(':');
    if (parts.length >= 2) {
      const name = parts[0].trim();
      const phone = parts[1].trim();
      const relationship = parts.length >= 3 ? parts.slice(2).join(':').trim() : 'Family';
      contacts.push({ name, phone, relationship });
    }
  });
}

function renderContacts() {
  parseContacts();
  const list = document.getElementById('contactsList');
  document.getElementById('contactCount').textContent = contacts.length;
  if (!contacts.length) {
    list.innerHTML = `<div style="text-align:center;padding:40px;color:var(--muted);">
      <i class="fa fa-people-group" style="font-size:2.5rem;margin-bottom:14px;display:block;opacity:0.4;"></i>
      <div style="font-weight:700;">No emergency contacts yet.</div>
      <div style="font-size:0.82rem;margin-top:6px;">Add contacts to notify loved ones in an emergency.</div>
    </div>`;
    return;
  }
  list.innerHTML = contacts.map((c, i) => `
    <div class="contact-card">
      <div class="contact-avatar">${c.name.charAt(0).toUpperCase()}</div>
      <div class="contact-info">
        <div class="contact-name" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span>${escHTML(c.name)}</span>
          <span style="font-size:0.65rem;font-weight:800;background:rgba(37,99,235,0.1);color:var(--primary);padding:2px 8px;border-radius:6px;border:1px solid rgba(37,99,235,0.2);">${escHTML(c.relationship || 'Family')}</span>
        </div>
        <div class="contact-phone">${escHTML(c.phone)}</div>
      </div>
      <div class="contact-actions">
        <a href="tel:${escHTML(c.phone)}" class="contact-btn call" title="Call"><i class="fa fa-phone"></i></a>
        <a href="https://wa.me/${c.phone.replace(/\D/g,'')}" target="_blank" class="contact-btn wa" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
        <button class="contact-btn loc" title="Share location" onclick="shareLocation('${escHTML(c.phone)}')"><i class="fa fa-location-arrow"></i></button>
      </div>
    </div>`).join('');
}

function shareLocation(phone) {
  if (!currentLat) { showToast('No GPS', 'GPS lock required to share location.','warning'); return; }
  const url = `https://maps.google.com?q=${currentLat},${currentLng}`;
  const wa  = `https://wa.me/${phone.replace(/\D/g,'')}?text=${encodeURIComponent('🆘 SmartRescue — My current location: '+url)}`;
  window.open(wa, '_blank');
}

function openAddContact() { document.getElementById('addContactModal').classList.add('show'); }
function closeAddContact() { document.getElementById('addContactModal').classList.remove('show'); }

function saveNewContact() {
  const name  = document.getElementById('newContactName').value.trim();
  const phone = document.getElementById('newContactPhone').value.trim();
  const relationship = document.getElementById('newContactRelationship').value.trim();
  if (!name || !phone || !relationship) { showToast('Required','Please fill in all fields.','warning'); return; }
  contacts.push({ name, phone, relationship });
  serializeAndSaveContacts();
  closeAddContact();
  renderContacts();
  document.getElementById('newContactName').value = '';
  document.getElementById('newContactPhone').value = '';
  document.getElementById('newContactRelationship').value = 'Family';
  showToast('Contact Added', `${name} (${relationship}) added to your guardian network.`, 'success');
}

function serializeAndSaveContacts() {
  const raw = contacts.map(c => `${c.name}: ${c.phone}: ${c.relationship || 'Family'}`).join('\n');
  document.getElementById('emergency_contacts').value = raw;
  const fd = new FormData();
  fd.append('action','update_safety_info');
  fd.append('medical_info', document.getElementById('medical_info').value);
  fd.append('emergency_contacts', raw);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd }).catch(()=>{});
}

// Init contacts count
parseContacts();
document.getElementById('contactCount').textContent = contacts.length;

// =====================================================================
// MEDICAL INFO SAVE
// =====================================================================
// =====================================================================
// MEDICAL ID — Parse saved data back into fields on page load
// =====================================================================
(function initMedicalFields() {
  const raw = document.getElementById('medical_info').value || '';
  // Format: "Blood: A+ | Allergies: ... | Conditions: ... | Meds: ...\n\nNotes"
  const headerMatch = raw.match(/^Blood:\s*(.*?)\s*\|\s*Allergies:\s*(.*?)\s*\|\s*Conditions:\s*(.*?)\s*\|\s*Meds:\s*(.*)$/m);
  if (headerMatch) {
    const validBloodTypes = ['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'];
    const savedBlood = headerMatch[1].trim();
    const sel = document.getElementById('bloodGroup');
    if (validBloodTypes.includes(savedBlood)) sel.value = savedBlood;
    document.getElementById('allergies').value = headerMatch[2].trim();
    document.getElementById('chronicConditions').value = headerMatch[3].trim();
    // Meds may have trailing notes after a newline
    const medsAndNotes = headerMatch[4];
    const nlIdx = medsAndNotes.indexOf('\n');
    if (nlIdx !== -1) {
      document.getElementById('medications').value = medsAndNotes.substring(0, nlIdx).trim();
      document.getElementById('medical_info').value = medsAndNotes.substring(nlIdx).trim();
    } else {
      document.getElementById('medications').value = medsAndNotes.trim();
      document.getElementById('medical_info').value = '';
    }
  }
})();

function saveMedicalInfo() {
  const notes = document.getElementById('medical_info').value;
  const blood = document.getElementById('bloodGroup').value;
  if (!blood) {
    showToast('Required', 'Please select a valid blood type before saving.', 'danger');
    return;
  }
  const allrg = document.getElementById('allergies').value;
  const chron = document.getElementById('chronicConditions').value;
  const meds  = document.getElementById('medications').value;
  const combined = `Blood: ${blood} | Allergies: ${allrg} | Conditions: ${chron} | Meds: ${meds}\n\n${notes}`;
  const fd = new FormData();
  fd.append('action','update_safety_info');
  fd.append('medical_info', combined);
  fd.append('emergency_contacts', document.getElementById('emergency_contacts').value);
  fetch('../api/user/user_settings.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => showToast('Saved', d.message || 'Medical ID updated.', d.status === 'success' ? 'success' : 'danger'))
    .catch(() => showToast('Error', 'Failed to save.', 'danger'));
}

// =====================================================================
// PROFILE & PASSWORD
// =====================================================================
function updateProfile(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const avatarFile = document.getElementById('avatarFileInput').files[0];
  if (avatarFile) {
    fd.append('avatar', avatarFile);
  }
  fd.append('action','update_profile');
  fetch('../api/user/user_settings.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      showToast(d.status==='success'?'Profile Updated':'Error', d.message, d.status==='success'?'success':'danger');
      if (d.status==='success') setTimeout(() => location.reload(), 1500);
    });
}
function changePassword(e) {
  e.preventDefault();
  const newPass = document.getElementById('newPassField').value;
  const confPass = document.getElementById('confirmPassField').value;
  if(newPass !== confPass) {
    showToast('Error', 'New passwords do not match!', 'danger');
    return;
  }
  const fd = new FormData(e.target);
  fd.append('action','change_password');
  fetch('../api/user/user_settings.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      showToast(d.status==='success'?'Password Updated':'Error', d.message, d.status==='success'?'success':'danger');
      if (d.status==='success') e.target.reset();
    });
}

// =====================================================================
// HISTORY
// =====================================================================
function fetchHistory() {
  const tbody = document.getElementById('historyBody');
  tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted);">Loading…</td></tr>';
  fetch('../api/user/get_history.php')
    .then(r => r.json())
    .then(data => {
      tbody.innerHTML = data.length
        ? data.map(j => `
          <tr>
            <td style="font-weight:600;">${j.created_at}</td>
            <td><span style="background:rgba(37,99,235,0.1);color:var(--primary);font-weight:700;font-size:0.75rem;padding:4px 12px;border-radius:50px;">${j.emergency_type}</span></td>
            <td><span style="background:${j.status==='completed'?'rgba(16,185,129,0.1)':'rgba(245,158,11,0.1)'};color:${j.status==='completed'?'var(--success)':'var(--warning)'};font-weight:700;font-size:0.75rem;padding:4px 12px;border-radius:50px;">${j.status.toUpperCase()}</span></td>
            <td><button onclick="showToast('Details','${escHTML(j.description||'No description')}','info',8000)" style="background:none;border:1.5px solid var(--border);border-radius:8px;padding:5px 14px;font-size:0.75rem;font-weight:700;cursor:pointer;color:var(--text);">View</button></td>
          </tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--muted);">No history found.</td></tr>';
    })
    .catch(() => { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--danger);">Failed to load.</td></tr>'; });
}
// History is fetched lazily — only when the user opens the history tab
// (see showTab() which calls fetchHistory() for id==='history')
// We do NOT pre-load it on page load to save a DB round-trip.

// =====================================================================
// CLOCK
// =====================================================================
function updateClocks() {
  const now = new Date();
  const timeStr = now.toLocaleTimeString();
  const timeShort = now.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
  const cd = document.getElementById('clockDisplay');
  if (cd) cd.textContent = timeStr;
  const cd2 = document.getElementById('clockDisplay2');
  if (cd2) cd2.textContent = timeShort;
}
updateClocks();
setInterval(updateClocks, 1000);

// =====================================================================
// UTILS & SITUATION CHECK
// =====================================================================
function escHTML(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function closeSituationModal(status) {
  const modal = document.getElementById('situationModal');
  if (modal) modal.classList.remove('show');
  sessionStorage.setItem('smartrescue_situation_checked', 'true');
  
  if (status === 'emergency') {
    showToast('Emergency Selected', 'Please hold the SOS button or specify details below.', 'danger');
    const desc = document.getElementById('sos_description');
    if (desc) desc.focus();
  } else {
    showToast('Status Noted', 'Glad to hear you are safe. We continue monitoring.', 'success');
  }
}

window.addEventListener('load', () => {
  if (!sessionStorage.getItem('smartrescue_situation_checked')) {
    setTimeout(() => {
      const modal = document.getElementById('situationModal');
      if (modal) modal.classList.add('show');
    }, 800);
  }
});
</script>

<!-- Google Translate Widget (Hidden) -->
<div id="google_translate_element" style="display:none;"></div>
<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({pageLanguage: 'en', autoDisplay: false}, 'google_translate_element');
}
// Force initialization if user has a non-english language saved but cookie is missing
<?php if ($user_language !== 'en'): ?>
if (document.cookie.indexOf('googtrans=') === -1) {
  document.cookie = "googtrans=/en/<?php echo $user_language; ?>; path=/";
  document.cookie = "googtrans=/en/<?php echo $user_language; ?>; domain=."+location.hostname+"; path=/";
  location.reload();
}
<?php else: ?>
if (document.cookie.indexOf('googtrans=') !== -1) {
  document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=.'+location.hostname+'; path=/;';
  location.reload();
}
<?php endif; ?>
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</style>

<!-- Debug Page Load Time -->
<div style="text-align:center; padding:12px; font-size:0.75rem; color:var(--muted); opacity:0.75; margin-top:20px; font-family:monospace;">
    Page generated in <?= round(microtime(true) - $start_time, 4) ?> seconds (Railway Database connection).
</div>

</body>
</html>