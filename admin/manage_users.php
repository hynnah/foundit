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
        $action = $_POST['action'] ?? '';
        
        // Handle create user
        if ($action === 'create_user') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirm_password = trim($_POST['confirm_password'] ?? '');
            $user_type = trim($_POST['user_type'] ?? 'User');
            
            // Validation
            if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
                $error_message = "Please fill in all required fields.";
            } elseif (strlen($name) < 2 || strlen($name) > 100) {
                $error_message = "Name must be between 2 and 100 characters.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                $error_message = "Invalid email format or email too long (max 100 characters).";
            } elseif (!empty($phone) && (!preg_match('/^[0-9]{10,15}$/', $phone) || strlen($phone) > 20)) {
                $error_message = "Phone number must contain only numbers and be 10-15 digits long.";
            } elseif ($password !== $confirm_password) {
                $error_message = "Passwords do not match.";
            } elseif (strlen($password) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } else {
                // Check if email already exists
                $check_email_sql = "SELECT PersonID FROM Person WHERE email = ?";
                $check_email_stmt = mysqli_prepare($connection, $check_email_sql);
                mysqli_stmt_bind_param($check_email_stmt, "s", $email);
                mysqli_stmt_execute($check_email_stmt);
                $email_result = mysqli_stmt_get_result($check_email_stmt);
                
                if (mysqli_num_rows($email_result) > 0) {
                    $error_message = "Email already exists.";
                } else {
                    // Begin transaction
                    mysqli_begin_transaction($connection);
                    
                    try {
                        // Hash the password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert into Person table
                        $insert_person_sql = "INSERT INTO Person (name, email, phone_number, password, person_type) VALUES (?, ?, ?, ?, ?)";
                        $insert_person_stmt = mysqli_prepare($connection, $insert_person_sql);
                        mysqli_stmt_bind_param($insert_person_stmt, "sssss", $name, $email, $phone, $password_hash, $user_type);
                        mysqli_stmt_execute($insert_person_stmt);
                        
                        $person_id = mysqli_insert_id($connection);
                        
                        // Insert into User table with role
                        $insert_user_sql = "INSERT INTO User (UserID, role) VALUES (?, ?)";
                        $insert_user_stmt = mysqli_prepare($connection, $insert_user_sql);
                        mysqli_stmt_bind_param($insert_user_stmt, "is", $person_id, $role);
                        mysqli_stmt_execute($insert_user_stmt);
                        
                        // If user type is Administrator, add to Administrator table
                        if ($user_type === 'Administrator') {
                            $insert_admin_sql = "INSERT INTO Administrator (AdminID) VALUES (?)";
                            $insert_admin_stmt = mysqli_prepare($connection, $insert_admin_sql);
                            mysqli_stmt_bind_param($insert_admin_stmt, "i", $person_id);
                            mysqli_stmt_execute($insert_admin_stmt);
                        }
                        
                        // Commit transaction
                        mysqli_commit($connection);
                        
                        $success_message = "User created successfully.";
                        
                    } catch (Exception $e) {
                        // Rollback transaction
                        mysqli_rollback($connection);
                        $error_message = "Error creating user: " . $e->getMessage();
                    }
                }
            }
        } else {
            // Handle other actions
            $userId = $_POST['user_id'] ?? '';
            
            if ($userId && in_array($action, ['make_admin', 'remove_admin', 'delete_user', 'activate_user', 'edit_user'])) {
                try {
                switch ($action) {
                    case 'make_admin':
                        // Prevent making system admin (ID=1) a regular user (this shouldn't happen anyway)
                        if ($userId == 1) {
                            $error_message = "Cannot modify system administrator (ID=1).";
                            break;
                        }
                        
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
                        // Prevent removal of system admin (ID=1)
                        if ($userId == 1) {
                            $error_message = "Cannot remove system administrator (ID=1).";
                            break;
                        }
                        
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
                        
                    case 'delete_user':
                        // Prevent deletion of system admin (ID=1)
                        if ($userId == 1) {
                            $error_message = "Cannot deactivate system administrator (ID=1).";
                            break;
                        }
                        
                        // Prevent deletion of current user
                        if ($userId == getUserId()) {
                            $error_message = "Cannot deactivate your own account.";
                            break;
                        }
                        
                        // Soft delete: Update account_status to 'Deactivated'
                        $deactivate_sql = "UPDATE Person SET account_status = 'Deactivated' WHERE PersonID = ?";
                        $deactivate_stmt = mysqli_prepare($connection, $deactivate_sql);
                        mysqli_stmt_bind_param($deactivate_stmt, "i", $userId);
                        mysqli_stmt_execute($deactivate_stmt);
                        
                        if (mysqli_stmt_affected_rows($deactivate_stmt) > 0) {
                            $success_message = "User account has been deactivated.";
                        } else {
                            $error_message = "Error deactivating user account.";
                        }
                        break;
                        
                    case 'activate_user':
                        // Activate user: Update account_status to 'Active'
                        $activate_sql = "UPDATE Person SET account_status = 'Active' WHERE PersonID = ?";
                        $activate_stmt = mysqli_prepare($connection, $activate_sql);
                        mysqli_stmt_bind_param($activate_stmt, "i", $userId);
                        mysqli_stmt_execute($activate_stmt);
                        
                        if (mysqli_stmt_affected_rows($activate_stmt) > 0) {
                            $success_message = "User account has been activated.";
                        } else {
                            $error_message = "Error activating user account.";
                        }
                        break;
                        
                    case 'edit_user':
                        // Prevent editing of system admin (ID=1)
                        if ($userId == 1) {
                            $error_message = "Cannot edit system administrator (ID=1).";
                            break;
                        }
                        
                        $edit_name = trim($_POST['edit_name'] ?? '');
                        $edit_email = trim($_POST['edit_email'] ?? '');
                        $edit_phone = trim($_POST['edit_phone'] ?? '');
                        $edit_password = trim($_POST['edit_password'] ?? '');
                        $edit_user_type = trim($_POST['edit_user_type'] ?? '');
                        $edit_role = trim($_POST['edit_role'] ?? '');
                        
                        // Validation
                        if (empty($edit_name) || empty($edit_email)) {
                            $error_message = "Name and email are required.";
                            break;
                        }
                        
                        if (strlen($edit_name) < 2 || strlen($edit_name) > 100) {
                            $error_message = "Name must be between 2 and 100 characters.";
                            break;
                        }
                        
                        if (!filter_var($edit_email, FILTER_VALIDATE_EMAIL) || strlen($edit_email) > 100) {
                            $error_message = "Invalid email format or email too long (max 100 characters).";
                            break;
                        }
                        
                        if (!empty($edit_phone) && (!preg_match('/^[0-9]{10,15}$/', $edit_phone) || strlen($edit_phone) > 20)) {
                            $error_message = "Phone number must contain only numbers and be 10-15 digits long.";
                            break;
                        }
                        
                        if (!empty($edit_password) && strlen($edit_password) < 8) {
                            $error_message = "Password must be at least 8 characters long.";
                            break;
                        }
                        
                        // Check if email already exists for another user
                        $check_email_sql = "SELECT PersonID FROM Person WHERE email = ? AND PersonID != ?";
                        $check_email_stmt = mysqli_prepare($connection, $check_email_sql);
                        mysqli_stmt_bind_param($check_email_stmt, "si", $edit_email, $userId);
                        mysqli_stmt_execute($check_email_stmt);
                        $email_result = mysqli_stmt_get_result($check_email_stmt);
                        
                        if (mysqli_num_rows($email_result) > 0) {
                            $error_message = "Email already exists for another user.";
                            break;
                        }
                        
                        // Begin transaction
                        mysqli_begin_transaction($connection);
                        
                        try {
                            // Get current user type
                            $current_type_sql = "SELECT person_type FROM Person WHERE PersonID = ?";
                            $current_type_stmt = mysqli_prepare($connection, $current_type_sql);
                            mysqli_stmt_bind_param($current_type_stmt, "i", $userId);
                            mysqli_stmt_execute($current_type_stmt);
                            $current_type_result = mysqli_stmt_get_result($current_type_stmt);
                            $current_user = mysqli_fetch_assoc($current_type_result);
                            $current_user_type = $current_user['person_type'];
                            
                            // Update Person table
                            if (!empty($edit_password)) {
                                $password_hash = password_hash($edit_password, PASSWORD_DEFAULT);
                                $update_sql = "UPDATE Person SET name = ?, email = ?, phone_number = ?, password = ?, person_type = ? WHERE PersonID = ?";
                                $update_stmt = mysqli_prepare($connection, $update_sql);
                                mysqli_stmt_bind_param($update_stmt, "sssssi", $edit_name, $edit_email, $edit_phone, $password_hash, $edit_user_type, $userId);
                            } else {
                                $update_sql = "UPDATE Person SET name = ?, email = ?, phone_number = ?, person_type = ? WHERE PersonID = ?";
                                $update_stmt = mysqli_prepare($connection, $update_sql);
                                mysqli_stmt_bind_param($update_stmt, "ssssi", $edit_name, $edit_email, $edit_phone, $edit_user_type, $userId);
                            }
                            mysqli_stmt_execute($update_stmt);
                            
                            // Update User table role if edit_role is provided
                            if (!empty($edit_role)) {
                                $update_user_sql = "UPDATE User SET role = ? WHERE UserID = ?";
                                $update_user_stmt = mysqli_prepare($connection, $update_user_sql);
                                mysqli_stmt_bind_param($update_user_stmt, "si", $edit_role, $userId);
                                mysqli_stmt_execute($update_user_stmt);
                            }
                            
                            // Handle admin status changes
                            if ($current_user_type === 'Administrator' && $edit_user_type === 'User') {
                                // Remove from Administrator table
                                $delete_admin_sql = "DELETE FROM Administrator WHERE AdminID = ?";
                                $delete_admin_stmt = mysqli_prepare($connection, $delete_admin_sql);
                                mysqli_stmt_bind_param($delete_admin_stmt, "i", $userId);
                                mysqli_stmt_execute($delete_admin_stmt);
                            } elseif ($current_user_type === 'User' && $edit_user_type === 'Administrator') {
                                // Add to Administrator table
                                $insert_admin_sql = "INSERT INTO Administrator (AdminID) VALUES (?)";
                                $insert_admin_stmt = mysqli_prepare($connection, $insert_admin_sql);
                                mysqli_stmt_bind_param($insert_admin_stmt, "i", $userId);
                                mysqli_stmt_execute($insert_admin_stmt);
                            }
                            
                            // Commit transaction
                            mysqli_commit($connection);
                            
                            $success_message = "User updated successfully.";
                            
                        } catch (Exception $e) {
                            // Rollback transaction
                            mysqli_rollback($connection);
                            $error_message = "Error updating user: " . $e->getMessage();
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
}

// Fix for existing users without User table entries
// This ensures all Person records have corresponding User entries
$fix_missing_users_sql = "
    INSERT INTO User (UserID, role)
    SELECT p.PersonID, 
           CASE 
               WHEN p.email LIKE '%@usc.edu.ph' THEN 'Student'
               WHEN p.email LIKE '%admin%' THEN 'Staff'
               ELSE 'Student'
           END as role
    FROM Person p
    LEFT JOIN User u ON p.PersonID = u.UserID
    WHERE u.UserID IS NULL
";
mysqli_query($connection, $fix_missing_users_sql);

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
$sql = "SELECT p.PersonID as UserID, p.name, p.email, p.phone_number, p.person_type, p.account_status,
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
                SUM(CASE WHEN person_type = 'Administrator' THEN 1 ELSE 0 END) as admin_users,
                SUM(CASE WHEN account_status = 'Active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN account_status = 'Deactivated' THEN 1 ELSE 0 END) as inactive_users
              FROM Person";
$stats_result = mysqli_query($connection, $sql_stats);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Manage Users</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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
            
            <div class="stat-card danger">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <h3><?php echo $stats['inactive_users']; ?></h3>
                    <p>Inactive Users</p>
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
            <div class="filter-header">
                <h3>User Management</h3>
                <div>
                    <button type="button" class="btn primary" onclick="openCreateUserModal()">
                        <i class="fas fa-plus"></i> Create New User
                    </button>
                </div>
            </div>
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
                        <th>Status</th>
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
                                <td>
                                    <?php
                                    $account_status = $user['account_status'] ?? 'Active';
                                    $statusClass = strtolower($account_status);
                                    $statusColor = $account_status === 'Active' ? 'success' : 'danger';
                                    ?>
                                    <span class="status-badge <?php echo $statusColor; ?>">
                                        <?php echo htmlspecialchars($account_status); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['report_count']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['UserID'] != getUserId()): // Can't modify own account ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                
                                                <?php if ($user['person_type'] === 'Administrator'): ?>
                                                    <?php if ($user['UserID'] == 1): ?>
                                                        <span class="text-muted">System Admin</span>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="remove_admin" class="btn-sm secondary" onclick="return confirm('Are you sure you want to remove admin privileges?')">
                                                            Remove Admin
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="make_admin" class="btn-sm primary" onclick="return confirm('Are you sure you want to make this user an admin?')">
                                                        Make Admin
                                                    </button>
                                                <?php endif; ?>
                                            </form>                            <!-- Edit User Button -->
                            <?php if ($user['UserID'] != 1): // Can't edit system admin ?>
                                <button type="button" class="btn-sm secondary" 
                                        onclick="openEditUserModal(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['phone_number'], ENT_QUOTES); ?>', '<?php echo $user['person_type']; ?>', '<?php echo htmlspecialchars($user['role'] ?? '', ENT_QUOTES); ?>')">
                                    Edit
                                </button>
                            <?php endif; ?>
                                            <?php if ($user['UserID'] != 1 && $user['UserID'] != getUserId()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                    
                                                    <?php 
                                                    $account_status = $user['account_status'] ?? 'Active';
                                                    if ($account_status === 'Active'): ?>
                                                        <button type="submit" name="action" value="delete_user" class="btn-sm danger" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                                            Deactivate
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="action" value="activate_user" class="btn-sm success" onclick="return confirm('Are you sure you want to activate this user?')">
                                                            Activate
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Current User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No users found</td>
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
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New User</h3>
                <span class="close" onclick="closeCreateUserModal()">&times;</span>
            </div>
            <form method="POST" class="modal-form" id="createUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="create_name">Full Name *</label>
                    <input type="text" id="create_name" name="name" required maxlength="100" 
                           placeholder="Enter full name (2-100 characters)">
                    <div class="char-counter" id="create_name_counter">0/100 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="create_email">Email Address *</label>
                    <input type="email" id="create_email" name="email" required maxlength="100" 
                           placeholder="Enter email address">
                    <div class="char-counter" id="create_email_counter">0/100 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="create_phone">Phone Number</label>
                    <input type="tel" id="create_phone" name="phone" 
                           pattern="[0-9]{10,15}" 
                           title="Please enter 10-15 digits only" 
                           maxlength="15"
                           placeholder="Enter phone number (10-15 digits)">
                    <div class="validation-message" id="create_phone-validation" style="display: none;"></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_role">Role *</label>
                        <select id="create_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="Student">Student</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Staff">Staff</option>
                            <option value="Visitor">Visitor</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Guard">Guard</option>
                            <option value="Janitor">Janitor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_user_type">User Type *</label>
                        <select id="create_user_type" name="user_type" required>
                            <option value="User">Regular User</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_password">Password *</label>
                        <div class="password-input-container">
                            <input type="password" id="create_password" name="password" required minlength="6">
                            <button type="button" class="toggle-password" onclick="toggleModalPassword('create_password')">
                                <i class="fas fa-eye" id="create_password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_confirm_password">Confirm Password *</label>
                        <div class="password-input-container">
                            <input type="password" id="create_confirm_password" name="confirm_password" required minlength="6">
                            <button type="button" class="toggle-password" onclick="toggleModalPassword('create_confirm_password')">
                                <i class="fas fa-eye" id="create_confirm_password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn secondary" onclick="closeCreateUserModal()">Cancel</button>
                    <button type="submit" class="btn primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="close" onclick="closeEditUserModal()">&times;</span>
            </div>
            <form method="POST" class="modal-form" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Full Name *</label>
                        <input type="text" name="edit_name" id="edit_name" required maxlength="100" 
                               placeholder="Enter full name (2-100 characters)">
                        <div class="char-counter" id="edit_name_counter">0/100 characters</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" name="edit_email" id="edit_email" required maxlength="100" 
                               placeholder="Enter email address">
                        <div class="char-counter" id="edit_email_counter">0/100 characters</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_phone">Phone Number</label>
                        <input type="tel" name="edit_phone" id="edit_phone" 
                               pattern="[0-9]{10,15}" 
                               title="Please enter 10-15 digits only" 
                               maxlength="15"
                               placeholder="Enter phone number (10-15 digits)">
                        <div class="validation-message" id="edit_phone-validation" style="display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">System Role</label>
                        <select name="edit_role" id="edit_role">
                            <option value="">Select Role</option>
                            <option value="Student">Student</option>
                            <option value="Teacher">Teacher</option>
                            <option value="Staff">Staff</option>
                            <option value="Visitor">Visitor</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Guard">Guard</option>
                            <option value="Janitor">Janitor</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_user_type">User Type</label>
                        <select name="edit_user_type" id="edit_user_type" required>
                            <option value="User">User</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <!-- Empty space for alignment -->
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                        <div class="password-container">
                            <input type="password" name="edit_password" id="edit_password" placeholder="Leave blank to keep current password">
                            <button type="button" class="toggle-password" onclick="toggleModalPassword('edit_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn primary">Update User</button>
                </div>
            </form>
        </div>
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
        .stat-card.danger { border-left: 4px solid #dc3545; }
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
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .filter-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.4em;
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

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-badge.success { background: #d4edda; color: #155724; }
        .status-badge.danger { background: #f8d7da; color: #721c24; }
        .status-badge.warning { background: #fff3cd; color: #856404; }

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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn.primary {
            background: #007bff;
            color: white;
        }
        
        .btn.primary:hover {
            background: #0056b3;
        }
        
        .btn.secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn.secondary:hover {
            background: #5a6268;
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
        .btn-sm.danger { background: #dc3545; color: white; }
        
        .btn-sm.primary:hover { background: #0056b3; }
        .btn-sm.success:hover { background: #218838; }
        .btn-sm.warning:hover { background: #e0a800; }
        .btn-sm.secondary:hover { background: #5a6268; }
        .btn-sm.danger:hover { background: #c82333; }

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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 40px;
            border-radius: 12px;
            width: 95%;
            max-width: 650px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 100000;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .modal-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .modal-form label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        
        .modal-form input,
        .modal-form select {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .modal-form input:focus,
        .modal-form select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .modal-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .modal-form .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .password-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input-container input {
            padding-right: 45px;
            width: 100%;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s ease;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #007bff;
            background: rgba(0, 123, 255, 0.1);
        }
        
        .toggle-password:focus {
            outline: 2px solid #007bff;
            outline-offset: 2px;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 98%;
                margin: 2% auto;
                padding: 25px;
            }
            
            .modal-form .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
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

        /* Character counter styles */
        .char-counter {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
            text-align: right;
        }

        .char-counter.warning {
            color: #ff6b35;
        }

        .char-counter.error {
            color: #dc3545;
        }
        
        .validation-message {
            font-size: 12px;
            color: #dc3545;
            margin-top: 5px;
            padding: 5px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
    </style>

    <script>
        // Simple modal functions - back to basics
        function openCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'block';
        }
        
        function closeCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'none';
            document.getElementById('createUserForm').reset();
        }
        
        function openEditUserModal(userId, name, email, phone, userType, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone || '';
            document.getElementById('edit_user_type').value = userType;
            document.getElementById('edit_role').value = role || '';
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        function toggleModalPassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId + '-toggle-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createUserModal');
            const editModal = document.getElementById('editUserModal');
            
            if (event.target === createModal) {
                closeCreateUserModal();
            } else if (event.target === editModal) {
                closeEditUserModal();
            }
        }
        
        // Character counter function
        function updateCharCounter(input, maxLength, counterId) {
            const current = input.value.length;
            const counter = document.getElementById(counterId);
            
            if (counter) {
                counter.textContent = current + '/' + maxLength + ' characters';
                
                // Update counter color based on usage
                if (current >= maxLength * 0.9) {
                    counter.className = 'char-counter error';
                } else if (current >= maxLength * 0.7) {
                    counter.className = 'char-counter warning';
                } else {
                    counter.className = 'char-counter';
                }
            }
        }
        
        // Validation helper functions
        function showValidationMessage(fieldId, message) {
            const validationDiv = document.getElementById(fieldId + '-validation');
            if (validationDiv) {
                validationDiv.textContent = message;
                validationDiv.style.display = 'block';
            }
        }
        
        function hideValidationMessage(fieldId) {
            const validationDiv = document.getElementById(fieldId + '-validation');
            if (validationDiv) {
                validationDiv.style.display = 'none';
            }
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize character counters
            const createNameField = document.getElementById('create_name');
            const createEmailField = document.getElementById('create_email');
            const editNameField = document.getElementById('edit_name');
            const editEmailField = document.getElementById('edit_email');
            
            if (createNameField) {
                createNameField.addEventListener('input', function() {
                    updateCharCounter(this, 100, 'create_name_counter');
                });
                updateCharCounter(createNameField, 100, 'create_name_counter');
            }
            
            if (createEmailField) {
                createEmailField.addEventListener('input', function() {
                    updateCharCounter(this, 100, 'create_email_counter');
                });
                updateCharCounter(createEmailField, 100, 'create_email_counter');
            }
            
            if (editNameField) {
                editNameField.addEventListener('input', function() {
                    updateCharCounter(this, 100, 'edit_name_counter');
                });
                updateCharCounter(editNameField, 100, 'edit_name_counter');
            }
            
            if (editEmailField) {
                editEmailField.addEventListener('input', function() {
                    updateCharCounter(this, 100, 'edit_email_counter');
                });
                updateCharCounter(editEmailField, 100, 'edit_email_counter');
            }
            
            // Phone number validation
            const phoneField = document.getElementById('create_phone');
            const editPhoneField = document.getElementById('edit_phone');
            
            if (phoneField) {
                phoneField.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Validate phone number
                    if (this.value.length > 0 && this.value.length < 10) {
                        showValidationMessage('create_phone', 'Phone number must be at least 10 digits');
                    } else if (this.value.length > 15) {
                        showValidationMessage('create_phone', 'Phone number cannot exceed 15 digits');
                    } else if (this.value.length >= 10 && this.value.length <= 15) {
                        hideValidationMessage('create_phone');
                    }
                });
                
                phoneField.addEventListener('blur', function(e) {
                    if (this.value.length === 0) {
                        hideValidationMessage('create_phone');
                    }
                });
            }
            
            if (editPhoneField) {
                editPhoneField.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Validate phone number
                    if (this.value.length > 0 && this.value.length < 10) {
                        showValidationMessage('edit_phone', 'Phone number must be at least 10 digits');
                    } else if (this.value.length > 15) {
                        showValidationMessage('edit_phone', 'Phone number cannot exceed 15 digits');
                    } else if (this.value.length >= 10 && this.value.length <= 15) {
                        hideValidationMessage('edit_phone');
                    }
                });
                
                editPhoneField.addEventListener('blur', function(e) {
                    if (this.value.length === 0) {
                        hideValidationMessage('edit_phone');
                    }
                });
            }
            
            // Password confirmation validation
            const confirmPasswordField = document.getElementById('create_confirm_password');
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function(e) {
                    const password = document.getElementById('create_password').value;
                    const confirmPassword = this.value;
                    
                    if (password !== confirmPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>

    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>
