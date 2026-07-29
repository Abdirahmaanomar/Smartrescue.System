<!-- ── DISPATCH EMERGENCY POPUP MODAL ────────────────────────────── -->
<div id="dispatchModalOverlay" class="dm-overlay" style="display: none;">
    <div class="dm-card">
        <!-- Top Circle Emergency Icon -->
        <div class="dm-icon-wrap">
            <i class="fa-solid fa-asterisk dm-icon"></i>
        </div>

        <!-- New Dispatch Badge & Title -->
        <div class="dm-badge">
            <span class="dm-badge-icon">🚨</span> NEW DISPATCH
        </div>
        <h2 class="dm-title" id="dmTitle">Medical Request</h2>
        <p class="dm-subtitle">A new emergency has been assigned to your unit.</p>

        <!-- Details Box -->
        <div class="dm-info-box">
            <div class="dm-row">
                <span class="dm-label">Patient</span>
                <span class="dm-val" id="dmPatient">--</span>
            </div>
            <div class="dm-row">
                <span class="dm-label">Type</span>
                <span class="dm-val" id="dmType">--</span>
            </div>
            <div class="dm-row">
                <span class="dm-label">Contact</span>
                <span class="dm-val dm-phone" id="dmPhone">--</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="dm-actions">
            <button type="button" class="dm-btn dm-btn-accept" onclick="acceptDispatchFromModal()">
                <i class="fa-solid fa-circle-check"></i> Accept &amp; Respond
            </button>
            <div class="dm-btn-row">
                <button type="button" class="dm-btn dm-btn-reject" onclick="rejectDispatchFromModal()">
                    <i class="fa-solid fa-circle-xmark"></i> Reject
                </button>
                <button type="button" class="dm-btn dm-btn-dismiss" onclick="dismissDispatchModal()">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.dm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: fadeInModal 0.25s ease-out forwards;
}

.dm-card {
    width: 100%;
    max-width: 390px;
    background: #eef1f6;
    border-radius: 28px;
    padding: 32px 24px 24px 24px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.7);
    animation: popInModal 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}

[data-theme="dark"] .dm-card {
    background: #111c2e;
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
}

.dm-icon-wrap {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #fce8e8;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px auto;
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.15);
}

[data-theme="dark"] .dm-icon-wrap {
    background: rgba(239, 68, 68, 0.18);
}

.dm-icon {
    font-size: 34px;
    color: #ef4444;
}

.dm-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 800;
    color: #ef4444;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.dm-title {
    font-weight: 900;
    font-size: 22px;
    color: #0f172a;
    margin-bottom: 4px;
    letter-spacing: -0.4px;
}

[data-theme="dark"] .dm-title {
    color: #f8fafc;
}

.dm-subtitle {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 22px;
    line-height: 1.4;
}

[data-theme="dark"] .dm-subtitle {
    color: #94a3b8;
}

.dm-info-box {
    background: rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    padding: 6px 18px;
    margin-bottom: 22px;
    border: 1px solid rgba(0, 0, 0, 0.04);
}

[data-theme="dark"] .dm-info-box {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.06);
}

.dm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 13.5px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .dm-row {
    border-bottom-color: rgba(255, 255, 255, 0.06);
}

.dm-row:last-child {
    border-bottom: none;
}

.dm-label {
    color: #64748b;
    font-weight: 600;
}

[data-theme="dark"] .dm-label {
    color: #94a3b8;
}

.dm-val {
    font-weight: 800;
    color: #0f172a;
}

[data-theme="dark"] .dm-val {
    color: #f1f5f9;
}

.dm-phone {
    color: #f59e0b !important;
}

.dm-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dm-btn {
    width: 100%;
    height: 48px;
    border-radius: 30px;
    border: none;
    font-family: inherit;
    font-weight: 800;
    font-size: 14.5px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.dm-btn-accept {
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
}

.dm-btn-accept:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(16, 185, 129, 0.45);
}

.dm-btn-row {
    display: flex;
    gap: 10px;
}

.dm-btn-reject {
    flex: 1;
    height: 44px;
    background: #ffffff;
    border: 1.5px solid #ef4444;
    color: #ef4444;
    border-radius: 30px;
    font-weight: 700;
    font-size: 13.5px;
}

[data-theme="dark"] .dm-btn-reject {
    background: rgba(239, 68, 68, 0.08);
}

.dm-btn-reject:hover {
    background: #ef4444;
    color: #ffffff;
}

.dm-btn-dismiss {
    flex: 1;
    height: 44px;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    color: #2563eb;
    border-radius: 30px;
    font-weight: 700;
    font-size: 13.5px;
}

[data-theme="dark"] .dm-btn-dismiss {
    background: rgba(37, 99, 235, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
    color: #60a5fa;
}

.dm-btn-dismiss:hover {
    background: rgba(37, 99, 235, 0.12);
    border-color: #2563eb;
}

@keyframes fadeInModal {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes popInModal {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
let currentModalRequest = null;
let dismissedModalRequestId = null;

function checkAndShowDispatchModal(req) {
    if (!req) {
        hideDispatchModal();
        return;
    }
    
    // Show modal if job is pending assignment and not dismissed in current session
    if (req.status === 'pending' && dismissedModalRequestId !== req.id) {
        currentModalRequest = req;
        const etype = req.emergency_type ? (req.emergency_type.charAt(0).toUpperCase() + req.emergency_type.slice(1)) : 'Emergency';
        document.getElementById('dmTitle').textContent = etype + ' Request';
        document.getElementById('dmPatient').textContent = req.patient_name || 'Emergency Victim';
        document.getElementById('dmType').textContent = etype;
        document.getElementById('dmPhone').textContent = req.patient_phone || 'N/A';
        
        const overlay = document.getElementById('dispatchModalOverlay');
        if (overlay.style.display !== 'flex') {
            overlay.style.display = 'flex';
            if (typeof playAlert === 'function') {
                playAlert();
            }
        }
    } else {
        if (req.status !== 'pending') {
            hideDispatchModal();
        }
    }
}

function hideDispatchModal() {
    const overlay = document.getElementById('dispatchModalOverlay');
    if (overlay) overlay.style.display = 'none';
}

function dismissDispatchModal() {
    if (currentModalRequest) {
        dismissedModalRequestId = currentModalRequest.id;
    }
    hideDispatchModal();
}

function acceptDispatchFromModal() {
    if (!currentModalRequest) return;
    const rid = currentModalRequest.id;
    const uid = typeof UNIT_ID !== 'undefined' ? UNIT_ID : 0;
    const did = typeof DRV_ID !== 'undefined' ? DRV_ID : 0;

    if (typeof doAction === 'function') {
        doAction(rid, 'accept');
    } else {
        fetch('../api/driver/update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${rid}&unit_id=${uid}&action=accept&driver_id=${did}`
        }).then(r => r.json()).then(() => {
            if (typeof poll === 'function') poll();
            if (typeof pollJob === 'function') pollJob();
            if (typeof pollModal === 'function') pollModal();
        });
    }
    hideDispatchModal();
    if (!window.location.pathname.includes('map.php')) {
        window.location.href = 'map.php';
    }
}

function rejectDispatchFromModal() {
    if (!currentModalRequest) return;
    const rid = currentModalRequest.id;
    const uid = typeof UNIT_ID !== 'undefined' ? UNIT_ID : 0;
    const did = typeof DRV_ID !== 'undefined' ? DRV_ID : 0;

    if (typeof doAction === 'function') {
        doAction(rid, 'reject');
    } else {
        fetch('../api/driver/update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `request_id=${rid}&unit_id=${uid}&action=reject&driver_id=${did}`
        }).then(r => r.json()).then(() => {
            if (typeof poll === 'function') poll();
            if (typeof pollJob === 'function') pollJob();
            if (typeof pollModal === 'function') pollModal();
        });
    }
    hideDispatchModal();
}
</script>
