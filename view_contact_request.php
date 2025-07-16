<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$content_header = "Contact Request Details";
$currentUserId = getUserId();

// Get contact request ID from URL
$contactId = $_GET['id'] ?? '';

if (!$contactId || !is_numeric($contactId)) {
    header("Location: inbox.php?error=invalid_id");
    exit;
}

// Get contact request details
$sql = "SELECT 
            cr.ContactID, 
            cr.ownership_description, 
            cr.submission_date, 
            cr.review_status,
            cr.evidence_file_path,
            cr.review_notes,
            cr.review_date,
            r.item_name,
            r.description as item_description,
            r.incident_date,
            r.image_path,
            r.report_type,
            l.location_last_seen,
            f.location_found,
            fp.PostID,
            fp.post_date,
            c.claim_status,
            c.interrogation_notes,
            c.claim_date,
            c.resolution_date,
            admin_user.name as admin_name
        FROM ContactRequest cr
        JOIN FeedPost fp ON cr.PostID = fp.PostID
        JOIN Report r ON fp.ReportID = r.ReportID
        LEFT JOIN Lost l ON r.ReportID = l.ReportID AND r.report_type = 'Lost'
        LEFT JOIN Found f ON r.ReportID = f.ReportID AND r.report_type = 'Found'
        LEFT JOIN Claim c ON cr.ContactID = c.ContactID
        LEFT JOIN Administrator admin_a ON cr.AdminID_reviewer = admin_a.AdminID
        LEFT JOIN Person admin_user ON admin_a.AdminID = admin_user.PersonID
        WHERE cr.ContactID = ? AND cr.UserID_claimant = ?";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "ii", $contactId, $currentUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: inbox.php?error=not_found");
    exit;
}

$request = mysqli_fetch_assoc($result);

// Start output buffering to capture the page content
ob_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Contact Request Details</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }

        .view-contact-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .header-content h2 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .header-content p {
            margin: 0;
            color: #666;
        }

        .status-indicator {
            display: flex;
            align-items: center;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.status-pending { background: #fff3cd; color: #856404; }
        .status-badge.status-approved { background: #d4edda; color: #155724; }
        .status-badge.status-rejected { background: #f8d7da; color: #721c24; }

        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .type-badge.lost { background: #fff3cd; color: #856404; }
        .type-badge.found { background: #d4edda; color: #155724; }

        .contact-content {
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
            gap: 25px;
            align-items: flex-start;
        }

        .item-image {
            flex-shrink: 0;
            text-align: center;
        }

        .item-image img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .image-placeholder {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #666;
            border: 2px dashed #dee2e6;
        }

        .item-info,
        .request-details,
        .review-info,
        .claim-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .info-row strong {
            color: #333;
            min-width: 150px;
            font-weight: 600;
        }

        .info-row span {
            color: #666;
            flex: 1;
        }

        .message-content {
            background: #f8f9fc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            margin-top: 10px;
            color: #333;
        }

        .admin-notes {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-top: 10px;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
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

        .btn.primary {
            background: #007bff;
            color: white;
        }

        .btn.secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .contact-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .item-details {
                flex-direction: column;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
            }

            .info-row strong {
                min-width: auto;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="view-contact-container">
    <div class="contact-header">
        <div class="header-content">
            <h2>Contact Request Details</h2>
            <p>Review your contact request status and information</p>
        </div>
        <div class="status-indicator">
            <span class="status-badge status-<?php echo strtolower($request['review_status']); ?>">
                <?php echo htmlspecialchars($request['review_status']); ?>
            </span>
        </div>
    </div>

    <div class="contact-content">
        <!-- Item Details -->
        <div class="section-card">
            <h3>Item Information</h3>
            <div class="item-details">
                <?php if (!empty($request['image_path'])): ?>
                    <div class="item-image">
                        <?php
                        $imagePath = $request['image_path'];
                        if (strpos($imagePath, 'uploads/') === 0) {
                            $imageUrl = $imagePath;
                        } else {
                            $imageUrl = 'uploads/' . $imagePath;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="<?php echo htmlspecialchars($request['item_name']); ?>" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="image-placeholder" style="display:none;">
                            <p>Image not available</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="item-info">
                    <div class="info-row">
                        <strong>Item Name:</strong>
                        <span><?php echo htmlspecialchars($request['item_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Type:</strong>
                        <span class="type-badge <?php echo strtolower($request['report_type']); ?>">
                            <?php echo htmlspecialchars($request['report_type']); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <strong>Description:</strong>
                        <span><?php echo nl2br(htmlspecialchars($request['item_description'])); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Date <?php echo ucfirst(strtolower($request['report_type'])); ?>:</strong>
                        <span><?php echo formatDate($request['incident_date']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Location:</strong>
                        <span><?php 
                            if ($request['report_type'] === 'Lost') {
                                echo htmlspecialchars($request['location_last_seen'] ?? 'Not specified');
                            } else {
                                echo htmlspecialchars($request['location_found'] ?? 'Not specified');
                            }
                        ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Posted:</strong>
                        <span><?php echo formatDate($request['post_date']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Contact Request -->
        <div class="section-card">
            <h3>Your Contact Request</h3>
            <div class="request-details">
                <div class="info-row">
                    <strong>Submitted:</strong>
                    <span><?php echo formatDate($request['submission_date']); ?></span>
                </div>
                <div class="info-row">
                    <strong>Status:</strong>
                    <span class="status-badge <?php echo strtolower($request['review_status']); ?>">
                        <?php echo htmlspecialchars($request['review_status']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <strong>Your Ownership Description:</strong>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($request['ownership_description'])); ?>
                    </div>
                </div>
                <?php if (!empty($request['detailed_description'])): ?>
                    <div class="info-row">
                        <strong>Detailed Description:</strong>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($request['detailed_description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($request['evidence_file_path'])): ?>
                    <div class="info-row">
                        <strong>Evidence Details:</strong>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($request['evidence_file_path'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Review Information -->
        <?php if ($request['review_status'] !== 'Pending'): ?>
            <div class="section-card">
                <h3>Review Information</h3>
                <div class="review-info">
                    <div class="info-row">
                        <strong>Reviewed:</strong>
                        <span><?php echo formatDate($request['review_date']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Reviewed By:</strong>
                        <span><?php echo htmlspecialchars($request['admin_name'] ?? 'Administrator'); ?></span>
                    </div>
                    <?php if ($request['review_notes']): ?>
                        <div class="info-row">
                            <strong>Admin Notes:</strong>
                            <div class="admin-notes">
                                <?php echo nl2br(htmlspecialchars($request['review_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Claim Status -->
        <?php if ($request['claim_status']): ?>
            <div class="section-card">
                <h3>Claim Status</h3>
                <div class="claim-info">
                    <div class="info-row">
                        <strong>Claim Status:</strong>
                        <span class="status-badge <?php echo strtolower($request['claim_status']); ?>">
                            <?php echo htmlspecialchars($request['claim_status']); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <strong>Claim Date:</strong>
                        <span><?php echo formatDate($request['claim_date']); ?></span>
                    </div>
                    <?php if ($request['resolution_date']): ?>
                        <div class="info-row">
                            <strong>Resolution Date:</strong>
                            <span><?php echo formatDate($request['resolution_date']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="section-card">
            <h3>Actions</h3>
            <div class="action-buttons">
                <a href="inbox.php" class="btn secondary">
                    ← Back to Inbox
                </a>
                <?php if ($request['review_status'] === 'Approved' && (!$request['claim_status'] || $request['claim_status'] === 'Pending')): ?>
                    <a href="start_claim.php?contact_id=<?php echo $request['ContactID']; ?>" class="btn primary">
                        Start Claim Process
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

<?php
$page_content = ob_get_clean();
include_once "includes/general_layout.php";
?>
