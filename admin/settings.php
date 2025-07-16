<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Settings";

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = getUserId();
        
        switch ($action) {
            case 'update_profile':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                // Validation
                $errors = [];
                
                if (empty($name)) {
                    $errors[] = "Name is required.";
                } elseif (strlen($name) > 100) {
                    $errors[] = "Name cannot exceed 100 characters.";
                }
                
                if (empty($email)) {
                    $errors[] = "Email is required.";
                } elseif (!validateEmail($email)) {
                    $errors[] = "Invalid email format.";
                }
                
                if (!empty($phone) && !validatePhone($phone)) {
                    $errors[] = "Invalid phone number format.";
                }
                
                if (empty($errors)) {
                    // Check if email already exists (for other users)
                    $sql_check = "SELECT PersonID FROM Person WHERE email = ? AND PersonID != ?";
                    $stmt_check = mysqli_prepare($connection, $sql_check);
                    mysqli_stmt_bind_param($stmt_check, "si", $email, $userId);
                    mysqli_stmt_execute($stmt_check);
                    $result_check = mysqli_stmt_get_result($stmt_check);
                    
                    if (mysqli_num_rows($result_check) > 0) {
                        $error_message = "Email already exists. Please use a different email.";
                    } else {
                        // Update profile
                        $sql_update = "UPDATE Person SET name = ?, email = ?, phone_number = ? WHERE PersonID = ?";
                        $stmt_update = mysqli_prepare($connection, $sql_update);
                        mysqli_stmt_bind_param($stmt_update, "sssi", $name, $email, $phone, $userId);
                        
                        if (mysqli_stmt_execute($stmt_update)) {
                            $_SESSION['userName'] = $name;
                            $_SESSION['userEmail'] = $email;
                            $success_message = "Profile updated successfully!";
                            logActivity($userId, 'PROFILE_UPDATED', "Name: $name, Email: $email");
                        } else {
                            $error_message = "Error updating profile. Please try again.";
                        }
                        mysqli_stmt_close($stmt_update);
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                    $error_message = implode('<br>', $errors);
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                // Validation
                $errors = [];
                
                if (empty($currentPassword)) {
                    $errors[] = "Current password is required.";
                }
                
                if (empty($newPassword)) {
                    $errors[] = "New password is required.";
                } elseif (strlen($newPassword) < 8) {
                    $errors[] = "New password must be at least 8 characters long.";
                }
                
                if ($newPassword !== $confirmPassword) {
                    $errors[] = "New passwords do not match.";
                }
                
                if (empty($errors)) {
                    // Verify current password
                    $sql_verify = "SELECT password FROM Person WHERE PersonID = ?";
                    $stmt_verify = mysqli_prepare($connection, $sql_verify);
                    mysqli_stmt_bind_param($stmt_verify, "i", $userId);
                    mysqli_stmt_execute($stmt_verify);
                    $result_verify = mysqli_stmt_get_result($stmt_verify);
                    
                    if ($row = mysqli_fetch_assoc($result_verify)) {
                        if (password_verify($currentPassword, $row['password'])) {
                            // Update password
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            $sql_update = "UPDATE Person SET password = ? WHERE PersonID = ?";
                            $stmt_update = mysqli_prepare($connection, $sql_update);
                            mysqli_stmt_bind_param($stmt_update, "si", $hashedPassword, $userId);
                            
                            if (mysqli_stmt_execute($stmt_update)) {
                                $success_message = "Password changed successfully!";
                                logActivity($userId, 'PASSWORD_CHANGED', "Password updated");
                            } else {
                                $error_message = "Error changing password. Please try again.";
                            }
                            mysqli_stmt_close($stmt_update);
                        } else {
                            $error_message = "Current password is incorrect.";
                        }
                    } else {
                        $error_message = "User not found.";
                    }
                    mysqli_stmt_close($stmt_verify);
                } else {
                    $error_message = implode('<br>', $errors);
                }
                break;
        }
    }
}

// Fetch current user data
$currentUserId = getUserId();
$sql_user = "SELECT p.*, u.role FROM Person p LEFT JOIN User u ON p.PersonID = u.UserID WHERE p.PersonID = ?";
$stmt_user = mysqli_prepare($connection, $sql_user);
mysqli_stmt_bind_param($stmt_user, "i", $currentUserId);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Admin Settings</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-section {
            background: white;
            margin-bottom: 30px;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .settings-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .submit-btn {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background: #0056b3;
        }
        
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
        
        .readonly-field {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .required {
            color: #dc3545;
        }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        
        .admin-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>
    
    <div class="settings-container">
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Profile Information Section -->
        <div class="settings-section">
            <h3>Administrator Profile</h3>
            <div class="info-box">
                <strong>Account Type:</strong> Administrator <span class="admin-badge">ADMIN</span>
                <br><strong>Access Level:</strong> Full System Access
            </div>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required maxlength="100"
                           value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required maxlength="100"
                           value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" maxlength="20" 
                           pattern="[0-9]+" title="Please enter numbers only"
                           value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>"
                           placeholder="e.g., 09123456789 (numbers only)">
                </div>
                
                <button type="submit" class="submit-btn">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password Section -->
        <div class="settings-section">
            <h3>Change Password</h3>
            <p style="color: #666; margin-bottom: 20px;">
                For security purposes, please enter your current password to change it.
            </p>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password <span class="required">*</span></label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                    <small style="color: #666;">Password must be at least 8 characters long.</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <button type="submit" class="submit-btn">Change Password</button>
            </form>
        </div>
        
        <!-- Account Information Section -->
        <div class="settings-section">
            <h3>Account Information</h3>
            <div class="form-group">
                <label>User ID</label>
                <input type="text" class="readonly-field" readonly value="<?php echo htmlspecialchars($user_data['PersonID'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Account Type</label>
                <input type="text" class="readonly-field" readonly value="<?php echo htmlspecialchars($user_data['person_type'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Administrator Since</label>
                <input type="text" class="readonly-field" readonly value="System Installation">
            </div>
        </div>
    </div>
    
    <script>
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]');
                if (!action) return;
                
                const actionValue = action.value;
                
                if (actionValue === 'change_password') {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match.');
                        return false;
                    }
                    
                    if (newPassword.length < 8) {
                        e.preventDefault();
                        alert('New password must be at least 8 characters long.');
                        return false;
                    }
                }
            });
        });
    </script>
    
    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>
</body>
</html>
