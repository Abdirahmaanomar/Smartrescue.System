<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if(!isset($_GET['id'])) { header("Location: manage_units.php"); exit(); }
$unit_id = mysqli_real_escape_string($conn, $_GET['id']);

// Fetch existing unit data
$unit_query = "SELECT * FROM emergency_units WHERE id = '$unit_id'";
$unit_res = mysqli_query($conn, $unit_query);
$unit = mysqli_fetch_assoc($unit_res);

// Fetch all drivers
$driver_query = "SELECT id, fullname FROM users WHERE role = 'driver'";
$drivers = mysqli_query($conn, $driver_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $unit_name = mysqli_real_escape_string($conn, $_POST['unit_name']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $driver_id = mysqli_real_escape_string($conn, $_POST['driver_id']);

    $update_sql = "UPDATE emergency_units 
                   SET unit_name = '$unit_name', 
                       plate_number = '$plate', 
                       status = '$status', 
                       driver_id = '$driver_id' 
                   WHERE id = '$unit_id'";

    if (mysqli_query($conn, $update_sql)) {
        header("Location: manage_units.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Unit | SmartRescue</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #0a58ca;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #0f172a;
            --sidebar-width: 280px;
        }

        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); margin: 0; }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            background: #08214f;
            color: white;
            padding: 40px 25px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: 10px 0 30px rgba(0,0,0,0.15);
        }

        .nav-link-custom {
            display: flex;
            align-items: center;gap: 15px;
            padding: 13px 18px;
            border-radius: 12px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 8px;
            transition: 0.3s;
        }
        .nav-link-custom:hover, .nav-link-custom.active { background: rgba(255,255,255,0.08); color: white; transform: translateX(5px); }
        .nav-link-custom.active { background: var(--primary); box-shadow: 0 8px 20px rgba(13,110,253,0.3); }

        .main-wrapper { margin-left: var(--sidebar-width); padding: 40px 50px; display: flex; flex-direction: column; align-items: center; }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(0,0,0,0.04);
        }

        .glass-input {
            background: #fdfdfd;
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 600;
        }
        .glass-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(13,110,253,0.1); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="d-flex align-items-center gap-3 mb-5 px-2">
        <div style="background: linear-gradient(135deg, #0d6efd, #0a58ca); color: white; border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(13,110,253,0.35); flex-shrink:0;">
            <i class="fa-solid fa-suitcase-medical fs-5"></i>
        </div>
        <span class="fw-800 fs-5 mb-0 text-white" style="letter-spacing: -0.5px;">Smart<span style="color: #93c5fd;">Rescue</span></span>
    </div>

    <nav class="flex-grow-1">
        <a href="index.php" class="nav-link-custom"><i class="fa fa-th-large"></i> Tactical View</a>
        <a href="manage_units.php" class="nav-link-custom active"><i class="fa fa-truck-medical"></i> Fleet Management</a>
        <a href="view-requests.php" class="nav-link-custom"><i class="fa fa-history"></i> Mission Logs</a>
    </nav>
</aside>

<main class="main-wrapper">
    <div class="text-center mb-5">
        <h6 class="text-primary fw-900 text-uppercase tracking-widest mb-2" style="letter-spacing: 5px;">Modification</h6>
        <h1 class="display-4 fw-950 mb-0">Unit <span class="fw-light opacity-50">Editor</span></h1>
    </div>

    <div class="form-card animate__animated animate__fadeInUp">
        <form method="POST">
             <div class="mb-4">
                <label class="form-label small fw-800 text-uppercase">Unit Call-Sign</label>
                <input type="text" name="unit_name" class="form-control glass-input" value="<?php echo htmlspecialchars($unit['unit_name']); ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-800 text-uppercase">Registry ID (Plate)</label>
                <input type="text" name="plate_number" class="form-control glass-input" value="<?php echo htmlspecialchars($unit['plate_number']); ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-800 text-uppercase">Availability Status</label>
                <select name="status" class="form-select glass-input shadow-none">
                    <option value="available" <?php if($unit['status'] == 'available') echo 'selected'; ?>>✅ Available (Diyaar ah)</option>
                    <option value="busy" <?php if($unit['status'] == 'busy') echo 'selected'; ?>>⚠️ Busy (Mashquul)</option>
                    <option value="offline" <?php if($unit['status'] == 'offline') echo 'selected'; ?>>❌ Offline (Ma shaqaynayo)</option>
                </select>
            </div>

            <div class="mb-5">
                <label class="form-label small fw-800 text-uppercase">Assigned Responder</label>
                <select name="driver_id" class="form-select glass-input shadow-none" required>
                    <?php while($d = mysqli_fetch_assoc($drivers)): ?>
                        <option value="<?php echo $d['id']; ?>" <?php if($unit['driver_id'] == $d['id']) echo 'selected'; ?>>
                            <?php echo $d['fullname']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-900 shadow-sm fs-5">SAVE CHANGES</button>
            <a href="manage_units.php" class="btn btn-link w-100 text-muted mt-3 fw-bold text-decoration-none small">Cancel & Go Back</a>
        </form>
    </div>
</main>

</body>
</html>