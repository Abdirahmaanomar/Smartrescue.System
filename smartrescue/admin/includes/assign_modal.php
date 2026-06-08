<style>
.assign-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.assign-modal-backdrop.show{display:flex;animation:fadeIn 0.2s;}
.assign-modal{background:#fff;border-radius:20px;padding:32px;width:90%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.2);animation:slideUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes slideUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}

.modal-info-card { background: #f8fafc; border: 1px solid rgba(0,0,0,0.06); border-radius: 12px; padding: 14px; margin-bottom: 16px; }
.info-label { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.info-value { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
.info-danger { color: #ef4444; }

/* Grid Selection UI */
.assign-unit-grid { display: flex; flex-direction: column; gap: 8px; max-height: 240px; overflow-y: auto; margin-bottom: 16px; padding-right: 4px; }
.assign-unit-grid::-webkit-scrollbar { width: 6px; }
.assign-unit-grid::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
.assign-unit-card { padding: 12px 14px; border-radius: 12px; border: 2px solid rgba(0,0,0,0.06); background: #f8fafc; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: space-between; }
.assign-unit-card:hover { border-color: rgba(59,130,246,0.3); background: #f1f5f9; }
.assign-unit-card.active { border-color: #3b82f6; background: rgba(59,130,246,0.05); box-shadow: 0 4px 12px rgba(59,130,246,0.1); }
.unit-mini-avatar { width: 34px; height: 34px; border-radius: 8px; object-fit: cover; margin-right: 12px; border: 1px solid rgba(0,0,0,0.05); }
</style>

<div class="assign-modal-backdrop" id="global-assign-modal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="assign-modal">
        <h5 style="font-weight:800;margin-bottom:20px;color:#0f172a;"><i class="fa fa-truck-medical text-primary me-2"></i>Smart Assign (#<span id="assignIncidentIdDisplay"></span>)</h5>
        
        <!-- Victim Info -->
        <div class="modal-info-card" id="assignVictimInfo" style="display:none; border-left: 4px solid #ef4444;">
            <div class="info-label">Victim Profile</div>
            <div class="info-value" id="vicNamePhone">Loading...</div>
            <div style="margin-top:8px;">
                <div class="info-label text-danger">Medical Profile & Contacts</div>
                <div class="info-value info-danger" id="vicMedical" style="font-size:0.8rem">...</div>
            </div>
        </div>

        <div id="assignMatchLabel" style="display:none; background:rgba(34,197,94,0.1); color:#15803d; padding:8px 12px; border-radius:10px; font-size:0.8rem; font-weight:700; margin-bottom:16px;">
            <i class="fa fa-crosshairs"></i> Closest matching units prioritized.
        </div>

        <form id="assignAjaxForm" onsubmit="submitAssignAjax(event)">
            <input type="hidden" name="request_id" id="assignRequestId">
            <input type="hidden" name="unit_id" id="assignUnitIdValue">
            
            <div class="info-label">Available Response Network</div>
            <div id="assignUnitGrid" class="assign-unit-grid">
                <div style="padding:14px;text-align:center;color:#64748b;font-weight:600;font-size:0.9rem;">Loading live units...</div>
            </div>
            
            <!-- Driver Preview Info -->
            <div class="modal-info-card" id="assignDriverInfo" style="display:none; border-left: 4px solid #3b82f6; padding: 18px;">
                <div class="info-label">Responder Preview</div>
                <div style="display:flex; align-items:center; gap:14px;">
                    <img id="drvPreviewPhoto" src="" style="width:44px; height:44px; border-radius:10px; object-fit:cover; display:none; border:2px solid #fff; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                    <div id="drvPreviewInit" style="width:44px; height:44px; border-radius:10px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-weight:800; color:#3b82f6; border:1px solid #e2e8f0;">?</div>
                    <div>
                        <div class="info-value" id="drvNamePhone">Select a unit...</div>
                        <div class="info-value" id="drvPlate" style="font-size:0.8rem; color:#64748b; margin-top:2px;"></div>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit" id="btnAssignSubmit" style="flex:1;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border:none;padding:12px;border-radius:8px;font-weight:700;box-shadow:0 4px 12px rgba(59,130,246,0.3);cursor:pointer;transition:all 0.2s;">Dispatch Unit</button>
                <button type="button" style="flex:1;background:#f1f5f9;color:#475569;border:1px solid rgba(0,0,0,0.08);padding:12px;border-radius:8px;font-weight:700;cursor:pointer;" onclick="document.getElementById('global-assign-modal').classList.remove('show')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentUnitsData = {};

function calcDist(lat1, lon1, lat2, lon2) {
    if(!lat1 || !lon1 || !lat2 || !lon2) return 99999;
    const R = 6371; 
    const dLat = (lat2-lat1)*Math.PI/180;
    const dLon = (lon2-lon1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function selectAssignUnit(el, unitId) {
    document.querySelectorAll('.assign-unit-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('assignUnitIdValue').value = unitId;
    updateDriverPreview();
}

function updateDriverPreview() {
    const val = document.getElementById('assignUnitIdValue').value;
    const u = currentUnitsData[val];
    if (u) {
        document.getElementById('assignDriverInfo').style.display = 'block';
        document.getElementById('drvNamePhone').innerHTML = `${escHtmlLocal(u.driver_name)} <span style="margin-left:12px; color:#64748b; font-weight:500;"><i class="fa fa-phone" style="font-size:0.75rem;"></i> ${escHtmlLocal(u.phone)}</span>`;
        document.getElementById('drvPlate').innerHTML = `Plate: ${escHtmlLocal(u.plate_number)} (${u.type.toUpperCase()})`;
        
        const photo = document.getElementById('drvPreviewPhoto');
        const init = document.getElementById('drvPreviewInit');
        if(u.driver_image) {
            photo.src = '../' + u.driver_image;
            photo.style.display = 'block';
            photo.onerror = () => { photo.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(u.driver_name)}&background=3b82f6&color=fff`; };
            init.style.display = 'none';
        } else {
            photo.style.display = 'none';
            init.style.display = 'flex';
            init.textContent = (u.driver_name || '?').charAt(0).toUpperCase();
        }
    } else {
        document.getElementById('assignDriverInfo').style.display = 'none';
    }
}

function escHtmlLocal(str) {
    if(!str) return '';
    return str.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function openAssignModal(id, vicLat, vicLng, type) {
    document.getElementById('assignIncidentIdDisplay').textContent = id;
    document.getElementById('assignRequestId').value = id;
    const grid = document.getElementById('assignUnitGrid');
    grid.innerHTML = '<div style="padding:14px;text-align:center;color:#64748b;font-weight:600;font-size:0.9rem;"><i class="fa fa-spinner fa-spin me-2"></i> Identifying best responder...</div>';
    
    document.getElementById('global-assign-modal').classList.add('show');
    document.getElementById('assignMatchLabel').style.display = 'none';
    document.getElementById('assignVictimInfo').style.display = 'block';
    // Make sure we have the placeholder visible while fetching
    document.getElementById('assignDriverInfo').style.display = 'block';
    document.getElementById('drvNamePhone').textContent = 'Loading...';
    document.getElementById('drvPlate').textContent = '';
    
    document.getElementById('vicNamePhone').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Fetching...';
    document.getElementById('vicMedical').textContent = '';
    
    // 1. Fetch Victim Details
    fetch(`../api/admin/get_request_details.php?id=${id}`)
        .then(r=>r.json()).then(d=>{
            if(d.status === 'success') {
                document.getElementById('vicNamePhone').innerHTML = `<i class="fa fa-user" style="color:#cbd5e1; margin-right:6px;"></i> ${escHtmlLocal(d.user.fullname)} <span style="margin-left:12px;color:#64748b"><i class="fa fa-phone"></i> ${escHtmlLocal(d.user.phone)}</span>`;
                let med = escHtmlLocal(d.user.medical_info) || 'None provided';
                let emContact = escHtmlLocal(d.user.emergency_contacts) || 'None provided';
                let demoString = '';
                if(d.user.age || d.user.gender) {
                    demoString = `<div style="margin-bottom:4px"><b>Demographics:</b> ${d.user.age ? escHtmlLocal(d.user.age) + ' yrs' : ''} ${d.user.gender ? '· ' + escHtmlLocal(d.user.gender) : ''}</div>`;
                }
                document.getElementById('vicMedical').innerHTML = demoString + `<div style="margin-bottom:4px"><b>Conditions:</b> ${med}</div> <div><b>ICE Contacts:</b> ${emContact}</div>`;
            } else {
                document.getElementById('vicNamePhone').textContent = 'Victim details unavailable';
            }
        }).catch(e=>{ document.getElementById('vicNamePhone').textContent = 'Victim data error'; });

    // 2. Fetch live Fleet data
    fetch('../api/admin/get_fleet_data.php').then(r=>r.json()).then(d=>{
        if(d.status === 'success'){
            // Include ALL available units (Ambulance/Medical, Police, Fire, Accident)
            let avail = d.units.filter(u => u.status === 'available');
            
            if(avail.length === 0){
                grid.innerHTML = '<div style="padding:14px;text-align:center;color:#ef4444;font-weight:800;font-size:0.9rem;">No available responders found nearby.</div>';
                document.getElementById('btnAssignSubmit').disabled = true;
                return;
            }
            
            // Calculate distance & prioritize
            let et = (type||'').toLowerCase();
            let isKnownType = et.includes('medical') || et.includes('accident') || et.includes('fire') || et.includes('police');
            
            avail.forEach(u => {
                u.dist = calcDist(vicLat, vicLng, u.current_lat, u.current_lng);
                
                let typeMatch = false;
                let ut = (u.type||'').toLowerCase();
                
                // Map 'medical' in DB to 'ambulance' request, else direct string match
                if(et.includes('medical') && (ut === 'ambulance' || ut === 'medical')) typeMatch = true;
                else if(et.includes('accident') && ut === 'accident') typeMatch = true;
                else if(et.includes('fire') && ut === 'fire') typeMatch = true;
                else if(et.includes('police') && ut === 'police') typeMatch = true;
                else if(!isKnownType) typeMatch = true; // Fallback: show all if emergency type is unknown
                
                u.isMatch = typeMatch;
            });
            
            // PRIORITIZED FILTERING: Try to find responders that match the emergency type first
            let filteredAvail = avail.filter(u => u.isMatch);
            let hasDirectMatch = true;
            
            if (filteredAvail.length === 0) {
                // FALLBACK: If no direct matching available responder is found, show ANY available responder!
                filteredAvail = avail;
                hasDirectMatch = false;
                
                document.getElementById('assignMatchLabel').innerHTML = '<i class="fa fa-exclamation-triangle"></i> No direct ' + escHtmlLocal(type) + ' match found. Showing <b>any available responder</b> sorted by nearest distance.';
                document.getElementById('assignMatchLabel').style.background = 'rgba(234, 179, 8, 0.1)';
                document.getElementById('assignMatchLabel').style.color = '#a16207';
                document.getElementById('assignMatchLabel').style.display = 'block';
            } else {
                document.getElementById('assignMatchLabel').innerHTML = '<i class="fa fa-crosshairs"></i> Prioritizing matching ' + escHtmlLocal(type) + ' units, sorted by nearest distance.';
                document.getElementById('assignMatchLabel').style.background = 'rgba(34,197,94,0.1)';
                document.getElementById('assignMatchLabel').style.color = '#15803d';
                document.getElementById('assignMatchLabel').style.display = 'block';
            }
            
            // Sort by nearest distance (closest first)
            filteredAvail.sort((a,b) => a.dist - b.dist);
            
            currentUnitsData = {};
            grid.innerHTML = filteredAvail.map((u, index) => {
                currentUnitsData[u.id] = u;
                let dStr = u.dist < 99999 ? u.dist.toFixed(1)+' km' : 'Unknown dist';
                let matchBadge = u.isMatch ? '<i class="fa fa-star" title="Best Match" style="color:#f59e0b; margin-right:6px;"></i>' : '';
                
                let avatarHtml = `<div class="unit-mini-avatar" style="background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-weight:800;color:#3b82f6;">${(u.driver_name||'?').charAt(0).toUpperCase()}</div>`;
                if(u.driver_image) {
                    avatarHtml = `<img src="../${escHtmlLocal(u.driver_image)}" class="unit-mini-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.driver_name)}&background=3b82f6&color=fff'">`;
                }

                return `
                <div class="assign-unit-card ${index === 0 ? 'active' : ''}" onclick="selectAssignUnit(this, ${u.id})">
                    <div style="display:flex; align-items:center;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight:800;font-size:0.9rem;color:#0f172a;margin-bottom:2px;">${matchBadge}${escHtmlLocal(u.unit_name)}</div>
                            <div style="font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:1px;">Type: ${escHtmlLocal(u.type)}</div>
                        </div>
                    </div>
                    <div style="font-size:0.85rem;font-weight:900;color:#3b82f6;background:rgba(59,130,246,0.1);padding:4px 8px;border-radius:6px;">
                        <i class="fa fa-location-arrow" style="margin-right:2px"></i> ${dStr}
                    </div>
                </div>`;
            }).join('');
            
            document.getElementById('assignUnitIdValue').value = filteredAvail[0].id;
            document.getElementById('btnAssignSubmit').disabled = false;
            updateDriverPreview();
        }
    });
}

function submitAssignAjax(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAssignSubmit');
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled = true;
    
    // In case there's an existing assigned_unit (Re-assignment), backend handles override
    const fd = new FormData(e.target);
    fetch('../api/admin/assign_unit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                document.getElementById('global-assign-modal').classList.remove('show');
                if(typeof pollData === 'function') {
                    pollData(); // We are in index.php
                } else if(typeof pollAlerts === 'function') {
                    pollAlerts(); // notifications.php
                    location.reload(); 
                } else {
                    location.reload();
                }
            } else { 
                alert('Error: ' + d.message); 
                btn.innerHTML = 'Dispatch Unit'; btn.disabled = false;
            }
        }).catch(() => {
            alert('Network error.'); btn.innerHTML = 'Dispatch Unit'; btn.disabled = false;
        });
}
</script>
