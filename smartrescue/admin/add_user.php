<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); 
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = clean_input($_POST['fullname'], $conn);
    $phone    = clean_input($_POST['phone'], $conn);
    $email    = clean_input($_POST['email'] ?? '', $conn);
    $password = $_POST['password'];
    
    // Check if phone or email already exists
    $email_check = !empty($email) ? " OR email = '$email'" : "";
    $check_q = "SELECT id FROM users WHERE phone = '$phone' $email_check";
    $check_res = mysqli_query($conn, $check_q);
    
    if (mysqli_num_rows($check_res) > 0) {
        // User already exists, redirect back with error (can use session error message if desired)
        // For simplicity, just redirecting for now. You could add $_SESSION['error'] here.
        header("Location: users.php");
        exit();
    }
    
    $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
    $email_sql = !empty($email) ? "'$email'" : "NULL";
    
    $insert_q = "INSERT INTO users (fullname, phone, email, password, role) VALUES ('$fullname', '$phone', $email_sql, '$hashed_pass', 'user')";
    mysqli_query($conn, $insert_q);
    
    header("Location: users.php");
    exit();
} else {
    header("Location: users.php");
    exit();
}
?>
