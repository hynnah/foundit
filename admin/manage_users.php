<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Manage Users";

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $userId = $_POST['user_id'] ?? '';
        $action = $_POST['action'] ?? '';
        
        if ($userId && in_array($action, ['make_admin', 'remove_admin'])) {
            try {
                switch ($action) {
                    case 'make_admin':
                        // Check if user already exists in Administrator table
                        $check_sql = "SELECT AdminID FROM Administrator WHERE AdminID = ?";
                        $check_stmt = mysqli_prepare($connection, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "i", $userId);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        
                        if (mysqli_num_rows($check_result) === 0) {
                            // Update person_type to Administrator
                            $sql = "UPDATE Person SET person_type = 'Administrator' WHERE PersonID = ?";
                            $stmt = mysqli_prepare($connection, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $userId);
                            mysqli_stmt_execute($stmt);
                            
                            // Add to Administrator table
                            $sql_admin = "INSERT INTO Administrator (AdminID) VALUES (?)";
                            $stmt_admin = mysqli_prepare($connection, $sql_admin);
                            mysqli_stmt_bind_param($stmt_admin, "i", $userId);
                            mysqli_stmt_execute($stmt_admin);
                            
                            $success_message = "User promoted to admin successfully.";
                        } else {
                            $error_message = "User is already an administrator.";
                        }
                        break;
                        
                    case 'remove_admin':
                        // Check if this is not the last admin
                        $count_sql = "SELECT COUNT(*) as admin_count FROM Administrator";
                        $count_result = mysqli_query($connection, $count_sql);
                        $admin_count = mysqli_fetch_assoc($count_result)['admin_count'];
                        
                        if ($admin_count > 1) {
                            // Update person_type to User
                            $sql = "UPDATE Person SET person_type = 'User' WHERE PersonID = ?";
                            $stmt = mysqli_prepare($connection, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $userId);
                            mysqli_stmt_execute($stmt);
                            
                            // Remove from Administrator table
                            $sql_admin = "DELETE FROM Administrator WHERE AdminID = ?";
                            $stmt_admin = mysqli_prepare($connection, $sql_admin);
                            mysqli_stmt_bind_param($stmt_admin, "i", $userId);
                            mysqli_stmt_execute($stmt_admin);
                            
                            $success_message = "Admin privileges removed successfully.";
                        } else {
                            $error_message = "Cannot remove the last administrator.";
                        }
                        break;
                }
                
                // Log the action
                logActivity($userId, 'User Management', "$action performed by admin");
                
            } catch (Exception $e) {
                $error_message = "Error performing action: " . $e->getMessage();
            }
        } else {
            $error_message = "Invalid action or user ID.";
        }
    }
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$page = max(1, $_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($filter_role === 'admin') {
    $where_conditions[] = "p.person_type = 'Administrator'";
} elseif ($filter_role === 'user') {
    $where_conditions[] = "p.person_type = 'User'";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users
$sql = "SELECT p.PersonID as UserID, p.name, p.email, p.phone_number, p.person_type,
               u.role,
               (SELECT COUNT(*) FROM Report WHERE UserID_submitter = p.PersonID) as report_count
        FROM Person p
        LEFT JOIN User u ON p.PersonID = u.UserID
        $where_clause
        ORDER BY p.PersonID DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($connection, $sql);
if ($params) {
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= 'ii';
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
}

mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total
              FROM Person p
              LEFT JOIN User u ON p.PersonID = u.UserID
              $where_clause";

$count_stmt = mysqli_prepare($connection, $count_sql);
if ($params && count($params) > 2) {
    $count_params = array_slice($params, 0, -2); // Remove limit and offset
    $count_param_types = substr($param_types, 0, -2);
    mysqli_stmt_bind_param($count_stmt, $count_param_types, ...$count_params);
}
mysqli_stmt_execute($count_stmt);
$total_users = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_users / $limit);

// Get statistics
$sql_stats = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN person_type = 'User' THEN 1 ELSE 0 END) as regular_users,
                SUM(CASE WHEN person_type = 'Administrator' THEN 1 ELSE 0 END) as admin_users
              FROM Person";
$stats_result = mysqli_query($connection, $sql_stats);
$stats = mysqli_fetch_assoc($stats_result);
$stats['active_users'] = $stats['total_users']; // Since we don't have account_status
$stats['inactive_users'] = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Manage Users</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <?php
    ob_start();
    ?> 

    <div class="manage-users-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $stats['active_users']; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo $stats['regular_users']; ?></h3>
                    <p>Regular Users</p>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">👑</div>
                <div class="stat-content">
                    <h3><?php echo $stats['admin_users']; ?></h3>
                    <p>Administrators</p>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" class="filters-form" id="filterForm">
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn primary">Filter</button>
                    <a href="manage_users.php" class="btn secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>System Role</th>
                        <th>Reports</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($users) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($users)): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                        <br><small>ID: <?php echo $user['UserID']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                        <?php if ($user['phone_number']): ?>
                                            <br><small><?php echo htmlspecialchars($user['phone_number']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['person_type'] === 'Administrator'): ?>
                                        <span class="role-badge admin">Administrator</span>
                                    <?php else: ?>
                                        <span class="role-badge user">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role']): ?>
                                        <span class="system-role"><?php echo htmlspecialchars($user['role']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['report_count']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['UserID'] != getUserId()): // Can't modify own account ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                
                                                <?php if ($user['person_type'] === 'Administrator'): ?>
                                                    <button type="submit" name="action" value="remove_admin" class="btn-sm secondary" onclick="return confirm('Are you sure you want to remove admin privileges?')">
                                                        Remove Admin
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="make_admin" class="btn-sm primary" onclick="return confirm('Are you sure you want to make this user an admin?')">
                                                        Make Admin
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">Current User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>" class="page-link">Previous</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>" class="page-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($filter_role); ?>" class="page-link">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .manage-users-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.info { border-left: 4px solid #17a2b8; }

        .stat-icon {
            font-size: 2em;
            opacity: 0.8;
        }

        .stat-content h3 {
            margin: 0;
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
        }

        .stat-content p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 0.9em;
        }

        .search-filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-form {
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

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9em;
            background: white;
            min-width: 120px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .users-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .user-info strong {
            color: #333;
        }

        .contact-info {
            font-size: 0.9em;
        }

        .status-badge,
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #f8d7da; color: #721c24; }

        .role-badge.admin { background: #d1ecf1; color: #0c5460; }
        .role-badge.user { background: #e2e3e5; color: #383d41; }

        .system-role {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8em;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-sm.primary { background: #007bff; color: white; }
        .btn-sm.success { background: #28a745; color: white; }
        .btn-sm.warning { background: #ffc107; color: #212529; }
        .btn-sm.secondary { background: #6c757d; color: white; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
        }

        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .page-link:hover:not(.active) {
            background: #f8f9fa;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #6c757d;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .users-table-container {
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>

    <script>
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
            const roleSelect = document.querySelector('select[name="role"]');
            if (roleSelect) {
                console.log('Role select current value:', roleSelect.value);
                console.log('Role select options:', Array.from(roleSelect.options).map(opt => opt.value));
            }
        });
    </script>

    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>

</html>
