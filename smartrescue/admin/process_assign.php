<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// 1. Amniga: Hubi in qofka uu yahay Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Error: Ma lihid ogolaansho aad ku qabato shaqadan.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Nadiifi xogta laga soo diray Form-ka
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $unit_id = mysqli_real_escape_string($conn, $_POST['unit_id']);

    // 2. Hubi in gaariga uu wali Available yahay (Double Check)
    $check_unit = mysqli_query($conn, "SELECT status FROM emergency_units WHERE id = '$unit_id'");
    $unit_data = mysqli_fetch_assoc($check_unit);

    if ($unit_data['status'] !== 'available') {
        header("Location: assign_unit.php?id=$request_id&error=unit_busy");
        exit();
    }

    // 3. Bilow Isbeddelka Database-ka (Transaction-like)
    
    // A. Cusboonaysii Codsiga SOS (Ka dhig Accepted + ku dar Gaariga)
    $q1 = "UPDATE rescue_requests 
           SET assigned_unit_id = '$unit_id', 
               status = 'accepted' 
           WHERE id = '$request_id'";
    
    // B. Beddel Status-ka Ambaalaasta (Ka dhig Busy)
    $q2 = "UPDATE emergency_units SET status = 'busy' WHERE id = '$unit_id'";

    // 4. Fulinta iyo Hubinta
    if (mysqli_query($conn, $q1) && mysqli_query($conn, $q2)) {
        // Log Activity
        log_activity($conn, $_SESSION['user_id'], 'Unit Dispatched', "Mission #$request_id assigned to Unit #$unit_id.", 'info');

        // Guul: Admin-ka dib ugu celi Dashboard-ka isagoo wata farriin guul ah
        header("Location: index.php?msg=dispatched_success");
        exit();
    } else {
        // Haddii cilad dhacdo
        echo "Cilad farsamo: " . mysqli_error($conn);
    }
} else {
    // Haddii si qaldan bogga loo soo booqdo
    header("Location: index.php");
    exit();
}
?>