<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$req_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$uid = $_SESSION['user_id'];

// Get Request Status
$query = "SELECT r.*, u.unit_name, u.plate_number, u.current_lat as u_lat, u.current_lng as u_lng, d.fullname as driver_name 
          FROM rescue_requests r 
          LEFT JOIN emergency_units u ON r.assigned_unit_id = u.id 
          LEFT JOIN users d ON u.driver_id = d.id
          WHERE r.id = '$req_id' AND r.user_id = '$uid'";

$res = mysqli_query($conn, $query);
$req = mysqli_fetch_assoc($res);

if (!$req) {
    $page_title = "Error - SmartRescue";
    require_once '../includes/header.php';
    echo '<div class="glass-card" style="padding:40px; text-align:center; margin-top:20px;"><h2>Request Not Found</h2><p>This tracking ID is invalid or does not belong to you.</p></div>';
    require_once '../includes/footer.php';
    exit();
}

$page_title = "Track Rescue #" . $req['id'];

// Define Status Colors
$status_color = 'var(--warning)';
$status_text = 'Searching for Unit';
if ($req['status'] === 'accepted') { $status_color = 'var(--primary)'; $status_text = 'Driver Assigned'; }
if ($req['status'] === 'en_route') { $status_color = 'var(--success)'; $status_text = 'Unit En Route'; }
if ($req['status'] === 'arrived') { $status_color = 'var(--primary)'; $status_text = 'Unit Arrived'; }
if ($req['status'] === 'completed') { $status_color = 'var(--success)'; $status_text = 'Mission Complete'; }
if ($req['status'] === 'cancelled') { $status_color = 'var(--danger)'; $status_text = 'Cancelled'; }

require_once '../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <h2><i class="fa fa-map-location-dot" style="color:var(--primary); margin-right:12px;"></i>Track Rescue</h2>
    <div style="background: rgba(255,255,255,0.8); border: 1px solid var(--border); padding: 8px 16px; border-radius: 50px; font-weight:700; color: <?= $status_color ?>; box-shadow: var(--shadow-sm);">
        <i class="fa fa-circle" style="font-size:0.6rem; margin-right:6px;"></i> <?= htmlspecialchars($status_text) ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start;">
    
    <!-- LEFT: MAP -->
    <div class="glass-card" style="padding:12px; border-radius: 24px;">
        <div id="map" style="width: 100%; height: 500px; border-radius: 16px;"></div>
    </div>

    <!-- RIGHT: DETAILS PANEL -->
    <div>
        <div class="glass-card" style="padding: 24px; margin-bottom: 24px;">
            <h4 style="font-weight: 800; font-size: 1.1rem; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">Emergency Details</h4>
            
            <div style="display:flex; gap: 12px; margin-bottom: 16px;">
                <div style="width:40px; height:40px; border-radius:12px; background:rgba(239,68,68,0.1); color:var(--danger); display:flex; align-items:center; justify-content:center; font-size:1.1rem;"><i class="fa fa-triangle-exclamation"></i></div>
                <div>
                    <div style="font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Type</div>
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text);"><?= htmlspecialchars($req['emergency_type']) ?></div>
                </div>
            </div>

            <div style="display:flex; gap: 12px; margin-bottom: 16px;">
                <div style="width:40px; height:40px; border-radius:12px; background:rgba(37,99,235,0.1); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.1rem;"><i class="fa fa-clock"></i></div>
                <div>
                    <div style="font-size: 0.72rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Time</div>
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text);"><?= date('h:i A \o\n M d', strtotime($req['created_at'])) ?></div>
                </div>
            </div>

            <?php if ($req['status'] !== 'completed' && $req['status'] !== 'cancelled'): ?>
            <button onclick="confirmCancelSOS()" class="btn-cancel-sos" 
              style="width:100%; margin-top:16px; padding:12px 20px; background:linear-gradient(135deg, #ef4444, #dc2626); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:0.9rem; cursor:pointer; box-shadow:0 4px 15px rgba(239,68,68,0.3); display:flex; align-items:center; justify-content:center; gap:8px; transition: all 0.2s ease-in-out;">
                <i class="fa fa-ban"></i> Cancel SOS Request
            </button>
            <style>
              .btn-cancel-sos:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(239,68,68,0.45) !important;
              }
              .btn-cancel-sos:active {
                transform: translateY(0);
              }
            </style>
            <?php endif; ?>
        </div>

        <?php if ($req['assigned_unit_id']): ?>
        <div class="glass-card" style="padding: 24px;">
            <h4 style="font-weight: 800; font-size: 1.1rem; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">Assigned Unit</h4>
            
            <div style="display:flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                <div style="width:54px; height:54px; border-radius:16px; background:linear-gradient(135deg, #10b981, #059669); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow: 0 4px 15px rgba(16,185,129,0.3);"><i class="fa fa-ambulance"></i></div>
                <div>
                    <div style="font-weight: 800; font-size: 1.1rem; color: var(--text);"><?= htmlspecialchars($req['unit_name']) ?></div>
                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--muted);">Plate: <?= htmlspecialchars($req['plate_number']) ?></div>
                </div>
            </div>

            <?php if ($req['driver_name']): ?>
            <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--muted); text-transform: uppercase;">Driver</div>
                    <div style="font-weight: 800; font-size: 0.9rem; color: var(--text);"><?= htmlspecialchars($req['driver_name']) ?></div>
                </div>
                <div id="distance-holder" style="font-weight:900; color:var(--primary);">--</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Pass coordinates to JS safely
$pLat = $req['lat'] ? (float)$req['lat'] : 'null';
$pLng = $req['lng'] ? (float)$req['lng'] : 'null';
$dLat = $req['u_lat'] ? (float)$req['u_lat'] : 'null';
$dLng = $req['u_lng'] ? (float)$req['u_lng'] : 'null';

$extra_js = "
<script src=\"https://unpkg.com/leaflet@1.9.4/dist/leaflet.js\"></script>
<script>
// Initialize map
const patientLoc = [$pLat, $pLng];
let driverLoc = [$dLat, $dLng];

const map = L.map('map', { zoomControl: false, attributionControl: false }).setView(patientLoc, 15);
L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',{subdomains:'0123',maxZoom:20}).addTo(map);
L.control.zoom({ position: 'bottomright' }).addTo(map);

// Markers
const patientIcon = L.divIcon({
    className: '',
    html: `<div style=\"background:#ef4444;width:24px;height:24px;border-radius:50%;border:4px solid #fff;box-shadow:0 0 0 4px rgba(239,68,68,.3),0 4px 12px rgba(239,68,68,.5)\"></div>`,
    iconSize: [24,24], iconAnchor: [12,12]
});

L.marker(patientLoc, {icon: patientIcon}).addTo(map).bindPopup('<b>Your Location</b>');

let driverMarker = null;
let routeLine = null;

if (driverLoc[0] !== null) {
    const driverIcon = L.divIcon({
        className: '',
        html: `<div style=\"background:linear-gradient(135deg,#10b981,#059669);width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;box-shadow:0 4px 15px rgba(16,185,129,.4);border:3px solid #fff\"><i class=\"fa fa-ambulance\"></i></div>`,
        iconSize: [38,38], iconAnchor: [19,19]
    });
    driverMarker = L.marker(driverLoc, {icon: driverIcon}).addTo(map);

    // Initial Routing Line (straight fallback)
    routeLine = L.polyline([patientLoc, driverLoc], {color: '#3b82f6', weight: 5, dashArray: '10,10', opacity: 0.8}).addTo(map);
    
    // Fit Bounds
    map.fitBounds(L.featureGroup([L.marker(patientLoc), driverMarker]).getBounds().pad(0.3));

    // Calculate Distance immediately
    function haversineDist(ll1, ll2) {
        if (!ll1[0] || !ll2[0]) return null;
        const R = 6371; const dL = (ll2[0]-ll1[0])*Math.PI/180; const dO = (ll2[1]-ll1[1])*Math.PI/180;
        const a = Math.sin(dL/2)**2 + Math.cos(ll1[0]*Math.PI/180)*Math.cos(ll2[0]*Math.PI/180)*Math.sin(dO/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }
    
    const d = haversineDist(patientLoc, driverLoc);
    if(d) document.getElementById('distance-holder').textContent = d.toFixed(1) + ' km away';
}

// Live polling to update driver marker (optional enhancement)
setInterval(() => {
    fetch(`../api/user/get_request_status.php?id={$req_id}`)
      .then(r => r.json())
      .then(d => {
          if(d.status === 'success' && d.request && d.request.u_lat) {
              const newLat = parseFloat(d.request.u_lat);
              const newLng = parseFloat(d.request.u_lng);
              if (driverMarker) {
                  driverMarker.setLatLng([newLat, newLng]);
                  if (routeLine) routeLine.setLatLngs([patientLoc, [newLat, newLng]]);
                  const dist = haversineDist(patientLoc, [newLat, newLng]);
                  if(dist) document.getElementById('distance-holder').textContent = dist.toFixed(1) + ' km away';
              }
          }
      }).catch(e => console.log('Polling err', e));
}, 5000);

function confirmCancelSOS() {
    if (confirm(\"Are you sure you want to cancel this emergency SOS request?\")) {
        fetch(`../api/user/cancel_request.php?id={$req_id}`)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    alert(\"Your emergency SOS request has been successfully cancelled.\");
                    window.location.reload();
                } else {
                    alert(\"Failed to cancel request: \" + d.message);
                }
            })
            .catch(e => {
                console.error(e);
                alert(\"An error occurred. Please try again.\");
            });
    }
}
</script>
";

require_once '../includes/footer.php';
?>
