<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];

    // Handle Image Upload — store to uploads/avatars/ with full relative path
    $image_query = "";
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        // Delete old avatar
        $old_img_res = mysqli_query($conn, "SELECT profile_image FROM users WHERE id='$user_id'");
        $old_img_row = mysqli_fetch_assoc($old_img_res);
        if (!empty($old_img_row['profile_image'])) {
            $old_file = '../' . $old_img_row['profile_image'];
            if (file_exists($old_file)) @unlink($old_file);
        }
        
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $target_file  = $upload_dir . $new_filename;
        $public_path  = 'uploads/avatars/' . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $safe_path = mysqli_real_escape_string($conn, $public_path);
            $image_query = ", profile_image='$safe_path'";
            $_SESSION['profile_image'] = $public_path; // Sync session
        }
    }

    $pass_query = "";
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $pass_query = ", password='$hashed_password'";
    }

    $query = "UPDATE users SET fullname='$fullname', email='$email', phone='$phone' $image_query $pass_query WHERE id=$user_id";
    if (mysqli_query($conn, $query)) {
        $success = 'Profile updated successfully.';
        $_SESSION['fullname'] = $fullname; // Update session name
    } else {
        $error = 'Failed to update profile. Please try again.';
    }
}

// Fetch current user data and sync profile_image to session
$q = mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id");
$user = mysqli_fetch_assoc($q);
// Always keep session in sync with DB
if (!empty($user['profile_image'])) {
    $_SESSION['profile_image'] = $user['profile_image'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile | SmartRescue Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
.main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}

.profile-card {
    background: var(--card-bg);
    border-radius: 20px;
    box-shadow: var(--shadow);
    border: 1px solid rgba(0,0,0,0.04);
    padding: 32px;
    max-width: 800px;
    margin: 0 auto;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.avatar-upload {
    position: relative;
    width: 100px;
    height: 100px;
    border-radius: 20px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    font-weight: 800;
    box-shadow: 0 8px 24px rgba(59,130,246,0.25);
    flex-shrink: 0;
    overflow: hidden;
}

.avatar-upload img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    opacity: 0;
    transition: 0.2s;
    cursor: pointer;
}

.avatar-upload:hover .avatar-overlay {
    opacity: 1;
}

.profile-title h4 {
    font-weight: 800;
    margin: 0 0 6px 0;
    color: var(--text);
}

.profile-title p {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.form-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.form-control {
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.08);
    font-family: 'Outfit', sans-serif;
    font-weight: 500;
    background: #f8fafc;
    transition: 0.2s;
}

.form-control:focus {
    background: #fff;
    border-color: var(--accent);
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
}

.btn-save {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 12px 28px;
    border-radius: 12px;
    font-weight: 700;
    border: none;
    box-shadow: 0 6px 20px rgba(59,130,246,0.3);
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59,130,246,0.4);
    color: white;
}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<main class="main-wrapper">
<?php $page_title = 'Account'; $page_subtitle = 'Profile'; include 'includes/topbar.php'; ?>

<div class="profile-card">
    <?php if ($success): ?>
        <div class="alert alert-success fw-bold py-2 px-3 border-0 rounded-3 mb-4" style="background:#dcfce7;color:#166534;"><i class="fa fa-check-circle me-2"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger fw-bold py-2 px-3 border-0 rounded-3 mb-4" style="background:#fee2e2;color:#991b1b;"><i class="fa fa-triangle-exclamation me-2"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="profile-header">
            <div class="avatar-upload" onclick="document.getElementById('profile_image').click()">
                <?php if (!empty($user['profile_image'])): ?>
                    <img id="avatar-preview" src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
                <?php else: ?>
                    <span id="avatar-initial"><?= strtoupper(substr($user['fullname'] ?? 'A', 0, 1)) ?></span>
                    <img id="avatar-preview" src="" style="display:none;" alt="Avatar">
                <?php endif; ?>
                <div class="avatar-overlay"><i class="fa fa-camera"></i></div>
            </div>
            <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display:none;" onchange="previewImage(this)">
            
            <div class="profile-title">
                <h4><?= htmlspecialchars($user['fullname'] ?? 'Admin') ?></h4>
                <p>Chief Dispatcher</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">New Password (optional)</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
            </div>
        </div>
        
        <div class="mt-4 pt-3 text-end border-top" style="border-color: rgba(0,0,0,0.06) !important;">
            <button type="submit" class="btn btn-save"><i class="fa fa-save me-2"></i> Save Changes</button>
        </div>
    </form>
</div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            const initial = document.getElementById('avatar-initial');
            if (initial) initial.style.display = 'none';
            const preview = document.getElementById('avatar-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
