<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$content_header = "Report History";

// Get search parameters
$search = $_GET['search'] ?? '';

// Get logged-in user ID
$userId = $_SESSION['userId'] ?? null;

$where_conditions = ["r.report_type = 'Lost'", "r.UserID_submitter = ?"];
$params = [$userId];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(r.item_name LIKE ? OR r.description LIKE ? OR l.location_last_seen LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$sql = "SELECT r.*, 
               l.location_last_seen,
               fp.post_status,
               fp.post_date,
               fp.PostID
        FROM Report r
        JOIN Lost l ON r.ReportID = l.ReportID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        $where_clause
        ORDER BY r.submission_date DESC";

$stmt = mysqli_prepare($connection, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Start output buffering to capture the page content
ob_start();
?>

<style>
    .report-history-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .search-section {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }
    
    .search-section h2 {
        color: #cb7f00;
        margin-bottom: 20px;
        font-size: 1.5rem;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .search-input {
        flex: 1;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }
    
    .search-input:focus {
        outline: none;
        border-color: #cb7f00;
    }
    
    .search-btn {
        padding: 12px 25px;
        background: #e89611;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s ease;
        text-decoration: none;
    }
    
    .search-btn:hover {
        background: #cb7f00;
    }
    
    .items-table {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table th,
    .table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .table th {
        background: #f8f9fa;
        font-weight: bold;
        color: #333;
    }
    
    .table tr:hover {
        background: #f8f9fa;
    }
    
    .item-name {
        font-weight: bold;
        color: #333;
    }
    
    .item-description {
        color: #666;
        max-width: 300px;
        word-wrap: break-word;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: bold;
        background: #51cf66;
        color: white;
        text-transform: uppercase;
    }
    
    .contact-btn {
        padding: 8px 15px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.9rem;
        transition: background 0.3s ease;
    }
    
    .contact-btn:hover {
        background: #5a6268;
        text-decoration: none;
        color: white;
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #666;
    }
    
    .empty-state img {
        width: 100px;
        height: 100px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
        .report-history-container {
            padding: 10px;
        }
        
        .search-form {
            flex-direction: column;
        }
        
        .search-input {
            width: 100%;
        }
        
        .table {
            font-size: 0.9rem;
        }
        
        .table th,
        .table td {
            padding: 10px;
        }
        
        .item-description {
            max-width: 200px;
        }
    }
</style>

<div class="report-history-container">
    <!-- Search Section -->
    <div class="search-section">
        <h2><i class="fas fa-history"></i>  Report History</h2>
        <form method="get" class="search-form">
            <input type="text" name="search" class="search-input" 
                   placeholder="Search by item name, description, or location..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
                <a href="reports.php" class="search-btn" style="background: #6c757d; text-decoration: none;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Items Table -->
    <div class="items-table">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Last Known Location</th>
                        <th>Date Lost</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td class="item-description"><?php echo htmlspecialchars(truncateText($row['description'], 100)); ?></td>
                            <td><?php echo htmlspecialchars($row['location_last_seen']); ?></td>
                            <td><?php echo formatDate($row['incident_date'], 'M d, Y'); ?></td>
                            <td>
                                <span class="status-badge">
                                    <?php 
                                        if ($row['ApprovalStatusID'] != 2) {
                                            echo 'Pending';
                                        } else {
                                            echo htmlspecialchars($row['post_status'] ?? 'Unknown');
                                        }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_item_details.php?post_id=<?php echo $row['PostID']; ?>" class="contact-btn" style="background: #5A6268;">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <img src="resources/search.png" alt="No Items">
                <h3>No Lost Item Reports Found</h3>
                <p>You have not submitted any lost item reports yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<?php
$page_content = ob_get_clean();
include_once 'includes/general_layout.php';
?>
