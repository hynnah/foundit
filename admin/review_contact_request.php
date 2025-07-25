<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';
require_once '../email_service.php';

$user_name = getUserName();
$content_header = "Review Contact Request";
// Get contact request ID
$contactId = $_GET['id'] ?? '';

if (!$contactId || !is_numeric($contactId)) {
    header("Location: inbox.php");
    exit;
}

// Get contact request details first
$sql = "SELECT cr.*, 
               p_claimant.name as claimant_name,
               p_claimant.email as claimant_email,
               p_claimant.phone_number as claimant_phone,
               u_claimant.role as claimant_role,
               r.item_name,
               r.description as item_description,
               r.report_type,
               l.location_last_seen,
               f.location_found,
               f.vague_item_name,
               r.incident_date,
               r.image_path,
               fp.PostID,
               p_submitter.name as submitter_name,
               p_submitter.email as submitter_email,
               u_submitter.role as submitter_role,
               p_admin.name as reviewer_name
        FROM ContactRequest cr
        JOIN User u_claimant ON cr.UserID_claimant = u_claimant.UserID
        JOIN Person p_claimant ON u_claimant.UserID = p_claimant.PersonID
        JOIN FeedPost fp ON cr.PostID = fp.PostID
        JOIN Report r ON fp.ReportID = r.ReportID
        JOIN User u_submitter ON r.UserID_submitter = u_submitter.UserID
        JOIN Person p_submitter ON u_submitter.UserID = p_submitter.PersonID
        LEFT JOIN Lost l ON r.ReportID = l.ReportID
        LEFT JOIN Found f ON r.ReportID = f.ReportID
        LEFT JOIN Administrator a ON cr.AdminID_reviewer = a.AdminID
        LEFT JOIN Person p_admin ON a.AdminID = p_admin.PersonID
        WHERE cr.ContactID = ?";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $contactId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: inbox.php");
    exit;
}

$contact_request = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'];
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        $adminId = getUserId();
        
        if (in_array($action, ['approve', 'reject'])) {
            $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';
            
            try {
                // Update contact request
                $sql = "UPDATE ContactRequest SET 
                        review_status = ?, 
                        AdminID_reviewer = ?, 
                        review_date = NOW(),
                        review_notes = ?
                        WHERE ContactID = ?";
                
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "sisi", $newStatus, $adminId, $adminNotes, $contactId);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log the action
                    logActivity($contactId, 'Contact Request', "Contact request $action by admin");
                    
                    // Send email notification to claimant
                    try {
                        // Get claimant email and details from the already loaded contact_request
                        $claimantEmail = $contact_request['claimant_email'];
                        $claimantName = $contact_request['claimant_name'];
                        $itemName = $contact_request['item_name'];
                        
                        $emailSent = sendContactRequestNotification($claimantEmail, $claimantName, $itemName, $newStatus, $adminNotes);
                        
                        if ($emailSent) {
                            $success_message = "Contact request has been $action successfully and notification sent to claimant.";
                        } else {
                            $success_message = "Contact request has been $action successfully, but email notification failed.";
                        }
                    } catch (Exception $e) {
                        $success_message = "Contact request has been $action successfully, but email notification failed: " . $e->getMessage();
                    }
                    
                    // Redirect to inbox after successful action
                    header("Location: inbox.php?success=" . urlencode($success_message));
                    exit;
                } else {
                    $error_message = "Error updating contact request.";
                }
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}

/**
 * Send contact request notification email to claimant
 */
function sendContactRequestNotification($to, $name, $itemName, $status, $adminNotes) {
    global $emailService;
    $subject = "Your Contact Request for '$itemName' - $status";
    $message = "<p>Dear " . htmlspecialchars($name) . ",</p>";
    $message .= "<p>Your contact request for the item '<strong>" . htmlspecialchars($itemName) . "</strong>' has been <strong>" . htmlspecialchars($status) . "</strong>.</p>";
    if (!empty($adminNotes)) {
        $message .= "<p><strong>Admin Notes:</strong><br>" . nl2br(htmlspecialchars($adminNotes)) . "</p>";
    }
    $message .= "<p>If you have any questions, please reply to this email.</p>";
    $message .= "<p>Thank you,<br>FoundIt Admin Team</p>";
    return $emailService->sendEmail($to, $subject, $message, true);
}

// Note: Verification questions feature not implemented in current database schema
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Review Contact Request</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>

    <div class="review-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="review-header">
            <h2>Review Contact Request</h2>
            <div class="status-badge <?php echo strtolower($contact_request['review_status']); ?>">
                <?php echo $contact_request['review_status']; ?>
            </div>
        </div>

        <div class="review-content">
            <!-- Item Details -->
            <div class="section-card">
                <h3>Item Details</h3>
                <div class="item-details">
                    <?php if ($contact_request['image_path']): ?>
                        <div class="item-image">
                            <?php
                            // Handle different image path formats
                            $imagePath = $contact_request['image_path'];
                            if (strpos($imagePath, 'uploads/') === 0 || strpos($imagePath, 'admin/uploads/') === 0) {
                                // Full path stored
                                $imageUrl = '../' . $imagePath;
                            } else {
                                // Just filename stored
                                $imageUrl = '../uploads/' . $imagePath;
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Item Image" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="display:none; padding:20px; background:#f8f9fa; border-radius:8px; text-align:center; color:#666;">
                                <p>Image not found</p>
                                <small>Path: <?php echo htmlspecialchars($imagePath); ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($contact_request['item_name']); ?></h4>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($contact_request['report_type']); ?></p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($contact_request['item_description']); ?></p>
                        <p><strong>Location:</strong> <?php 
                            if ($contact_request['report_type'] === 'Lost') {
                                echo htmlspecialchars($contact_request['location_last_seen'] ?? 'Not specified');
                            } else {
                                echo htmlspecialchars($contact_request['location_found'] ?? 'Not specified');
                            }
                        ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($contact_request['incident_date']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Claimant Information -->
            <div class="section-card">
                <h3>Claimant Information</h3>
                <div class="user-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($contact_request['claimant_name']); ?></p>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($contact_request['claimant_role']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($contact_request['claimant_email']); ?></p>
                    <?php if ($contact_request['claimant_phone']): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($contact_request['claimant_phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Original Submitter Information -->
            <div class="section-card">
                <h3>Original Submitter</h3>
                <div class="user-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($contact_request['submitter_name']); ?></p>
                    <p><strong>Role:</strong> <?php echo htmlspecialchars($contact_request['submitter_role']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($contact_request['submitter_email']); ?></p>
                </div>
            </div>

            <!-- Claimant's Verification Information -->
            <div class="section-card">
                <h3>Verification Information</h3>
                <p class="no-answers">Verification questions system is not currently implemented in this database schema.</p>
                <p class="info-note">Review the claimant's ownership description and evidence details above for verification purposes.</p>
            </div>

            <!-- Contact Request Details -->
            <div class="section-card">
                <h3>Contact Request Details</h3>
                <div class="request-details">
                    <p><strong>Submitted:</strong> <?php echo formatDate($contact_request['submission_date']); ?></p>
                    <p><strong>Ownership Description:</strong></p>
                    <div class="message-box">
                        <?php echo nl2br(htmlspecialchars($contact_request['ownership_description'] ?? 'No description provided')); ?>
                    </div>
                    
                    <?php if (!empty($contact_request['item_appearance'])): ?>
                        <p><strong>Item Appearance:</strong></p>
                        <div class="message-box">
                            <?php echo nl2br(htmlspecialchars($contact_request['item_appearance'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_request['location_lost'])): ?>
                        <p><strong>Location Lost:</strong></p>
                        <div class="message-box">
                            <?php echo htmlspecialchars($contact_request['location_lost']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_request['date_lost'])): ?>
                        <p><strong>Date Lost:</strong></p>
                        <div class="message-box">
                            <?php echo formatDate($contact_request['date_lost'], 'M d, Y'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_request['unique_marks'])): ?>
                        <p><strong>Unique Marks:</strong></p>
                        <div class="message-box">
                            <?php echo nl2br(htmlspecialchars($contact_request['unique_marks'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_request['evidence_file_path'])): ?>
                        <p><strong>Evidence File:</strong></p>
                        <div class="message-box">
                            <a href="<?php echo htmlspecialchars($contact_request['evidence_file_path']); ?>" target="_blank">
                                <?php echo htmlspecialchars($contact_request['evidence_file_name'] ?? 'View File'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($contact_request['review_status'] !== 'Pending'): ?>
                        <div class="review-info">
                            <p><strong>Reviewed by:</strong> <?php echo htmlspecialchars($contact_request['reviewer_name'] ?? 'Unknown'); ?></p>
                            <p><strong>Review Date:</strong> <?php echo formatDate($contact_request['review_date']); ?></p>
                            <?php if ($contact_request['review_notes']): ?>
                                <p><strong>Review Notes:</strong></p>
                                <div class="admin-notes">
                                    <?php echo nl2br(htmlspecialchars($contact_request['review_notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Review Actions -->
            <?php if ($contact_request['review_status'] === 'Pending'): ?>
                <div class="section-card">
                    <h3>Review Actions</h3>
                    <form method="POST" class="review-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="form-group">
                            <label for="admin_notes">Admin Notes (required for rejection):</label>
                            <textarea id="admin_notes" name="admin_notes" rows="3" placeholder="Add notes for approval or specify reason for rejection..." required></textarea>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="action" value="approve" class="btn success">Approve</button>
                            <div class="rejection-dropdown">
                                <button type="button" class="btn danger rejection-dropdown-toggle">Quick Reject ▼</button>
                                <div class="rejection-dropdown-menu">
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Insufficient Proof of Ownership">Insufficient Proof of Ownership</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Need More Information">Need More Information</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Description Doesn't Match Item">Description Doesn't Match Item</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Item Already Claimed">Item Already Claimed</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Invalid or Fraudulent Claim">Invalid or Fraudulent Claim</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Missing Required Documents">Missing Required Documents</a>
                                    <a href="#" class="rejection-dropdown-item reject-reason" data-reason="Other">Other (specify reason)...</a>
                                </div>
                            </div>
                            <button type="submit" name="action" value="reject" id="hiddenRejectBtn" style="display:none;"></button>
                        </div>
                    </form>
                </div>
                <style>
                .rejection-dropdown { position: relative; display: inline-block; }
                .rejection-dropdown-menu { display: none; position: absolute; left: 0; top: 100%; min-width: 220px; background: #fff; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.08); z-index: 100; border-radius: 4px; padding: 0.5em 0; }
                .rejection-dropdown-menu.show { display: block; }
                .rejection-dropdown-item { display: block; padding: 8px 16px; color: #333; text-decoration: none; cursor: pointer; transition: background 0.2s; }
                .rejection-dropdown-item:hover { background: #f8d7da; color: #721c24; }
                </style>
                <script>
                document.addEventListener('click', function(e) {
                    // Dropdown toggle
                    if (e.target.classList.contains('rejection-dropdown-toggle')) {
                        e.preventDefault();
                        const button = e.target;
                        const dropdown = button.closest('.rejection-dropdown');
                        const menu = dropdown.querySelector('.rejection-dropdown-menu');
                        // Close other dropdowns
                        document.querySelectorAll('.rejection-dropdown-menu').forEach(m => { if (m !== menu) m.classList.remove('show'); });
                        menu.classList.toggle('show');
                        button.style.backgroundColor = menu.classList.contains('show') ? '#c82333' : '#dc3545';
                    }
                    // Dropdown item click
                    if (e.target.classList.contains('reject-reason')) {
                        e.preventDefault();
                        const reasonText = e.target.getAttribute('data-reason');
                        // Set reason in notes
                        const notes = document.getElementById('admin_notes');
                        if (notes) notes.value = reasonText;
                        // Close dropdown
                        const dropdown = e.target.closest('.rejection-dropdown');
                        const dropdownToggle = dropdown.querySelector('.rejection-dropdown-toggle');
                        dropdown.querySelector('.rejection-dropdown-menu').classList.remove('show');
                        dropdownToggle.style.backgroundColor = '#dc3545';
                        // Submit reject form
                        document.getElementById('hiddenRejectBtn').click();
                    }
                });
                // Close dropdowns when clicking outside
                window.addEventListener('click', function(e) {
                    if (!e.target.closest('.rejection-dropdown')) {
                        document.querySelectorAll('.rejection-dropdown-menu').forEach(m => m.classList.remove('show'));
                        document.querySelectorAll('.rejection-dropdown-toggle').forEach(b => b.style.backgroundColor = '#dc3545');
                    }
                });
                </script>
            <?php endif; ?>
        </div>

        <div class="back-actions">
            <a href="inbox.php" class="btn secondary">← Back to Inbox</a>
        </div>
    </div>

    <style>
        .review-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .review-header h2 {
            margin: 0;
            color: #333;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9em;
        }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }

        .review-content {
            display: grid;
            gap: 20px;
        }

        .section-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .section-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 10px;
        }

        .item-details {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .item-image {
            flex-shrink: 0;
        }

        .item-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .item-info, .user-info {
            flex: 1;
        }

        .item-info h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.3em;
        }

        .item-info p, .user-info p {
            margin: 8px 0;
            color: #666;
        }

        .item-info strong, .user-info strong {
            color: #333;
        }

        .verification-answers {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .answer-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .answer-item strong {
            color: #333;
            display: block;
            margin-bottom: 8px;
        }

        .answer-item p {
            margin: 0;
            color: #666;
        }

        .no-answers {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        .info-note {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #17a2b8;
        }

        .message-box, .admin-notes {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-top: 10px;
        }

        .review-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .review-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 500;
            color: #333;
        }

        .form-group textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            font-family: inherit;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn.success {
            background: #28a745;
            color: white;
        }

        .btn.danger {
            background: #dc3545;
            color: white;
        }

        .btn.secondary {
            background: #6c757d;
            color: white;
        }

        .btn.info {
            background: #17a2b8;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .back-actions {
            margin-top: 30px;
            text-align: center;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .rejection-dropdown {
            position: relative;
            display: inline-block;
        }

        .rejection-dropdown-menu {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 100;
            min-width: 200px;
        }

        .rejection-dropdown-item {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
        }

        .rejection-dropdown-item:hover {
            background: #f1f1f1;
        }

        @media (max-width: 768px) {
            .review-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .item-details {
                flex-direction: column;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>

    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>

</html>
