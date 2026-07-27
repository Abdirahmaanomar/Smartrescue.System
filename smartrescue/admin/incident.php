<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

// Handle status update actions BEFORE any output
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'complete') {
        mysqli_query($conn, "UPDATE rescue_requests SET status='completed' WHERE id=$id");
    } elseif ($action === 'cancel') {
        mysqli_query($conn, "UPDATE rescue_requests SET status='cancelled' WHERE id=$id");
    }
    header("Location: incident.php?id=$id");
    exit();
}

$q = "SELECT r.*, u.fullname as patient_name, u.phone as patient_phone, u.email as patient_email, u.medical_info, u.emergency_contacts, u.birth_date as patient_birth_date, u.gender as patient_gender,
             e.unit_name, e.plate_number, d.fullname as driver_name, d.phone as driver_phone, d.profile_image as driver_image
      FROM rescue_requests r
      JOIN users u ON r.user_id = u.id
      LEFT JOIN emergency_units e ON r.assigned_unit_id = e.id
      LEFT JOIN users d ON e.driver_id = d.id
      WHERE r.id = $id LIMIT 1";
$res = mysqli_query($conn, $q);
$inc = mysqli_fetch_assoc($res);
if (!$inc) { header("Location: index.php"); exit(); }

$statusColors = ['pending'=>'#ef4444','accepted'=>'#3b82f6','completed'=>'#22c55e','cancelled'=>'#94a3b8'];
$statusColor = $statusColors[$inc['status']] ?? '#94a3b8';

// Fetch available units for assignment
$avail = mysqli_query($conn, "SELECT e.*, u.fullname as driver_name FROM emergency_units e LEFT JOIN users u ON e.driver_id = u.id WHERE e.status = 'available'");
$availUnits = [];
while ($row = mysqli_fetch_assoc($avail)) $availUnits[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Incident #<?= $id ?> | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
.main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}

.back-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:10px;background:var(--card-bg);border:1px solid rgba(0,0,0,0.08);color:var(--text-muted);font-size:0.82rem;font-weight:700;text-decoration:none;margin-bottom:28px;transition:0.2s;}
.back-btn:hover{background:#e2e8f0;color:var(--text);}

.incident-layout{display:grid;grid-template-columns:1fr 380px;gap:24px;}

.panel{background:var(--card-bg);border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow:hidden;margin-bottom:24px;}
.panel-head{padding:20px 24px;border-bottom:1px solid rgba(0,0,0,0.04);display:flex;align-items:center;gap:10px;}
.panel-head h5{font-size:0.95rem;font-weight:800;margin:0;display:flex;align-items:center;gap:8px;}
.panel-body{padding:24px;}

.victim-hero{display:flex;align-items:center;gap:20px;padding:24px;border-bottom:1px solid rgba(0,0,0,0.04);}
.victim-avatar{
    width:70px;height:70px;border-radius:20px;
    font-size:1.8rem;font-weight:900;color:white;
    display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#ef4444,#b91c1c);
    box-shadow:0 8px 20px rgba(239,68,68,0.3);flex-shrink:0;
}
.victim-name-big{font-size:1.3rem;font-weight:900;line-height:1.2;}
.victim-meta{font-size:0.8rem;color:var(--text-muted);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap;}
.victim-meta span{display:flex;align-items:center;gap:5px;}

.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid rgba(0,0,0,0.04);}
.info-row:last-child{border-bottom:none;}
.info-row-label{font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);}
.info-row-value{font-size:0.9rem;font-weight:700;}

.status-tag{
    display:inline-flex;align-items:center;gap:6px;padding:6px 16px;
    border-radius:50px;font-size:0.75rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;
}

.desc-box{
    background:#f8fafc;border-radius:12px;padding:16px 18px;
    font-size:0.88rem;line-height:1.7;color:var(--text);
    border:1px solid rgba(0,0,0,0.05);min-height:80px;
    font-style:italic;
}

#incident-map{height:280px;border-radius:12px;}

.evidence-img{
    width:100%;border-radius:14px;object-fit:cover;
    max-height:280px;border:2px solid rgba(0,0,0,0.06);
    margin-bottom:10px;cursor:zoom-in;transition:transform 0.2s;
}
.evidence-img:hover{transform:scale(1.01);}

.action-btn{
    width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
    padding:14px;border-radius:14px;font-size:0.9rem;font-weight:700;
    text-decoration:none;border:none;cursor:pointer;transition:all 0.2s;
    margin-bottom:10px;
}
.action-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;box-shadow:0 4px 14px rgba(59,130,246,0.35);}
.action-primary:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(59,130,246,0.45);color:white;}
.action-success{background:linear-gradient(135deg,#22c55e,#15803d);color:white;box-shadow:0 4px 14px rgba(34,197,94,0.3);}
.action-success:hover{transform:translateY(-2px);color:white;}
.action-secondary{background:#f1f5f9;color:#475569;border:1px solid rgba(0,0,0,0.08);}
.action-secondary:hover{background:#e2e8f0;}

.timeline{list-style:none;padding:0;margin:0;position:relative;}
.timeline::before{content:'';position:absolute;left:18px;top:0;bottom:0;width:2px;background:rgba(0,0,0,0.06);}
.timeline-item{position:relative;padding-left:48px;margin-bottom:18px;}
.timeline-dot{
    position:absolute;left:10px;top:3px;
    width:16px;height:16px;border-radius:50%;border:3px solid var(--card-bg);
    box-shadow:0 0 0 2px currentColor;
}
.tl-time{font-size:0.7rem;font-weight:700;color:var(--text-muted);margin-bottom:2px;}
.tl-label{font-size:0.85rem;font-weight:700;}



/* Modal */
.assign-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;align-items:center;justify-content:center;}
.assign-modal-backdrop.show{display:flex;}
.assign-modal{background:var(--card-bg);border-radius:20px;padding:32px;width:90%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,0.2);}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = 'Incident'; $page_subtitle = '#'.$id; include 'includes/topbar.php'; ?>

<a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>

<div class="incident-layout">
    <!-- LEFT COLUMN -->
    <div>
        <!-- Victim Profile -->
        <div class="panel">
            <div class="victim-hero">
                <div class="victim-avatar"><?= strtoupper(substr($inc['patient_name'], 0, 1)) ?></div>
                <div>
                    <div class="victim-name-big"><?= htmlspecialchars($inc['patient_name']) ?></div>
                    <div class="victim-meta">
                        <span><i class="fa fa-phone"></i> <?= htmlspecialchars($inc['patient_phone']) ?></span>
                        <?php if($inc['patient_email']): ?><span><i class="fa fa-envelope"></i> <?= htmlspecialchars($inc['patient_email']) ?></span><?php endif; ?>
                        <?php if($inc['patient_birth_date']): ?><span><i class="fa fa-cake-candles"></i> Age: <?= (new DateTime())->diff(new DateTime($inc['patient_birth_date']))->y ?></span><?php endif; ?>
                        <?php if($inc['patient_gender']): ?><span><i class="fa fa-venus-mars"></i> <?= htmlspecialchars($inc['patient_gender']) ?></span><?php endif; ?>
                    </div>
                    <div style="margin-top:12px">
                        <span class="status-tag" style="background:<?= $statusColor ?>1a;color:<?= $statusColor ?>">
                            <span style="width:7px;height:7px;border-radius:50%;background:<?= $statusColor ?>"></span>
                            <?= ucfirst($inc['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="info-row">
                    <span class="info-row-label">Emergency Type</span>
                    <span class="info-row-value"><?= htmlspecialchars($inc['emergency_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Time Reported</span>
                    <span class="info-row-value"><?= date('M j, Y · H:i', strtotime($inc['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Assigned Unit</span>
                    <span class="info-row-value"><?= $inc['unit_name'] ? htmlspecialchars($inc['unit_name'].' · '.$inc['plate_number']) : '<span style="color:#94a3b8">Unassigned</span>' ?></span>
                </div>
                <?php if ($inc['driver_name']): ?>
                <div class="info-row" style="padding: 16px 0;">
                    <span class="info-row-label">Responder</span>
                    <span class="info-row-value" style="display:flex; align-items:center; gap:10px;">
                        <?php if(!empty($inc['driver_image'])): ?>
                            <img src="../<?= htmlspecialchars($inc['driver_image']) ?>" style="width:34px; height:34px; border-radius:8px; object-fit:cover; border:1px solid rgba(0,0,0,0.05);" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($inc['driver_name']) ?>&background=3b82f6&color=fff'">
                        <?php else: ?>
                            <div style="width:34px; height:34px; border-radius:8px; background:rgba(59,130,246,0.1); color:#3b82f6; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem; border:1px solid rgba(59,130,246,0.15);"><?= strtoupper(substr($inc['driver_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight:800;"><?= htmlspecialchars($inc['driver_name']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:500;"><?= htmlspecialchars($inc['driver_phone'] ?? '') ?></div>
                        </div>
                    </span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-row-label">GPS Coordinates</span>
                    <span class="info-row-value"><?= !empty($inc['lat']) ? round($inc['lat'],5).', '.round($inc['lng'],5) : 'Unknown' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">📍 Xaafadda / Neighborhood</span>
                    <span class="info-row-value" style="color:<?= !empty($inc['neighborhood']) ? '#10b981' : '#94a3b8' ?>;font-weight:800;">
                        <?= !empty($inc['neighborhood']) ? htmlspecialchars($inc['neighborhood']) : '<span style="color:#94a3b8;font-weight:500;">Not recorded</span>' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="panel">
            <div class="panel-head"><h5><i class="fa fa-align-left text-primary"></i> Incident Description</h5></div>
            <div class="panel-body">
                <div class="desc-box"><?= !empty($inc['description']) ? nl2br(htmlspecialchars($inc['description'])) : '<span style="color:#94a3b8">No description provided.</span>' ?></div>
                
                <?php
                $evidence_str = $inc['evidence_image'] ?? '';
                if (!empty($evidence_str)):
                    $images = explode(',', $evidence_str);
                    $valid_images = [];
                    foreach ($images as $img) {
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
                            $valid_images[] = $webPath;
                        } elseif (file_exists($apiFsPath)) {
                            $valid_images[] = $apiWebPath;
                        } else {
                            $valid_images[] = $webPath;
                        }
                    }
                    if (!empty($valid_images)):
                ?>
                <div style="margin-top: 18px;">
                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted);margin-bottom:10px;">
                        <i class="fa fa-image me-1"></i> Attached Photos (<?= count($valid_images) ?>)
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($valid_images as $v_img): ?>
                            <div style="width: 80px; height: 80px; border-radius: 10px; overflow: hidden; border: 1.5px solid rgba(0,0,0,0.06); cursor: zoom-in;" onclick="openLightbox('<?= htmlspecialchars($v_img) ?>')">
                                <img src="<?= htmlspecialchars($v_img) ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.background='#fee2e2';">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>

        <!-- Medical & Emergency Info -->
        <div class="panel" style="border-color:rgba(239,68,68,0.2);">
            <div class="panel-head" style="background:rgba(239,68,68,0.02)"><h5><i class="fa fa-notes-medical text-danger"></i> Medical Profile & Contacts</h5></div>
            <div class="panel-body">
                <div style="margin-bottom:16px;">
                    <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">Known Conditions / Allergies</div>
                    <div style="background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);padding:12px;border-radius:10px;color:#ef4444;font-weight:600;font-size:0.9rem;">
                        <?= !empty($inc['medical_info']) ? nl2br(htmlspecialchars($inc['medical_info'])) : 'None reported.' ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">Emergency Contacts</div>
                    <div style="background:#f8fafc;border:1px solid rgba(0,0,0,0.06);padding:12px;border-radius:10px;font-weight:600;font-size:0.9rem;">
                        <?= !empty($inc['emergency_contacts']) ? nl2br(htmlspecialchars($inc['emergency_contacts'])) : 'No contacts listed.' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <?php if ($inc['lat'] && $inc['lng']): ?>
        <div class="panel">
            <div class="panel-head"><h5><i class="fa fa-map-location-dot text-primary"></i> Exact Location</h5></div>
            <div class="panel-body" style="position:relative;">
                <div id="incident-map"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Evidence -->
        <?php
        $evidence_str = $inc['evidence_image'] ?? '';
        if (!empty($evidence_str)):
            $images = explode(',', $evidence_str);
            $valid_images = [];
            foreach ($images as $img) {
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
                    $valid_images[] = $webPath;
                } elseif (file_exists($apiFsPath)) {
                    $valid_images[] = $apiWebPath;
                } else {
                    $valid_images[] = $webPath;
                }
            }
            if (!empty($valid_images)):
        ?>
        <div class="panel">
            <div class="panel-head"><h5><i class="fa fa-image text-primary"></i> Evidence (<?= count($valid_images) ?>)</h5></div>
            <div class="panel-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php 
                    $num_imgs = count($valid_images);
                    foreach ($valid_images as $v_img): 
                        $width_style = ($num_imgs === 1) ? 'flex: 1 1 100%; max-width: 100%;' : 'flex: 1 1 calc(50% - 6px); max-width: calc(50% - 6px); min-width: 120px;';
                    ?>
                        <div style="<?= $width_style ?>">
                            <img src="<?= htmlspecialchars($v_img) ?>" alt="Evidence" class="evidence-img"
                                 style="margin-bottom:0px; height:180px; width:100%; object-fit:cover;"
                                 onclick="openLightbox('<?= htmlspecialchars($v_img) ?>')"
                                 onerror="this.style.background='#fee2e2';this.style.display='flex';this.alt='Image not found';">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:10px;"><i class="fa fa-magnifying-glass-plus"></i> Click any photo to view full size</div>
            </div>
        </div>
        <?php endif; endif; ?>
    </div>

    <!-- RIGHT COLUMN -->
    <div>
        <!-- Actions -->
        <div class="panel">
            <div class="panel-head"><h5><i class="fa fa-bolt text-warning"></i> Quick Actions</h5></div>
            <div class="panel-body">
                <?php if ($inc['status'] === 'pending'): ?>
                <button class="action-btn action-primary" onclick="openAssignModal(<?= $inc['id'] ?>, <?= $inc['lat'] ? floatval($inc['lat']) : 'null' ?>, <?= $inc['lng'] ? floatval($inc['lng']) : 'null' ?>, '<?= addslashes(htmlspecialchars($inc['emergency_type'] ?? '')) ?>') ">
                    <i class="fa-solid fa-suitcase-medical"></i> Assign Unit
                </button>
                <?php elseif ($inc['status'] === 'accepted' || $inc['status'] === 'dispatched'): ?>
                <button class="action-btn action-primary" onclick="openAssignModal(<?= $inc['id'] ?>, <?= $inc['lat'] ? floatval($inc['lat']) : 'null' ?>, <?= $inc['lng'] ? floatval($inc['lng']) : 'null' ?>, '<?= addslashes(htmlspecialchars($inc['emergency_type'] ?? '')) ?>') ">
                    <i class="fa fa-rotate"></i> Change Responder
                </button>
                <a href="process_assign.php?id=<?= $inc['id'] ?>&action=complete" class="action-btn action-success"
                   onclick="return confirm('Mark this mission as completed?')">
                    <i class="fa fa-circle-check"></i> Mark as Complete
                </a>
                <?php endif; ?>
                <?php if ($inc['status'] !== 'cancelled' && $inc['status'] !== 'completed'): ?>
                <a href="process_assign.php?id=<?= $inc['id'] ?>&action=cancel" class="action-btn action-secondary"
                   onclick="return confirm('Cancel this mission?')" style="color:#ef4444;border-color:rgba(239,68,68,0.2);">
                    <i class="fa fa-ban"></i> Cancel Mission
                </a>
                <?php endif; ?>
                <a href="tel:<?= $inc['patient_phone'] ?>" class="action-btn action-success">
                    <i class="fa fa-phone"></i> Call Victim
                </a>
                <?php if ($inc['driver_phone']): ?>
                <a href="tel:<?= $inc['driver_phone'] ?>" class="action-btn action-secondary">
                    <i class="fa fa-headset"></i> Call Responder
                </a>
                <?php endif; ?>
                <a href="view-requests.php" class="action-btn action-secondary">
                    <i class="fa fa-list-check"></i> All Missions
                </a>
            </div>
        </div>

        <!-- Timeline -->
        <div class="panel">
            <div class="panel-head"><h5><i class="fa fa-timeline text-primary"></i> Status Timeline</h5></div>
            <div class="panel-body">
                <ul class="timeline">
                    <li class="timeline-item">
                        <div class="timeline-dot" style="color:#ef4444;background:#ef4444"></div>
                        <div class="tl-time"><?= date('H:i', strtotime($inc['created_at'])) ?></div>
                        <div class="tl-label">SOS Activated</div>
                    </li>
                    <?php if ($inc['status'] !== 'pending'): ?>
                    <li class="timeline-item">
                        <div class="timeline-dot" style="color:#3b82f6;background:#3b82f6"></div>
                        <div class="tl-time">Unit Assigned</div>
                        <div class="tl-label">Accepted by <?= htmlspecialchars($inc['unit_name'] ?? 'Unit') ?></div>
                    </li>
                    <?php endif; ?>
                    <?php if ($inc['status'] === 'completed'): ?>
                    <li class="timeline-item">
                        <div class="timeline-dot" style="color:#22c55e;background:#22c55e"></div>
                        <div class="tl-time">Mission Complete</div>
                        <div class="tl-label">Incident resolved</div>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/assign_modal.php'; ?>

<!-- Lightbox Overlay -->
<div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:99999;align-items:center;justify-content:center;cursor:zoom-out;" onclick="closeLightbox()">
    <button onclick="closeLightbox()" style="position:absolute;top:20px;right:24px;background:rgba(255,255,255,0.1);border:none;color:#fff;font-size:1.5rem;width:44px;height:44px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fa fa-xmark"></i></button>
    <img id="lightbox-img" src="" style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:12px;box-shadow:0 30px 80px rgba(0,0,0,0.5);">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($inc['lat'] && $inc['lng']): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('incident-map', { attributionControl: false }).setView([<?= $inc['lat'] ?>, <?= $inc['lng'] ?>], 15);
L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }).addTo(map);
const icon = L.divIcon({
    className:'',
    html:`<div style="width:20px;height:20px;background:#ef4444;border-radius:50%;border:3px solid white;box-shadow:0 0 0 6px rgba(239,68,68,0.2),0 4px 12px rgba(239,68,68,0.4)"></div>`,
    iconSize:[20,20],iconAnchor:[10,10]
});
L.marker([<?= $inc['lat'] ?>, <?= $inc['lng'] ?>], {icon}).addTo(map)
 .bindPopup('<b><?= addslashes(htmlspecialchars($inc['patient_name'])) ?></b>').openPopup();
</script>
<?php endif; ?>
<script>
function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightbox-img').src = src;
    lb.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.getElementById('lightbox-img').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
