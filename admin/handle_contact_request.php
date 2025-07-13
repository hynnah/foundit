<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this endpoint

require_once '../functions.php';
require_once '../dbh.inc.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Get and validate input
    $contactId = $_POST['contact_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (!$contactId || !is_numeric($contactId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid contact ID']);
        exit;
    }

    if (!in_array($action, ['approve', 'reject'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    // Check if contact request exists and is pending
    $sql_check = "SELECT cr.review_status, cr.UserID_claimant, r.item_name, r.UserID_submitter
                  FROM ContactRequest cr
                  JOIN FeedPost fp ON cr.PostID = fp.PostID
                  JOIN Report r ON fp.ReportID = r.ReportID
                  WHERE cr.ContactID = ?";
    
    $stmt_check = mysqli_prepare($connection, $sql_check);
    if (!$stmt_check) {
        throw new Exception('Failed to prepare check query: ' . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($stmt_check, "i", $contactId);
    
    if (!mysqli_stmt_execute($stmt_check)) {
        throw new Exception('Failed to execute check query: ' . mysqli_stmt_error($stmt_check));
    }
    
    $result = mysqli_stmt_get_result($stmt_check);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contact request not found']);
        exit;
    }
    
    $contact = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_check);
    
    if ($contact['review_status'] !== 'Pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contact request is not pending (current status: ' . $contact['review_status'] . ')']);
        exit;
    }
    
    // Set status based on action
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $adminId = getUserId();
    
    // Start transaction
    mysqli_autocommit($connection, FALSE);
    
    try {
        // Update contact request
        $sql_update = "UPDATE ContactRequest SET 
                       review_status = ?, 
                       AdminID_reviewer = ?, 
                       review_date = NOW(),
                       review_notes = ?
                       WHERE ContactID = ?";
        
        $stmt_update = mysqli_prepare($connection, $sql_update);
        if (!$stmt_update) {
            throw new Exception('Failed to prepare update query: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt_update, "sisi", $status, $adminId, $adminNotes, $contactId);
        
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception('Failed to update contact request: ' . mysqli_stmt_error($stmt_update));
        }
        
        $affected_rows = mysqli_stmt_affected_rows($stmt_update);
        mysqli_stmt_close($stmt_update);
        
        if ($affected_rows === 0) {
            throw new Exception('No rows were updated - contact request may not exist');
        }
        
        // If approved, create claim record
        if ($action === 'approve') {
            $sql_claim = "INSERT INTO Claim (ContactID, UserID_claimant, AdminID_processor, claim_date, claim_status) 
                         VALUES (?, ?, ?, NOW(), 'Processing')";
            $stmt_claim = mysqli_prepare($connection, $sql_claim);
            if (!$stmt_claim) {
                throw new Exception('Failed to prepare claim query: ' . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($stmt_claim, "iii", $contactId, $contact['UserID_claimant'], $adminId);
            
            if (!mysqli_stmt_execute($stmt_claim)) {
                throw new Exception('Failed to create claim record: ' . mysqli_stmt_error($stmt_claim));
            }
            mysqli_stmt_close($stmt_claim);
        }
        
        // Commit transaction
        mysqli_commit($connection);
        mysqli_autocommit($connection, TRUE);
        
        // Log the activity
        logActivity($adminId, 'Contact Request', "Contact request {$contactId} {$status} by admin");
        
        // Send email notification to claimant
        try {
            // Get claimant details for email
            $sql_claimant = "SELECT p.name, p.email FROM Person p 
                           JOIN User u ON p.PersonID = u.UserID 
                           WHERE u.UserID = ?";
            $stmt_claimant = mysqli_prepare($connection, $sql_claimant);
            mysqli_stmt_bind_param($stmt_claimant, "i", $contact['UserID_claimant']);
            mysqli_stmt_execute($stmt_claimant);
            $claimant_result = mysqli_stmt_get_result($stmt_claimant);
            
            if ($claimant_result && mysqli_num_rows($claimant_result) > 0) {
                $claimant = mysqli_fetch_assoc($claimant_result);
                
                $emailSent = sendContactRequestNotification(
                    $claimant['email'], 
                    $claimant['name'], 
                    $contact['item_name'], 
                    $status, 
                    $adminNotes
                );
                
                if ($emailSent) {
                    $message = "Contact request {$status} successfully and notification sent to claimant.";
                } else {
                    $message = "Contact request {$status} successfully, but email notification failed.";
                }
            } else {
                $message = "Contact request {$status} successfully, but could not send email notification.";
            }
        } catch (Exception $e) {
            $message = "Contact request {$status} successfully, but email notification failed: " . $e->getMessage();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        mysqli_autocommit($connection, TRUE);
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Contact request handling error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'A system error occurred. Please try again or contact support if the problem persists.',
        'debug' => $e->getMessage() // Remove this in production
    ]);
}
?>
