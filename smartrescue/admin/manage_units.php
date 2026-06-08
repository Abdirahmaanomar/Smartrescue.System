<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_unit_id'])) {
    $unit_id = mysqli_real_escape_string($conn, $_POST['edit_unit_id']);
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
    mysqli_query($conn, $update_sql);
    header("Location: manage_units.php");
    exit();
}

$admin_name = $_SESSION['fullname'];

// Fetch drivers for the edit modal
$driver_query = "SELECT id, fullname FROM users WHERE role = 'driver'";
$drivers = mysqli_query($conn, $driver_query);
$drivers_arr = [];
while($d = mysqli_fetch_assoc($drivers)) {
    $drivers_arr[] = $d;
}

// Fetch unit data
$query = "SELECT e.*, u.fullname FROM emergency_units e 
          JOIN users u ON e.driver_id = u.id";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Management | SmartRescue</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#f1f5f9;--card-bg:#fff;--text:#0f172a;--text-muted:#64748b;--accent:#3b82f6;--sidebar-width:268px;--shadow:0 4px 24px rgba(0,0,0,0.06);}
        body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}
        .main-wrapper{margin-left:var(--sidebar-width);padding:36px 44px;min-height:100vh;}
        .fleet-card{background:var(--card-bg);border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(0,0,0,0.04);overflow:hidden;}
        .table{margin:0;}
        .table thead th{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);background:#f8fafc;padding:13px 16px;border:none;border-bottom:1px solid rgba(0,0,0,0.05);}
        .table tbody td{padding:14px 16px;font-size:0.87rem;border:none;border-bottom:1px solid rgba(0,0,0,0.04);vertical-align:middle;}
        .table tbody tr:last-child td{border-bottom:none;}
        .table tbody tr:hover{background:#f8fafe;}
        .unit-item{transition:0.2s;}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:50px;font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;}
        .status-available{background:rgba(34,197,94,0.1);color:#22c55e;}
        .status-busy{background:rgba(59,130,246,0.1);color:#3b82f6;}
        .status-offline{background:rgba(100,116,139,0.08);color:#94a3b8;}
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<main class="main-wrapper">
<?php $page_title = 'Emergency'; $page_subtitle = 'Fleet'; include 'includes/topbar.php'; ?>

    <div style="display:flex;justify-content:flex-end;margin-bottom:24px">
        <a href="add_unit.php" style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;font-size:0.85rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(59,130,246,0.35)">
            <i class="fa fa-plus"></i> Register New Unit
        </a>
    </div>

    <div class="fleet-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="text-muted small text-uppercase">
                    <tr>
                        <th class="ps-3">Unit Identity</th>
                        <th>Assigned Responder</th>
                        <th>Plate ID</th>
                        <th>Current Status</th>
                        <th class="text-end pe-3">Command</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="unit-item">
                        <td class="ps-3">
                            <div class="fw-900 fs-5"><?php echo $row['unit_name']; ?></div>
                            <div class="small text-muted text-uppercase fw-bold"><?php echo $row['unit_type']; ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo $row['fullname']; ?>" class="rounded-circle" style="width: 35px;">
                                <div class="fw-700"><?php echo $row['fullname']; ?></div>
                            </div>
                        </td>
                        <td><code class="fw-bold text-dark fs-6"><?php echo $row['plate_number']; ?></code></td>
                        <td>
                            <?php 
                                $status = strtolower($row['status']);
                                $class = "status-" . $status;
                            ?>
                            <span class="status-pill <?php echo $class; ?>"><?php echo $status; ?></span>
                        </td>
                        <td class="text-end pe-3">
                            <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="btn btn-light rounded-pill btn-sm px-3 fw-800 border">Edit</button>
                            <a href="delete_unit.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Ma hubtaa in aad tiraeysid gaarigaan? (Are you sure you want to delete this unit?)')" class="btn btn-danger rounded-pill btn-sm px-3 fw-800 border shadow-sm"><i class="fa fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1" aria-labelledby="editUnitModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: var(--shadow);">
      <div class="modal-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding: 20px 24px;">
        <h5 class="modal-title fw-800" id="editUnitModalLabel"><i class="fa fa-pen-to-square text-primary me-2"></i>Edit Unit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
      <div class="modal-body" style="padding: 24px;">
          <input type="hidden" name="edit_unit_id" id="edit_unit_id">
          
          <div class="mb-3">
              <label class="form-label small fw-800 text-uppercase">Unit Call-Sign</label>
              <input type="text" name="unit_name" id="edit_unit_name" class="form-control" style="border-radius: 12px; padding: 10px 16px;" required>
          </div>
          <div class="mb-3">
              <label class="form-label small fw-800 text-uppercase">Registry ID (Plate)</label>
              <input type="text" name="plate_number" id="edit_plate_number" class="form-control" style="border-radius: 12px; padding: 10px 16px;" required>
          </div>
          <div class="mb-3">
              <label class="form-label small fw-800 text-uppercase">Status</label>
              <select name="status" id="edit_status" class="form-select shadow-none" style="border-radius: 12px; padding: 10px 16px;">
                  <option value="available">✅ Available (Diyaar ah)</option>
                  <option value="busy">⚠️ Busy (Mashquul)</option>
                  <option value="offline">❌ Offline (Ma shaqaynayo)</option>
              </select>
          </div>
          <div class="mb-3">
              <label class="form-label small fw-800 text-uppercase">Assigned Responder</label>
              <select name="driver_id" id="edit_driver_id" class="form-select shadow-none" style="border-radius: 12px; padding: 10px 16px;" required>
                  <?php foreach($drivers_arr as $d): ?>
                      <option value="<?php echo $d['id']; ?>"><?php echo $d['fullname']; ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
      </div>
      <div class="modal-footer" style="border-top: none; padding: 16px 24px 24px;">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Save Changes</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let editModal = null;
document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editUnitModal'));
});

function openEditModal(unitData) {
    document.getElementById('edit_unit_id').value = unitData.id;
    document.getElementById('edit_unit_name').value = unitData.unit_name;
    document.getElementById('edit_plate_number').value = unitData.plate_number;
    document.getElementById('edit_status').value = unitData.status;
    document.getElementById('edit_driver_id').value = unitData.driver_id;
    editModal.show();
}
</script>
</body>
</html>