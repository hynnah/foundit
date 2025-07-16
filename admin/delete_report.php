<?php
require_once '../auth_check.php';
requireAdmin();

require_once '../functions.php';
require_once '../dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    $reportId = $_POST['report_id'] ?? '';
    
    if ($action === 'soft_delete' && $reportId) {
        // Soft delete: Mark report as deleted
        $sql = "UPDATE Report SET deletedYN = 1, deletedDate = NOW() WHERE ReportID = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $reportId);
        
        if (mysqli_stmt_execute($stmt)) {
            // Also update the feed post status to deleted
            $sql_feed = "UPDATE FeedPost SET post_status = 'Deleted' WHERE ReportID = ?";
            $stmt_feed = mysqli_prepare($connection, $sql_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
            mysqli_stmt_execute($stmt_feed);
            
            $response = ['success' => true, 'message' => 'Report soft deleted successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Error deleting report'];
        }
    } elseif ($action === 'restore' && $reportId) {
        // Restore: Unmark report as deleted
        $sql = "UPDATE Report SET deletedYN = 0, deletedDate = NULL WHERE ReportID = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $reportId);
        
        if (mysqli_stmt_execute($stmt)) {
            // Also update the feed post status back to active
            $sql_feed = "UPDATE FeedPost SET post_status = 'Active' WHERE ReportID = ?";
            $stmt_feed = mysqli_prepare($connection, $sql_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
            mysqli_stmt_execute($stmt_feed);
            
            $response = ['success' => true, 'message' => 'Report restored successfully'];
        } else {
            $response = ['success' => false, 'message' => 'Error restoring report'];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If not POST request, redirect to reports page
header('Location: review_reports.php');
exit;
?>
