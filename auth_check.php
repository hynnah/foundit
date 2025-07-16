<?php
// Authentication and authorization check
session_start();

// Helper function to get redirect path based on current directory
function getRedirectPath($filename) {
    $currentDir = dirname($_SERVER['PHP_SELF']);
    if (strpos($currentDir, '/admin') !== false) {
        // We're in the admin folder, redirect to parent directory
        return "../{$filename}";
    } else {
        // We're in the root directory
        return $filename;
    }
}

// Check if user is logged in
if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: " . getRedirectPath('login.php'));
    exit();
}

// Check if user account is still active
require_once 'dbh.inc.php';
$userId = $_SESSION['userId'];
$sql = "SELECT account_status FROM Person WHERE PersonID = ?";
$stmt = mysqli_stmt_init($connection);
if (mysqli_stmt_prepare($stmt, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row && isset($row['account_status']) && $row['account_status'] === 'Deactivated') {
        // Account has been deactivated, destroy session and redirect
        session_unset();
        session_destroy();
        header("Location: " . getRedirectPath('login.php?error=accountdeactivated'));
        exit();
    }
}

// Check session validity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
    // Session expired after 24 hours
    session_unset();
    session_destroy();
    header("Location: " . getRedirectPath('login.php?error=sessionexpired'));
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;
}

// Function to require admin access
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: " . getRedirectPath('dashboard.php?error=unauthorized'));
        exit();
    }
}

// Function to check if user can submit found items (admin only)
function canSubmitFoundItems() {
    return isAdmin();
}

// Function to get user ID
function getUserId() {
    return $_SESSION['userId'] ?? null;
}

// Function to get user name
function getUserName() {
    return $_SESSION['userName'] ?? 'User';
}

// Function to get user role
function getUserRole() {
    return $_SESSION['userRole'] ?? null;
}

// Basic CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
