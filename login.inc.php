<?php
require_once 'dbh.inc.php';

if (isset($_POST['login-submit'])) {
    // Validate inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=emptyfields");
        exit();
    }

    $sql = "SELECT p.*, u.role FROM Person p 
            JOIN Users u ON p.PersonID = u.PersonID 
            WHERE p.email=?";
    $stmt = mysqli_stmt_init($connection);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        error_log("SQL error: " . mysqli_error($connection));
        header("Location: login.php?error=sqlerror");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            session_start();
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            $_SESSION = [
                'userId' => $row['PersonID'],
                'userEmail' => $row['email'],
                'userRole' => $row['role'],
                'userName' => $row['name'],
                'logged_in' => true
            ];
            
            // Redirect based on role
            header("Location: " . ($row['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
            exit();
        }
    }
    
    // Generic error message to prevent user enumeration
    header("Location: login.php?error=authfailed");
    exit();
} else {
    header("Location: login.php");
    exit();
}