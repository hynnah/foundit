<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$content_header = "Item Details";

// Get post ID from URL
$post_id = $_GET['post_id'] ?? '';

if (!$post_id || !is_numeric($post_id)) {
    header("Location: dashboard.php");
    exit();
}

// Get post details
$sql = "SELECT r.*, 
               l.location_last_seen AS lost_location, 
               f.location_found AS found_location,
               f.vague_item_name,
               a.status_name AS approvalstatus,
               fp.PostID,
               fp.post_date,
               fp.post_status,
               r.ApprovalStatusID,
               p.name as submitter_name,
               p.email as submitter_email
        FROM Report r
        LEFT JOIN Lost l ON r.ReportID = l.ReportID AND r.report_type = 'Lost'
        LEFT JOIN Found f ON r.ReportID = f.ReportID AND r.report_type = 'Found'
        LEFT JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        LEFT JOIN User u ON r.UserID_submitter = u.UserID
        LEFT JOIN Person p ON u.UserID = p.PersonID
        WHERE fp.PostID = ?";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: dashboard.php");
    exit();
}

$item = mysqli_fetch_assoc($result);

// Start output buffering
ob_start();
?>
<style>
    .item-details-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .item-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }
    
    .item-header {
        background: linear-gradient(135deg, #cb7f00, #e89611); 
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .item-type-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 25px;
        font-weight: bold;
        font-size: 0.9rem;
        margin-bottom: 15px;
        border: 2px solid white;
    }
    
    .item-type-badge.lost {
        background-color: #ff6b6b;
    }
    
    .item-type-badge.found {
        background-color: #51cf66;
    }
    
    .item-title {
        font-size: 2.5rem;
        margin: 0;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    
    .item-content {
        display: flex;
        gap: 30px;
        padding: 30px;
    }
    
    .item-image-section {
        flex: 0 0 300px;
    }
    
    .item-image {
        width: 100%;
        height: 300px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid #e0e0e0;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .item-image:hover {
        border-color: #cb7f00;
        box-shadow: 0 6px 24px rgba(203, 127, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .item-image.found-item-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border: 2px solid #e0e0e0;
        transition: all 0.3s ease;
    }
    
    .item-image.found-item-placeholder:hover {
        border-color: #cb7f00;
        box-shadow: 0 6px 24px rgba(203, 127, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .item-info-section {
        flex: 1;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .info-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #cb7f00;
    }
    
    .info-label {
        font-weight: bold;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-value {
        color: #333;
        font-size: 1.1rem;
    }
    
    .description-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
    }
    
    .description-section h3 {
        color: #cb7f00;
        margin-top: 0;
        margin-bottom: 15px;
    }
    
    .description-text {
        line-height: 1.6;
        color: #555;
        white-space: pre-wrap;
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .btn-primary {
        background: #e89611;
        color: white;
    }
    
    .btn-primary:hover {
        background: #cb7f00;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #6c757d, #5a6268);
        color: white;
    }
    
    .btn-secondary:hover {
        background: linear-gradient(135deg, #5a6268, #4e555b);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-success {
        background: #e89611;
        color: white;
    }
    
    .btn-success:hover {
        background: #cb7f00;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    @media (max-width: 768px) {
        .item-content {
            flex-direction: column;
            padding: 20px;
        }
        
        .item-image-section {
            flex: none;
            margin-bottom: 20px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .item-title {
            font-size: 2rem;
        }
        
        .action-buttons {
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            min-width: 140px;
            justify-content: center;
        }
    }
</style>

<div class="item-details-container">
    <div class="item-card">
        <div class="item-header">
            <div class="item-type-badge <?php echo strtolower($item['report_type']); ?>">
                <?php echo ucfirst($item['report_type']); ?> Item
            </div>
            <h1 class="item-title"><?php echo htmlspecialchars($item['report_type'] === 'Found' ? ($item['vague_item_name'] ?? $item['item_name']) : $item['item_name']); ?></h1>
        </div>
        
        <div class="item-content">
            <div class="item-image-section">
                <?php if ($item['image_path'] && $item['report_type'] === 'Lost'): ?>
                    <?php
                    // Handle different image path formats
                    $imagePath = $item['image_path'];
                    $imageUrl = '';
                    
                    // Check different possible locations
                    $possiblePaths = [
                        'uploads/' . $imagePath,
                        'admin/uploads/' . $imagePath,
                        $imagePath // if full path is stored
                    ];
                    
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path)) {
                            $imageUrl = $path;
                            break;
                        }
                    }
                    
                    if (!$imageUrl) {
                        $imageUrl = 'resources/search.png'; // fallback image
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                         alt="<?php echo htmlspecialchars($item['report_type'] === 'Found' ? ($item['vague_item_name'] ?? $item['item_name']) : $item['item_name']); ?>" 
                         class="item-image"
                         onerror="this.src='resources/search.png';">
                <?php elseif ($item['report_type'] === 'Found'): ?>
                    <div class="item-image found-item-placeholder">
                        <i class="fas fa-question-circle" style="font-size: 6rem; color: #666; margin-bottom: 20px;"></i>
                        <p style="margin: 0; font-size: 1.1rem; color: #666; text-align: center; font-weight: 500;">Contact Administrator<br>for Image Details</p>
                    </div>
                <?php else: ?>
                    <img src="resources/search.png" 
                         alt="Default item image" 
                         class="item-image">
                <?php endif; ?>
            </div>
            
            <div class="item-info-section">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo $item['report_type'] === 'Lost' ? 'Last Seen Location' : 'Found Location'; ?>
                        </div>
                        <div class="info-value">
                            <?php 
                            if ($item['report_type'] === 'Lost') {
                                echo htmlspecialchars($item['lost_location'] ?? 'Unknown location');
                            } else {
                                echo htmlspecialchars($item['found_location'] ?? 'Unknown location');
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo $item['report_type'] === 'Lost' ? 'Date Lost' : 'Date Found'; ?>
                        </div>
                        <div class="info-value">
                            <?php echo date('F j, Y', strtotime($item['incident_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-clock"></i>
                            Date Submitted
                        </div>
                        <div class="info-value">
                            <?php echo date('F j, Y g:i A', strtotime($item['submission_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user"></i>
                            Submitted By
                        </div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($item['submitter_name'] ?? 'System'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action buttons moved here for better positioning -->
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    
                    <?php if ($item['report_type'] === 'Found'): ?>
                        <a href="contact_owner.php?post_id=<?php echo $item['PostID']; ?>" class="btn btn-primary">
                            <i class="fas fa-envelope"></i> Contact Administrator
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($item['report_type'] === 'Lost'): ?>
                        <a href="found_items.php" class="btn btn-success">
                            <i class="fas fa-search"></i> Browse Found Items
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="description-section">
            <h3><i class="fas fa-align-left"></i> Description</h3>
            <div class="description-text">
                <?php echo htmlspecialchars($item['description']); ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<?php
$page_content = ob_get_clean();
include_once 'includes/general_layout.php';
?>
