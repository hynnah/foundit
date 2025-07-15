<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = htmlspecialchars(getUserName());
$user_id = getUserId();
$content_header = "My Reports";

// Fetch user's reports
$sql = "SELECT r.*, 
               l.location_last_seen AS lost_location, 
               f.location_found AS found_location,
               a.status_name AS approvalstatus,
               fp.PostID,
               fp.post_date,
               fp.post_status
        FROM Report r
        LEFT JOIN Lost l ON r.ReportID = l.ReportID AND r.report_type = 'Lost'
        LEFT JOIN Found f ON r.ReportID = f.ReportID AND r.report_type = 'Found'
        LEFT JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        WHERE r.UserID_submitter = ?
        ORDER BY r.submission_date DESC";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$reports = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $reports[] = $row;
    }
}

// Start output buffering
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
    .reports-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .reports-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .reports-header h1 {
        color: #cb7f00;
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .reports-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .reports-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        border: 2px solid rgba(203, 127, 0, 0.2);
    }
    
    .stat-card h3 {
        color: #cb7f00;
        font-size: 2rem;
        margin-bottom: 5px;
    }
    
    .stat-card p {
        color: #666;
        font-size: 0.9rem;
    }
    
    .reports-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .report-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
        border: 2px solid rgba(203, 127, 0, 0.2);
    }
    
    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }
    
    .report-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: rgba(255, 227, 142, 0.3);
        border-bottom: 1px solid rgba(203, 127, 0, 0.2);
    }
    
    .report-type {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .report-type.lost {
        background-color: #ff6b6b;
        color: white;
    }
    
    .report-type.found {
        background-color: #51cf66;
        color: white;
    }
    
    .report-status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: bold;
    }
    
    .status-pending {
        background-color: #ffc107;
        color: #333;
    }
    
    .status-approved {
        background-color: #28a745;
        color: white;
    }
    
    .status-rejected {
        background-color: #dc3545;
        color: white;
    }
    
    .status-inactive {
        background-color: #6c757d;
        color: white;
    }
    
    .report-body {
        display: flex;
        gap: 20px;
        padding: 20px;
    }
    
    .report-image {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 10px;
        flex-shrink: 0;
    }
    
    .report-details {
        flex: 1;
    }
    
    .report-title {
        font-size: 1.4rem;
        margin-bottom: 10px;
        color: #333;
    }
    
    .report-description {
        color: #666;
        line-height: 1.5;
        margin-bottom: 15px;
    }
    
    .report-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 0.9rem;
    }
    
    .meta-item i {
        color: #cb7f00;
        width: 16px;
    }
    
    .report-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0056b3;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
    }
    
    .no-reports {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .no-reports i {
        font-size: 4rem;
        color: #ddd;
        margin-bottom: 20px;
    }
    
    .no-reports h3 {
        color: #666;
        margin-bottom: 15px;
    }
    
    .no-reports p {
        color: #888;
        margin-bottom: 20px;
    }
    
    .report-btn {
        background: linear-gradient(45deg, #cb7f00, #e89611);
        color: white;
        padding: 12px 24px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .report-btn:hover {
        background: linear-gradient(45deg, #bd7800, #d48806);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(203, 127, 0, 0.3);
    }
    
    @media (max-width: 768px) {
        .report-body {
            flex-direction: column;
        }
        
        .report-image {
            width: 100%;
            height: 200px;
        }
        
        .report-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        
        .report-meta {
            grid-template-columns: 1fr;
        }
        
        .report-actions {
            justify-content: center;
        }
    }
</style>

<div class="reports-container">
    <div class="reports-header">
        <h1><i class="fas fa-clipboard-list"></i> My Reports</h1>
        <p>View and manage all your lost and found item reports</p>
    </div>
    
    <?php if (!empty($reports)): ?>
        <?php
        // Calculate statistics
        $total_reports = count($reports);
        $lost_reports = count(array_filter($reports, function($r) { return $r['report_type'] === 'Lost'; }));
        $found_reports = count(array_filter($reports, function($r) { return $r['report_type'] === 'Found'; }));
        $approved_reports = count(array_filter($reports, function($r) { return $r['ApprovalStatusID'] == 2; }));
        $pending_reports = count(array_filter($reports, function($r) { return $r['ApprovalStatusID'] == 1; }));
        ?>
        
        <div class="reports-stats">
            <div class="stat-card">
                <h3><?php echo $total_reports; ?></h3>
                <p>Total Reports</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $lost_reports; ?></h3>
                <p>Lost Items</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $found_reports; ?></h3>
                <p>Found Items</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $approved_reports; ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_reports; ?></h3>
                <p>Pending</p>
            </div>
        </div>
        
        <div class="reports-grid">
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <span class="report-type <?php echo strtolower($report['report_type']); ?>">
                                <?php echo ucfirst($report['report_type']); ?>
                            </span>
                        </div>
                        <div>
                            <?php 
                            $statusClass = '';
                            $statusText = '';
                            
                            switch ($report['ApprovalStatusID']) {
                                case 1:
                                    $statusClass = 'status-pending';
                                    $statusText = 'Pending Review';
                                    break;
                                case 2:
                                    if ($report['post_status'] === 'Active') {
                                        $statusClass = 'status-approved';
                                        $statusText = 'Approved & Active';
                                    } else {
                                        $statusClass = 'status-inactive';
                                        $statusText = 'Approved but Inactive';
                                    }
                                    break;
                                case 3:
                                    $statusClass = 'status-rejected';
                                    $statusText = 'Rejected';
                                    break;
                                default:
                                    $statusClass = 'status-pending';
                                    $statusText = 'Unknown';
                            }
                            ?>
                            <span class="report-status <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="report-body">
                        <?php if ($report['image_path']): ?>
                            <?php
                            $imagePath = $report['image_path'];
                            $imageUrl = '';
                            
                            $possiblePaths = [
                                'uploads/' . $imagePath,
                                'admin/uploads/' . $imagePath,
                                $imagePath
                            ];
                            
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path)) {
                                    $imageUrl = $path;
                                    break;
                                }
                            }
                            
                            if (!$imageUrl) {
                                $imageUrl = 'resources/search.png';
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                 alt="<?php echo htmlspecialchars($report['item_name']); ?>" 
                                 class="report-image"
                                 onerror="this.src='resources/search.png';">
                        <?php else: ?>
                            <img src="resources/search.png" alt="Default item image" class="report-image">
                        <?php endif; ?>
                        
                        <div class="report-details">
                            <h3 class="report-title"><?php echo htmlspecialchars($report['item_name']); ?></h3>
                            <p class="report-description"><?php echo htmlspecialchars(truncateText($report['description'], 150)); ?></p>
                            
                            <div class="report-meta">
                                <div class="meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>
                                        <?php 
                                        if ($report['report_type'] === 'Lost') {
                                            echo "Last seen: " . htmlspecialchars($report['lost_location'] ?? 'Unknown');
                                        } else {
                                            echo "Found at: " . htmlspecialchars($report['found_location'] ?? 'Unknown');
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo date('M j, Y', strtotime($report['incident_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Submitted: <?php echo date('M j, Y g:i A', strtotime($report['submission_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="report-actions">
                                <?php if ($report['PostID'] && $report['ApprovalStatusID'] == 2): ?>
                                    <a href="view_item_details.php?post_id=<?php echo $report['PostID']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($report['ApprovalStatusID'] == 1): ?>
                                    <span class="btn btn-secondary" style="cursor: default;">
                                        <i class="fas fa-hourglass-half"></i> Awaiting Review
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-reports">
            <i class="fas fa-clipboard-list"></i>
            <h3>No Reports Yet</h3>
            <p>You haven't submitted any lost or found item reports yet.</p>
            <a href="report_lost_item.php" class="report-btn">
                <i class="fas fa-plus"></i> Create Your First Report
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include_once "includes/general_layout.php";
?>