<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "My Profile - SmartRescue";
$uid = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_profile') {
    $fn = mysqli_real_escape_string($conn, $_POST['fullname']);
    $em = mysqli_real_escape_string($conn, $_POST['email']);
    $ph = mysqli_real_escape_string($conn, $_POST['phone']);
    $mi = mysqli_real_escape_string($conn, $_POST['medical_info']);
    $ec = mysqli_real_escape_string($conn, $_POST['emergency_contacts'] ?? '');
    $img = mysqli_real_escape_string($conn, $_POST['profile_image']);
    $birth_date = !empty($_POST['birth_date']) ? "'".mysqli_real_escape_string($conn, $_POST['birth_date'])."'" : 'NULL';
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    
    $up_q = "UPDATE users SET fullname='$fn', email='$em', phone='$ph', medical_info='$mi', emergency_contacts='$ec', profile_image='$img', birth_date=$birth_date, gender='$gender' WHERE id='$uid'";
    if (mysqli_query($conn, $up_q)) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile: " . mysqli_error($conn);
    }
}

// Fetch fresh data
$u_res = mysqli_query($conn, "SELECT * FROM users WHERE id = '$uid'");
$user = mysqli_fetch_assoc($u_res);

$initials = strtoupper(substr($user['fullname'], 0, 1));
$profile_img = $user['profile_image'] ?? '';

require_once '../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <h2><i class="fa fa-user" style="color:var(--primary); margin-right:12px;"></i>User Profile</h2>
</div>

<?php if ($success): ?>
    <div style="background: rgba(16,185,129,0.1); border: 1px solid var(--success); color: var(--success); padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; font-weight:600;">
        <i class="fa fa-check-circle" style="margin-right:8px;"></i><?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(239,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; font-weight:600;">
        <i class="fa fa-circle-exclamation" style="margin-right:8px;"></i><?= $error ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 32px; max-width: 600px; margin: 0 auto; border-radius: 20px;">
    
    <div style="display:flex; flex-direction:column; align-items:center; margin-bottom: 32px;">
        <div style="position:relative; width: 100px; height: 100px; margin-bottom: 12px;">
            <div id="avatar-preview" style="width:100%; height:100%; border-radius:50%; background: linear-gradient(135deg, var(--primary), #1d4ed8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:2.5rem; font-weight:900; overflow:hidden; border: 4px solid var(--surface-solid); box-shadow: var(--shadow-sm);">
                <?php if ($profile_img): ?>
                    <img src="../<?= $profile_img ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <?= $initials ?>
                <?php endif; ?>
            </div>
            
            <label for="imgUpload" style="position:absolute; bottom:0; right:0; background:var(--surface-solid); border:1px solid var(--border); box-shadow:var(--shadow-sm); width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text); transition:all 0.2s;">
                <i class="fa fa-camera" style="font-size:0.8rem;"></i>
                <input type="file" id="imgUpload" accept="image/*" style="display:none;" onchange="uploadImage(this)">
            </label>
        </div>
        <h3 style="font-weight: 800; font-size: 1.4rem; color: var(--text);"><?= htmlspecialchars($user['fullname']) ?></h3>
        <p style="color: var(--muted); font-size: 0.9rem;"><?= htmlspecialchars($user['email']) ?></p>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="save_profile">
        <input type="hidden" name="profile_image" id="profile_image_input" value="<?= htmlspecialchars($profile_img) ?>">
        
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Full Name</label>
            <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none;">
        </div>

        <div style="display:flex; gap:20px; margin-bottom:20px;">
            <div style="flex:1;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Birth Date</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>" style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Gender</label>
                <select name="gender" style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none;">
                    <option value="" <?= empty($user['gender']) ? 'selected' : '' ?>>Select...</option>
                    <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Medical Information (Optional)</label>
            <textarea name="medical_info" rows="3" placeholder="Allergies, blood type, known conditions..." style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none; resize:vertical;"><?= htmlspecialchars($user['medical_info'] ?? '') ?></textarea>
        </div>

        <div style="margin-bottom: 30px;">
            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Emergency Contacts (Optional)</label>
            <textarea name="emergency_contacts" rows="3" placeholder="Name: Phone Number" style="width:100%; padding:14px; border-radius:12px; border:2px solid var(--border); background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; font-weight:600; font-size:0.95rem; outline:none; resize:vertical;"><?= htmlspecialchars($user['emergency_contacts'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-primary" style="width:100%; border-radius:14px; padding:16px; font-weight:800; font-size:1rem; letter-spacing:0.5px;">
            <i class="fa fa-save" style="margin-right:8px;"></i> Save Changes
        </button>
    </form>
</div>

<?php 
$extra_js = "
<script>
async function uploadImage(input) {
    if (!input.files || input.files.length === 0) return;
    SmartRescue.toast('Uploading photo...', '#3b82f6');
    const formData = new FormData();
    formData.append('avatar', input.files[0]);
    try {
        const res = await fetch('../api/user/upload_avatar.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('profile_image_input').value = data.file_path;
            document.getElementById('avatar-preview').innerHTML = '<img src=\"../' + data.file_path + '\" style=\"width:100%; height:100%; object-fit:cover;\">';
            const navAv = document.getElementById('nav-user-avatar');
            if (navAv) navAv.innerHTML = '<img src=\"../' + data.file_path + '\" style=\"width:100%;height:100%;object-fit:cover;border-radius:50%;\">';
            SmartRescue.toast('Photo saved!', '#10b981');
        } else {
            SmartRescue.toast(data.message || 'Upload failed', '#ef4444');
        }
    } catch (e) {
        SmartRescue.toast('Upload failed.', '#ef4444');
    }
}
</script>
";

require_once '../includes/footer.php'; 
?>
