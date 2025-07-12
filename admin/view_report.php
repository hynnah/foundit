<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "View Report";

// Get report ID
$reportId = $_GET['id'] ?? '';

if (!$reportId || !is_numeric($reportId)) {
    header("Location: review_reports.php");
    exit;
}

// Get report details
$sql = "SELECT r.*, 
               p_submitter.name as submitter_name,
               p_submitter.email as submitter_email,
               p_submitter.phone_number as submitter_phone,
               u_submitter.role as submitter_role,
               a.status_name,
               p_admin.name as reviewer_name,
               fp.PostID,
               fp.post_date,
               fp.post_status,
               l.location_last_seen,
               f.location_found
        FROM Report r
        JOIN User u_submitter ON r.UserID_submitter = u_submitter.UserID
        JOIN Person p_submitter ON u_submitter.UserID = p_submitter.PersonID
        JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        LEFT JOIN User u_admin ON r.AdminID_reviewer = u_admin.UserID
        LEFT JOIN Person p_admin ON u_admin.UserID = p_admin.PersonID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        LEFT JOIN Lost l ON r.ReportID = l.ReportID AND r.report_type = 'Lost'
        LEFT JOIN Found f ON r.ReportID = f.ReportID AND r.report_type = 'Found'
        WHERE r.ReportID = ?";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $reportId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: review_reports.php");
    exit;
}

$report = mysqli_fetch_assoc($result);

// Get contact requests for this report (if it's posted)
$contact_requests = null;
if ($report['PostID']) {
    $sql_contacts = "SELECT cr.*, 
                           p.name as claimant_name,
                           p.email as claimant_email,
                           u.role as claimant_role
                    FROM ContactRequest cr
                    JOIN User u ON cr.UserID_claimant = u.UserID
                    JOIN Person p ON u.UserID = p.PersonID
                    WHERE cr.PostID = ?
                    ORDER BY cr.submission_date DESC";
    
    $stmt_contacts = mysqli_prepare($connection, $sql_contacts);
    mysqli_stmt_bind_param($stmt_contacts, "i", $report['PostID']);
    mysqli_stmt_execute($stmt_contacts);
    $contact_requests = mysqli_stmt_get_result($stmt_contacts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - View Report</title>
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

    <div class="view-report-container">
        <div class="report-header">
            <div class="header-content">
                <h2><?php echo htmlspecialchars($report['item_name']); ?></h2>
                <div class="report-badges">
                    <span class="type-badge <?php echo strtolower($report['report_type']); ?>">
                        <?php echo $report['report_type']; ?>
                    </span>
                    <span class="status-badge <?php echo strtolower($report['status_name']); ?>">
                        <?php echo $report['status_name']; ?>
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($report['status_name'] === 'Pending'): ?>
                    <a href="review_reports.php?id=<?php echo $report['ReportID']; ?>" class="btn primary">
                        Review Report
                    </a>
                <?php endif; ?>
                <a href="review_reports.php" class="btn secondary">
                    ← Back to Reports
                </a>
            </div>
        </div>

        <div class="report-content">
            <!-- Item Details -->
            <div class="section-card">
                <h3>Item Details</h3>
                <div class="item-details">
                    <?php if (!empty($report['image_path'])): ?>
                        <div class="item-image">
                            <?php
                            // Handle different image path formats
                            $imagePath = $report['image_path'];
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
                        <div class="info-row">
                            <strong>Item Name:</strong>
                            <span><?php echo htmlspecialchars($report['item_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Description:</strong>
                            <span><?php echo nl2br(htmlspecialchars($report['description'] ?? 'No description provided')); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Report Type:</strong>
                            <span><?php echo htmlspecialchars($report['report_type']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Incident Date:</strong>
                            <span><?php echo formatDate($report['incident_date']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Submitted:</strong>
                            <span><?php echo formatDate($report['submission_date']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location & Date -->
            <div class="section-card">
                <h3>Location & Date Information</h3>
                <div class="location-info">
                    <div class="info-row">
                        <strong>Location:</strong>
                        <span><?php 
                            if ($report['report_type'] === 'Lost') {
                                echo htmlspecialchars($report['location_last_seen'] ?? 'Not specified');
                            } else {
                                echo htmlspecialchars($report['location_found'] ?? 'Not specified');
                            }
                        ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Date <?php echo ucfirst($report['report_type']); ?>:</strong>
                        <span><?php echo formatDate($report['incident_date']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Submission Date:</strong>
                        <span><?php echo formatDate($report['submission_date']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Submitter Information -->
            <div class="section-card">
                <h3>Submitter Information</h3>
                <div class="user-info">
                    <div class="info-row">
                        <strong>Name:</strong>
                        <span><?php echo htmlspecialchars($report['submitter_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Role:</strong>
                        <span><?php echo htmlspecialchars($report['submitter_role']); ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($report['submitter_email']); ?></span>
                    </div>
                    <?php if ($report['submitter_phone']): ?>
                        <div class="info-row">
                            <strong>Phone:</strong>
                            <span><?php echo htmlspecialchars($report['submitter_phone']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Report Status -->
            <div class="section-card">
                <h3>Report Status</h3>
                <div class="status-info">
                    <div class="info-row">
                        <strong>Current Status:</strong>
                        <span class="status-badge <?php echo strtolower($report['status_name']); ?>">
                            <?php echo $report['status_name']; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <strong>Submitted:</strong>
                        <span><?php echo formatDate($report['submission_date']); ?></span>
                    </div>
                    <?php if ($report['reviewDate']): ?>
                        <div class="info-row">
                            <strong>Reviewed:</strong>
                            <span><?php echo formatDate($report['reviewDate']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Reviewed By:</strong>
                            <span><?php echo htmlspecialchars($report['reviewer_name'] ?? 'Unknown'); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($report['reviewNote']): ?>
                        <div class="info-row">
                            <strong>Review Notes:</strong>
                            <div class="review-notes">
                                <?php echo nl2br(htmlspecialchars($report['reviewNote'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Feed Post Information -->
            <?php if ($report['PostID']): ?>
                <div class="section-card">
                    <h3>Feed Post Information</h3>
                    <div class="post-info">
                        <div class="info-row">
                            <strong>Post ID:</strong>
                            <span>#<?php echo $report['PostID']; ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Posted:</strong>
                            <span><?php echo formatDate($report['post_date']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Post Status:</strong>
                            <span class="status-badge <?php echo strtolower($report['post_status']); ?>">
                                <?php echo $report['post_status']; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Contact Requests -->
            <?php if ($contact_requests && mysqli_num_rows($contact_requests) > 0): ?>
                <div class="section-card">
                    <h3>Contact Requests (<?php echo mysqli_num_rows($contact_requests); ?>)</h3>
                    <div class="contact-requests">
                        <?php while ($contact = mysqli_fetch_assoc($contact_requests)): ?>
                            <div class="contact-item">
                                <div class="contact-header">
                                    <div class="contact-user">
                                        <strong><?php echo htmlspecialchars($contact['claimant_name']); ?></strong>
                                        <span class="username"><?php echo htmlspecialchars($contact['claimant_role']); ?></span>
                                    </div>
                                    <div class="contact-status">
                                        <span class="status-badge <?php echo strtolower($contact['review_status']); ?>">
                                            <?php echo $contact['review_status']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="contact-content">
                                    <div class="info-row">
                                        <strong>Ownership Description:</strong>
                                        <span><?php echo nl2br(htmlspecialchars($contact['ownership_description'] ?? 'No description provided')); ?></span>
                                    </div>
                                    <?php if (!empty($contact['detailed_description'])): ?>
                                        <div class="info-row">
                                            <strong>Detailed Description:</strong>
                                            <span><?php echo nl2br(htmlspecialchars($contact['detailed_description'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($contact['evidence_details'])): ?>
                                        <div class="info-row">
                                            <strong>Evidence Details:</strong>
                                            <span><?php echo nl2br(htmlspecialchars($contact['evidence_details'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="contact-meta">
                                        <small>Submitted: <?php echo formatDate($contact['submission_date']); ?></small>
                                        <a href="review_contact_request.php?id=<?php echo $contact['ContactID']; ?>" class="btn-sm primary">
                                            Review
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .view-report-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .report-header {
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

        .report-badges {
            display: flex;
            gap: 10px;
        }

        .type-badge,
        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .type-badge.lost { background: #fff3cd; color: #856404; }
        .type-badge.found { background: #d4edda; color: #155724; }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        .status-badge.active { background: #d4edda; color: #155724; }

        .header-actions {
            display: flex;
            gap: 15px;
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

        .report-content {
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
        }

        .item-image img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .item-info,
        .location-info,
        .user-info,
        .status-info,
        .post-info {
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
            min-width: 120px;
            font-weight: 600;
        }

        .info-row span {
            color: #666;
            flex: 1;
        }

        .review-notes {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
            margin-top: 10px;
        }

        .contact-requests {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .contact-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }

        .contact-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .contact-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .username {
            color: #666;
            font-size: 0.9em;
        }

        .contact-content p {
            margin: 10px 0;
            color: #333;
        }

        .contact-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .btn-sm {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8em;
            font-weight: 500;
        }

        .btn-sm.primary {
            background: #007bff;
            color: white;
        }

        @media (max-width: 768px) {
            .report-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
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
        }
    </style>

    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>

</html>
