<?php
// 0. Bilow Session-ka si loo kaydiyo xogta qofka
session_start();

// 1. Isku xirka database-ka iyo functions-ka
require_once '../config/db.php';
require_once '../includes/functions.php';

$message = "";

// 2. Hubi haddii form-ka la soo riixay
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Nadiifinta xogta (Security)
    $fullname = clean_input($_POST['fullname'], $conn);
    $phone    = clean_input($_POST['phone'], $conn);
    $email_val = clean_input($_POST['email'], $conn);
    $email_sql = "'$email_val'";
    $birth_date = !empty($_POST['birth_date']) ? "'".clean_input($_POST['birth_date'], $conn)."'" : 'NULL';
    $gender   = clean_input($_POST['gender'], $conn);
    $role     = isset($_POST['role']) ? strtolower(clean_input($_POST['role'], $conn)) : 'user';
    $password = $_POST['password'];

    // 3. Hubi haddii nambarkaas ama email-kaas horay loo diwaangeliyey
    $check_user = "SELECT id, phone, email FROM users WHERE phone = '$phone' " . (!empty($email_val) ? "OR email = '$email_val'" : "");
    $result = mysqli_query($conn, $check_user);

    if (mysqli_num_rows($result) > 0) {
        $existing = mysqli_fetch_assoc($result);
        $error_msg = "Registration failed: ";
        if ($existing['phone'] === $phone) {
            $error_msg = "This phone number is already registered!";
        } else {
            $error_msg = "This email is already registered!";
        }

        if (isset($_POST['flutter'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error_msg]);
            exit();
        }
        $message = "<div class='alert alert-danger shadow-sm mt-3 p-3'>$error_msg</div>";
    } else {
        // 4. Password-ka oo si ammaan ah loo qariyo (Bcrypt)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // 5. Kaydi isticmaalaha cusub
        $query = "INSERT INTO users (fullname, phone, email, password, role, birth_date, gender) 
                  VALUES ('$fullname', '$phone', $email_sql, '$hashed_password', '$role', $birth_date, '$gender')";

        if (mysqli_query($conn, $query)) {
            // Hel ID-ga cusub ee hadda la diwaangeliyey ee MySQL
            $new_user_id = mysqli_insert_id($conn);
            
            // Si toos ah u gal (Auto Login)
            $_SESSION['user_id']  = $new_user_id;
            $_SESSION['fullname'] = $fullname;
            $_SESSION['role']     = $role;
            
            if (isset($_POST['flutter'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'id' => $new_user_id, 'role' => $role]);
                exit();
            }
            
            // U diyaar garoow in loo weeciyo dashboard-ka saxda ah (Sida: ../user/index.php)
            $target = "../" . $role . "/index.php";
            header("Location: $target");
            exit();
        } else {
            if (isset($_POST['flutter'])) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
                exit();
            }
            $message = "<div class='alert alert-danger shadow-sm mt-3 p-3'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartRescue - Registration</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap and Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #0d6efd; --secondary: #0a58ca; --accent: #e0f2fe; --light: #ffffff; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--light); color: #334155; margin: 0; overflow-x: hidden; }
        .auth-wrapper { display: flex; min-height: 100vh; }
        .auth-image-side { flex: 1; background: linear-gradient(135deg, rgba(240, 248, 255, 0.98), rgba(224, 242, 254, 0.95)), url('../assets/images/mogadishu.jpg') center/cover no-repeat; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; color: #0f172a; padding: 80px; position: relative; }
        .auth-brand { position: absolute; top: 40px; left: 40px; font-size: 1.8rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; transition: all 0.3s ease; }
        .auth-brand:hover { opacity: 0.8; }
        .auth-brand i { color: var(--primary); margin-right: 10px; font-size: 2rem; }
        .promo-content { max-width: 500px; animation: fadeInUp 1s ease; text-align: left; }
        .promo-content h1 { font-weight: 800; font-size: 3.8rem; margin-bottom: 20px; line-height: 1.1; letter-spacing: -2px; color: #0f172a; }
        .promo-content p { font-size: 1.25rem; opacity: 0.9; line-height: 1.7; font-weight: 400; color: #334155; }
        .auth-form-side { flex: 1; display: flex; justify-content: center; align-items: center; padding: 40px; background: #fff; position: relative; }
        .form-container { width: 100%; max-width: 440px; animation: fadeInRight 0.8s ease; }
        .form-header { margin-bottom: 35px; }
        .form-header h2 { font-weight: 800; color: #0f172a; font-size: 2.3rem; margin-bottom: 8px; letter-spacing: -0.5px; }
        .form-header p { color: #6c757d; font-size: 1rem; }
        .form-floating > .form-control, .form-floating > .form-select { border-radius: 12px; border: 1.5px solid #e9ecef; padding: 1rem 1.2rem; height: calc(3.5rem + 10px); font-size: 1rem; transition: all 0.3s ease; box-shadow: none !important; background-color: #fcfcfc; }
        .form-floating > .form-control:focus, .form-floating > .form-select:focus { border-color: var(--primary); background-color: #fff; box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1) !important; }
        .form-floating > label { padding: 1.1rem 1.2rem; color: #6c757d; font-weight: 500; }
        .btn-custom { background: linear-gradient(135deg, var(--primary), #0a58ca); color: white; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 16px; font-weight: 700; width: 100%; text-transform: uppercase; letter-spacing: 1px; font-size: 1rem; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); margin-top: 15px; box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3); }
        .btn-custom:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 12px 30px rgba(13, 110, 253, 0.5); color: white; background: linear-gradient(135deg, #0a58ca, #084298); }
        .login-link { text-align: center; margin-top: 30px; font-weight: 500; color: #6c757d; }
        .login-link a { color: var(--primary); font-weight: 700; text-decoration: none; transition: 0.3s; margin-left: 5px; }
        .login-link a:hover { color: #084298; text-decoration: underline; }
        .back-btn-mobile { display: none; }
        
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
        
        @media (max-width: 991px) {
            .auth-image-side { display: none; }
            .auth-form-side { padding: 30px 20px; background: var(--light); }
            .form-container { background: #fff; padding: 40px 30px; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.05); max-width: 500px; margin: auto; }
            .back-btn-mobile { display: inline-flex; align-items: center; color: var(--secondary); font-weight: 600; text-decoration: none; margin-bottom: 30px; transition: 0.3s; }
            .back-btn-mobile:hover { color: var(--primary); }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <!-- Left Promotional Side -->
    <div class="auth-image-side">
        <a href="../index.php" class="auth-brand" style="display: flex; align-items: center; gap: 10px;">
            <div style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border-radius: 14px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);">
                <i class="fa-solid fa-suitcase-medical" style="font-size: 1.5rem; margin: 0;"></i>
            </div>
            <span style="font-weight: 800; font-size: 1.6rem; letter-spacing: -0.5px; color: #0f172a;">Smart<span style="color: #2563eb;">Rescue</span></span>
        </a>
        <div class="promo-content">
            <h1>Join the Network<br>of Heroes</h1>
            <p>Register an account to become a part of the most advanced emergency response system in Mogadishu. Your prompt action can save a life today.</p>
        </div>
    </div>
    
    <!-- Right Form Side -->
    <div class="auth-form-side">
        <div class="form-container">
            <a href="../index.php" class="back-btn-mobile"><i class="fa-solid fa-arrow-left me-2"></i> Back to Home</a>
            
            <div class="form-header">
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 22px;">
                    <div style="background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border-radius: 16px; width: 54px; height: 54px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);">
                        <i class="fa-solid fa-suitcase-medical"></i>
                    </div>
                    <div>
                        <div style="font-weight: 900; font-size: 1.4rem; color: #0f172a; letter-spacing: -0.4px; line-height: 1.2;">Smart<span style="color: #2563eb;">Rescue</span></div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Emergency Response System</div>
                    </div>
                </div>
                <h2>Create Account</h2>
                <p>Welcome to SmartRescue! Please fill in your details below.</p>
            </div>
            
            <?php echo $message; ?>
            
            <form action="" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" name="fullname" class="form-control" id="fullname" placeholder="Ahmed Ali" required>
                    <label for="fullname">Full Name</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="tel" name="phone" class="form-control" id="phone" placeholder="61XXXXXXX" required>
                    <label for="phone">Phone Number</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="email" name="email" class="form-control" id="email" placeholder="axmed@email.com" required>
                    <label for="email">Email</label>
                </div>
                

                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="date" name="birth_date" class="form-control" id="birth_date" required>
                            <label for="birth_date">Birth Date</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select name="gender" class="form-select" id="gender" required>
                                <option value="" disabled selected>Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <label for="gender">Gender</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-floating mb-4">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <label for="password">New Password</label>
                </div>
                
                <button type="submit" class="btn-custom">Register Now <i class="fa-solid fa-arrow-right ms-2 mt-1"></i></button>
                
                <div class="login-link">
                    Already registered? <a href="login.php">Login Here</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>