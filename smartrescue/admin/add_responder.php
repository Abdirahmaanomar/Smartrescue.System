<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$errors  = [];
$success = '';
$old     = ['fullname' => '', 'phone' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = clean_input($_POST['fullname'], $conn);
    $phone    = clean_input($_POST['phone'],    $conn);
    $email    = clean_input($_POST['email'],    $conn);
    $password = $_POST['password'];
    $role     = 'driver';

    $old = compact('fullname', 'phone', 'email');

    if (!$fullname) $errors[] = 'Full name is required.';
    if (!$phone)    $errors[] = 'Phone number is required.';
    if (!$password || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        $check_res = mysqli_query($conn, "SELECT id, phone, email FROM users WHERE phone = '$phone' " . (!empty($email) ? "OR email = '$email'" : ""));
        if (mysqli_num_rows($check_res) > 0) {
            $existing = mysqli_fetch_assoc($check_res);
            if ($existing['phone'] === $phone) {
                $errors[] = 'This phone number is already registered.';
            } else {
                $errors[] = 'This email is already registered.';
            }
        } else {
            $hashed  = password_hash($password, PASSWORD_BCRYPT);
            $email_sql = !empty($email) ? "'$email'" : 'NULL';
            $query   = "INSERT INTO users (fullname, phone, email, password, role)
                        VALUES ('$fullname', '$phone', $email_sql, '$hashed', '$role')";
            if (mysqli_query($conn, $query)) {
                header("Location: team.php?msg=responder_added");
                exit();
            } else {
                $errors[] = 'Database error: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Responder | SmartRescue Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── RESET ──────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --sidebar-w:   268px;
    --dark:        #080f1e;
    --dark-panel:  #0c1528;
    --accent:      #3b82f6;
    --accent-dark: #1d4ed8;
    --accent-glow: rgba(59,130,246,.35);
    --green:       #22c55e;
    --amber:       #f59e0b;
    --red:         #ef4444;
    --purple:      #8b5cf6;
    --text:        #0f172a;
    --muted:       #64748b;
    --border:      #e2e8f0;
    --r-input:     12px;
    --r-card:      20px;
    --transition:  all .22s cubic-bezier(.4,0,.2,1);
}

body {
    font-family: 'Inter', sans-serif;
    background: #f0f4f9;
    min-height: 100vh;
    color: var(--text);
}

/* ── PAGE SHELL ─────────────────────────────────────────────────────── */
.page-shell {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    display: flex;
    align-items: stretch;
}

/* ── LEFT PANEL (dark hero) ─────────────────────────────────────────── */
.left-panel {
    width: 48%;
    background: linear-gradient(160deg, #0c1a3a 0%, #0d1f4b 40%, #111c3e 100%);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 40px 44px 40px;
    min-height: 100vh;
}

/* Decorative blobs */
.left-panel::before {
    content: '';
    position: absolute;
    width: 420px; height: 420px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(59,130,246,.18) 0%, transparent 70%);
    top: -120px; right: -140px;
    pointer-events: none;
}
.left-panel::after {
    content: '';
    position: absolute;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(139,92,246,.12) 0%, transparent 70%);
    bottom: 60px; left: -80px;
    pointer-events: none;
}

/* Back link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: rgba(255,255,255,.45);
    font-size: .78rem;
    font-weight: 600;
    text-decoration: none;
    letter-spacing: .4px;
    transition: color .2s;
    position: relative; z-index: 1;
    margin-bottom: 50px;
}
.back-link i { font-size: .7rem; }
.back-link:hover { color: rgba(255,255,255,.75); }

/* Dispatch badge */
.dispatch-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 50px;
    padding: 6px 16px;
    font-size: .68rem;
    font-weight: 700;
    color: rgba(255,255,255,.7);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 22px;
    position: relative; z-index: 1;
    width: fit-content;
}
.dispatch-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 0 3px rgba(34,197,94,.25);
    animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
    0%,100% { box-shadow: 0 0 0 3px rgba(34,197,94,.25); }
    50%      { box-shadow: 0 0 0 7px rgba(34,197,94,.06); }
}

/* Hero headline */
.hero-title {
    font-size: clamp(2rem, 3.5vw, 2.8rem);
    font-weight: 900;
    color: #fff;
    line-height: 1.12;
    letter-spacing: -.5px;
    margin-bottom: 18px;
    position: relative; z-index: 1;
}
.hero-title .accent-word {
    background: linear-gradient(90deg, #60a5fa, #a78bfa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-desc {
    font-size: .88rem;
    font-weight: 400;
    color: rgba(255,255,255,.5);
    line-height: 1.7;
    max-width: 360px;
    margin-bottom: 44px;
    position: relative; z-index: 1;
}

/* Responder role cards */
.role-cards {
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative; z-index: 1;
    flex: 1;
}

.role-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(255,255,255,.055);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 14px;
    padding: 14px 18px;
    cursor: default;
    transition: background .22s, border-color .22s, transform .22s;
    position: relative;
    overflow: hidden;
}
.role-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.02));
    opacity: 0;
    transition: opacity .22s;
}
.role-card:hover {
    background: rgba(255,255,255,.085);
    border-color: rgba(255,255,255,.14);
    transform: translateX(4px);
}
.role-card:hover::before { opacity: 1; }

.role-icon-box {
    width: 42px; height: 42px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.role-card-info { flex: 1; }
.role-card-name {
    font-size: .88rem;
    font-weight: 700;
    color: rgba(255,255,255,.88);
    margin-bottom: 2px;
}
.role-card-desc {
    font-size: .7rem;
    color: rgba(255,255,255,.38);
    font-weight: 500;
}
.role-card-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Bottom brand */
.left-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 40px;
    position: relative; z-index: 1;
}
.left-brand-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .75rem;
    box-shadow: 0 4px 12px rgba(59,130,246,.4);
}
.left-brand-text {
    font-size: .82rem;
    font-weight: 800;
    color: rgba(255,255,255,.6);
    letter-spacing: -.2px;
}
.left-brand-text span { color: #60a5fa; }

/* ── RIGHT PANEL (form) ─────────────────────────────────────────────── */
.right-panel {
    flex: 1;
    background: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 52px 52px 52px 48px;
    overflow-y: auto;
}

/* Form header */
.form-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 32px;
}
.form-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, rgba(59,130,246,.1), rgba(139,92,246,.1));
    border: 1.5px solid rgba(59,130,246,.18);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    color: var(--accent);
    flex-shrink: 0;
}
.form-header-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 4px;
    letter-spacing: -.3px;
}
.form-header-sub {
    font-size: .82rem;
    color: var(--muted);
    font-weight: 400;
    line-height: 1.5;
}

/* ── SECTION LABEL ──────────────────────────────────────────────────── */
.section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: .62rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--accent);
    margin-bottom: 16px;
}
.section-label i { font-size: .65rem; }
.section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── FIELD ──────────────────────────────────────────────────────────── */
.field-group { margin-bottom: 20px; }
.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.field-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: .71rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--muted);
    margin-bottom: 7px;
}
.req { color: var(--red); }

.input-wrap { position: relative; }
.input-icon {
    position: absolute;
    left: 13px; top: 50%;
    transform: translateY(-50%);
    font-size: .8rem;
    color: #b0bcc9;
    pointer-events: none;
    transition: color .2s;
}
.input-wrap:focus-within .input-icon { color: var(--accent); }

.form-input {
    width: 100%;
    padding: 11px 13px 11px 38px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-input);
    background: #f8fafd;
    font-family: 'Inter', sans-serif;
    font-size: .875rem;
    font-weight: 500;
    color: var(--text);
    outline: none;
    transition: var(--transition);
}
.form-input::placeholder { color: #b8c6d9; font-weight: 400; }
.form-input:hover { border-color: #c8d8ee; background: #f4f8fd; }
.form-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: #fff;
}

/* Password toggle */
.pw-toggle {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: #b0bcc9; font-size: .8rem;
    cursor: pointer; padding: 4px;
    border-radius: 6px;
    transition: color .2s, background .2s;
    display: flex; align-items: center; justify-content: center;
}
.pw-toggle:hover { color: var(--accent); background: rgba(59,130,246,.08); }

/* Field hint */
.field-hint {
    font-size: .67rem;
    font-weight: 500;
    color: #b0bcc9;
    margin-top: 5px;
}

/* Strength meter */
.strength-bar-wrap { display: flex; gap: 4px; margin-top: 7px; }
.strength-seg {
    flex: 1; height: 3px; border-radius: 3px;
    background: var(--border);
    transition: background .3s;
}

/* ── ERROR BLOCK ────────────────────────────────────────────────────── */
.alert-block {
    background: rgba(239,68,68,.06);
    border: 1px solid rgba(239,68,68,.18);
    border-left: 4px solid var(--red);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 24px;
}
.alert-block-title {
    display: flex; align-items: center; gap: 8px;
    font-size: .82rem; font-weight: 700;
    color: #b91c1c; margin-bottom: 6px;
}
.alert-block ul {
    padding-left: 18px;
    font-size: .8rem; font-weight: 500;
    color: #dc2626; line-height: 1.7;
}

/* ── SUBMIT BUTTON ──────────────────────────────────────────────────── */
.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, #2563eb, var(--accent-dark));
    color: #fff;
    font-family: 'Inter', sans-serif;
    font-size: .92rem; font-weight: 700;
    border: none;
    border-radius: 13px;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(59,130,246,.4);
    transition: transform .22s, box-shadow .22s;
    margin-top: 24px;
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 32px rgba(59,130,246,.5);
}
.btn-submit:active { transform: scale(.99); }

.btn-arrow {
    width: 28px; height: 28px;
    background: rgba(255,255,255,.18);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem;
    flex-shrink: 0;
    transition: transform .3s;
}
.btn-submit:hover .btn-arrow { transform: translateX(4px); }

/* Cancel link */
.cancel-link {
    display: block;
    text-align: center;
    margin-top: 14px;
    font-size: .8rem; font-weight: 600;
    color: #b0bcc9;
    text-decoration: none;
    transition: color .2s;
    padding: 6px;
}
.cancel-link:hover { color: var(--muted); }

/* ── FORM FOOTER BADGES ─────────────────────────────────────────────── */
.form-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-top: 28px;
    padding-top: 22px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.fbadge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .67rem;
    font-weight: 600;
    color: #b0bcc9;
}

/* ── RESPONSIVE ─────────────────────────────────────────────────────── */
@media(max-width: 1100px) {
    .left-panel { width: 44%; padding: 36px 32px; }
    .right-panel { padding: 44px 36px; }
}
@media(max-width: 860px) {
    .page-shell { flex-direction: column; }
    .left-panel { width: 100%; min-height: auto; padding: 36px 28px 32px; }
    .left-panel::before, .left-panel::after { display: none; }
    .hero-title { font-size: 1.9rem; }
    .role-cards { flex-direction: row; flex-wrap: wrap; }
    .role-card { width: calc(50% - 5px); }
    .right-panel { padding: 36px 28px; }
}
@media(max-width: 560px) {
    .field-row { grid-template-columns: 1fr; }
    .role-card { width: 100%; }
    .page-shell { margin-left: 0; }
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="page-shell">

    <!-- ═══════════════════════════════════════════════════════
         LEFT PANEL — hero + role cards
    ════════════════════════════════════════════════════════ -->
    <div class="left-panel">

        <!-- Back -->
        <a href="team.php" class="back-link">
            <i class="fa fa-arrow-left"></i> Responder
        </a>

        <!-- Badge -->
        <div class="dispatch-badge">
            <span class="dispatch-dot"></span>
            Team Expansion
        </div>

        <!-- Hero -->
        <div class="hero-title">
            Register a<br><span class="accent-word">New Responder</span>
        </div>
        <div class="hero-desc">
            Add a certified emergency responder to the SmartRescue network. Once registered, they can be assigned to active units and dispatched in real time.
        </div>

        <!-- Responder Role Cards -->
        <div class="role-cards">

            <div class="role-card">
                <div class="role-icon-box" style="background:rgba(59,130,246,.15);">
                    <i class="fa fa-truck-medical" style="color:#60a5fa;"></i>
                </div>
                <div class="role-card-info">
                    <div class="role-card-name">Emergency Medical</div>
                    <div class="role-card-desc">Ambulance · Paramedic response</div>
                </div>
                <span class="role-card-dot" style="background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);"></span>
            </div>

            <div class="role-card">
                <div class="role-icon-box" style="background:rgba(239,68,68,.12);">
                    <i class="fa fa-fire-extinguisher" style="color:#f87171;"></i>
                </div>
                <div class="role-card-info">
                    <div class="role-card-name">Rapid Fire Response</div>
                    <div class="role-card-desc">Fire truck · Hazmat crew</div>
                </div>
                <span class="role-card-dot" style="background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.2);"></span>
            </div>

            <div class="role-card">
                <div class="role-icon-box" style="background:rgba(139,92,246,.12);">
                    <i class="fa fa-shield-halved" style="color:#a78bfa;"></i>
                </div>
                <div class="role-card-info">
                    <div class="role-card-name">Tactical Police</div>
                    <div class="role-card-desc">Law enforcement · Patrol unit</div>
                </div>
                <span class="role-card-dot" style="background:#94a3b8;box-shadow:0 0 0 3px rgba(148,163,184,.2);"></span>
            </div>

            <div class="role-card">
                <div class="role-icon-box" style="background:rgba(245,158,11,.12);">
                    <i class="fa fa-life-ring" style="color:#fbbf24;"></i>
                </div>
                <div class="role-card-info">
                    <div class="role-card-name">Accident Response</div>
                    <div class="role-card-desc">Rescue · Recovery team</div>
                </div>
                <span class="role-card-dot" style="background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.2);"></span>
            </div>

        </div>

        <!-- Brand -->
        <div class="left-brand">
            <div class="left-brand-icon"><i class="fa fa-truck-medical"></i></div>
            <div class="left-brand-text">Smart<span>Rescue</span></div>
        </div>

    </div><!-- /left-panel -->

    <!-- ═══════════════════════════════════════════════════════
         RIGHT PANEL — registration form
    ════════════════════════════════════════════════════════ -->
    <div class="right-panel">

        <!-- Form header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fa fa-user-shield"></i>
            </div>
            <div>
                <div class="form-header-title">Responder Registration</div>
                <div class="form-header-sub">Complete the form below to authorize a new emergency responder for active deployment.</div>
            </div>
        </div>

        <!-- Error block -->
        <?php if (!empty($errors)): ?>
        <div class="alert-block">
            <div class="alert-block-title">
                <i class="fa fa-circle-exclamation"></i> Please fix the following:
            </div>
            <ul>
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" id="responder-form" novalidate>

            <!-- PERSONAL INFORMATION -->
            <div class="section-label"><i class="fa fa-user"></i> Personal Information</div>

            <div class="field-group">
                <div class="field-label">Full Name <span class="req">*</span></div>
                <div class="input-wrap">
                    <i class="input-icon fa fa-user"></i>
                    <input
                        type="text"
                        name="fullname"
                        id="fullname"
                        class="form-input"
                        placeholder="e.g. Ahmed Hassan"
                        value="<?= htmlspecialchars($old['fullname']) ?>"
                        required autocomplete="name" maxlength="100"
                    >
                </div>
            </div>

            <div class="field-group field-row">
                <div>
                    <div class="field-label">Phone Number <span class="req">*</span></div>
                    <div class="input-wrap">
                        <i class="input-icon fa fa-phone"></i>
                        <input
                            type="tel"
                            name="phone"
                            id="phone"
                            class="form-input"
                            placeholder="e.g. 61XXXXXXX"
                            value="<?= htmlspecialchars($old['phone']) ?>"
                            required autocomplete="tel" maxlength="20"
                        >
                    </div>
                    <div class="field-hint">Used as login identifier</div>
                </div>
                <div>
                    <div class="field-label">Email Address</div>
                    <div class="input-wrap">
                        <i class="input-icon fa fa-envelope"></i>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            class="form-input"
                            placeholder="john@example.com"
                            value="<?= htmlspecialchars($old['email']) ?>"
                            autocomplete="email" maxlength="100"
                        >
                    </div>
                    <div class="field-hint">Optional — for notifications</div>
                </div>
            </div>

            <!-- ACCOUNT SECURITY -->
            <div class="section-label" style="margin-top:8px;"><i class="fa fa-shield-halved"></i> Account Security</div>

            <div class="field-group">
                <div class="field-label">Login Password <span class="req">*</span></div>
                <div class="input-wrap">
                    <i class="input-icon fa fa-lock"></i>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-input"
                        placeholder="Minimum 6 characters"
                        required autocomplete="new-password"
                        oninput="updateStrength(this.value)"
                    >
                    <button type="button" class="pw-toggle" id="pw-toggle" onclick="togglePw()" title="Toggle visibility">
                        <i class="fa fa-eye" id="pw-eye"></i>
                    </button>
                </div>
                <div class="strength-bar-wrap" id="strength-bar">
                    <div class="strength-seg" id="seg1"></div>
                    <div class="strength-seg" id="seg2"></div>
                    <div class="strength-seg" id="seg3"></div>
                    <div class="strength-seg" id="seg4"></div>
                </div>
                <div class="field-hint" id="strength-label">Enter a password to see strength</div>
            </div>

            <!-- SUBMIT -->
            <button type="submit" class="btn-submit" id="submit-btn">
                <i class="fa fa-user-plus"></i>
                <span id="btn-text">Register Responder</span>
                <div class="btn-arrow"><i class="fa fa-arrow-right"></i></div>
            </button>

            <a href="team.php" class="cancel-link">
                <i class="fa fa-xmark" style="margin-right:5px;opacity:.5;"></i>Cancel &amp; Go Back
            </a>

        </form>

        <!-- Footer badges -->
        <div class="form-footer">
            <div class="fbadge"><i class="fa fa-shield-halved" style="color:#22c55e;"></i> Encrypted Credentials</div>
            <div class="fbadge"><i class="fa fa-user-check" style="color:#3b82f6;"></i> Role: Responder</div>
            <div class="fbadge"><i class="fa fa-bolt" style="color:#f59e0b;"></i> Instant Activation</div>
        </div>

    </div><!-- /right-panel -->

</div><!-- /page-shell -->

<script>
// ── PASSWORD VISIBILITY TOGGLE ────────────────────────────────────────
function togglePw() {
    const input = document.getElementById('password');
    const eye   = document.getElementById('pw-eye');
    if (input.type === 'password') {
        input.type = 'text';
        eye.className = 'fa fa-eye-slash';
    } else {
        input.type = 'password';
        eye.className = 'fa fa-eye';
    }
}

// ── PASSWORD STRENGTH METER ───────────────────────────────────────────
function updateStrength(val) {
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
    const labels = ['Weak', 'Fair', 'Good', 'Strong'];
    const segs = ['seg1','seg2','seg3','seg4'].map(id => document.getElementById(id));
    segs.forEach((s, i) => {
        s.style.background = (i < score && val.length > 0) ? colors[score - 1] : '#e2e8f0';
    });
    const lbl = document.getElementById('strength-label');
    lbl.textContent = val.length === 0
        ? 'Enter a password to see strength'
        : `Strength: ${labels[score - 1] || 'Weak'}`;
    lbl.style.color = val.length > 0 ? colors[score - 1] : '#b0bcc9';
}

// ── SUBMIT LOADING STATE ──────────────────────────────────────────────
document.getElementById('responder-form').addEventListener('submit', function() {
    const name  = document.getElementById('fullname').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const pw    = document.getElementById('password').value;
    if (!name || !phone || pw.length < 6) return;

    const btn = document.getElementById('submit-btn');
    const txt = document.getElementById('btn-text');
    btn.style.opacity = '.8';
    btn.style.pointerEvents = 'none';
    btn.querySelector('.fa-user-plus').className = 'fa fa-circle-notch fa-spin';
    txt.textContent = 'Registering…';
});

// ── NAME AUTO-CAPITALIZE ──────────────────────────────────────────────
document.getElementById('fullname').addEventListener('input', function() {
    const caret = this.selectionStart;
    this.value = this.value.replace(/\b\w/g, c => c.toUpperCase());
    this.setSelectionRange(caret, caret);
});
</script>
</body>
</html>
