<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch drivers
$driver_query = "SELECT id, fullname FROM users WHERE role = 'driver' ORDER BY fullname ASC";
$drivers = mysqli_query($conn, $driver_query);
$drivers_arr = [];
while ($d = mysqli_fetch_assoc($drivers)) $drivers_arr[] = $d;

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $unit_name = mysqli_real_escape_string($conn, trim($_POST['unit_name']));
    $unit_type = mysqli_real_escape_string($conn, $_POST['unit_type']);
    $plate     = mysqli_real_escape_string($conn, trim($_POST['plate_number']));
    $driver_id = mysqli_real_escape_string($conn, $_POST['driver_id']);

    if (!$unit_name || !$plate || !$driver_id) {
        $error = 'Please fill in all required fields.';
    } else {
        $sql = "INSERT INTO emergency_units (unit_name, unit_type, plate_number, status, driver_id)
                VALUES ('$unit_name', '$unit_type', '$plate', 'available', '$driver_id')";
        if (mysqli_query($conn, $sql)) {
            header("Location: fleet.php?added=1");
            exit();
        } else {
            $error = 'Database error: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Unit | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── RESET & VARS ───────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:           #f0f4f9;
    --card:         #ffffff;
    --text:         #0f172a;
    --muted:        #64748b;
    --border:       #e2eaf4;
    --accent:       #3b82f6;
    --accent-dark:  #1d4ed8;
    --accent-light: rgba(59,130,246,.08);
    --green:        #22c55e;
    --amber:        #f59e0b;
    --red:          #ef4444;
    --sidebar-w:    268px;
    --shadow-sm:    0 1px 4px rgba(0,0,0,.05);
    --shadow-md:    0 8px 32px rgba(0,0,0,.08);
    --shadow-lg:    0 20px 60px rgba(0,0,0,.12);
    --r-sm:         8px;
    --r-md:         12px;
    --r-lg:         16px;
    --r-xl:         20px;
}
body {
    font-family: 'Outfit', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* ── SIDEBAR OFFSET ─────────────────────────────────────────────── */
.page-wrap {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex;
    align-items: stretch;
}

/* ── TWO-COLUMN SPLIT ───────────────────────────────────────────── */
.left-panel {
    width: 42%;
    min-height: 100vh;
    position: relative;
    background: linear-gradient(150deg, #0f172a 0%, #1e3a8a 55%, #1d4ed8 100%);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    padding: 60px 48px;
    overflow: hidden;
}

/* Decorative circles */
.left-panel::before {
    content: '';
    position: absolute;
    width: 420px; height: 420px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    top: -120px; right: -120px;
}
.left-panel::after {
    content: '';
    position: absolute;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    bottom: -80px; left: -60px;
}
.left-glow {
    position: absolute;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59,130,246,.25) 0%, transparent 70%);
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.back-link {
    display: inline-flex; align-items: center; gap: 8px;
    color: rgba(255,255,255,.55);
    font-size: .8rem; font-weight: 700;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 52px;
    transition: color .2s;
    position: relative; z-index: 1;
}
.back-link:hover { color: rgba(255,255,255,.9); }

.left-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 50px;
    padding: 6px 16px;
    font-size: .72rem; font-weight: 800;
    color: rgba(255,255,255,.7);
    text-transform: uppercase; letter-spacing: 1.5px;
    margin-bottom: 24px;
    position: relative; z-index: 1;
}
.left-badge-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 3px rgba(34,197,94,.25);
    animation: badge-pulse 2s infinite;
}
@keyframes badge-pulse {
    0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,.25); }
    50%       { box-shadow: 0 0 0 6px rgba(34,197,94,.1); }
}

.left-title {
    font-size: 2.6rem;
    font-weight: 900;
    color: #fff;
    line-height: 1.1;
    letter-spacing: -.5px;
    margin-bottom: 16px;
    position: relative; z-index: 1;
}
.left-title span { color: #60a5fa; }

.left-desc {
    font-size: .95rem;
    color: rgba(255,255,255,.55);
    line-height: 1.7;
    max-width: 340px;
    margin-bottom: 44px;
    font-weight: 400;
    position: relative; z-index: 1;
}

/* Unit type preview cards */
.unit-types {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    position: relative; z-index: 1;
}
.unit-type-item {
    display: flex; align-items: center; gap: 14px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: var(--r-md);
    padding: 12px 16px;
    transition: background .2s;
}
.unit-type-item:hover { background: rgba(255,255,255,.1); }
.uti-icon {
    width: 38px; height: 38px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
}
.uti-label { font-size: .82rem; font-weight: 700; color: rgba(255,255,255,.85); }
.uti-sub   { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: 1px; font-weight: 500; }
.uti-dot   { margin-left: auto; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

/* Bottom brand */
.left-brand {
    position: absolute;
    bottom: 32px; left: 48px;
    display: flex; align-items: center; gap: 10px;
    z-index: 1;
}
.left-brand-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; color: #fff;
    box-shadow: 0 4px 12px rgba(59,130,246,.35);
}
.left-brand-text { font-size: .88rem; font-weight: 800; color: rgba(255,255,255,.6); }
.left-brand-text span { color: #60a5fa; }

/* ── RIGHT PANEL ────────────────────────────────────────────────── */
.right-panel {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 40px;
    background: var(--bg);
}

.form-card {
    background: var(--card);
    border-radius: var(--r-xl);
    padding: 40px 36px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    width: 100%;
    max-width: 520px;
    animation: slideUp .45s cubic-bezier(.16,1,.3,1) both;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Card header */
.card-header-block {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: flex-start; gap: 14px;
}
.card-header-icon {
    width: 48px; height: 48px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: #fff;
    box-shadow: 0 6px 18px rgba(59,130,246,.3);
}
.card-header-text h1 {
    font-size: 1.2rem; font-weight: 900;
    color: var(--text); line-height: 1.2;
    margin-bottom: 4px;
}
.card-header-text p {
    font-size: .8rem; color: var(--muted);
    font-weight: 500; line-height: 1.4;
}

/* Error / success alert */
.alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px;
    border-radius: var(--r-md);
    font-size: .82rem; font-weight: 600;
    margin-bottom: 24px;
    animation: slideUp .3s ease;
}
.alert-error {
    background: rgba(239,68,68,.07);
    border: 1px solid rgba(239,68,68,.2);
    color: #b91c1c;
}
.alert-error i { color: var(--red); margin-top: 1px; }
.alert-success {
    background: rgba(34,197,94,.07);
    border: 1px solid rgba(34,197,94,.2);
    color: #15803d;
}

/* Section heading */
.section-label {
    display: flex; align-items: center; gap: 8px;
    font-size: .65rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 1.2px;
    color: var(--muted);
    margin-bottom: 14px;
    margin-top: 24px;
}
.section-label:first-child { margin-top: 0; }
.section-label::after {
    content: '';
    flex: 1; height: 1px;
    background: var(--border);
}
.section-label i { color: var(--accent); font-size: .7rem; opacity: .8; }

/* Form fields */
.field { margin-bottom: 16px; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }

.field-label {
    display: flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--muted); margin-bottom: 7px;
}
.field-label .req { color: var(--red); font-size: .9em; }

.input-wrap {
    position: relative;
}
.input-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .8rem; pointer-events: none;
    transition: color .2s;
}
.form-input, .form-select {
    width: 100%;
    padding: 11px 14px 11px 38px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-md);
    background: #f8fafd;
    font-family: 'Outfit', sans-serif;
    font-size: .88rem; font-weight: 600;
    color: var(--text);
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
    appearance: none;
    -webkit-appearance: none;
}
.form-input::placeholder { color: #b0bcc9; font-weight: 500; }
.form-input:hover, .form-select:hover { border-color: #c0d2e8; }
.form-input:focus, .form-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: #fff;
}
.input-wrap:focus-within .input-icon { color: var(--accent); }

/* Select arrow */
.select-arrow {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .7rem; pointer-events: none;
}

/* Helper text */
.field-hint {
    font-size: .7rem; color: var(--muted); font-weight: 500;
    margin-top: 5px; padding-left: 2px;
}

/* Submit button */
.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    font-family: 'Outfit', sans-serif;
    font-size: .95rem; font-weight: 800;
    border: none; border-radius: var(--r-md);
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(59,130,246,.35);
    transition: transform .2s, box-shadow .2s;
    margin-top: 28px;
    letter-spacing: .2px;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(59,130,246,.45); }
.btn-submit:active { transform: translateY(0); }
.btn-submit-icon {
    width: 28px; height: 28px;
    background: rgba(255,255,255,.18);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem; flex-shrink: 0;
    transition: transform .3s;
}
.btn-submit:hover .btn-submit-icon { transform: translateX(3px); }

.btn-cancel {
    display: block; text-align: center;
    margin-top: 14px;
    font-size: .82rem; font-weight: 700;
    color: var(--muted); text-decoration: none;
    transition: color .2s;
    padding: 8px;
}
.btn-cancel:hover { color: var(--text); }

/* Footer trust row */
.form-footer {
    display: flex; align-items: center; justify-content: center; gap: 20px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}
.form-footer-item {
    display: flex; align-items: center; gap: 6px;
    font-size: .7rem; font-weight: 700; color: var(--muted);
}
.form-footer-item i { font-size: .72rem; }

/* ── RESPONSIVE ─────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .left-panel { width: 38%; padding: 48px 36px; }
    .left-title { font-size: 2rem; }
}
@media (max-width: 768px) {
    .page-wrap { flex-direction: column; }
    .left-panel { width: 100%; min-height: auto; padding: 48px 28px 40px; }
    .left-panel::before, .left-panel::after, .left-glow { display: none; }
    .left-title { font-size: 1.8rem; }
    .unit-types { display: none; }
    .back-link { margin-bottom: 28px; }
    .right-panel { padding: 32px 20px; }
    .form-card { padding: 28px 22px; }
    .field-row { grid-template-columns: 1fr; }
    .left-brand { position: static; margin-top: 32px; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="page-wrap">

  <!-- ══════════════════════════════════════════════════════════════ -->
  <!--  LEFT — VISUAL / BRANDING PANEL                              -->
  <!-- ══════════════════════════════════════════════════════════════ -->
  <div class="left-panel">
    <div class="left-glow"></div>

    <a href="fleet.php" class="back-link">
      <i class="fa fa-arrow-left"></i> Fleet Management
    </a>

    <div class="left-badge">
      <span class="left-badge-dot"></span>
      Dispatch Authorization
    </div>

    <h1 class="left-title">
      Deploy a<br>New <span>Unit</span>
    </h1>

    <p class="left-desc">
      Register an emergency vehicle into the SmartRescue dispatch network. Once authorized, the unit becomes available for real-time mission assignment.
    </p>

    <!-- Unit type cards -->
    <div class="unit-types">
      <div class="unit-type-item">
        <div class="uti-icon" style="background:rgba(59,130,246,.18)">
          <i class="fa fa-truck-medical" style="color:#60a5fa"></i>
        </div>
        <div>
          <div class="uti-label">Emergency Medical</div>
          <div class="uti-sub">Ambulance · Paramedic response</div>
        </div>
        <span class="uti-dot" style="background:#22c55e"></span>
      </div>
      <div class="unit-type-item">
        <div class="uti-icon" style="background:rgba(239,68,68,.18)">
          <i class="fa fa-fire-extinguisher" style="color:#fca5a5"></i>
        </div>
        <div>
          <div class="uti-label">Rapid Fire Response</div>
          <div class="uti-sub">Fire truck · Hazmat crew</div>
        </div>
        <span class="uti-dot" style="background:#f59e0b"></span>
      </div>
      <div class="unit-type-item">
        <div class="uti-icon" style="background:rgba(100,116,139,.18)">
          <i class="fa fa-shield-halved" style="color:#94a3b8"></i>
        </div>
        <div>
          <div class="uti-label">Tactical Police</div>
          <div class="uti-sub">Law enforcement · Patrol unit</div>
        </div>
        <span class="uti-dot" style="background:#94a3b8"></span>
      </div>
      <div class="unit-type-item">
        <div class="uti-icon" style="background:rgba(245,158,11,.18)">
          <i class="fa fa-car-burst" style="color:#fcd34d"></i>
        </div>
        <div>
          <div class="uti-label">Accident Response</div>
          <div class="uti-sub">Rescue · Recovery team</div>
        </div>
        <span class="uti-dot" style="background:#f59e0b"></span>
      </div>
    </div>

    <!-- Brand mark -->
    <div class="left-brand">
      <div class="left-brand-icon"><i class="fa fa-truck-medical"></i></div>
      <div class="left-brand-text">Smart<span>Rescue</span></div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════════ -->
  <!--  RIGHT — FORM PANEL                                          -->
  <!-- ══════════════════════════════════════════════════════════════ -->
  <div class="right-panel">
    <div class="form-card">

      <!-- Card header -->
      <div class="card-header-block">
        <div class="card-header-icon"><i class="fa fa-truck-medical"></i></div>
        <div class="card-header-text">
          <h1>Unit Registration</h1>
          <p>Complete the form below to authorize a new emergency vehicle for active dispatch.</p>
        </div>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa fa-circle-exclamation"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="unit-form" novalidate>

        <!-- ── SECTION 1: UNIT INFORMATION ──────────────────────── -->
        <div class="section-label"><i class="fa fa-tag"></i> Unit Information</div>

        <div class="field-row">
          <!-- Unit Call-Sign -->
          <div>
            <div class="field-label">Unit Call-Sign <span class="req">*</span></div>
            <div class="input-wrap">
              <i class="input-icon fa fa-radio"></i>
              <input
                type="text"
                name="unit_name"
                id="unit_name"
                class="form-input"
                placeholder="e.g. ALPHA-01"
                value="<?= htmlspecialchars($_POST['unit_name'] ?? '') ?>"
                required
                autocomplete="off"
                maxlength="60"
              >
            </div>
            <div class="field-hint">Unique identifier used in dispatch comms</div>
          </div>

          <!-- Registry Plate -->
          <div>
            <div class="field-label">Registry ID (Plate) <span class="req">*</span></div>
            <div class="input-wrap">
              <i class="input-icon fa fa-id-card"></i>
              <input
                type="text"
                name="plate_number"
                id="plate_number"
                class="form-input"
                placeholder="e.g. SL-101-ADM"
                value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>"
                required
                autocomplete="off"
                maxlength="20"
              >
            </div>
            <div class="field-hint">Official vehicle registration number</div>
          </div>
        </div>

        <!-- ── SECTION 2: ASSIGNMENT ─────────────────────────────── -->
        <div class="section-label"><i class="fa fa-users-gear"></i> Assignment</div>

        <!-- Specialization -->
        <div class="field">
          <div class="field-label">Unit Specialization <span class="req">*</span></div>
          <div class="input-wrap">
            <i class="input-icon fa fa-stethoscope"></i>
            <select name="unit_type" id="unit_type" class="form-select" required>
              <option value="medical"  <?= (($_POST['unit_type'] ?? '') === 'medical')  ? 'selected' : '' ?>>🚑 Emergency Medical</option>
              <option value="fire"     <?= (($_POST['unit_type'] ?? '') === 'fire')     ? 'selected' : '' ?>>🚒 Rapid Fire Response</option>
              <option value="police"   <?= (($_POST['unit_type'] ?? '') === 'police')   ? 'selected' : '' ?>>🚓 Tactical Police</option>
              <option value="accident" <?= (($_POST['unit_type'] ?? '') === 'accident') ? 'selected' : '' ?>>💥 Accident Response</option>
            </select>
            <i class="select-arrow fa fa-chevron-down"></i>
          </div>
          <div class="field-hint">Determines which emergency type this unit handles</div>
        </div>

        <!-- Assign Driver -->
        <div class="field">
          <div class="field-label">Assign Responder <span class="req">*</span></div>
          <div class="input-wrap">
            <i class="input-icon fa fa-user-shield"></i>
            <select name="driver_id" id="driver_id" class="form-select" required>
              <option value="">— Select Certified Driver —</option>
              <?php foreach ($drivers_arr as $d): ?>
              <option value="<?= $d['id'] ?>" <?= (($_POST['driver_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['fullname']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <i class="select-arrow fa fa-chevron-down"></i>
          </div>
          <div class="field-hint">
            <?= count($drivers_arr) ?> certified driver<?= count($drivers_arr) !== 1 ? 's' : '' ?> available
            <?php if (empty($drivers_arr)): ?>
            — <a href="add_responder.php" style="color:var(--accent);font-weight:700">Add a driver first</a>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── SUBMIT ─────────────────────────────────────────────── -->
        <button type="submit" class="btn-submit" id="submit-btn">
          <span>Authorize Unit</span>
          <div class="btn-submit-icon"><i class="fa fa-arrow-right"></i></div>
        </button>

        <a href="fleet.php" class="btn-cancel">
          <i class="fa fa-xmark" style="margin-right:5px;opacity:.5"></i> Cancel &amp; Go Back
        </a>

      </form>

      <!-- Trust footer -->
      <div class="form-footer">
        <div class="form-footer-item">
          <i class="fa fa-shield-halved" style="color:var(--green)"></i>
          Secure Dispatch
        </div>
        <div style="width:1px;height:14px;background:var(--border);flex-shrink:0"></div>
        <div class="form-footer-item">
          <i class="fa fa-satellite-dish" style="color:var(--accent)"></i>
          Live GPS Tracking
        </div>
        <div style="width:1px;height:14px;background:var(--border);flex-shrink:0"></div>
        <div class="form-footer-item">
          <i class="fa fa-clock" style="color:var(--amber)"></i>
          24/7 Network
        </div>
      </div>

    </div><!-- /form-card -->
  </div><!-- /right-panel -->

</div><!-- /page-wrap -->

<script>
// Live call-sign uppercase formatting
document.getElementById('unit_name').addEventListener('input', function() {
    const caret = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(caret, caret);
});
document.getElementById('plate_number').addEventListener('input', function() {
    const caret = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(caret, caret);
});

// Submit loading state
document.getElementById('unit-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('submit-btn');
    const hasErrors = !this.checkValidity();
    if (!hasErrors) {
        btn.innerHTML = `<i class="fa fa-circle-notch fa-spin"></i> <span>Authorizing…</span>`;
        btn.style.opacity = '.8';
        btn.style.pointerEvents = 'none';
    }
});

// Change icon color based on specialization
document.getElementById('unit_type').addEventListener('change', function() {
    const icon = this.previousElementSibling;
    const map = { medical: '#3b82f6', fire: '#ef4444', police: '#64748b', accident: '#f59e0b' };
    icon.style.color = map[this.value] || '#64748b';
});
</script>
</body>
</html>