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

    // Corrected query with proper table name casing
    $sql = "SELECT p.*, u.role, p.person_type FROM Person p 
        LEFT JOIN User u ON p.PersonID = u.UserID 
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
        // Check if account is deactivated
        if (isset($row['account_status']) && $row['account_status'] === 'Deactivated') {
            header("Location: login.php?error=accountdeactivated");
            exit();
        }
        
        if (password_verify($password, $row['password'])) {
            session_start();
        // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    // Clear existing session data
    $_SESSION = [];

    // Set secure session parameters
    $_SESSION = [
        'userId' => $row['PersonID'],
        'userEmail' => $row['email'],
        'userRole' => $row['role'] ?? null,
        'isAdmin' => ($row['person_type'] === 'Administrator'),
        'userName' => $row['name'],
        'logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'last_activity' => time()
    ];

    // Set secure session cookie params
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
            
            // Redirect based on admin status
            header("Location: " . ($_SESSION['isAdmin'] ? 'admin/dashboard.php' : 'dashboard.php'));
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