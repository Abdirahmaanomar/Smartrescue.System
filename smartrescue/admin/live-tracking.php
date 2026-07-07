<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Tracking | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--sidebar-width:268px;--panel:320px;}
body{font-family:'Outfit',sans-serif;background:#0a1628;color:#fff;overflow:hidden;height:100vh;}

/* Full-screen map */
#live-map{position:fixed;inset:0;z-index:1;}

/* Side panel */
.track-panel{
    position:fixed;right:0;top:0;bottom:0;
    width:var(--panel);
    background:rgba(8,15,30,0.95);
    backdrop-filter:blur(20px);
    z-index:100;
    display:flex;flex-direction:column;
    box-shadow:-10px 0 40px rgba(0,0,0,0.4);
    border-left:1px solid rgba(255,255,255,0.06);
    transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);
}
.track-panel.collapsed{transform:translateX(100%);}

.panel-toggle{
    position:fixed;right:var(--panel);top:50%;transform:translateY(-50%);
    z-index:200;
    width:28px;height:56px;
    background:rgba(8,15,30,0.95);
    backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,0.08);
    border-right:none;
    border-radius:8px 0 0 8px;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:rgba(255,255,255,0.6);font-size:0.75rem;
    transition:all 0.3s;
}
.panel-toggle:hover{color:white;background:rgba(59,130,246,0.3);}
.track-panel.collapsed ~ .panel-toggle{right:0;border-radius:8px 0 0 8px;}

.panel-header{padding:24px 20px 16px;}
.panel-header h4{font-size:1rem;font-weight:800;color:white;margin-bottom:4px;display:flex;align-items:center;gap:8px;}
.panel-header p{font-size:0.72rem;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;letter-spacing:1.5px;}

.live-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:4px 10px;border-radius:50px;
    background:rgba(34,197,94,0.15);color:#22c55e;
    font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;
}
.live-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:live-dot 1.4s infinite;}
@keyframes live-dot{0%,100%{opacity:1;}50%{opacity:0.3;}}

.panel-stats{
    display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;
    padding:0 20px 16px;
    border-bottom:1px solid rgba(255,255,255,0.06);
}
.pstat{
    background:rgba(255,255,255,0.04);
    border-radius:12px;padding:12px 10px;text-align:center;
    border:1px solid rgba(255,255,255,0.06);
}
.pstat-num{font-size:1.4rem;font-weight:900;line-height:1;}
.pstat-label{font-size:0.62rem;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:rgba(255,255,255,0.35);margin-top:3px;}

.unit-list{flex:1;overflow-y:auto;padding:16px 20px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.1) transparent;}
.unit-list::-webkit-scrollbar{width:4px;}
.unit-list::-webkit-scrollbar-track{background:transparent;}
.unit-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:4px;}

.unit-list-item{
    display:flex;align-items:center;gap:12px;
    padding:14px 12px;border-radius:14px;
    background:rgba(255,255,255,0.03);
    border:1px solid rgba(255,255,255,0.05);
    margin-bottom:8px;cursor:pointer;transition:all 0.2s;
}
.unit-list-item:hover{background:rgba(59,130,246,0.15);border-color:rgba(59,130,246,0.3);}
.uli-icon{
    width:38px;height:38px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    font-size:0.95rem;flex-shrink:0;
}
.uli-name{font-size:0.85rem;font-weight:800;color:white;line-height:1.2;}
.uli-sub{font-size:0.7rem;color:rgba(255,255,255,0.4);font-weight:600;margin-top:2px;}
.uli-status{
    margin-left:auto;padding:3px 9px;border-radius:50px;
    font-size:0.62rem;font-weight:800;text-transform:uppercase;letter-spacing:0.5px;flex-shrink:0;
}
.status-available{background:rgba(34,197,94,0.15);color:#4ade80;}
.status-busy{background:rgba(59,130,246,0.15);color:#60a5fa;}
.status-offline{background:rgba(100,116,139,0.15);color:#94a3b8;}

.incident-section{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.06);}
.incident-section h6{font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,0.35);margin-bottom:10px;}
.inc-item{
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;border-radius:12px;
    background:rgba(239,68,68,0.06);
    border:1px solid rgba(239,68,68,0.12);
    margin-bottom:6px;
}
.inc-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;animation:live-dot 1.5s infinite;flex-shrink:0;}
.inc-name{font-size:0.8rem;font-weight:700;color:white;flex:1;}
.inc-type{font-size:0.68rem;color:rgba(255,255,255,0.4);}
.inc-btn{
    padding:4px 10px;border-radius:6px;
    font-size:0.68rem;font-weight:700;text-decoration:none;
    background:rgba(239,68,68,0.15);color:#f87171;transition:0.2s;
}
.inc-btn:hover{background:#ef4444;color:white;}

/* Top HUD */
.top-hud{
    position:fixed;top:20px;left:20px;z-index:200;
    display:flex;align-items:center;gap:12px;
}
.hud-brand{
    display:flex;align-items:center;gap:10px;
    padding:10px 16px;border-radius:14px;
    background:rgba(8,15,30,0.9);backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
}
.hud-brand-icon{
    width:32px;height:32px;border-radius:8px;
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    display:flex;align-items:center;justify-content:center;
    font-size:0.85rem;color:white;
}
.hud-brand-text{font-size:0.9rem;font-weight:800;color:white;}
.hud-brand-text span{color:#60a5fa;}
.hud-btn{
    display:flex;align-items:center;gap:8px;
    padding:10px 16px;border-radius:12px;
    background:rgba(8,15,30,0.9);backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,0.08);
    color:rgba(255,255,255,0.7);font-size:0.8rem;font-weight:700;
    text-decoration:none;transition:all 0.2s;cursor:pointer;
}
.hud-btn:hover{background:rgba(59,130,246,0.3);color:white;border-color:rgba(59,130,246,0.4);}

.refresh-indicator{
    position:fixed;bottom:20px;left:20px;z-index:200;
    display:flex;align-items:center;gap:8px;
    padding:8px 14px;border-radius:10px;
    background:rgba(8,15,30,0.9);backdrop-filter:blur(10px);
    border:1px solid rgba(255,255,255,0.06);
    font-size:0.72rem;font-weight:700;color:rgba(255,255,255,0.5);
}
.refresh-bar{
    height:2px;background:rgba(255,255,255,0.1);border-radius:1px;overflow:hidden;width:60px;
}
.refresh-fill{height:100%;background:#3b82f6;border-radius:1px;animation:refresh-anim 3s linear infinite;}
@keyframes refresh-anim{from{width:0}to{width:100%}}
</style>
</head>
<body>

<div id="live-map"></div>

<!-- Top HUD -->
<div class="top-hud">
    <div class="hud-brand">
        <div class="hud-brand-icon"><i class="fa fa-satellite-dish"></i></div>
        <span class="hud-brand-text">Smart<span>Rescue</span> Live</span>
    </div>
    <a href="index.php" class="hud-btn"><i class="fa fa-arrow-left"></i> Dashboard</a>
    <div class="hud-btn" onclick="map.setView([2.0469, 45.3182], 13)"><i class="fa fa-crosshairs"></i> Reset View</div>
    <div style="width:1px;height:20px;background:rgba(255,255,255,0.1);margin:0 4px;"></div>
    <div class="hud-btn" id="btn-layer-std" onclick="setLayer('std')" style="background:rgba(59,130,246,0.3);color:white;border-color:rgba(59,130,246,0.4);"><i class="fa fa-map"></i> Map</div>
    <div class="hud-btn" id="btn-layer-sat" onclick="setLayer('sat')"><i class="fa fa-satellite"></i> Satellite</div>
</div>

<!-- Refresh indicator -->
<div class="refresh-indicator">
    <div class="refresh-bar"><div class="refresh-fill"></div></div>
    Live · Updating every 3s
</div>

<!-- Side Panel -->
<div class="track-panel" id="track-panel">
    <div class="panel-header">
        <h4><i class="fa fa-satellite-dish" style="color:#3b82f6"></i> Live Tracking</h4>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
            <p>Real-time unit positions</p>
            <div class="live-badge"><div class="live-dot"></div>Live</div>
        </div>
    </div>
    <div class="panel-stats">
        <div class="pstat">
            <div class="pstat-num" id="track-incidents" style="color:#ef4444">—</div>
            <div class="pstat-label">Incidents</div>
        </div>
        <div class="pstat">
            <div class="pstat-num" id="track-units" style="color:#3b82f6">—</div>
            <div class="pstat-label">Units</div>
        </div>
        <div class="pstat">
            <div class="pstat-num" id="track-avail" style="color:#22c55e">—</div>
            <div class="pstat-label">Available</div>
        </div>
    </div>
    <div class="unit-list" id="unit-list">
        <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.3);font-size:0.85rem">Loading fleet data...</div>
    </div>
    <div class="incident-section">
        <h6>Active Incidents</h6>
        <div id="incident-list">
            <div style="color:rgba(255,255,255,0.3);font-size:0.78rem;text-align:center;padding:10px">No active incidents</div>
        </div>
    </div>
</div>

<div class="panel-toggle" id="panel-toggle" onclick="togglePanel()">
    <i class="fa fa-chevron-right" id="toggle-icon"></i>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Map
const mapLayers = {
    std: L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { subdomains: '0123', attribution: '© Google Maps', maxZoom: 20 }),
    sat: L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {subdomains: '0123', attribution:'© Google Maps', maxZoom:20})
};

const map = L.map('live-map', {zoomControl:false, layers: [mapLayers.std]}).setView([2.0469, 45.3182], 13);
L.control.zoom({position:'bottomright'}).addTo(map);

function setLayer(type) {
    Object.keys(mapLayers).forEach(k => map.removeLayer(mapLayers[k]));
    map.addLayer(mapLayers[type]);
    
    // Reset buttons
    document.getElementById('btn-layer-std').style = "";
    document.getElementById('btn-layer-sat').style = "";
    
    // Highlight active
    document.getElementById('btn-layer-' + type).style.background = "rgba(59,130,246,0.3)";
    document.getElementById('btn-layer-' + type).style.color = "white";
    document.getElementById('btn-layer-' + type).style.borderColor = "rgba(59,130,246,0.4)";
}

const victimIcon = L.divIcon({
    className:'',
    html:`<div style="background:#ef4444;color:#ffffff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 4px rgba(239,68,68,0.2),0 0 20px rgba(239,68,68,0.5);border:2px solid rgba(255,255,255,0.9);animation:live-dot 1.5s infinite;"></div>`,
    iconSize:[28,28],iconAnchor:[14,14]
});
const unitIcon = L.divIcon({
    className:'',
    html:`<div style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);padding:8px 10px;border-radius:12px;color:white;border:2px solid rgba(255,255,255,0.9);box-shadow:0 4px 16px rgba(59,130,246,0.6);font-size:12px"><i class="fa fa-ambulance"></i></div>`,
    iconSize:[40,38],iconAnchor:[20,19]
});

let markers = {}, routeLines = {};
let panelOpen = true;

function togglePanel() {
    panelOpen = !panelOpen;
    document.getElementById('track-panel').classList.toggle('collapsed', !panelOpen);
    document.getElementById('toggle-icon').className = panelOpen ? 'fa fa-chevron-right' : 'fa fa-chevron-left';
    document.getElementById('panel-toggle').style.right = panelOpen ? 'var(--panel)' : '0';
    setTimeout(() => map.invalidateSize(), 320);
}

function pollTracking() {
    fetch('../api/admin/get_fleet_data.php')
        .then(r => r.json())
        .then(d => {
            if (d.status !== 'success') return;
            updateStats(d.stats, d.incidents, d.units);
            updateUnitsPanel(d.units);
            updateIncidentPanel(d.incidents);
            updateMapMarkers(d.incidents, d.units);
        }).catch(() => {});
}

function updateStats(s, inc, units) {
    document.getElementById('track-incidents').textContent = inc.length;
    document.getElementById('track-units').textContent = units.length;
    document.getElementById('track-avail').textContent = s.units_available;
}

function updateUnitsPanel(units) {
    const el = document.getElementById('unit-list');
    if (!units.length) { el.innerHTML = '<div style="text-align:center;padding:30px;color:rgba(255,255,255,0.3);font-size:0.8rem">No units registered</div>'; return; }
    el.innerHTML = units.map(u => {
        const s = u.status || 'offline';
        const icons = {ambulance:'fa-truck-medical',fire:'fa-fire-extinguisher',police:'fa-shield-halved'};
        const iconKey = Object.keys(icons).find(k => (u.unit_type||'').toLowerCase().includes(k)) || 'ambulance';
        const ic = icons[iconKey];
        const col = s === 'available' ? '#22c55e' : s === 'busy' ? '#3b82f6' : '#94a3b8';
        const hasGps = u.current_lat && u.current_lng;
        return `<div class="unit-list-item" onclick="${hasGps ? `map.flyTo([${u.current_lat},${u.current_lng}],17,{animate:true})` : ''}">
            <div class="uli-icon" style="background:${col}18;color:${col}"><i class="fa ${ic}"></i></div>
            <div>
                <div class="uli-name">${esc(u.unit_name)}</div>
                <div class="uli-sub">${hasGps ? `GPS: ${parseFloat(u.current_lat).toFixed(4)}, ${parseFloat(u.current_lng).toFixed(4)}` : 'No GPS signal'}</div>
            </div>
            <span class="uli-status status-${s}">${s}</span>
        </div>`;
    }).join('');
}

function updateIncidentPanel(incidents) {
    const el = document.getElementById('incident-list');
    const active = incidents.filter(i => i.status !== 'completed' && i.status !== 'cancelled');
    if (!active.length) { el.innerHTML = '<div style="color:rgba(255,255,255,0.3);font-size:0.75rem;text-align:center;padding:10px">No active incidents</div>'; return; }
    el.innerHTML = active.slice(0,5).map(i => `
        <div class="inc-item">
            <div class="inc-dot"></div>
            <div>
                <div class="inc-name">${esc(i.patient_name)}</div>
                <div class="inc-type">${esc(i.emergency_type)}</div>
            </div>
            ${(i.user_lat || i.lat) ? `<span onclick="map.flyTo([${i.user_lat || i.lat},${i.user_lng || i.lng}],17)" class="inc-btn"><i class="fa fa-crosshairs"></i></span>` : ''}
        </div>`).join('');
}

function updateMapMarkers(incidents, units) {
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
            if (markers[mid]) {
                markers[mid].setLatLng([vLat, vLng]);
            } else {
                markers[mid] = L.marker([vLat, vLng], {icon: victimIcon}).addTo(map)
                    .bindPopup(`<div style="min-width:150px">
                        <b style="color:#ef4444;font-size:1.1rem">⚠ ${esc(i.patient_name)}</b><br>
                        <span style="font-size:12px;font-weight:700;color:#94a3b8">${esc(i.emergency_type)}</span><hr style="margin:8px 0;opacity:0.1">
                        <div style="font-size:11px;display:flex;align-items:center;gap:5px;color:#cbd5e1">
                            <i class="fa fa-phone"></i> ${esc(i.patient_phone)}
                        </div>
                        <div style="font-size:10px;margin-top:5px;color:#64748b">
                            <i class="fa fa-clock"></i> Reported: ${new Date(i.created_at).toLocaleTimeString()}
                        </div>
                        ${i.accuracy ? `<div style="font-size:9px;color:#64748b;margin-top:4px"><i class="fa fa-crosshairs"></i> Precision: ±${Math.round(i.accuracy)}m</div>` : ''}
                    </div>`);
            }
    });
    units.forEach(u => {
        if (!u.current_lat || u.status === 'available' || u.status === 'offline') return;
        const mid = 'unit_' + u.id;
        activeIds.add(mid);
        if (markers[mid]) markers[mid].setLatLng([parseFloat(u.current_lat), parseFloat(u.current_lng)]);
        else markers[mid] = L.marker([parseFloat(u.current_lat), parseFloat(u.current_lng)], {icon: unitIcon}).addTo(map)
            .bindPopup(`<b style="color:#3b82f6">🚑 ${esc(u.unit_name)}</b><br><span style="font-size:11px">${u.status}</span>`);
    });
    const activeLines = new Set();
    incidents.forEach(i => {
        if (i.status === 'accepted' && i.assigned_unit_id) {
            const u = units.find(x => x.id == i.assigned_unit_id);
            if (u && u.current_lat) {
                const lid = 'line_' + i.id;
                activeLines.add(lid);
                const vLat = i.user_lat || i.lat;
                const vLng = i.user_lng || i.lng;
                const pts = [[parseFloat(u.current_lat), parseFloat(u.current_lng)], [parseFloat(vLat), parseFloat(vLng)]];
                if (routeLines[lid]) routeLines[lid].setLatLngs(pts);
                else routeLines[lid] = L.polyline(pts, {color:'#60a5fa',weight:2,dashArray:'8,12',opacity:0.7}).addTo(map);
            }
        }
    });
    Object.keys(markers).forEach(k => { 
        if (!activeIds.has(k)) { 
            map.removeLayer(markers[k]); 
            delete markers[k]; 
        }
    });
    Object.keys(routeLines).forEach(k => { if (!activeLines.has(k)) { map.removeLayer(routeLines[k]); delete routeLines[k]; }});
}

function esc(s){ return s ? String(s).replace(/</g,'&lt;') : ''; }

pollTracking();
setInterval(pollTracking, 5000); // 5s is fast enough for live map; was 3s
</script>
</body>
</html>
