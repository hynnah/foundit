<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Found Items Management";

// Handle status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $postId = $_POST['post_id'] ?? '';
        
        if ($postId && in_array($action, ['archive', 'reactivate'])) {
            $newStatus = ($action === 'archive') ? 'Archived' : 'Active';
            
            $sql_update = "UPDATE FeedPost SET post_status = ? WHERE PostID = ?";
            $stmt_update = mysqli_prepare($connection, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "si", $newStatus, $postId);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $success_message = "Item status updated to " . $newStatus . " successfully.";
            } else {
                $error_message = "Error updating item status: " . mysqli_error($connection);
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error_message = "Invalid action or post ID.";
        }
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query
$where_conditions = ["r.report_type = 'Found'"];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(r.item_name LIKE ? OR r.description LIKE ? OR f.location_found LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($filter_status) {
    if ($filter_status === 'Pending') {
        // Items without FeedPost (truly pending)
        $where_conditions[] = "fp.PostID IS NULL";
    } else {
        // Items with specific status in FeedPost
        $where_conditions[] = "fp.post_status = ?";
        $params[] = $filter_status;
        $param_types .= 's';
    }
}

if ($filter_date) {
    $where_conditions[] = "DATE(r.incident_date) = ?";
    $params[] = $filter_date;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$sql = "SELECT r.*, 
               f.location_found,
               fp.post_status,
               fp.post_date,
               fp.PostID,
               p.name as submitter_name,
               COUNT(cr.ContactID) as contact_requests
        FROM Report r
        JOIN Found f ON r.ReportID = f.ReportID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        LEFT JOIN User u ON r.UserID_submitter = u.UserID
        LEFT JOIN Person p ON u.UserID = p.PersonID
        LEFT JOIN ContactRequest cr ON fp.PostID = cr.PostID
        $where_clause
        GROUP BY r.ReportID
        ORDER BY r.submission_date DESC";

$stmt = mysqli_prepare($connection, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get stats
$stats_sql = "SELECT 
                COUNT(*) as total_found,
                SUM(CASE WHEN fp.post_status = 'Active' THEN 1 ELSE 0 END) as active_items,
                SUM(CASE WHEN fp.post_status = 'Claimed' THEN 1 ELSE 0 END) as claimed_items,
                SUM(CASE WHEN fp.post_status = 'Archived' THEN 1 ELSE 0 END) as archived_items,
                SUM(CASE WHEN fp.PostID IS NULL THEN 1 ELSE 0 END) as pending_items
              FROM Report r
              JOIN Found f ON r.ReportID = f.ReportID
              LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
              WHERE r.report_type = 'Found'";
$stats_result = mysqli_query($connection, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
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

    <div class="found-items-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <strong>Error!</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_found'] ?? 0; ?></h3>
                    <p>Total Found Items</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_items'] ?? 0; ?></h3>
                    <p>Pending Items</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['active_items'] ?? 0; ?></h3>
                    <p>Active Items</p>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <h3><?php echo $stats['claimed_items'] ?? 0; ?></h3>
                    <p>Claimed Items</p>
                </div>
            </div>
            
            <div class="stat-card archived">
                <div class="stat-icon">📁</div>
                <div class="stat-content">
                    <h3><?php echo $stats['archived_items'] ?? 0; ?></h3>
                    <p>Archived Items</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters">
            <form method="get" class="filter-row" id="filterForm">
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" placeholder="Search items..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                       
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Claimed" <?php echo $filter_status === 'Claimed' ? 'selected' : ''; ?>>Claimed</option>
                        <option value="Archived" <?php echo $filter_status === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="found_items.php" class="btn btn-secondary">Clear</a>
                    <a href="log_found_item.php" class="btn btn-success">+ Log New Item</a>
                </div>
            </form>
        </div>

        <!-- Found Items List -->
        <div class="items-grid">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="item-card">
                        <?php if ($row['image_path']): ?>
                            <div class="item-image">
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
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($row['item_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.classList.add('placeholder'); this.parentElement.innerHTML='<span>📦</span>';">
                            </div>
                        <?php else: ?>
                            <div class="item-image placeholder">
                                <span>📦</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-content">
                            <div class="item-header">
                                <h3><?php echo htmlspecialchars($row['item_name']); ?></h3>
                                <span class="status-badge status-<?php echo strtolower($row['post_status'] ?? 'pending'); ?>">
                                    <?php echo htmlspecialchars($row['post_status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            
                            <div class="item-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Location Found:</span>
                                    <span><?php echo htmlspecialchars($row['location_found']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Date Found:</span>
                                    <span><?php echo formatDate($row['incident_date'], 'M d, Y'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Logged by:</span>
                                    <span><?php echo htmlspecialchars($row['submitter_name'] ?? 'System'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Contact Requests:</span>
                                    <span class="contact-count"><?php echo $row['contact_requests']; ?></span>
                                </div>
                            </div>
                            
                            <div class="item-description">
                                <p><?php echo htmlspecialchars(truncateText($row['description'], 120)); ?></p>
                            </div>
                            
                            <div class="item-actions">
                                <a href="view_report.php?id=<?php echo $row['ReportID']; ?>" class="btn btn-primary">View Details</a>
                                
                                <?php if ($row['contact_requests'] > 0): ?>
                                    <a href="inbox.php" class="btn btn-info">
                                        View Requests (<?php echo $row['contact_requests']; ?>)
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($row['post_status'] === 'Active'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="post_id" value="<?php echo $row['PostID']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to archive this item?')">Archive</button>
                                    </form>
                                <?php elseif ($row['post_status'] === 'Archived'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="reactivate">
                                        <input type="hidden" name="post_id" value="<?php echo $row['PostID']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to reactivate this item?')">Reactivate</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <img src="../resources/search.png" alt="No Items">
                    <h3>No Found Items</h3>
                    <p>No found items match your current filters.</p>
                    <a href="log_found_item.php" class="btn btn-primary">Log First Item</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .found-items-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.info { border-left: 4px solid #17a2b8; }
        .stat-card.archived { border-left: 4px solid #7c3aed; }

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

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
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

        .filter-row button,
        .filter-row .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .item-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }

        .item-card:hover {
            transform: translateY(-2px);
        }

        .item-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image.placeholder {
            font-size: 4em;
            color: #ccc;
        }

        .item-content {
            padding: 20px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .item-header h3 {
            margin: 0;
            color: #333;
        }

        .item-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-weight: bold;
            color: #666;
            font-size: 0.8em;
        }

        .contact-count {
            color: #007bff;
            font-weight: bold;
        }

        .item-description {
            margin-bottom: 15px;
            color: #666;
        }

        .item-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .item-actions .btn {
            padding: 6px 12px;
            font-size: 12px;
        }

        .item-actions form {
            margin: 0;
        }

        .item-actions form button {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-claimed { background: #d1ecf1; color: #0c5460; }
        .status-archived { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }

        .empty-state {
            grid-column: 1 / -1;
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

        @media (max-width: 768px) {
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Add filter change detection and debugging
            const filterForm = document.querySelector('.filter-row');
            if (filterForm) {
                const inputs = filterForm.querySelectorAll('input, select');
                inputs.forEach(function(input) {
                    input.addEventListener('change', function() {
                        console.log('Filter changed:', this.name, '=', this.value);
                        // Auto-submit form when dropdown changes (optional)
                        // filterForm.submit();
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
            const statusSelect = document.querySelector('select[name="status"]');
            if (statusSelect) {
                console.log('Status select current value:', statusSelect.value);
                console.log('Status select options:', Array.from(statusSelect.options).map(opt => opt.value));
            }
        });
    </script>

    <!-- Please dont remove -->  
    <?php
        $page_content = ob_get_clean();
        include_once '../includes/admin_layout.php';
    ?>

</body>
</html>