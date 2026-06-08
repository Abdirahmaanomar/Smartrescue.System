<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['request_id'])) {
    header("Location: index.php");
    exit();
}

$request_id = mysqli_real_escape_string($conn, $_GET['request_id']);

// 2. Fetch Victim Info
$req_sql = "SELECT r.*, u.fullname as patient_name, u.phone as patient_phone 
            FROM rescue_requests r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = '$request_id'";
$req_res = mysqli_query($conn, $req_sql);
$request = mysqli_fetch_assoc($req_res);

// 3. Fetch Available Units
$unit_sql = "SELECT e.*, u.fullname as driver_name, u.profile_image as driver_image 
             FROM emergency_units e 
             JOIN users u ON e.driver_id = u.id 
             WHERE e.status = 'available'";
$units = mysqli_query($conn, $unit_sql);
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tactical Dispatch | SmartRescue</title>
    
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

        .main-wrapper { margin-left: var(--sidebar-width); padding: 40px 50px; }

        .victim-briefing {
            background: linear-gradient(135deg, #08214f 0%, #0d6efd 100%);
            color: white;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 50px;
            box-shadow: 0 15px 40px rgba(13, 110, 253, 0.25);
            border-left: 8px solid rgba(255,255,255,0.3);
        }

        .unit-card-tactical {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 8px 25px rgba(0,0,0,0.04);
            transition: 0.3s;
            height: 100%;
        }
        .unit-card-tactical:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }
        
        .distance-tag {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            padding: 5px 15px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="d-flex align-items-center gap-3 mb-5 px-2">
        <div style="background: linear-gradient(135deg, #0d6efd, #0a58ca); color: white; border-radius: 10px; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(13,110,253,0.35); flex-shrink:0;">
            <i class="fa fa-truck-medical fs-5"></i>
        </div>
        <span class="fw-800 fs-5 mb-0 text-white" style="letter-spacing: -0.5px;">Smart<span style="color: #93c5fd;">Rescue</span></span>
    </div>

    <nav class="flex-grow-1">
        <a href="index.php" class="nav-link-custom active"><i class="fa fa-th-large"></i> Tactical View</a>
        <a href="manage_units.php" class="nav-link-custom"><i class="fa fa-truck-medical"></i> Fleet Management</a>
        <a href="view-requests.php" class="nav-link-custom"><i class="fa fa-history"></i> Mission Logs</a>
    </nav>
</aside>

<main class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h6 class="text-primary fw-900 text-uppercase tracking-widest mb-2" style="letter-spacing: 5px;">Dispatch Logic</h6>
            <h1 class="display-4 fw-950 mb-0">Tactical <span class="fw-light opacity-50">Deployment</span></h1>
        </div>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold"><i class="fa fa-arrow-left me-2"></i> Dashboard</a>
    </div>

    <!-- Victim Briefing -->
    <div class="victim-briefing animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-3 fw-bold">PRIORITY: RED</span>
                <h2 class="fw-900 mb-2"><?php echo strtoupper($request['emergency_type']); ?> INCIDENT</h2>
                <div class="d-flex gap-4 mb-4">
                    <div class="small"><i class="fa fa-user me-2 opacity-50"></i> <?php echo $request['patient_name']; ?></div>
                    <div class="small"><i class="fa fa-phone me-2 opacity-50"></i> <?php echo $request['patient_phone']; ?></div>
                    <div class="small"><i class="fa fa-clock me-2 opacity-50"></i> <?php echo date('H:i:s', strtotime($request['created_at'])); ?></div>
                </div>
                <p class="mb-0 opacity-75 fw-bold"><i class="fa fa-quote-left me-2"></i> <?php echo $request['description']; ?></p>
            </div>
            <div class="col-md-5 text-md-end">
                <div class="p-3 bg-white bg-opacity-10 rounded-4">
                    <small class="text-uppercase opacity-50 fw-bold">Live Target Coordinates</small>
                    <h4 class="mb-0 fw-800"><?php echo $request['lat']; ?>, <?php echo $request['lng']; ?></h4>
                </div>
            </div>
        </div>
    </div>

    <h4 class="fw-900 mb-4 px-2">Optimal Fleet Selection</h4>
    
    <div class="row g-4">
        <?php if(mysqli_num_rows($units) > 0): ?>
            <?php while($unit = mysqli_fetch_assoc($units)): 
                $dist = calculateDistance($request['lat'], $request['lng'], $unit['current_lat'], $unit['current_lng']);
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="unit-card-tactical d-flex flex-column h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4">
                            <i class="fa fa-ambulance fs-3"></i>
                        </div>
                        <div class="distance-tag"><?php echo $dist; ?> KM AWAY</div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <?php if(!empty($unit['driver_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($unit['driver_image']); ?>" style="width:48px; height:48px; border-radius:12px; object-fit:cover; border:2px solid #fff; box-shadow:0 4px 10px rgba(0,0,0,0.05);" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($unit['driver_name']); ?>&background=0d6efd&color=fff'">
                        <?php else: ?>
                            <div style="width:48px; height:48px; border-radius:12px; background:rgba(13,110,253,0.1); color:#0d6efd; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.2rem; border:1px solid rgba(13,110,253,0.15);">
                                <?php echo strtoupper(substr($unit['driver_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h5 class="fw-900 mb-0"><?php echo $unit['unit_name']; ?></h5>
                            <p class="text-muted small fw-bold mb-0">Plate: <?php echo $unit['plate_number']; ?></p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <small class="text-uppercase tracking-wider fw-800 text-muted opacity-75" style="font-size:0.65rem;">Assigned Responder</small>
                        <div class="fw-bold" style="font-size:0.95rem;"><?php echo $unit['driver_name']; ?></div>
                    </div>
                    
                    <div class="mt-auto">
                        <form action="process_assign.php" method="POST">
                            <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                            <input type="hidden" name="unit_id" value="<?php echo $unit['id']; ?>">
                            <button type="submit" class="btn btn-primary w-100 rounded-pill fw-900 py-3 shadow-sm">DISPATCH NOW</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning rounded-4 p-5 text-center border-0 shadow">
                    <i class="fa fa-triangle-exclamation display-4 mb-3"></i>
                    <h3 class="fw-900">FLEET DEPLETED</h3>
                    <p class="mb-0">All registered units are currently busy or offline. Immediate action required.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>