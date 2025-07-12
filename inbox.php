<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$content_header = "Inbox";
$currentUserId = getUserId();

// For regular users, show their contact requests and claims
// For admins, show all contact requests that need review
$isAdmin = isAdmin();

// Get contact requests based on user role
if ($isAdmin) {
    // Admin: Show all contact requests that need review
    $sql = "SELECT 
                cr.ContactID, 
                cr.ownership_description, 
                cr.submission_date, 
                cr.review_status,
                cr.detailed_description,
                cr.evidence_details,
                p.name as claimant_name,
                p.email as claimant_email,
                r.item_name,
                r.description as item_description,
                fp.PostID
            FROM ContactRequest cr
            JOIN User u ON cr.UserID_claimant = u.UserID
            JOIN Person p ON u.UserID = p.PersonID
            JOIN FeedPost fp ON cr.PostID = fp.PostID
            JOIN Report r ON fp.ReportID = r.ReportID
            WHERE cr.review_status = 'Pending'
            ORDER BY cr.submission_date DESC";
} else {
    // Regular user: Show their own contact requests and claims
    $sql = "SELECT 
                cr.ContactID, 
                cr.ownership_description, 
                cr.submission_date, 
                cr.review_status,
                cr.detailed_description,
                cr.evidence_details,
                cr.review_notes,
                r.item_name,
                r.description as item_description,
                fp.PostID,
                c.claim_status,
                c.interrogation_notes,
                c.passed_interrogation,
                c.resolution_date
            FROM ContactRequest cr
            JOIN FeedPost fp ON cr.PostID = fp.PostID
            JOIN Report r ON fp.ReportID = r.ReportID
            LEFT JOIN Claim c ON cr.ContactID = c.ContactID
            WHERE cr.UserID_claimant = ?
            ORDER BY cr.submission_date DESC";
}

$stmt = mysqli_prepare($connection, $sql);
if (!$isAdmin) {
    mysqli_stmt_bind_param($stmt, "i", $currentUserId);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Start output buffering to capture the page content
ob_start();
?>
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
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .request-content {
            margin-bottom: 15px;
        }
        
        .request-actions {
            display: flex;
            gap: 10px;
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
    </style>

<div class="inbox-container">
    <div class="inbox-header">
        <h2><?php echo $isAdmin ? 'Contact Requests Management' : 'My Contact Requests & Claims'; ?></h2>
        <div class="inbox-stats">
            <?php
            // Get stats
            if ($isAdmin) {
                $stats_sql = "SELECT 
                                COUNT(*) as total_requests,
                                SUM(CASE WHEN review_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                                SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests
                              FROM ContactRequest";
            } else {
                $stats_sql = "SELECT 
                                COUNT(*) as total_requests,
                                SUM(CASE WHEN review_status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
                                SUM(CASE WHEN review_status = 'Approved' THEN 1 ELSE 0 END) as approved_requests
                              FROM ContactRequest WHERE UserID_claimant = $currentUserId";
            }
            
            $stats_result = mysqli_query($connection, $stats_sql);
            $stats = mysqli_fetch_assoc($stats_result);
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
    </div>
    
    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="request-card">
                <div class="request-header">
                    <div class="request-title"><?php echo htmlspecialchars($row['item_name']); ?></div>
                    <div class="status-badge status-<?php echo strtolower($row['review_status']); ?>">
                        <?php echo htmlspecialchars($row['review_status']); ?>
                    </div>
                </div>
                
                <div class="request-meta">
                    <span><strong>Submitted:</strong> <?php echo formatDate($row['submission_date'], 'M d, Y h:i A'); ?></span>
                    <?php if ($isAdmin): ?>
                        <span><strong>Claimant:</strong> <?php echo htmlspecialchars($row['claimant_name']); ?></span>
                        <span><strong>Email:</strong> <?php echo htmlspecialchars($row['claimant_email']); ?></span>
                    <?php endif; ?>
                    <span><strong>Contact ID:</strong> #<?php echo $row['ContactID']; ?></span>
                </div>
                
                <div class="request-content">
                    <p><strong>Item Description:</strong> <?php echo htmlspecialchars($row['item_description']); ?></p>
                    <p><strong>Ownership Description:</strong> <?php echo htmlspecialchars($row['ownership_description']); ?></p>
                    
                    <?php if ($row['detailed_description']): ?>
                        <p><strong>Detailed Description:</strong> <?php echo htmlspecialchars($row['detailed_description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($row['evidence_details']): ?>
                        <p><strong>Evidence Details:</strong> <?php echo htmlspecialchars($row['evidence_details']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!$isAdmin && isset($row['review_notes']) && $row['review_notes']): ?>
                        <div class="details-section">
                            <h4>Admin Review Notes:</h4>
                            <p><?php echo htmlspecialchars($row['review_notes']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$isAdmin && isset($row['claim_status']) && $row['claim_status']): ?>
                        <div class="details-section">
                            <h4>Claim Status: 
                                <span class="status-badge status-<?php echo strtolower($row['claim_status']); ?>">
                                    <?php echo htmlspecialchars($row['claim_status']); ?>
                                </span>
                            </h4>
                            
                            <?php if ($row['interrogation_notes']): ?>
                                <p><strong>Interrogation Notes:</strong> <?php echo htmlspecialchars($row['interrogation_notes']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($row['passed_interrogation'] !== null): ?>
                                <p><strong>Interrogation Result:</strong> 
                                    <span class="status-badge status-<?php echo $row['passed_interrogation'] ? 'approved' : 'rejected'; ?>">
                                        <?php echo $row['passed_interrogation'] ? 'Passed' : 'Failed'; ?>
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
                    <?php if ($isAdmin && $row['review_status'] === 'Pending'): ?>
                        <a href="review_contact_request.php?id=<?php echo $row['ContactID']; ?>" class="btn btn-primary">Review Request</a>
                    <?php endif; ?>
                    
                    <?php if (!$isAdmin): ?>
                        <a href="view_contact_request.php?id=<?php echo $row['ContactID']; ?>" class="btn btn-secondary">View Details</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <img src="<?php echo $isAdmin ? '../' : './'; ?>resources/env.png" alt="Empty Inbox">
            <h3>No <?php echo $isAdmin ? 'Contact Requests' : 'Messages'; ?> Yet</h3>
            <p>
                <?php if ($isAdmin): ?>
                    When users submit contact requests to claim items, they will appear here for your review.
                <?php else: ?>
                    Your contact requests and claim status updates will appear here.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php
// Capture the page content and include the layout
$page_content = ob_get_clean();
include_once "includes/general_layout.php";
?>