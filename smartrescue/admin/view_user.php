<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

if (!isset($_GET['id'])) {
    header("Location: users.php"); exit();
}

$user_id = mysqli_real_escape_string($conn, $_GET['id']);

$q = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id' AND role='user'");
if (mysqli_num_rows($q) == 0) {
    header("Location: users.php"); exit();
}
$user = mysqli_fetch_assoc($q);

// Rescue stats for this user
$q_total  = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE user_id='$user_id'");
$q_done   = mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE user_id='$user_id' AND status='completed'");
$q_pending= mysqli_query($conn, "SELECT COUNT(*) as c FROM rescue_requests WHERE user_id='$user_id' AND status='pending'");
$q_last   = mysqli_query($conn, "SELECT created_at FROM rescue_requests WHERE user_id='$user_id' ORDER BY created_at DESC LIMIT 1");

$total_reqs  = mysqli_fetch_assoc($q_total)['c'];
$done_reqs   = mysqli_fetch_assoc($q_done)['c'];
$pending_reqs= mysqli_fetch_assoc($q_pending)['c'];
$last_row    = mysqli_fetch_assoc($q_last);
$last_active = $last_row ? date('M d, Y', strtotime($last_row['created_at'])) : 'No activity';

// Recent rescue requests
$q_recent = mysqli_query($conn, "
    SELECT r.id, r.emergency_type, r.status, r.created_at, r.description, r.evidence_image,
           e.unit_name
    FROM rescue_requests r
    LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
    WHERE r.user_id = '$user_id'
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recent_requests = [];
while ($row = mysqli_fetch_assoc($q_recent)) $recent_requests[] = $row;

// Age calc
$age = null;
if (!empty($user['birth_date'])) {
    $age = date_diff(date_create($user['birth_date']), date_create('today'))->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Profile · <?= htmlspecialchars($user['fullname']) ?> | SmartRescue</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --bg: #f0f4f9;
  --card: #fff;
  --text: #0f172a;
  --muted: #64748b;
  --border: rgba(0,0,0,0.07);
  --accent: #3b82f6;
  --green: #22c55e;
  --red: #ef4444;
  --amber: #f59e0b;
  --purple: #8b5cf6;
  --sidebar: 268px;
  --shadow: 0 2px 12px rgba(0,0,0,0.06);
  --shadow-lg: 0 8px 32px rgba(0,0,0,0.10);
  --r: 14px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}
.main-wrapper{margin-left:var(--sidebar);min-height:100vh;}

/* Topbar */
.topbar{position:sticky;top:0;z-index:100;background:var(--card);border-bottom:1px solid var(--border);height:58px;display:flex;align-items:center;padding:0 28px;gap:14px;box-shadow:var(--shadow);}
.back-link{display:inline-flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-weight:700;font-size:0.88rem;padding:6px 14px;border-radius:8px;background:var(--bg);border:1px solid var(--border);transition:all 0.2s;}
.back-link:hover{color:var(--accent);border-color:var(--accent);background:rgba(59,130,246,0.05);}
.topbar-title{font-weight:800;font-size:1rem;color:var(--text);}

/* Layout */
.content{padding:24px 32px;}
.layout{display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;}

/* Profile Card */
.profile-card{background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden;}
.profile-banner{height:80px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);}
.profile-body{padding:0 20px 20px;}
.avatar-wrap{margin-top:-40px;margin-bottom:12px;}
.avatar{width:80px;height:80px;border-radius:16px;border:3px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,0.12);overflow:hidden;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;font-weight:800;}
.avatar img{width:100%;height:100%;object-fit:cover;}
.profile-name{font-size:1.25rem;font-weight:900;color:var(--text);line-height:1.1;}
.profile-sub{font-size:0.75rem;color:var(--muted);font-weight:600;margin-top:3px;}
.profile-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;margin-top:8px;}
.badge-active{background:rgba(34,197,94,0.1);color:#15803d;}

.info-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;}
.info-label{font-size:0.66rem;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);}
.info-val{font-size:0.88rem;font-weight:700;color:var(--text);margin-top:1px;}

/* Stat Pills */
.stat-pills{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px;}
.stat-pill{background:var(--bg);border-radius:10px;padding:12px 14px;border:1px solid var(--border);text-align:center;}
.stat-pill-num{font-size:1.4rem;font-weight:900;line-height:1;}
.stat-pill-lbl{font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-top:3px;}

/* Right column */
.section-title{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:6px;}
.card-box{background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);border:1px solid var(--border);padding:16px 18px;margin-bottom:16px;}

/* KPI Row */
.kpi-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;}
.kpi-mini{background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);border:1px solid var(--border);padding:14px 16px;display:flex;align-items:center;gap:10px;}
.kpi-mini-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;}
.kpi-mini-num{font-size:1.3rem;font-weight:900;line-height:1;}
.kpi-mini-lbl{font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--muted);margin-top:2px;}

/* Request Table */
.req-table{width:100%;border-collapse:collapse;}
.req-table th{font-size:0.66rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--muted);padding:8px 12px;text-align:left;border-bottom:1px solid var(--border);background:#f8fafc;}
.req-table td{font-size:0.84rem;padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:middle;}
.req-table tr:last-child td{border-bottom:none;}
.req-table tr:hover td{background:#f8fafe;}
.status-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:0.62rem;font-weight:800;text-transform:uppercase;letter-spacing:0.6px;}
.chip-pending{background:rgba(239,68,68,0.1);color:#ef4444;}
.chip-accepted,.chip-dispatched{background:rgba(59,130,246,0.1);color:#3b82f6;}
.chip-completed{background:rgba(34,197,94,0.1);color:#22c55e;}
.chip-cancelled{background:rgba(100,116,139,0.08);color:#94a3b8;}

/* Medical card */
.medical-pre{font-size:0.82rem;font-weight:600;color:var(--text);background:#f8fafc;border-radius:10px;padding:12px 14px;border:1px solid var(--border);white-space:pre-wrap;line-height:1.6;}

.empty-small{text-align:center;padding:28px;color:var(--muted);font-size:0.8rem;font-weight:600;}
.empty-small i{display:block;font-size:1.6rem;opacity:0.2;margin-bottom:8px;}

@media(max-width:900px){
  .layout{grid-template-columns:1fr;}
  .content{padding:16px 14px;}
  .kpi-row{grid-template-columns:1fr 1fr;}
}

@media print {
  body { background: #fff !important; color: #000 !important; font-size: 11pt; }
  .sidebar, .topbar, .back-link, .dropdown, button, .no-print { display: none !important; }
  .main-wrapper { margin-left: 0 !important; padding: 0 !important; }
  .content { padding: 15px !important; }
  .layout { display: block !important; }
  .profile-card, .card-box, .kpi-mini { box-shadow: none !important; border: 1px solid #ccc !important; margin-bottom: 15px !important; page-break-inside: avoid; }
  .print-header { display: block !important; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
  .req-table th, .req-table td { border-bottom: 1px solid #ddd !important; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-wrapper">

  <!-- Topbar -->
  <header class="topbar">
    <a href="users.php" class="back-link"><i class="fa fa-arrow-left"></i> Back</a>
    <div class="topbar-title">User Profile</div>
  </header>

  <div class="content">
    <div class="layout">

      <!-- LEFT: Profile Card -->
      <div>
        <div class="profile-card">
          <div class="profile-banner"></div>
          <div class="profile-body">
            <div class="avatar-wrap">
              <div class="avatar">
                <?php if (!empty($user['profile_image'])): ?>
                  <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="">
                <?php else: ?>
                  <?= strtoupper(substr($user['fullname'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="profile-name"><?= htmlspecialchars($user['fullname']) ?></div>
            <div class="profile-sub">User #<?= $user['id'] ?> · Registered <?= date('M Y', strtotime($user['created_at'])) ?></div>
            <div class="profile-badge badge-active"><i class="fa fa-circle" style="font-size:0.45rem"></i> Active</div>

            <!-- Stat Pills -->
            <div class="stat-pills">
              <div class="stat-pill">
                <div class="stat-pill-num" style="color:var(--accent)"><?= $total_reqs ?></div>
                <div class="stat-pill-lbl">Total SOS</div>
              </div>
              <div class="stat-pill">
                <div class="stat-pill-num" style="color:var(--green)"><?= $done_reqs ?></div>
                <div class="stat-pill-lbl">Completed</div>
              </div>
            </div>

            <!-- Info Rows -->
            <div style="margin-top:16px;">
              <div class="info-row">
                <div class="info-icon" style="background:rgba(59,130,246,0.1);color:var(--accent)"><i class="fa fa-phone"></i></div>
                <div>
                  <div class="info-label">Phone</div>
                  <div class="info-val"><?= htmlspecialchars($user['phone']) ?></div>
                </div>
              </div>
              <div class="info-row">
                <div class="info-icon" style="background:rgba(139,92,246,0.1);color:var(--purple)"><i class="fa fa-envelope"></i></div>
                <div>
                  <div class="info-label">Email</div>
                  <div class="info-val" style="<?= empty($user['email']) ? 'color:var(--muted);font-style:italic' : '' ?>"><?= htmlspecialchars($user['email'] ?: 'Not provided') ?></div>
                </div>
              </div>
              <div class="info-row">
                <div class="info-icon" style="background:rgba(236,72,153,0.1);color:#ec4899"><i class="fa fa-venus-mars"></i></div>
                <div>
                  <div class="info-label">Gender</div>
                  <div class="info-val" style="<?= empty($user['gender']) ? 'color:var(--muted);font-style:italic' : '' ?>"><?= htmlspecialchars($user['gender'] ?: 'Not specified') ?></div>
                </div>
              </div>
              <div class="info-row">
                <div class="info-icon" style="background:rgba(245,158,11,0.1);color:var(--amber)"><i class="fa fa-cake-candles"></i></div>
                <div>
                  <div class="info-label">Birth Date<?= $age ? ' · Age ' . $age : '' ?></div>
                  <div class="info-val" style="<?= empty($user['birth_date']) ? 'color:var(--muted);font-style:italic' : '' ?>"><?= !empty($user['birth_date']) ? date('M d, Y', strtotime($user['birth_date'])) : 'Not specified' ?></div>
                </div>
              </div>
              <div class="info-row">
                <div class="info-icon" style="background:rgba(34,197,94,0.1);color:var(--green)"><i class="fa fa-clock-rotate-left"></i></div>
                <div>
                  <div class="info-label">Last Active</div>
                  <div class="info-val"><?= $last_active ?></div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- RIGHT: Stats + History -->
      <div>
        <!-- KPI Strip -->
        <div class="kpi-row">
          <div class="kpi-mini">
            <div class="kpi-mini-icon" style="background:rgba(59,130,246,0.1);color:var(--accent)"><i class="fa fa-chart-line"></i></div>
            <div>
              <div class="kpi-mini-num"><?= $total_reqs ?></div>
              <div class="kpi-mini-lbl">Total Requests</div>
            </div>
          </div>
          <div class="kpi-mini">
            <div class="kpi-mini-icon" style="background:rgba(34,197,94,0.1);color:var(--green)"><i class="fa fa-circle-check"></i></div>
            <div>
              <div class="kpi-mini-num"><?= $done_reqs ?></div>
              <div class="kpi-mini-lbl">Resolved</div>
            </div>
          </div>
          <div class="kpi-mini">
            <div class="kpi-mini-icon" style="background:rgba(239,68,68,0.1);color:var(--red)"><i class="fa fa-triangle-exclamation"></i></div>
            <div>
              <div class="kpi-mini-num"><?= $pending_reqs ?></div>
              <div class="kpi-mini-lbl">Pending</div>
            </div>
          </div>
        </div>

        <!-- Recent Requests -->
        <div class="card-box">
          <div class="section-title"><i class="fa fa-history"></i> Recent SOS Requests</div>
          <?php if (empty($recent_requests)): ?>
          <div class="empty-small"><i class="fa fa-inbox"></i>No rescue requests yet</div>
          <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="req-table">
              <thead>
                <tr>
                  <th>#ID</th>
                  <th>Type</th>
                  <th>Details Sent</th>
                  <th>Unit Assigned</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($recent_requests as $r): ?>
                <tr>
                  <td style="font-weight:800;color:var(--muted)">#<?= $r['id'] ?></td>
                  <td style="font-weight:700"><?= htmlspecialchars($r['emergency_type'] ?: '—') ?></td>
                  <td>
                    <?php 
                    $has_details = false;
                    if (!empty($r['description'])) {
                        $has_details = true;
                        echo '<div style="font-size:0.78rem;color:#475569;font-style:italic;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . htmlspecialchars($r['description']) . '">"' . htmlspecialchars($r['description']) . '"</div>';
                    }
                    if (!empty($r['evidence_image'])) {
                        $has_details = true;
                        echo '<div style="display:flex;gap:4px;margin-top:4px;">';
                        $imgs = explode(',', $r['evidence_image']);
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
                  <td style="color:var(--muted)"><?= htmlspecialchars($r['unit_name'] ?: '—') ?></td>
                  <td style="color:var(--muted);font-size:0.78rem"><?= date('M d, H:i', strtotime($r['created_at'])) ?></td>
                  <td><span class="status-chip chip-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                  <td><a href="incident.php?id=<?= $r['id'] ?>" style="color:var(--accent);font-size:0.75rem;font-weight:700;text-decoration:none;"><i class="fa fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($total_reqs > 5): ?>
          <div style="text-align:right;margin-top:10px;">
            <a href="view-requests.php?search=<?= urlencode($user['fullname']) ?>" style="font-size:0.75rem;font-weight:700;color:var(--accent);text-decoration:none;">View all <?= $total_reqs ?> requests <i class="fa fa-arrow-right" style="font-size:0.65rem"></i></a>
          </div>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Medical Info -->
        <div class="card-box">
          <div class="section-title"><i class="fa fa-notes-medical"></i> Medical Identity Card</div>
          <?php if (empty($user['medical_info'])): ?>
          <div class="empty-small"><i class="fa fa-file-medical"></i>No medical information provided</div>
          <?php else: ?>
          <div class="medical-pre"><?= htmlspecialchars($user['medical_info']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Emergency Contacts -->
        <?php if (!empty($user['emergency_contacts'])): ?>
        <div class="card-box">
          <div class="section-title"><i class="fa fa-address-book"></i> Emergency Contacts</div>
          <div class="medical-pre"><?= htmlspecialchars($user['emergency_contacts']) ?></div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /layout -->
  </div><!-- /content -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
