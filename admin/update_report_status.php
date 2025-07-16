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
    
    // Validate reportId
    if (!is_numeric($reportId) || $reportId <= 0) {
        $response = ['success' => false, 'message' => 'Invalid report ID provided'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    $reportId = intval($reportId);
    
    if ($action === 'archive' && $reportId) {
        // Begin transaction for better error handling
        mysqli_begin_transaction($connection);
        
        try {
            // First check if the report exists
            $check_sql = "SELECT ReportID, archiveYN, claimedYN FROM Report WHERE ReportID = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $reportId);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                throw new Exception("Report with ID " . $reportId . " not found");
            }
            
            $report_data = mysqli_fetch_assoc($check_result);
            
            // Update report to archived status
            $sql = "UPDATE Report SET archiveYN = 1, archiveDate = NOW() WHERE ReportID = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update Report table: " . mysqli_error($connection));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows === 0) {
                throw new Exception("No report was updated. Report ID: " . $reportId . " might already be archived.");
            }
            
            // Also update the feed post status to archived
            $sql_feed = "UPDATE FeedPost SET post_status = 'Archived' WHERE ReportID = ?";
            $stmt_feed = mysqli_prepare($connection, $sql_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt_feed)) {
                throw new Exception("Failed to update FeedPost table: " . mysqli_error($connection));
            }
            
            // Commit the transaction
            mysqli_commit($connection);
            
            $response = ['success' => true, 'message' => 'Report archived successfully. Report ID: ' . $reportId . ', affected rows: ' . $affected_rows];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connection);
            $response = ['success' => false, 'message' => 'Error archiving report: ' . $e->getMessage()];
        }
    } elseif ($action === 'unarchive' && $reportId) {
        // Begin transaction for better error handling
        mysqli_begin_transaction($connection);
        
        try {
            // First check if the report exists
            $check_sql = "SELECT ReportID, archiveYN, claimedYN FROM Report WHERE ReportID = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $reportId);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                throw new Exception("Report with ID " . $reportId . " not found");
            }
            
            $report_data = mysqli_fetch_assoc($check_result);
            
            // Update report to unarchived status
            $sql = "UPDATE Report SET archiveYN = 0, archiveDate = NULL WHERE ReportID = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update Report table: " . mysqli_error($connection));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows === 0) {
                throw new Exception("No report was updated. Report ID: " . $reportId . " might already be unarchived.");
            }
            
            // Also update the feed post status to active
            $sql_feed = "UPDATE FeedPost SET post_status = 'Active' WHERE ReportID = ?";
            $stmt_feed = mysqli_prepare($connection, $sql_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt_feed)) {
                throw new Exception("Failed to update FeedPost table: " . mysqli_error($connection));
            }
            
            // Commit the transaction
            mysqli_commit($connection);
            
            $response = ['success' => true, 'message' => 'Report unarchived successfully. Report ID: ' . $reportId . ', affected rows: ' . $affected_rows];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connection);
            $response = ['success' => false, 'message' => 'Error unarchiving report: ' . $e->getMessage()];
        }
    } elseif ($action === 'mark_claimed' && $reportId) {
        // Begin transaction
        mysqli_begin_transaction($connection);
        
        try {
            // First check if the report exists
            $check_sql = "SELECT ReportID, archiveYN, claimedYN FROM Report WHERE ReportID = ?";
            $check_stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $reportId);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) === 0) {
                throw new Exception("Report with ID " . $reportId . " not found");
            }
            
            $report_data = mysqli_fetch_assoc($check_result);
            
            // Update report to claimed status
            $sql = "UPDATE Report SET claimedYN = 1 WHERE ReportID = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update Report table: " . mysqli_error($connection));
            }
            
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows === 0) {
                throw new Exception("No report was updated. Report ID: " . $reportId . " might already be claimed.");
            }
            
            // Also update the feed post status to claimed
            $sql_feed = "UPDATE FeedPost SET post_status = 'Claimed' WHERE ReportID = ?";
            $stmt_feed = mysqli_prepare($connection, $sql_feed);
            mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
            
            if (!mysqli_stmt_execute($stmt_feed)) {
                throw new Exception("Failed to update FeedPost table: " . mysqli_error($connection));
            }
            
            // Commit transaction
            mysqli_commit($connection);
            $response = ['success' => true, 'message' => 'Report marked as claimed successfully. Report ID: ' . $reportId . ', affected rows: ' . $affected_rows];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connection);
            $response = ['success' => false, 'message' => 'Error marking report as claimed: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// If not POST request, redirect to reports page
header('Location: reports.php');
exit;
?>
