<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Review Reports";

// Handle report approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $reportId = $_POST['report_id'] ?? '';
        $action = $_POST['action'] ?? '';
        $reviewNotes = trim($_POST['review_notes'] ?? '');
        $adminId = getUserId();
        
        if ($reportId && in_array($action, ['approve', 'reject'])) {
            $newStatus = ($action === 'approve') ? 2 : 3; // 2 = Approved, 3 = Rejected
            
            // Start transaction
            mysqli_autocommit($connection, FALSE);
            
            try {
                // Update report status
                $sql_update = "UPDATE Report SET 
                              ApprovalStatusID = ?, 
                              AdminID_reviewer = ?, 
                              reviewDate = NOW(), 
                              reviewNote = ?
                              WHERE ReportID = ?";
                $stmt_update = mysqli_prepare($connection, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "iisi", $newStatus, $adminId, $reviewNotes, $reportId);
                
                if (!mysqli_stmt_execute($stmt_update)) {
                    throw new Exception("Error updating report status");
                }
                
                // If approved, create FeedPost
                if ($action === 'approve') {
                    $sql_feed = "INSERT INTO FeedPost (ReportID, post_date, post_status) VALUES (?, NOW(), 'Active')";
                    $stmt_feed = mysqli_prepare($connection, $sql_feed);
                    mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
                    
                    if (!mysqli_stmt_execute($stmt_feed)) {
                        throw new Exception("Error creating feed post");
                    }
                    mysqli_stmt_close($stmt_feed);
                }
                
                mysqli_stmt_close($stmt_update);
                mysqli_commit($connection);
                
                $success_message = "Report " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
                logActivity($adminId, 'REPORT_REVIEWED', "Report ID: $reportId, Action: $action");
                
            } catch (Exception $e) {
                mysqli_rollback($connection);
                $error_message = "Error processing request: " . $e->getMessage();
            }
            
            mysqli_autocommit($connection, TRUE);
        } else {
            $error_message = "Invalid request parameters.";
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'pending';
$filter_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($filter_status === 'pending') {
    $where_conditions[] = "r.ApprovalStatusID = 1";
} elseif ($filter_status === 'approved') {
    $where_conditions[] = "r.ApprovalStatusID = 2 AND (r.archiveYN = 0 OR r.archiveYN IS NULL) AND (r.claimedYN = 0 OR r.claimedYN IS NULL)";
} elseif ($filter_status === 'rejected') {
    $where_conditions[] = "r.ApprovalStatusID = 3";
} elseif ($filter_status === 'archived') {
    $where_conditions[] = "r.ApprovalStatusID = 2 AND r.archiveYN = 1";
} elseif ($filter_status === 'claimed') {
    $where_conditions[] = "r.ApprovalStatusID = 2 AND r.claimedYN = 1";
}

if ($filter_type) {
    $where_conditions[] = "r.report_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if ($search) {
    $where_conditions[] = "(r.item_name LIKE ? OR r.description LIKE ? OR p.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$sql = "SELECT r.*, 
               p.name as submitter_name, 
               p.email as submitter_email,
               a.status_name,
               l.location_last_seen,
               f.location_found,
               pr.name as reviewer_name,
               COALESCE(r.archiveYN, 0) as archiveYN,
               COALESCE(r.claimedYN, 0) as claimedYN
        FROM Report r
        JOIN User u ON r.UserID_submitter = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        LEFT JOIN Lost l ON r.ReportID = l.ReportID
        LEFT JOIN Found f ON r.ReportID = f.ReportID
        LEFT JOIN Person pr ON r.AdminID_reviewer = pr.PersonID
        $where_clause
        ORDER BY r.submission_date DESC";

$stmt = mysqli_prepare($connection, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get counts for different statuses
$counts_sql = "SELECT 
                 SUM(CASE WHEN ApprovalStatusID = 1 THEN 1 ELSE 0 END) as pending_count,
                 SUM(CASE WHEN ApprovalStatusID = 2 AND (archiveYN = 0 OR archiveYN IS NULL) AND (claimedYN = 0 OR claimedYN IS NULL) THEN 1 ELSE 0 END) as approved_count,
                 SUM(CASE WHEN ApprovalStatusID = 3 THEN 1 ELSE 0 END) as rejected_count,
                 SUM(CASE WHEN ApprovalStatusID = 2 AND archiveYN = 1 THEN 1 ELSE 0 END) as archived_count,
                 SUM(CASE WHEN ApprovalStatusID = 2 AND claimedYN = 1 THEN 1 ELSE 0 END) as claimed_count
               FROM Report";
$counts_result = mysqli_query($connection, $counts_sql);
$counts = mysqli_fetch_assoc($counts_result);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Review Reports</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .review-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007bff;
        }
        
        .status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .status-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .status-tab:hover {
            border-color: #007bff;
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
            align-items: end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-row input,
        .filter-row select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            background: white;
            min-width: 120px;
        }

        .filter-row select:focus,
        .filter-row input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        .filter-row button {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .report-card {
            background: white;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .report-card.pending {
            border-left-color: #ffc107;
        }
        
        .report-card.approved {
            border-left-color: #28a745;
        }
        
        .report-card.rejected {
            border-left-color: #dc3545;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .report-meta {
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
        
        .report-content {
            margin-bottom: 15px;
        }
        
        .report-image {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
        
        .review-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        
        .review-form.active {
            display: block;
        }
        
        .review-form textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .review-form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
        .status-archived { background: #f8f9fa; color: #6c757d; }
        .status-claimed { background: #cce5ff; color: #004085; }
        
        .type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .type-lost { background: #fff3cd; color: #856404; }
        .type-found { background: #d4edda; color: #155724; }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
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
    </style>
</head>
<body>
    <?php ob_start(); ?>
    
    <div class="review-container">
        <div class="review-header">
            <h2>Review Reports</h2>
            <div class="status-tabs">
                <a href="?status=pending" class="status-tab <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $counts['pending_count']; ?>)
                </a>
                <a href="?status=approved" class="status-tab <?php echo $filter_status === 'approved' ? 'active' : ''; ?>">
                    Approved (<?php echo $counts['approved_count']; ?>)
                </a>
                <a href="?status=rejected" class="status-tab <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">
                    Rejected (<?php echo $counts['rejected_count']; ?>)
                </a>
                <a href="?status=archived" class="status-tab <?php echo $filter_status === 'archived' ? 'active' : ''; ?>">
                    Archived (<?php echo $counts['archived_count']; ?>)
                </a>
                <a href="?status=claimed" class="status-tab <?php echo $filter_status === 'claimed' ? 'active' : ''; ?>">
                    Claimed (<?php echo $counts['claimed_count']; ?>)
                </a>
                <a href="?status=all" class="status-tab <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                    All Reports
                </a>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="get" class="filter-row" id="filterForm">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" placeholder="Search reports..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="type">Type:</label>
                    <select id="type" name="type">
                        <option value="">All Types</option>
                        <option value="Lost" <?php echo $filter_type === 'Lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="Found" <?php echo $filter_type === 'Found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="review_reports.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="report-card <?php echo strtolower($row['status_name']); ?>">
                    <div class="report-header">
                        <div class="report-title"><?php echo htmlspecialchars($row['item_name']); ?></div>
                        <div>
                            <span class="type-badge type-<?php echo strtolower($row['report_type']); ?>">
                                <?php echo $row['report_type']; ?>
                            </span>
                            <?php
                            $display_status = $row['status_name'];
                            if ($row['archiveYN'] == 1) {
                                $display_status = 'Archived';
                            } elseif ($row['claimedYN'] == 1) {
                                $display_status = 'Claimed';
                            }
                            ?>
                            <span class="status-badge status-<?php echo strtolower($display_status); ?>">
                                <?php echo $display_status; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="report-meta">
                        <div class="meta-item">
                            <span class="meta-label">Submitted by</span>
                            <span><?php echo htmlspecialchars($row['submitter_name']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Email</span>
                            <span><?php echo htmlspecialchars($row['submitter_email']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Date Submitted</span>
                            <span><?php echo formatDate($row['submission_date'], 'M d, Y h:i A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Location</span>
                            <span><?php echo htmlspecialchars($row['location_last_seen'] ?? $row['location_found'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Report ID</span>
                            <span>#<?php echo $row['ReportID']; ?></span>
                        </div>
                        <?php if ($row['reviewer_name']): ?>
                        <div class="meta-item">
                            <span class="meta-label">Reviewed by</span>
                            <span><?php echo htmlspecialchars($row['reviewer_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="report-content">
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                        <p><strong>Incident Date:</strong> <?php echo formatDate($row['incident_date'], 'M d, Y'); ?></p>
                        
                        <?php if ($row['image_path']): ?>
                            <p><strong>Image:</strong></p>
                            <img src="../<?php echo htmlspecialchars($row['image_path']); ?>" alt="Item Image" class="report-image">
                        <?php endif; ?>
                        
                        <?php if ($row['reviewNote']): ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px;">
                                <p><strong>Review Notes:</strong></p>
                                <p><?php echo htmlspecialchars($row['reviewNote']); ?></p>
                                <small>Reviewed on: <?php echo formatDate($row['reviewDate'], 'M d, Y h:i A'); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="report-actions">
                        <?php if ($row['status_name'] === 'Pending'): ?>
                            <button onclick="toggleReviewForm(<?php echo $row['ReportID']; ?>)" class="btn btn-primary">
                                Review Report
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($row['status_name'] === 'Approved' && $row['archiveYN'] == 0 && $row['claimedYN'] == 0): ?>
                            <button onclick="updateReportStatus(<?php echo $row['ReportID']; ?>, 'archive')" class="btn btn-secondary">
                                📦 Archive
                            </button>
                            <button onclick="updateReportStatus(<?php echo $row['ReportID']; ?>, 'mark_claimed')" class="btn btn-success">
                                ✓ Mark as Claimed
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($row['archiveYN'] == 1): ?>
                            <button onclick="updateReportStatus(<?php echo $row['ReportID']; ?>, 'unarchive')" class="btn btn-primary">
                                📤 Unarchive
                            </button>
                        <?php endif; ?>
                        
                        <a href="view_report.php?id=<?php echo $row['ReportID']; ?>" class="btn btn-secondary">View Details</a>
                    </div>
                    
                    <?php if ($row['status_name'] === 'Pending'): ?>
                    <div id="reviewForm<?php echo $row['ReportID']; ?>" class="review-form">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="report_id" value="<?php echo $row['ReportID']; ?>">
                            
                            <textarea name="review_notes" placeholder="Enter review notes (optional)..."></textarea>
                            
                            <div class="review-form-actions">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    ✓ Approve & Post
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">
                                    ✗ Reject
                                </button>
                                <button type="button" onclick="toggleReviewForm(<?php echo $row['ReportID']; ?>)" class="btn btn-secondary">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <img src="../resources/search.png" alt="No Reports">
                <h3>No Reports Found</h3>
                <p>No reports match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleReviewForm(reportId) {
            const form = document.getElementById('reviewForm' + reportId);
            form.classList.toggle('active');
        }
        
        function updateReportStatus(reportId, action) {
            if (!confirm('Are you sure you want to ' + action.replace('_', ' ') + ' this report?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('report_id', reportId);
            formData.append('csrf_token', '<?php echo htmlspecialchars(generateCSRFToken()); ?>');
            
            fetch('update_report_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the report status');
            });
        }
        
        // Enhanced filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('#filterForm');
            if (filterForm) {
                const inputs = filterForm.querySelectorAll('input, select');
                inputs.forEach(function(input) {
                    input.addEventListener('change', function() {
                        console.log('Filter changed:', this.name, '=', this.value);
                    });
                });
                
                // Add submit handler for debugging
                filterForm.addEventListener('submit', function(e) {
                    console.log('Form submitted with values:');
                    const formData = new FormData(this);
                    for (let [key, value] of formData.entries()) {
                        console.log(key, '=', value);
                    }
                });
            }
            
            // Ensure select elements are properly initialized
            const typeSelect = document.querySelector('select[name="type"]');
            if (typeSelect) {
                console.log('Type select current value:', typeSelect.value);
                console.log('Type select options:', Array.from(typeSelect.options).map(opt => opt.value));
            }
        });
        
        // Auto-refresh for pending reports
        setTimeout(() => {
            if (window.location.search.includes('status=pending')) {
                location.reload();
            }
        }, 60000); // Refresh every minute for pending reports
    </script>
    
    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>
</body>
</html>
