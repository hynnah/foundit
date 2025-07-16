<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Contact Requests Management";
$currentUserId = getUserId();

// Admin: Show all contact requests that need review
$sql = "SELECT 
            cr.ContactID, 
            cr.ownership_description, 
            cr.submission_date, 
            cr.review_status,
            cr.item_appearance,
            cr.location_lost,
            cr.date_lost,
            cr.evidence_file_path,
            cr.evidence_file_name,
            cr.unique_marks,
            cr.review_notes,
            cr.review_date,
            p.name as claimant_name,
            p.email as claimant_email,
            p.phone_number as claimant_phone,
            r.item_name,
            r.description as item_description,
            r.image_path,
            r.ReportID,
            fp.PostID,
            c.claim_status,
            c.interrogation_notes,
            c.passed_interrogationYN,
            c.resolution_date
        FROM ContactRequest cr
        JOIN User u ON cr.UserID_claimant = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        JOIN FeedPost fp ON cr.PostID = fp.PostID
        JOIN Report r ON fp.ReportID = r.ReportID
        LEFT JOIN Claim c ON cr.ContactID = c.ContactID
        WHERE r.archiveYN = 0
        ORDER BY 
            CASE WHEN cr.review_status = 'Pending' THEN 1 
                 WHEN cr.review_status = 'Approved' THEN 2 
                 ELSE 3 END,
            cr.submission_date DESC";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get stats
$stats_sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN review_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN review_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
              FROM ContactRequest";
$stats_result = mysqli_query($connection, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Admin Contact Requests</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .inbox-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .inbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007bff;
        }
        
        .inbox-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 80px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .request-card {
            background: white;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .request-card.urgent {
            border-left-color: #dc3545;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .request-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-processing { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .request-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-weight: bold;
            color: #333;
        }
        
        .request-content {
            margin-bottom: 15px;
        }
        
        .request-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        
        .empty-state img {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .details-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .details-section h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        .priority-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .priority-high { background: #dc3545; }
        .priority-medium { background: #ffc107; }
        .priority-low { background: #28a745; }
        
        .item-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filter-row select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Rejection Dropdown Styles */
        .rejection-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .rejection-dropdown-toggle {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            position: relative;
            z-index: 1;
        }
        
        .rejection-dropdown-toggle:hover {
            background: #c82333;
        }
        
        .rejection-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 250px;
            display: none;
            margin-top: 2px;
        }
        
        .rejection-dropdown-menu.show {
            display: block;
        }
        
        .rejection-dropdown-item {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .rejection-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .rejection-dropdown-item:hover {
            background: #f8f9fa;
            color: #dc3545;
        }
        
        /* Email Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .close {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        
        .close:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .email-status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            display: none;
        }
        
        .email-status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .email-status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .email-type-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1001;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
        }
        
        .notification.info {
            background: linear-gradient(135deg, #007bff, #6f42c1);
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>
    
    <div class="inbox-container">
        <div class="inbox-header">
            <h2>Contact Requests Management</h2>
            <div class="inbox-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['rejected_requests'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-row">
                <label>Filter by Status:</label>
                <select id="statusFilter" onchange="filterRequests()">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
                
                <label>Sort by:</label>
                <select id="sortFilter" onchange="sortRequests()">
                    <option value="date_desc">Newest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="status">Status</option>
                </select>
            </div>
        </div>
        
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div id="requestContainer">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="request-card <?php echo $row['review_status'] === 'Pending' ? 'urgent' : ''; ?>" 
                         data-status="<?php echo $row['review_status']; ?>"
                         data-date="<?php echo strtotime($row['submission_date']); ?>">
                        <div class="request-header">
                            <div class="request-title">
                                <span class="priority-indicator priority-<?php echo $row['review_status'] === 'Pending' ? 'high' : 'medium'; ?>"></span>
                                <?php echo htmlspecialchars($row['item_name']); ?>
                            </div>
                            <div class="status-badge status-<?php echo strtolower($row['review_status']); ?>">
                                <?php echo htmlspecialchars($row['review_status']); ?>
                            </div>
                        </div>
                        
                        <div class="request-meta">
                            <div class="meta-item">
                                <span class="meta-label">Claimant</span>
                                <span><?php echo htmlspecialchars($row['claimant_name']); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Email</span>
                                <span><?php echo htmlspecialchars($row['claimant_email']); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Phone</span>
                                <span><?php echo htmlspecialchars($row['claimant_phone'] ?? 'Not provided'); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Submitted</span>
                                <span><?php echo formatDate($row['submission_date'], 'M d, Y h:i A'); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Contact ID</span>
                                <span>#<?php echo $row['ContactID']; ?></span>
                            </div>
                        </div>
                        
                        <div class="request-content">
                            <p><strong>Item Description:</strong> <?php echo htmlspecialchars($row['item_description']); ?></p>
                            <p><strong>Ownership Description:</strong> <?php echo htmlspecialchars($row['ownership_description']); ?></p>
                            
                            <?php if ($row['item_appearance']): ?>
                                <p><strong>Item Appearance:</strong> <?php echo htmlspecialchars($row['item_appearance']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($row['location_lost']): ?>
                                <p><strong>Location Lost:</strong> <?php echo htmlspecialchars($row['location_lost']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($row['date_lost']): ?>
                                <p><strong>Date Lost:</strong> <?php echo formatDate($row['date_lost'], 'M d, Y'); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($row['unique_marks']): ?>
                                <p><strong>Unique Marks:</strong> <?php echo htmlspecialchars($row['unique_marks']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($row['evidence_file_path']): ?>
                                <p><strong>Evidence File:</strong> 
                                    <a href="<?php echo htmlspecialchars($row['evidence_file_path']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($row['evidence_file_name'] ?? 'View File'); ?>
                                    </a>
                                </p>
                                <?php
                                // Check if evidence file is an image
                                $evidenceFile = $row['evidence_file_path'];
                                $evidenceExt = strtolower(pathinfo($evidenceFile, PATHINFO_EXTENSION));
                                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                                if (in_array($evidenceExt, $imageExtensions)):
                                ?>
                                    <div style="margin-top: 10px;">
                                        <p><strong>Evidence Preview:</strong></p>
                                        <img src="<?php echo htmlspecialchars($evidenceFile); ?>" 
                                             alt="Evidence Preview" 
                                             class="item-image"
                                             style="max-width: 150px; max-height: 150px; border: 2px solid #007bff; border-radius: 8px; cursor: pointer;"
                                             onclick="openImageModal(this.src, '<?php echo htmlspecialchars($row['evidence_file_name'] ?? 'Evidence Image'); ?>')"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div style="display:none; padding:10px; background:#f8f9fa; border-radius:4px; text-align:center; color:#666; font-size:12px;">
                                            <p>Evidence image not found</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($row['image_path']): ?>
                                <p><strong>Item Image:</strong></p>
                                <?php
                                // Handle different image path formats
                                $imagePath = $row['image_path'];
                                if (strpos($imagePath, 'uploads/') === 0 || strpos($imagePath, 'admin/uploads/') === 0) {
                                    // Full path stored
                                    $imageUrl = '../' . $imagePath;
                                } else {
                                    // Just filename stored
                                    $imageUrl = '../uploads/' . $imagePath;
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Item Image" class="item-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <div style="display:none; padding:10px; background:#f8f9fa; border-radius:4px; text-align:center; color:#666; font-size:12px;">
                                    <p>Image not found</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($row['review_notes']): ?>
                                <div class="details-section">
                                    <h4>Review Notes:</h4>
                                    <p><?php echo htmlspecialchars($row['review_notes']); ?></p>
                                    <?php if ($row['review_date']): ?>
                                        <p><small>Reviewed on: <?php echo formatDate($row['review_date'], 'M d, Y h:i A'); ?></small></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($row['claim_status']): ?>
                                <div class="details-section">
                                    <h4>Claim Status: 
                                        <span class="status-badge status-<?php echo strtolower($row['claim_status']); ?>">
                                            <?php echo htmlspecialchars($row['claim_status']); ?>
                                        </span>
                                    </h4>
                                    
                                    <?php if ($row['interrogation_notes']): ?>
                                        <p><strong>Interrogation Notes:</strong> <?php echo htmlspecialchars($row['interrogation_notes']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['passed_interrogationYN'] !== null): ?>
                                        <p><strong>Interrogation Result:</strong> 
                                            <span class="status-badge status-<?php echo $row['passed_interrogationYN'] ? 'approved' : 'rejected'; ?>">
                                                <?php echo $row['passed_interrogationYN'] ? 'Passed' : 'Failed'; ?>
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['resolution_date']): ?>
                                        <p><strong>Resolution Date:</strong> <?php echo formatDate($row['resolution_date'], 'M d, Y h:i A'); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="request-actions">
                            <!-- Single Review/View Button -->
                            <?php if ($row['review_status'] === 'Pending'): ?>
                                <a href="review_contact_request.php?id=<?php echo $row['ContactID']; ?>" class="btn btn-primary">Review Request</a>
                            <?php else: ?>
                                <a href="view_contact_request.php?id=<?php echo $row['ContactID']; ?>" class="btn btn-primary">View Details</a>
                            <?php endif; ?>
                            
                            <!-- Quick Actions for Pending Requests -->
                            <?php if ($row['review_status'] === 'Pending'): ?>
                                <button type="button" class="btn btn-success quick-approve" data-contact-id="<?php echo $row['ContactID']; ?>">Quick Approve</button>
                                <div class="rejection-dropdown">
                                    <button type="button" class="btn btn-danger rejection-dropdown-toggle" data-contact-id="<?php echo $row['ContactID']; ?>" data-claimant-name="<?php echo htmlspecialchars($row['claimant_name']); ?>">
                                        Quick Reject ▼
                                    </button>
                                    <div class="rejection-dropdown-menu">
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="insufficient_proof">Insufficient Proof of Ownership</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="need_more_info">Need More Information</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="description_mismatch">Description Doesn't Match Item</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="already_claimed">Item Already Claimed</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="invalid_claim">Invalid or Fraudulent Claim</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="missing_documents">Missing Required Documents</a>
                                        <a href="#" class="rejection-dropdown-item reject-reason" data-reason="other">Other (specify reason)...</a>
                                    </div>
                                </div>
                            <?php elseif ($row['review_status'] === 'Approved'): ?>
                                <a href="create_claim.php?contact_id=<?php echo $row['ContactID']; ?>" class="btn btn-warning">
                                    <?php if (!$row['claim_status']): ?>
                                        <i class="fas fa-clipboard-check"></i> Process Claim
                                    <?php else: ?>
                                        <i class="fas fa-cog"></i> Manage Claim
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-info send-email-btn" data-contact-id="<?php echo $row['ContactID']; ?>" data-claimant-name="<?php echo htmlspecialchars($row['claimant_name']); ?>">Send Email</button>
                            
                            <!-- Archive/Soft Delete Button -->
                            <button type="button" class="btn btn-secondary archive-btn" data-contact-id="<?php echo $row['ContactID']; ?>" data-report-id="<?php echo $row['ReportID']; ?>">Archive</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <img src="../resources/env.png" alt="Empty Inbox">
                <h3>No Contact Requests Yet</h3>
                <p>When users submit contact requests to claim items, they will appear here for your review.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Email Modal -->
    <div id="emailModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Send Email to <span id="modalClaimantName"></span></h3>
                <span class="close" onclick="closeEmailModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="emailForm">
                    <input type="hidden" id="modalContactId" name="contact_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="email-status" id="emailStatus"></div>
                    
                    <div class="form-group">
                        <label for="email_type">Email Type:</label>
                        <select id="email_type" name="email_type" required>
                            <option value="">Select email type...</option>
                            <option value="status_update">Status Update</option>
                            <option value="request_info">Request Additional Information</option>
                            <option value="claim_reminder">Claim Reminder</option>
                            <option value="custom">Custom Message</option>
                        </select>
                        <div class="email-type-info" id="emailTypeInfo"></div>
                    </div>
                    
                    <div class="form-group" id="customMessageGroup" style="display: none;">
                        <label for="custom_message">Custom Message:</label>
                        <textarea id="custom_message" name="custom_message" rows="4" placeholder="Enter your custom message here..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="sendEmailBtn">Send Email</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Image Preview Modal -->
    <div id="imageModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 90vw; max-height: 90vh;">
            <div class="modal-header">
                <h3 id="imageModalTitle">Image Preview</h3>
                <span class="close" onclick="closeImageModal()">&times;</span>
            </div>
            <div class="modal-body" style="text-align: center; padding: 20px;">
                <img id="modalImage" src="" alt="Preview" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            </div>
        </div>
    </div>
    
    <script>
        // Notification system
        function showNotification(type, message) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(n => n.remove());
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }
        
        function filterRequests() {
            const filter = document.getElementById('statusFilter').value;
            const requests = document.querySelectorAll('.request-card');
            
            requests.forEach(request => {
                const status = request.getAttribute('data-status');
                if (filter === '' || status === filter) {
                    request.style.display = 'block';
                } else {
                    request.style.display = 'none';
                }
            });
        }
        
        function sortRequests() {
            const sortBy = document.getElementById('sortFilter').value;
            const container = document.getElementById('requestContainer');
            const requests = Array.from(container.querySelectorAll('.request-card'));
            
            requests.sort((a, b) => {
                switch (sortBy) {
                    case 'date_desc':
                        return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                    case 'date_asc':
                        return parseInt(a.getAttribute('data-date')) - parseInt(b.getAttribute('data-date'));
                    case 'status':
                        const statusOrder = { 'Pending': 1, 'Approved': 2, 'Rejected': 3 };
                        return statusOrder[a.getAttribute('data-status')] - statusOrder[b.getAttribute('data-status')];
                    default:
                        return 0;
                }
            });
            
            requests.forEach(request => container.appendChild(request));
        }
        
        // Auto-refresh every 30 seconds for pending requests (only if no modal is open)
        setInterval(() => {
            const pendingRequests = document.querySelectorAll('.request-card[data-status="Pending"]');
            const isModalOpen = document.getElementById('emailModal').style.display === 'flex';
            
            if (pendingRequests.length > 0 && !isModalOpen) {
                // Only refresh if user hasn't been active in the last 10 seconds
                const timeSinceLastActivity = Date.now() - (window.lastActivity || 0);
                if (timeSinceLastActivity > 10000) {
                    location.reload();
                }
            }
        }, 30000);
        
        // Track user activity
        window.lastActivity = Date.now();
        document.addEventListener('click', function() {
            window.lastActivity = Date.now();
        });
        document.addEventListener('keypress', function() {
            window.lastActivity = Date.now();
        });
        
        // Handle rejection dropdown toggles
        document.addEventListener('click', function(e) {
            // Check if the clicked element is a rejection dropdown button or its child
            let target = e.target;
            if (target.classList.contains('rejection-dropdown-toggle') || target.closest('.rejection-dropdown-toggle')) {
                e.preventDefault();
                
                // Get the actual dropdown button
                const button = target.classList.contains('rejection-dropdown-toggle') ? target : target.closest('.rejection-dropdown-toggle');
                const dropdown = button.closest('.rejection-dropdown');
                const menu = dropdown.querySelector('.rejection-dropdown-menu');
                
                // Close all other rejection dropdowns
                document.querySelectorAll('.rejection-dropdown-menu').forEach(m => {
                    if (m !== menu) {
                        m.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                menu.classList.toggle('show');
                
                // Add visual feedback
                if (menu.classList.contains('show')) {
                    button.style.backgroundColor = '#c82333';
                } else {
                    button.style.backgroundColor = '#dc3545';
                }
            }
            
            // Handle rejection dropdown item clicks
            if (e.target.classList.contains('reject-reason')) {
                e.preventDefault();
                const dropdown = e.target.closest('.rejection-dropdown');
                const dropdownToggle = dropdown.querySelector('.rejection-dropdown-toggle');
                const contactId = dropdownToggle.getAttribute('data-contact-id');
                const claimantName = dropdownToggle.getAttribute('data-claimant-name');
                const reason = e.target.getAttribute('data-reason');
                const reasonText = e.target.textContent;
                
                // Close dropdown and reset button color
                dropdown.querySelector('.rejection-dropdown-menu').classList.remove('show');
                dropdownToggle.style.backgroundColor = '#dc3545';
                
                // Handle rejection
                handleQuickReject(contactId, claimantName, reason, reasonText);
            }
            
            // Handle quick approve
            if (e.target.classList.contains('quick-approve')) {
                const contactId = e.target.getAttribute('data-contact-id');
                const button = e.target;
                const originalText = button.textContent;
                
                if (confirm('Are you sure you want to approve this contact request?')) {
                    // Disable button and show loading state
                    button.disabled = true;
                    button.textContent = 'Approving...';
                    button.style.opacity = '0.6';
                    
                    const formData = new FormData();
                    formData.append('contact_id', contactId);
                    formData.append('action', 'approve');
                    formData.append('admin_notes', 'Quick approve from inbox');
                    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                    
                    fetch('handle_contact_request.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('success', data.message);
                            // Update UI as needed
                            location.reload();
                        } else {
                            showNotification('error', data.message || 'An error occurred');
                            button.disabled = false;
                            button.textContent = originalText;
                            button.style.opacity = '1';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('error', 'An error occurred while processing the request');
                        button.disabled = false;
                        button.textContent = originalText;
                        button.style.opacity = '1';
                    });
                }
            }
            
            // Handle send email buttons
            if (e.target.classList.contains('send-email-btn')) {
                const contactId = e.target.getAttribute('data-contact-id');
                const claimantName = e.target.getAttribute('data-claimant-name');
                
                openEmailModal(contactId, claimantName);
            }
            
            // Handle archive buttons
            if (e.target.classList.contains('archive-btn')) {
                const contactId = e.target.getAttribute('data-contact-id');
                const reportId = e.target.getAttribute('data-report-id');
                
                if (confirm('Are you sure you want to archive this report? This will remove it from the active view but can be restored later.')) {
                    // Disable button and show loading state
                    const button = e.target;
                    const originalText = button.textContent;
                    button.disabled = true;
                    button.textContent = 'Archiving...';
                    button.style.opacity = '0.6';
                    
                    const formData = new FormData();
                    formData.append('report_id', reportId);
                    formData.append('action', 'archive');
                    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
                    
                    fetch('update_report_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then data => {
                        if (data.success) {
                            showNotification('success', data.message);
                            // Remove the card from the view
                            const card = button.closest('.request-card');
                            if (card) {
                                card.style.transition = 'opacity 0.3s ease';
                                card.style.opacity = '0';
                                setTimeout(() => {
                                    card.remove();
                                }, 300);
                            }
                        } else {
                            showNotification('error', data.message || 'An error occurred');
                            button.disabled = false;
                            button.textContent = originalText;
                            button.style.opacity = '1';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('error', 'An error occurred while archiving the report');
                        button.disabled = false;
                        button.textContent = originalText;
                        button.style.opacity = '1';
                    });
                }
            }
        });
        
        // Close rejection dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.rejection-dropdown')) {
                document.querySelectorAll('.rejection-dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
                // Reset button colors
                document.querySelectorAll('.rejection-dropdown-toggle').forEach(button => {
                    button.style.backgroundColor = '#dc3545';
                });
            }
        });
        
        // Image Modal Functions
        function openImageModal(imageSrc, imageTitle) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModalTitle').textContent = imageTitle || 'Image Preview';
            document.getElementById('imageModal').style.display = 'flex';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.getElementById('modalImage').src = '';
        }
        
        // Close image modal when clicking outside
        window.addEventListener('click', function(e) {
            const emailModal = document.getElementById('emailModal');
            const imageModal = document.getElementById('imageModal');
            
            if (e.target === emailModal) {
                closeEmailModal();
            }
            
            if (e.target === imageModal) {
                closeImageModal();
            }
        });
        
        // Handle keyboard navigation for modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const emailModal = document.getElementById('emailModal');
                const imageModal = document.getElementById('imageModal');
                
                if (emailModal.style.display === 'flex') {
                    closeEmailModal();
                }
                
                if (imageModal.style.display === 'flex') {
                    closeImageModal();
                }
            }
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('emailModal');
            if (e.target === modal) {
                closeEmailModal();
            }
        });
    </script>
    
    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>
</body>
</html>
