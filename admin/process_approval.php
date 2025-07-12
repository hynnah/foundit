<?php
session_start();
require_once '../dbh.inc.php';

if (!isset($_SESSION['userId'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['userId'];

// Validate this user is an administrator
$adminCheckQuery = "SELECT AdminID FROM Administrator WHERE AdminID = ?";
$stmtCheck = mysqli_prepare($connection, $adminCheckQuery);
mysqli_stmt_bind_param($stmtCheck, "i", $userId);
mysqli_stmt_execute($stmtCheck);
$resultCheck = mysqli_stmt_get_result($stmtCheck);

if (mysqli_num_rows($resultCheck) === 0) {
    die("Access denied: You are not authorized as an administrator.");
}
mysqli_stmt_close($stmtCheck);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = intval($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($report_id && in_array($action, ['approve', 'reject'])) {
        $status_id = ($action === 'approve') ? 2 : 3;

        $sql = "UPDATE Report 
                SET ApprovalStatusID = ?, 
                    AdminID_reviewer = ?, 
                    reviewDate = NOW() 
                WHERE ReportID = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $status_id, $userId, $report_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Insert into FeedPost if approved
        if ($status_id === 2) {
            $insert_feed = "INSERT INTO FeedPost (ReportID) VALUES (?)";
            $stmt_feed = mysqli_prepare($connection, $insert_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $report_id);
            mysqli_stmt_execute($stmt_feed);
            mysqli_stmt_close($stmt_feed);
        }
    }
}

header("Location: dashboard.php");
exit();
