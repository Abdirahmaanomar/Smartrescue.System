<?php
// 1. Bilow Session-ka
session_start();

// 2. Isku xirka Database-ka iyo Functions-ka
require_once '../config/db.php';
require_once '../includes/functions.php';

$message = "";
if (isset($_GET['error']) && $_GET['error'] === 'driver_app_only') {
    $message = "<div class='alert alert-danger shadow-sm mt-3 p-3'>Drivers must use the SmartRescue Mobile App. Web login is disabled for driver accounts.</div>";
}

// 3. Haddii uu qofku horay u soo galay, toos ugu dir dashboard-kiisa
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: ../" . strtolower($_SESSION['role']) . "/index.php");
    exit();
}

// 4. Marka la riixo badanka Login-ka
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_btn'])) {
    
    $identifier = "";
    if (isset($_POST['phone_or_email'])) {
        $identifier = clean_input($_POST['phone_or_email'], $conn);
    } elseif (isset($_POST['phone'])) {
        $identifier = clean_input($_POST['phone'], $conn);
    } elseif (isset($_POST['email'])) {
        $identifier = clean_input($_POST['email'], $conn);
    }
    $password = $_POST['password'];

    // Ka raadi user-ka database-ka
    $sql = "SELECT * FROM users WHERE (phone = '$identifier' OR email = '$identifier') LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Hubi Password-ka (Isticmaal password_verify)
        if (password_verify($password, $user['password'])) {
            // Generate a fresh session ID to prevent fixation
            session_regenerate_id(true);

                // Keydi xogta Session-ka
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['fullname']  = $user['fullname'];
                $_SESSION['role']      = strtolower($user['role']); // Hubi inuu yahay xuruuf yaryar
                $_SESSION['profile_image'] = $user['profile_image'] ?? '';

                // Store the Session ID for Single-Login Enforcement
                $sid = session_id();
                mysqli_query($conn, "UPDATE users SET last_session_id = '$sid' WHERE id = '{$user['id']}'");

                // Log Admin Login
                if (strtolower($user['role']) === 'admin') {
                    log_activity($conn, $user['id'], 'Login', 'Administrator logged into the secure dashboard.', 'info');
                }

                if (isset($_POST['flutter'])) {
                    header('Content-Type: application/json');
                    $user['status'] = 'success';
                    $user['session_id'] = $sid;
                    echo json_encode($user);
                    exit();
                }

                // U dir Dashboard-ka saxda ah
                $target = "../" . $_SESSION['role'] . "/index.php";
                
                header("Location: $target");
                exit();

        } else {
            if (isset($_POST['flutter'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password entered!']);
                exit();
            }
            $message = "<div class='alert alert-danger shadow-sm mt-3 p-3'>Incorrect password entered!</div>";
        }
    } else {
        if (isset($_POST['flutter'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'This phone number or email is not registered!']);
            exit();
        }
        $message = "<div class='alert alert-danger shadow-sm mt-3 p-3'>This phone number or email is not registered!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartRescue - Web Portal Login</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap and Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563eb; --secondary: #1e40af; --accent: #e0f2fe; --light: #f8fafc; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--light); color: #334155; margin: 0; overflow-x: hidden; }
        .auth-wrapper { display: flex; min-height: 100vh; }
        
        /* Left Hero Banner Side */
        .auth-image-side { 
            flex: 1; 
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.98), rgba(219, 234, 254, 0.95)), url('../assets/images/mogadishu.jpg') center/cover no-repeat; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: flex-start; 
            color: #0f172a; 
            padding: 80px; 
            position: relative; 
        }
        .auth-brand { position: absolute; top: 40px; left: 40px; font-size: 1.8rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; transition: all 0.3s ease; }
        .auth-brand:hover { opacity: 0.85; }
        
        /* Medical Bag Logo Badge (Boorso dhaxda plus kaga taallo) */
        .brand-icon-box {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 24px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
            margin-right: 12px;
        }

        .promo-content { max-width: 520px; animation: fadeInUp 1s ease; text-align: left; }
        .promo-content h1 { font-weight: 800; font-size: 3.6rem; margin-bottom: 20px; line-height: 1.1; letter-spacing: -2px; color: #0f172a; }
        .promo-content p { font-size: 1.25rem; opacity: 0.9; line-height: 1.7; font-weight: 400; color: #334155; }
        
        /* Right Form Side */
        .auth-form-side { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px; background: #ffffff; position: relative; }
        .form-container { width: 100%; max-width: 440px; animation: fadeInRight 0.8s ease; }
        .form-header { margin-bottom: 30px; }
        .form-header h2 { font-weight: 800; color: #0f172a; font-size: 2.3rem; margin-bottom: 8px; letter-spacing: -0.5px; }
        .form-header p { color: #64748b; font-size: 1rem; }
        
        .form-floating > .form-control { border-radius: 14px; border: 1.5px solid #cbd5e1; padding: 1rem 1.2rem; height: calc(3.5rem + 10px); font-size: 1rem; transition: all 0.3s ease; box-shadow: none !important; background-color: #f8fafc; font-weight: 600; color: #0f172a; }
        .form-floating > .form-control:focus { border-color: var(--primary); background-color: #ffffff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12) !important; }
        .form-floating > label { padding: 1.1rem 1.2rem; color: #64748b; font-weight: 500; }
        
        .btn-custom { 
            background: linear-gradient(135deg, #2563eb, #1e40af); 
            color: white; 
            border: none; 
            border-radius: 16px; 
            padding: 16px; 
            font-weight: 700; 
            width: 100%; 
            font-size: 1.05rem; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
            box-shadow: 0 8px 22px rgba(37, 99, 235, 0.35); 
        }
        .btn-custom:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(37, 99, 235, 0.5); color: white; background: linear-gradient(135deg, #1d4ed8, #1e3a8a); }
        
        /* Checkbox & Forgot Password styling */
        .form-check-input {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 1.5px solid #cbd5e1;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .forgot-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            font-size: 0.92rem;
            transition: color 0.2s ease;
        }
        .forgot-link:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        .register-link { text-align: center; margin-top: 28px; font-weight: 500; color: #64748b; }
        .register-link a { color: var(--primary); font-weight: 700; text-decoration: none; transition: 0.3s; margin-left: 5px; }
        .register-link a:hover { color: #1e40af; text-decoration: underline; }
        .back-btn-mobile { display: none; }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
        
        @media (max-width: 991px) {
            .auth-image-side { display: none; }
            .auth-form-side { padding: 30px 20px; background: var(--light); }
            .form-container { background: #fff; padding: 40px 30px; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); max-width: 500px; margin: auto; }
            .back-btn-mobile { display: inline-flex; align-items: center; color: var(--secondary); font-weight: 600; text-decoration: none; margin-bottom: 30px; transition: 0.3s; }
            .back-btn-mobile:hover { color: var(--primary); }
        }
        
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }
        /* Password toggle button */
        .pwd-wrapper { position: relative; }
        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #94a3b8;
            font-size: 1rem;
            z-index: 10;
            transition: color 0.2s ease;
            line-height: 1;
        }
        .pwd-toggle:hover { color: var(--primary); }
        .pwd-wrapper .form-floating .form-control { padding-right: 3rem; }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <!-- Left Promotional Side -->
    <div class="auth-image-side">
        <a href="../index.php" class="auth-brand">
            <div class="brand-icon-box">
                <i class="fa-solid fa-suitcase-medical"></i> <!-- Boorso dhexda Plus kaga taallo -->
            </div>
            <span style="font-weight: 800; font-size: 1.6rem; letter-spacing: -0.5px; color: #0f172a;">Smart<span style="color: var(--primary);">Rescue</span></span>
        </a>
        <div class="promo-content">
            <h1>Welcome Back<br>to SmartRescue</h1>
            <p>Log into your account to access the system. Your seamless connection to the Mogadishu emergency network is ready.</p>
        </div>
    </div>
    
    <!-- Right Form Side -->
    <div class="auth-form-side">
        <div class="form-container">
            <a href="../index.php" class="back-btn-mobile"><i class="fa-solid fa-arrow-left me-2"></i> Back to Home</a>
            
            <div class="form-header">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 22px;">
                    <!-- Medical Kit Icon Badge (Boorso dhexda Plus kaga taallo) -->
                    <div class="brand-icon-box" style="width: 56px; height: 56px; font-size: 26px; border-radius: 18px;">
                        <i class="fa-solid fa-suitcase-medical"></i>
                    </div>
                    <div>
                        <div style="font-weight: 900; font-size: 1.4rem; color: #0f172a; letter-spacing: -0.4px; line-height: 1.2;">Smart<span style="color: #2563eb;">Rescue</span></div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Emergency Response System</div>
                    </div>
                </div>
                <h2>Login to Account</h2>
                <p>Welcome to SmartRescue! Sign in to continue.</p>
            </div>
            
            <?php echo $message; ?>
            
            <form action="login.php" method="POST">
                
                <!-- Email or Phone -->
                <div class="form-floating mb-3">
                    <input type="text" name="phone_or_email" class="form-control" id="phone_or_email" placeholder="Phone or Email" required>
                    <label for="phone_or_email"><i class="fa-solid fa-user me-2 text-muted"></i> Email or Phone Number</label>
                </div>
                
                <!-- Password -->
                <div class="pwd-wrapper mb-3">
                    <div class="form-floating">
                        <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                        <label for="password"><i class="fa-solid fa-lock me-2 text-muted"></i> Password</label>
                    </div>
                    <button type="button" class="pwd-toggle" onclick="togglePassword('password', this)" tabindex="-1" aria-label="Show/Hide password">
                        <i class="fa-solid fa-eye-slash" id="pwd-icon-password"></i>
                    </button>
                </div>
                
                <!-- Remember Me + Forgot Password Row -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe">
                        <label class="form-check-label text-secondary fw-semibold" for="rememberMe" style="font-size: 0.9rem; cursor: pointer;">
                            Remember me
                        </label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="login_btn" class="btn-custom">Login <i class="fa-solid fa-arrow-right ms-2"></i></button>
                
                <div class="register-link">
                    Don't have an account? <a href="register.php">Register Here</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon  = document.getElementById('pwd-icon-' + fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.style.color = 'var(--primary)';
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.style.color = '';
    }
}
</script>
</body>
</html>