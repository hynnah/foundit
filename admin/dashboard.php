<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Admin Dashboard";

// Get comprehensive statistics
$stats = [];

// Total reports
$sql = "SELECT COUNT(*) as total FROM Report";
$result = mysqli_query($connection, $sql);
$stats['total_reports'] = mysqli_fetch_assoc($result)['total'];

// Reports by type
$sql = "SELECT report_type, COUNT(*) as count FROM Report GROUP BY report_type";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $stats['reports_' . strtolower($row['report_type'])] = $row['count'];
}

// Reports by status
$sql = "SELECT a.status_name, COUNT(*) as count 
        FROM Report r 
        JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID 
        GROUP BY a.status_name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $stats['status_' . strtolower($row['status_name'])] = $row['count'];
}

// Contact requests
$sql = "SELECT review_status, COUNT(*) as count FROM ContactRequest GROUP BY review_status";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $stats['contact_' . strtolower($row['review_status'])] = $row['count'];
}

// Recent activity
$sql = "SELECT r.*, p.name as submitter_name, a.status_name
        FROM Report r
        JOIN User u ON r.UserID_submitter = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        ORDER BY r.submission_date DESC
        LIMIT 10";
$recent_reports = mysqli_query($connection, $sql);

// Pending items needing attention
$sql = "SELECT r.*, p.name as submitter_name
        FROM Report r
        JOIN User u ON r.UserID_submitter = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        WHERE r.ApprovalStatusID = 1
        ORDER BY r.submission_date ASC
        LIMIT 5";
$pending_reports = mysqli_query($connection, $sql);

// Recent contact requests
$sql = "SELECT cr.*, p.name as claimant_name, r.item_name
        FROM ContactRequest cr
        JOIN User u ON cr.UserID_claimant = u.UserID
        JOIN Person p ON u.UserID = p.PersonID
        JOIN FeedPost fp ON cr.PostID = fp.PostID
        JOIN Report r ON fp.ReportID = r.ReportID
        WHERE cr.review_status = 'Pending'
        ORDER BY cr.submission_date DESC
        LIMIT 5";
$recent_contacts = mysqli_query($connection, $sql);

// Monthly stats for chart
$sql = "SELECT 
            MONTH(submission_date) as month,
            YEAR(submission_date) as year,
            COUNT(*) as count
        FROM Report 
        WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(submission_date), MONTH(submission_date)
        ORDER BY year, month";
$monthly_stats = mysqli_query($connection, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Found Items</title>
    <link rel="stylesheet" href="../style.css">
    <style> /* ayaw hilabti*/
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <!-- Please dont remove -->  
    <?php
    ob_start();
    ?> 

    <!-- Just add your main content here-->
    <div class="dashboard-container">
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_reports'] ?? 0; ?></h3>
                    <p>Total Reports</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['status_approved'] ?? 0; ?></h3>
                    <p>Approved Reports</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo $stats['status_pending'] ?? 0; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $stats['contact_pending'] ?? 0; ?></h3>
                    <p>Contact Requests</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="log_found_item.php" class="action-btn primary">
                    <span class="btn-icon">📦</span>
                    Log Found Item
                </a>
                <a href="review_reports.php" class="action-btn warning">
                    <span class="btn-icon">📋</span>
                    Review Reports (<?php echo $stats['status_pending'] ?? 0; ?>)
                </a>
                <a href="inbox.php" class="action-btn info">
                    <span class="btn-icon">📧</span>
                    Contact Requests
                </a>
                <a href="manage_users.php" class="action-btn secondary">
                    <span class="btn-icon">👥</span>
                    Manage Users
                </a>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Pending Reports -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Pending Reports</h3>
                    <a href="review_reports.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if (mysqli_num_rows($pending_reports) > 0): ?>
                        <div class="list-items">
                            <?php while ($report = mysqli_fetch_assoc($pending_reports)): ?>
                                <div class="list-item">
                                    <div class="item-info">
                                        <h4><?php echo htmlspecialchars($report['item_name']); ?></h4>
                                        <p>By: <?php echo htmlspecialchars($report['submitter_name']); ?></p>
                                        <small><?php echo formatDate($report['submission_date'], 'M d, Y'); ?></small>
                                    </div>
                                    <div class="item-actions">
                                        <a href="review_reports.php" class="btn-sm primary">Review</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No pending reports</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Contact Requests -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Recent Contact Requests</h3>
                    <a href="inbox.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if (mysqli_num_rows($recent_contacts) > 0): ?>
                        <div class="list-items">
                            <?php while ($contact = mysqli_fetch_assoc($recent_contacts)): ?>
                                <div class="list-item">
                                    <div class="item-info">
                                        <h4><?php echo htmlspecialchars($contact['item_name']); ?></h4>
                                        <p>By: <?php echo htmlspecialchars($contact['claimant_name']); ?></p>
                                        <small><?php echo formatDate($contact['submission_date'], 'M d, Y'); ?></small>
                                    </div>
                                    <div class="item-actions">
                                        <a href="review_contact_request.php?id=<?php echo $contact['ContactID']; ?>" class="btn-sm info">Review</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No pending contact requests</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="content-card full-width">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="card-content">
                    <div class="activity-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Submitter</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recent_reports) > 0): ?>
                                    <?php while ($report = mysqli_fetch_assoc($recent_reports)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($report['item_name']); ?></td>
                                            <td>
                                                <span class="type-badge <?php echo strtolower($report['report_type']); ?>">
                                                    <?php echo $report['report_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['submitter_name']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($report['status_name']); ?>">
                                                    <?php echo $report['status_name']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($report['submission_date'], 'M d, Y'); ?></td>
                                            <td>
                                                <a href="view_report.php?id=<?php echo $report['ReportID']; ?>" class="btn-sm secondary">View</a>
                                                <?php if ($report['status_name'] === 'Pending'): ?>
                                                    <a href="review_reports.php" class="btn-sm primary">Review</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No recent activity</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .dashboard-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.info { border-left: 4px solid #17a2b8; }

        .stat-icon {
            font-size: 2.5em;
            opacity: 0.8;
        }

        .stat-content h3 {
            margin: 0;
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }

        .stat-content p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 0.9em;
        }

        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .action-btn.primary { background: #007bff; color: white; }
        .action-btn.warning { background: #ffc107; color: #212529; }
        .action-btn.info { background: #17a2b8; color: white; }
        .action-btn.secondary { background: #6c757d; color: white; }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-icon {
            font-size: 1.2em;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .content-card.full-width {
            grid-column: 1 / -1;
        }

        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            color: #333;
        }

        .view-all {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .card-content {
            padding: 20px;
        }

        .list-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .item-info h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .item-info p {
            margin: 0 0 5px 0;
            color: #666;
            font-size: 0.9em;
        }

        .item-info small {
            color: #999;
            font-size: 0.8em;
        }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
        }

        .btn-sm.primary { background: #007bff; color: white; }
        .btn-sm.info { background: #17a2b8; color: white; }
        .btn-sm.secondary { background: #6c757d; color: white; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .activity-table {
            overflow-x: auto;
        }

        .activity-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th,
        .activity-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .activity-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .type-badge,
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .type-badge.lost { background: #fff3cd; color: #856404; }
        .type-badge.found { background: #d4edda; color: #155724; }

        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }

        .text-center {
            text-align: center;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <!-- Please dont remove -->  
    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>

</html>