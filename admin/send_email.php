<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this endpoint

require_once '../functions.php';
require_once '../dbh.inc.php';

// Set JSON response header
header('Content-Type: application/json');

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
$emailType = $_POST['email_type'] ?? '';
$customMessage = trim($_POST['custom_message'] ?? '');

if (!$contactId || !is_numeric($contactId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid contact ID']);
    exit;
}

if (!in_array($emailType, ['status_update', 'custom', 'request_info', 'claim_reminder'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email type']);
    exit;
}

try {
    // Get contact request details
    $sql = "SELECT 
                cr.ContactID,
                cr.review_status,
                cr.review_notes,
                p.name as claimant_name,
                p.email as claimant_email,
                r.item_name,
                r.description as item_description
            FROM ContactRequest cr
            JOIN User u ON cr.UserID_claimant = u.UserID
            JOIN Person p ON u.UserID = p.PersonID
            JOIN FeedPost fp ON cr.PostID = fp.PostID
            JOIN Report r ON fp.ReportID = r.ReportID
            WHERE cr.ContactID = ?";
    
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $contactId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Contact request not found']);
        exit;
    }
    
    $contact = mysqli_fetch_assoc($result);
    
    // Prepare email content based on type
    $subject = '';
    $message = '';
    
    switch ($emailType) {
        case 'status_update':
            $subject = "FoundIt - Contact Request Status Update";
            $message = buildStatusUpdateEmail($contact);
            break;
            
        case 'custom':
            $subject = "FoundIt - Message from Admin";
            $message = buildCustomEmail($contact, $customMessage);
            break;
            
        case 'request_info':
            $subject = "FoundIt - Additional Information Required";
            $message = buildRequestInfoEmail($contact);
            break;
            
        case 'claim_reminder':
            $subject = "FoundIt - Reminder: Complete Your Claim";
            $message = buildClaimReminderEmail($contact);
            break;
    }
    
    // Send the email
    $emailSent = sendEmailNotification($contact['claimant_email'], $subject, $message, true);
    
    if ($emailSent) {
        // Log the email activity
        logActivity(getUserId(), 'EMAIL_SENT', "Email sent to {$contact['claimant_name']} ({$contact['claimant_email']}) - Type: $emailType");
        
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully to ' . $contact['claimant_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send email'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function buildStatusUpdateEmail($contact) {
    $statusColor = $contact['review_status'] === 'Approved' ? '#28a745' : 
                   ($contact['review_status'] === 'Rejected' ? '#dc3545' : '#ffc107');
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; background: ' . $statusColor . '; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>FoundIt System</h1>
                <p>Contact Request Update</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($contact['claimant_name']) . ',</h2>
                
                <p>We have an update regarding your contact request for: <strong>' . htmlspecialchars($contact['item_name']) . '</strong></p>
                
                <p>Current Status: <span class="status-badge">' . htmlspecialchars($contact['review_status']) . '</span></p>
                
                ' . ($contact['review_notes'] ? '
                <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;">
                    <h4>Admin Notes:</h4>
                    <p>' . nl2br(htmlspecialchars($contact['review_notes'])) . '</p>
                </div>
                ' : '') . '
                
                <p>Please log in to your FoundIt account to view the full details.</p>
                
                <p>
                    <a href="' . $_SERVER['HTTP_HOST'] . '/foundit/login.php" class="btn">Access Your Account</a>
                </p>
            </div>
            
            <div class="footer">
                <p>This is an automated message from the FoundIt Lost & Found System.</p>
            </div>
        </div>
    </body>
    </html>';
}

function buildCustomEmail($contact, $customMessage) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>FoundIt Admin</h1>
                <p>Message from Administrator</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($contact['claimant_name']) . ',</h2>
                
                <p>Regarding your contact request for: <strong>' . htmlspecialchars($contact['item_name']) . '</strong></p>
                
                <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;">
                    <h4>Message from Admin:</h4>
                    <p>' . nl2br(htmlspecialchars($customMessage)) . '</p>
                </div>
                
                <p>
                    <a href="' . $_SERVER['HTTP_HOST'] . '/foundit/login.php" class="btn">Access Your Account</a>
                </p>
            </div>
            
            <div class="footer">
                <p>This message was sent by a FoundIt administrator.</p>
            </div>
        </div>
    </body>
    </html>';
}

function buildRequestInfoEmail($contact) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>FoundIt System</h1>
                <p>Additional Information Required</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($contact['claimant_name']) . ',</h2>
                
                <p>We need additional information to process your contact request for: <strong>' . htmlspecialchars($contact['item_name']) . '</strong></p>
                
                <p>Please provide more details about your ownership of this item to help us verify your claim.</p>
                
                <p>
                    <a href="' . $_SERVER['HTTP_HOST'] . '/foundit/login.php" class="btn">Update Your Request</a>
                </p>
            </div>
            
            <div class="footer">
                <p>This is an automated message from the FoundIt Lost & Found System.</p>
            </div>
        </div>
    </body>
    </html>';
}

function buildClaimReminderEmail($contact) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>FoundIt System</h1>
                <p>Claim Reminder</p>
            </div>
            
            <div class="content">
                <h2>Hello ' . htmlspecialchars($contact['claimant_name']) . ',</h2>
                
                <p>Your contact request for <strong>' . htmlspecialchars($contact['item_name']) . '</strong> has been approved!</p>
                
                <p>Please complete your claim process as soon as possible to retrieve your item.</p>
                
                <p>
                    <a href="' . $_SERVER['HTTP_HOST'] . '/foundit/login.php" class="btn">Complete Your Claim</a>
                </p>
            </div>
            
            <div class="footer">
                <p>This is an automated reminder from the FoundIt Lost & Found System.</p>
            </div>
        </div>
    </body>
    </html>';
}
?>
