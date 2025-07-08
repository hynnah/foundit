<?php
session_start();

require_once 'dbh.inc.php';

$user_name = htmlspecialchars($_SESSION['userName'] ?? 'User');
$content_header = "Recent Posts";

// Fetch recent reports (both lost and found)
$recent_reports = [];
$sql = "SELECT r.*, 
               l.location_last_seen AS location, 
               f.location_found AS location,
               a.status_name AS approvalstatus
        FROM report r
        LEFT JOIN lost l ON r.ReportID = l.ReportID AND r.report_type = 'lost'
        LEFT JOIN found f ON r.ReportID = f.ReportID AND r.report_type = 'found'
        LEFT JOIN approvalstatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        WHERE r.ApprovalStatusID = 2
        ORDER BY r.submission_date DESC
        LIMIT 10";

$result = mysqli_query($connection, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_reports[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .feed-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .feed-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .feed-header h1 {
            color: #cb7f00;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .feed-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .post-card {
            background: rgba(255, 227, 142, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .post-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .post-details {
            padding: 20px;
        }
        
        .post-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .post-type.lost {
            background-color: #ff6b6b;
            color: white;
        }
        
        .post-type.found {
            background-color: #51cf66;
            color: white;
        }
        
        .post-title {
            font-size: 1.3rem;
            margin: 10px 0;
            color: #333;
        }
        
        .post-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .post-meta {
            display: flex;
            align-items: center;
            margin-top: 15px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .post-meta i {
            margin-right: 5px;
            color: #cb7f00;
        }
        
        .post-location, .post-date {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .no-posts {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2rem;
            grid-column: 1 / -1;
        }
        
        .report-btn {
            display: block;
            width: 200px;
            margin: 40px auto;
            padding: 15px;
            background: linear-gradient(45deg, #cb7f00, #e89611);
            color: white;
            text-align: center;
            border-radius: 30px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .report-btn:hover {
            background: linear-gradient(45deg, #bd7800, #d48806);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(203, 127, 0, 0.3);
        }
        
        @media (max-width: 768px) {
            .posts-grid {
                grid-template-columns: 1fr;
            }
            
            .feed-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Please dont remove -->  
    <?php
    ob_start();
    ?> 

    <!-- Main content -->
    <div class="feed-container">
        <div class="feed-header">
            <h1>Recent Lost & Found Items</h1>
            <p>Check out the latest items reported in our community</p>
        </div>
        
        <?php if (!empty($recent_reports)): ?>
            <div class="posts-grid">
                <?php foreach ($recent_reports as $report): ?>
                    <div class="post-card">
                        <?php if ($report['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($report['image_path']); ?>" alt="<?php echo htmlspecialchars($report['item_name']); ?>" class="post-image">
                        <?php else: ?>
                            <img src="images/default-item.jpg" alt="Default item image" class="post-image">
                        <?php endif; ?>
                        
                        <div class="post-details">
                            <span class="post-type <?php echo htmlspecialchars($report['report_type']); ?>">
                                <?php echo ucfirst(htmlspecialchars($report['report_type'])); ?>
                            </span>
                            <h3 class="post-title"><?php echo htmlspecialchars($report['item_name']); ?></h3>
                            <p class="post-description"><?php echo htmlspecialchars($report['description']); ?></p>
                            
                            <div class="post-meta">
                                <div class="post-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($report['location'] ?? 'Unknown location'); ?>
                                </div>
                                <div class="post-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('M j, Y', strtotime($report['incident_date'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-posts">
                <p>No recent posts found. Be the first to report a lost or found item!</p>
            </div>
        <?php endif; ?>
        
        <a href="report_lost_item.php" class="report-btn">
            <i class="fas fa-plus"></i> Report Lost Item
        </a>
    </div>

    <!-- Please dont remove -->  
    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>
</body>
</html>