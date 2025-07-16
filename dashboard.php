<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = htmlspecialchars(getUserName());
$content_header = "Recent Posts - All Items";

// Fetch recent reports (both lost and found) - show all approved and active posts
$recent_reports = [];
$sql = "SELECT r.*, 
               l.location_last_seen AS lost_location, 
               f.location_found AS found_location,
               f.vague_item_name AS vague_item_name,
               a.status_name AS approvalstatus,
               fp.PostID,
               p.name AS submitter_name,
               p.person_type AS submitter_role
        FROM Report r
        LEFT JOIN Lost l ON r.ReportID = l.ReportID AND r.report_type = 'Lost'
        LEFT JOIN Found f ON r.ReportID = f.ReportID AND r.report_type = 'Found'
        LEFT JOIN ApprovalStatus a ON r.ApprovalStatusID = a.ApprovalStatusID
        LEFT JOIN FeedPost fp ON r.ReportID = fp.ReportID
        LEFT JOIN Person p ON r.UserID_submitter = p.PersonID
        WHERE r.ApprovalStatusID = 2 AND fp.post_status = 'Active'
        ORDER BY r.submission_date DESC
        LIMIT 50";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$lost_count = 0;
$found_count = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_reports[] = $row;
        if ($row['report_type'] === 'Lost') {
            $lost_count++;
        } else {
            $found_count++;
        }
    }
}

// Start output buffering to capture the page content
ob_start();
?>
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
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: 2px solid rgba(203, 127, 0, 0.2);
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 150px;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #333;
            font-size: 0.9rem;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #cb7f00;
            box-shadow: 0 0 0 3px rgba(203, 127, 0, 0.1);
        }
        
        .filter-group select:hover {
            border-color: #cb7f00;
        }
        
        .clear-filters-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .clear-filters-btn:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .filter-results {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: rgba(203, 127, 0, 0.1);
            border-radius: 8px;
            font-weight: bold;
            color: #cb7f00;
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
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        
        .post-card {
            background: rgba(255, 227, 142, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 180px;
            border: 2px solid rgba(203, 127, 0, 0.2);
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(203, 127, 0, 0.2);
        }
        
        .post-body {
            display: flex;
            flex-direction: row;
            flex: 1;
        }
        
        .post-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border-color: rgba(203, 127, 0, 0.4);
        }
        
        .post-image {
            width: 220px;
            height: 180px;
            object-fit: cover;
            flex-shrink: 0;
            border-right: 2px solid rgba(203, 127, 0, 0.2);
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .post-image:hover {
            border-color: #cb7f00;
            box-shadow: 0 4px 16px rgba(203, 127, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .found-item-placeholder {
            width: 220px;
            height: 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .found-item-placeholder:hover {
            border-color: #cb7f00;
            box-shadow: 0 4px 16px rgba(203, 127, 0, 0.2);
            transform: translateY(-2px);
        }
        
        .post-details {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 227, 142, 0.7));
        }
        
        .post-username {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #333;
            font-weight: bold;
            font-size: 1rem;
        }
        
        .post-username i {
            color: #666;
            font-size: 1.2rem;
        }
        
        .user-role-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 8px;
        }
        
        .user-role-tag.admin {
            background-color: #ff6b6b;
            color: white;
        }
        
        .user-role-tag.student {
            background-color: #5A6268;
            color: white;
        }
        
        .user-role-tag.user {
            background-color: #5A6268;
            color: white;
        }
        
        .post-timestamp {
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        .post-content {
            flex: 1;
            margin-bottom: 15px;
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
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            color: #666;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.7);
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(203, 127, 0, 0.2);
        }
        
        .post-meta i {
            margin-right: 5px;
            color: #cb7f00;
        }
        
        .post-location, .post-date, .post-submitter {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .post-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }
        
        .view-details-btn {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .view-details-btn:hover {
            background: #5A6268;
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
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
            .post-body {
                flex-direction: column;
            }
            
            .post-image {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 2px solid rgba(203, 127, 0, 0.2);
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            
            .found-item-placeholder {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 2px solid rgba(203, 127, 0, 0.2);
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            
            .post-header {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            
            .post-timestamp {
                font-size: 0.8rem;
            }
            
            .post-meta {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .post-meta > div {
                justify-content: center;
            }
            
            .post-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .feed-header h1 {
                font-size: 2rem;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-group {
                width: 100%;
                max-width: 300px;
            }
            
            .filter-group select,
            .filter-group input {
                width: 100%;
            }
        }
    </style>

<!-- Main content -->
<div class="feed-container">
    <div class="feed-header">
        <h1>Recent Lost & Found Items</h1>
        <p>Check out the latest items reported in our community (<?php echo count($recent_reports); ?> items available)</p>
    </div>
    
    <!-- Filter Section -->
    <div class="filters">
        <div class="filter-row">
            <div class="filter-group">
                <label for="typeFilter">Filter by Type:</label>
                <select id="typeFilter" onchange="filterPosts()">
                    <option value="">All Types (<?php echo count($recent_reports); ?>)</option>
                    <option value="Lost">Lost Items (<?php echo $lost_count; ?>)</option>
                    <option value="Found">Found Items (<?php echo $found_count; ?>)</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="dateFilter">Filter by Date:</label>
                <select id="dateFilter" onchange="filterPosts()">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="3months">Last 3 Months</option>
                    <option value="custom">Custom Date Range</option>
                </select>
            </div>
            
            <div class="filter-group" id="customDateGroup" style="display: none;">
                <label for="startDate">From Date:</label>
                <input type="date" id="startDate" onchange="filterPosts()" 
                       style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
            </div>
            
            <div class="filter-group" id="customDateToGroup" style="display: none;">
                <label for="endDate">To Date:</label>
                <input type="date" id="endDate" onchange="filterPosts()" 
                       style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;">
            </div>
            
            <div class="filter-group">
                <label for="sortFilter">Sort by:</label>
                <select id="sortFilter" onchange="sortPosts()">
                    <option value="date_desc">Newest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="type">Type (Lost/Found)</option>
                    <option value="name">Item Name</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="searchFilter">Search Items:</label>
                <input type="text" id="searchFilter" placeholder="Search by item name..." 
                       style="padding: 8px 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;"
                       onkeyup="filterPosts()">
            </div>
        </div>
        
        <div style="text-align: center;">
            <button class="clear-filters-btn" onclick="clearFilters()">
                <i class="fas fa-refresh"></i> Clear Filters
            </button>
        </div>
    </div>
    
    <div class="filter-results" id="filterResults" style="display: none;"></div>
    
    <?php if (!empty($recent_reports)): ?>
        <div class="posts-grid" id="postsContainer">
            <?php foreach ($recent_reports as $report): ?>
                <div class="post-card" 
                     data-type="<?php echo strtolower($report['report_type']); ?>"
                     data-name="<?php echo strtolower($report['report_type'] === 'Found' ? ($report['vague_item_name'] ?? $report['item_name']) : $report['item_name']); ?>"
                     data-date="<?php echo strtotime($report['submission_date']); ?>"
                     data-incident-date="<?php echo strtotime($report['incident_date']); ?>"
                     data-search="<?php echo strtolower(($report['report_type'] === 'Found' ? ($report['vague_item_name'] ?? $report['item_name']) : $report['item_name']) . ' ' . $report['description']); ?>">
                    
                    <div class="post-header">
                        <div class="post-username">
                            <i class="fas fa-user-circle"></i>
                            @<?php echo htmlspecialchars($report['submitter_name'] ?? 'unknown.user'); ?>
                            <?php 
                            $roleClass = ($report['submitter_role'] === 'Administrator') ? 'admin' : 'student';
                            $roleText = ($report['submitter_role'] === 'Administrator') ? 'Admin' : 'Student';
                            ?>
                            <span class="user-role-tag <?php echo $roleClass; ?>">
                                <?php echo $roleText; ?>
                            </span>
                        </div>
                        <div class="post-timestamp">
                            Posted on: <?php echo date('m/d/y g:i A', strtotime($report['submission_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="post-body">
                        <div class="post-details">
                            <div class="post-content">
                            <span class="post-type <?php echo strtolower($report['report_type']); ?>">
                                <?php echo ucfirst(htmlspecialchars($report['report_type'])); ?>
                            </span>
                            <h3 class="post-title"><?php echo htmlspecialchars($report['report_type'] === 'Found' ? ($report['vague_item_name'] ?? $report['item_name']) : $report['item_name']); ?></h3>
                            <p class="post-description"><?php echo htmlspecialchars(truncateText($report['description'], 150)); ?></p>
                        </div>
                        
                        <div class="post-meta">
                            <div class="post-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php
                                    if ($report['report_type'] === 'Lost') {
                                        echo "Last seen at: " . htmlspecialchars($report['lost_location'] ?? 'Unknown location');
                                    } else {
                                        echo "Found at: " . htmlspecialchars($report['found_location'] ?? 'Unknown location');
                                    }
                                ?>
                            </div>
                            <div class="post-date">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('M j, Y', strtotime($report['incident_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="post-actions">
                            <a href="view_item_details.php?post_id=<?php echo $report['PostID']; ?>" class="view-details-btn">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-posts">
            <p>No recent posts found. Be the first to report a lost item!</p>
        </div>
    <?php endif; ?>
    
    <a href="report_lost_item.php" class="report-btn">
        <i class="fas fa-plus"></i> Report Lost Item
    </a>
</div>

<script>
    // Filter and sort functionality
    function filterPosts() {
        const typeFilter = document.getElementById('typeFilter').value;
        const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
        const dateFilter = document.getElementById('dateFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const posts = document.querySelectorAll('.post-card');
        const filterResults = document.getElementById('filterResults');
        
        let visibleCount = 0;
        
        // Handle date filtering
        let dateFromTimestamp = null;
        let dateToTimestamp = null;
        
        if (dateFilter === 'custom') {
            // Use custom date range
            dateFromTimestamp = startDate ? new Date(startDate).getTime() / 1000 : null;
            dateToTimestamp = endDate ? new Date(endDate).getTime() / 1000 : null;
        } else if (dateFilter) {
            // Use predefined date ranges
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            
            switch (dateFilter) {
                case 'today':
                    dateFromTimestamp = today.getTime() / 1000;
                    dateToTimestamp = (today.getTime() + 86400000) / 1000; // +1 day
                    break;
                case 'yesterday':
                    const yesterday = new Date(today.getTime() - 86400000);
                    dateFromTimestamp = yesterday.getTime() / 1000;
                    dateToTimestamp = today.getTime() / 1000;
                    break;
                case 'week':
                    const weekAgo = new Date(today.getTime() - (7 * 86400000));
                    dateFromTimestamp = weekAgo.getTime() / 1000;
                    break;
                case 'month':
                    const monthAgo = new Date(today.getTime() - (30 * 86400000));
                    dateFromTimestamp = monthAgo.getTime() / 1000;
                    break;
                case '3months':
                    const threeMonthsAgo = new Date(today.getTime() - (90 * 86400000));
                    dateFromTimestamp = threeMonthsAgo.getTime() / 1000;
                    break;
            }
        }
        
        posts.forEach(post => {
            const postType = post.getAttribute('data-type');
            const postSearch = post.getAttribute('data-search');
            const postIncidentDate = parseInt(post.getAttribute('data-incident-date'));
            
            let showPost = true;
            
            // Filter by type
            if (typeFilter && postType !== typeFilter.toLowerCase()) {
                showPost = false;
            }
            
            // Filter by search
            if (searchFilter && !postSearch.includes(searchFilter)) {
                showPost = false;
            }
            
            // Filter by date range
            if (dateFromTimestamp && postIncidentDate < dateFromTimestamp) {
                showPost = false;
            }
            if (dateToTimestamp && postIncidentDate > dateToTimestamp) {
                showPost = false;
            }
            
            if (showPost) {
                post.style.display = 'flex';
                visibleCount++;
            } else {
                post.style.display = 'none';
            }
        });
        
        // Show filter results
        const hasFilters = typeFilter || searchFilter || dateFilter || startDate || endDate;
        if (hasFilters) {
            filterResults.style.display = 'block';
            filterResults.innerHTML = `Showing ${visibleCount} of ${posts.length} items`;
        } else {
            filterResults.style.display = 'none';
        }
    }
    
    function sortPosts() {
        const sortBy = document.getElementById('sortFilter').value;
        const container = document.getElementById('postsContainer');
        const posts = Array.from(container.querySelectorAll('.post-card'));
        
        posts.sort((a, b) => {
            switch (sortBy) {
                case 'date_desc':
                    return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                case 'date_asc':
                    return parseInt(a.getAttribute('data-date')) - parseInt(b.getAttribute('data-date'));
                case 'type':
                    const typeA = a.getAttribute('data-type');
                    const typeB = b.getAttribute('data-type');
                    return typeA.localeCompare(typeB);
                case 'name':
                    const nameA = a.getAttribute('data-name');
                    const nameB = b.getAttribute('data-name');
                    return nameA.localeCompare(nameB);
                default:
                    return 0;
            }
        });
        
        posts.forEach(post => container.appendChild(post));
    }
    
    function clearFilters() {
        document.getElementById('typeFilter').value = '';
        document.getElementById('searchFilter').value = '';
        document.getElementById('dateFilter').value = '';
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('sortFilter').value = 'date_desc';
        
        // Hide custom date inputs
        document.getElementById('customDateGroup').style.display = 'none';
        document.getElementById('customDateToGroup').style.display = 'none';
        
        filterPosts();
        sortPosts();
    }
    
    // Show/hide custom date inputs
    function toggleCustomDateInputs() {
        const dateFilter = document.getElementById('dateFilter').value;
        const customDateGroup = document.getElementById('customDateGroup');
        const customDateToGroup = document.getElementById('customDateToGroup');
        
        if (dateFilter === 'custom') {
            customDateGroup.style.display = 'block';
            customDateToGroup.style.display = 'block';
        } else {
            customDateGroup.style.display = 'none';
            customDateToGroup.style.display = 'none';
        }
    }
    
    // Auto-apply filters when page loads
    document.addEventListener('DOMContentLoaded', function() {
        filterPosts();
        
        // Add event listeners for date filters
        document.getElementById('dateFilter').addEventListener('change', function() {
            toggleCustomDateInputs();
            filterPosts();
        });
        document.getElementById('startDate').addEventListener('change', filterPosts);
        document.getElementById('endDate').addEventListener('change', filterPosts);
    });
</script>

<?php
// Capture the page content and include the layout
$page_content = ob_get_clean();
include_once "includes/general_layout.php";
?>
