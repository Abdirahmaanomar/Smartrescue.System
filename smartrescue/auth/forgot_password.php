<?php
// ─── Forgot Password API & Web Page ──────────────────────────────────────────
session_start();
require_once '../config/db.php';

// Sanitize helper
function clean($value, $conn) {
    return mysqli_real_escape_string($conn, trim($value));
}

// ── Handle JSON API Request (from Flutter App) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && ($_POST['action'] === 'verify' || $_POST['action'] === 'reset'))) {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-Session-ID");

    $data   = $_POST;
    $action = clean($data['action'], $conn);

    if ($action === 'verify') {
        $identifier = isset($data['identifier']) ? clean($data['identifier'], $conn) : '';
        if (empty($identifier)) {
            echo json_encode(['status' => 'error', 'message' => 'Email or phone number is required.']);
            exit();
        }

        $sql = "SELECT id, fullname, email, phone FROM users WHERE email = '$identifier' OR phone = '$identifier' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'No account found with that email or phone number.']);
            exit();
        }

        $user = mysqli_fetch_assoc($result);
        echo json_encode(['status' => 'success', 'message' => 'Account found! Please set your new password.', 'user_id' => $user['id']]);
        exit();
    }

    if ($action === 'reset') {
        $identifier   = isset($data['identifier']) ? clean($data['identifier'], $conn) : '';
        $new_password = isset($data['new_password']) ? trim($data['new_password']) : '';

        if (empty($identifier) || empty($new_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Identifier and new password are required.']);
            exit();
        }
        if (strlen($new_password) < 6) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
            exit();
        }

        $check = "SELECT id FROM users WHERE email = '$identifier' OR phone = '$identifier' LIMIT 1";
        $checkResult = mysqli_query($conn, $check);
        if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Account not found.']);
            exit();
        }

        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $escaped_hash = mysqli_real_escape_string($conn, $hashed);
        $update = "UPDATE users SET password = '$escaped_hash' WHERE email = '$identifier' OR phone = '$identifier'";
        if (mysqli_query($conn, $update)) {
            echo json_encode(['status' => 'success', 'message' => 'Password has been reset successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password.']);
        }
        exit();
    }
}

// ── Handle Web Form Submission (from Browser) ─────────────────────────────────
$message = "";
$step = 1;
$identifier_val = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['web_verify_btn'])) {
    $identifier_val = clean($_POST['identifier'], $conn);
    if (empty($identifier_val)) {
        $message = "<div class='alert alert-danger shadow-sm mb-3'>Please enter your email or phone number.</div>";
    } else {
        $sql = "SELECT id, fullname FROM users WHERE email = '$identifier_val' OR phone = '$identifier_val' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if (mysqli_num_rows($result) > 0) {
            $step = 2;
            $message = "<div class='alert alert-success shadow-sm mb-3'>Account verified! Set your new password below.</div>";
        } else {
            $message = "<div class='alert alert-danger shadow-sm mb-3'>No account found with that email or phone number.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['web_reset_btn'])) {
    $identifier_val = clean($_POST['identifier'], $conn);
    $new_pass     = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($new_pass) || empty($confirm_pass)) {
        $step = 2;
        $message = "<div class='alert alert-danger shadow-sm mb-3'>Please fill in all password fields.</div>";
    } elseif ($new_pass !== $confirm_pass) {
        $step = 2;
        $message = "<div class='alert alert-danger shadow-sm mb-3'>Passwords do not match.</div>";
    } elseif (strlen($new_pass) < 6) {
        $step = 2;
        $message = "<div class='alert alert-danger shadow-sm mb-3'>Password must be at least 6 characters.</div>";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $escaped_hash = mysqli_real_escape_string($conn, $hashed);
        $update = "UPDATE users SET password = '$escaped_hash' WHERE email = '$identifier_val' OR phone = '$identifier_val'";
        if (mysqli_query($conn, $update)) {
            $step = 3;
            $message = "<div class='alert alert-success shadow-sm mb-3'>Your password has been reset successfully! <a href='login.php' class='fw-bold text-decoration-none'>Click here to login</a>.</div>";
        } else {
            $step = 2;
            $message = "<div class='alert alert-danger shadow-sm mb-3'>Database error. Please try again.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartRescue - Reset Password</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card-box { width: 100%; max-width: 440px; background: #ffffff; border-radius: 24px; padding: 36px; box-shadow: 0 20px 40px rgba(30, 58, 138, 0.08); border: 1px solid #e2e8f0; }
        .logo-box { width: 64px; height: 64px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border-radius: 18px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 28px; margin: 0 auto 20px; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3); }
        .btn-primary-custom { background: linear-gradient(135deg, #2563eb, #1e40af); border: none; border-radius: 12px; padding: 14px; font-weight: 700; color: #fff; width: 100%; font-size: 1rem; transition: all 0.3s; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25); }
        .btn-primary-custom:hover { background: linear-gradient(135deg, #1d4ed8, #1e3a8a); transform: translateY(-2px); color: #fff; }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 1.5px solid #cbd5e1; font-weight: 500; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
    </style>
</head>
<body>

<div class="card-box">
    <div class="logo-box">
        <i class="fa-solid fa-suitcase-medical"></i>
    </div>
    
    <h3 class="text-center fw-extrabold mb-1" style="color: #0f172a; font-weight: 800;">Reset Password</h3>
    <p class="text-center text-secondary mb-4" style="font-size: 0.92rem;">
        <?php if ($step === 1): ?>
            Enter your email or phone number to verify your account.
        <?php elseif ($step === 2): ?>
            Enter a strong new password for your account.
        <?php else: ?>
            Password reset complete!
        <?php endif; ?>
    </p>

    <?php echo $message; ?>

    <?php if ($step === 1): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary" style="font-size: 0.85rem;">Email or Phone Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="identifier" class="form-control border-start-0" placeholder="e.g. 61XXXXXXX or email@example.com" required>
                </div>
            </div>
            <button type="submit" name="web_verify_btn" class="btn-primary-custom mt-2">Verify Account <i class="fa-solid fa-arrow-right ms-2"></i></button>
        </form>
    <?php elseif ($step === 2): ?>
        <form method="POST">
            <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($identifier_val); ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold text-secondary" style="font-size: 0.85rem;">New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="new_password" class="form-control border-start-0" placeholder="Minimum 6 characters" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold text-secondary" style="font-size: 0.85rem;">Confirm New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="confirm_password" class="form-control border-start-0" placeholder="Re-enter new password" required>
                </div>
            </div>
            <button type="submit" name="web_reset_btn" class="btn-primary-custom">Reset Password <i class="fa-solid fa-check ms-2"></i></button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="login.php" class="text-decoration-none fw-bold text-primary" style="font-size: 0.9rem;"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
    </div>
</div>

</body>
</html>
