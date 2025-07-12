<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Log Found Item";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        // Get form data
        $itemName = trim($_POST['itemName'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $incidentDate = $_POST['incidentDate'] ?? '';
        $adminId = getUserId();
        
        // Input validation
        $errors = [];
        
        if (empty($itemName)) {
            $errors[] = "Item name is required.";
        } elseif (strlen($itemName) > 100) {
            $errors[] = "Item name cannot exceed 100 characters.";
        }
        
        if (empty($description)) {
            $errors[] = "Description is required.";
        } elseif (strlen($description) > 1000) {
            $errors[] = "Description cannot exceed 1000 characters.";
        }
        
        if (empty($location)) {
            $errors[] = "Location found is required.";
        } elseif (strlen($location) > 255) {
            $errors[] = "Location cannot exceed 255 characters.";
        }
        
        if (empty($incidentDate)) {
            $errors[] = "Date found is required.";
        } elseif (!strtotime($incidentDate)) {
            $errors[] = "Invalid date format.";
        } elseif (strtotime($incidentDate) > time()) {
            $errors[] = "Date found cannot be in the future.";
        }
        
        // Validate image if uploaded
        if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if ($_FILES['itemImage']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading image.";
            } elseif (!in_array($_FILES['itemImage']['type'], $allowedTypes)) {
                $errors[] = "Invalid image type. Only JPEG, PNG, GIF, and WebP are allowed.";
            } elseif ($_FILES['itemImage']['size'] > $maxSize) {
                $errors[] = "Image size cannot exceed 5MB.";
            }
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        } else {
            // Handle image upload
            $imagePath = null;
            if (isset($_FILES['itemImage']) && $_FILES['itemImage']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('item_', true) . '.' . $extension;
                $destination = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                    $imagePath = $filename; // Store only filename, not full path
                }
            }
            
            // Start transaction
            mysqli_autocommit($connection, FALSE);
            
            try {
                // Check if admin has a User record, if not create one
                $sql_check_user = "SELECT UserID FROM User WHERE UserID = ?";
                $stmt_check = mysqli_prepare($connection, $sql_check_user);
                mysqli_stmt_bind_param($stmt_check, "i", $adminId);
                mysqli_stmt_execute($stmt_check);
                $result_check = mysqli_stmt_get_result($stmt_check);
                
                if (mysqli_num_rows($result_check) === 0) {
                    // Admin doesn't have a User record, create one
                    $sql_create_user = "INSERT INTO User (UserID, role) VALUES (?, 'Staff')";
                    $stmt_create = mysqli_prepare($connection, $sql_create_user);
                    mysqli_stmt_bind_param($stmt_create, "i", $adminId);
                    
                    if (!mysqli_stmt_execute($stmt_create)) {
                        throw new Exception("Error creating User record for admin: " . mysqli_error($connection));
                    }
                    mysqli_stmt_close($stmt_create);
                }
                mysqli_stmt_close($stmt_check);
                
                // Insert into Report table (admin as submitter)
                $sql_report = "INSERT INTO Report (UserID_submitter, AdminID_reviewer, report_type, item_name, description, incident_date, submission_date, ApprovalStatusID, image_path) 
                              VALUES (?, ?, 'Found', ?, ?, ?, NOW(), 2, ?)";
                
                $stmt_report = mysqli_prepare($connection, $sql_report);
                mysqli_stmt_bind_param($stmt_report, "iissss", $adminId, $adminId, $itemName, $description, $incidentDate, $imagePath);
                
                if (!mysqli_stmt_execute($stmt_report)) {
                    throw new Exception("Error inserting into Report table: " . mysqli_error($connection));
                }
                
                // Get the inserted report ID
                $reportId = mysqli_insert_id($connection);
                mysqli_stmt_close($stmt_report);
                
                // Insert into Found table
                $sql_found = "INSERT INTO Found (ReportID, location_found) VALUES (?, ?)";
                $stmt_found = mysqli_prepare($connection, $sql_found);
                mysqli_stmt_bind_param($stmt_found, "is", $reportId, $location);
                
                if (!mysqli_stmt_execute($stmt_found)) {
                    throw new Exception("Error inserting into Found table: " . mysqli_error($connection));
                }
                mysqli_stmt_close($stmt_found);
                
                // Create FeedPost entry (automatically approved since admin logged it)
                $sql_feed = "INSERT INTO FeedPost (ReportID, post_date, post_status) VALUES (?, NOW(), 'Active')";
                $stmt_feed = mysqli_prepare($connection, $sql_feed);
                mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
                
                if (!mysqli_stmt_execute($stmt_feed)) {
                    throw new Exception("Error inserting into FeedPost table: " . mysqli_error($connection));
                }
                mysqli_stmt_close($stmt_feed);
                
                // Commit transaction
                mysqli_commit($connection);
                $success_message = "Found item has been logged successfully and is now visible to users!";
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($connection);
                $error_message = "Database error: " . $e->getMessage();
            }
            
            // Restore autocommit
            mysqli_autocommit($connection, TRUE);
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Log Found Item</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .submit-btn {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
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
        
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload label {
            display: inline-block;
            padding: 12px 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
        }
        
        .file-upload label:hover {
            background: #e9ecef;
        }
        
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>
    
    <div class="form-container">
        <h2>Log Found Item</h2>
        <p style="color: #666; margin-bottom: 20px;">
            Use this form to log items that have been brought to the office or found on campus.
        </p>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="itemName">Item Name <span class="required">*</span></label>
                <input type="text" id="itemName" name="itemName" required maxlength="100"
                       value="<?php echo htmlspecialchars($_POST['itemName'] ?? ''); ?>"
                       placeholder="e.g., iPhone 12, Black Wallet, USC ID">
            </div>
            
            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" required maxlength="1000"
                          placeholder="Provide detailed description including color, brand, distinguishing features, contents, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="location">Location Found <span class="required">*</span></label>
                <input type="text" id="location" name="location" required maxlength="255"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                       placeholder="e.g., Room 301, Computer Lab, Hallway near elevator">
            </div>
            
            <div class="form-group">
                <label for="incidentDate">Date Found <span class="required">*</span></label>
                <input type="date" id="incidentDate" name="incidentDate" required
                       value="<?php echo htmlspecialchars($_POST['incidentDate'] ?? date('Y-m-d')); ?>"
                       max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label for="itemImage">Upload Image (Optional)</label>
                <div class="file-upload">
                    <input type="file" id="itemImage" name="itemImage" accept="image/*">
                    <label for="itemImage">Choose Image File (JPEG, PNG, GIF, WebP - Max 5MB)</label>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">Log Found Item</button>
        </form>
    </div>
    
    <script>
        // File upload feedback
        document.getElementById('itemImage').addEventListener('change', function(e) {
            var label = document.querySelector('label[for="itemImage"]');
            var fileName = e.target.files[0] ? e.target.files[0].name : 'Choose Image File (JPEG, PNG, GIF, WebP - Max 5MB)';
            label.textContent = fileName;
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            var itemName = document.getElementById('itemName').value.trim();
            var description = document.getElementById('description').value.trim();
            var location = document.getElementById('location').value.trim();
            var incidentDate = document.getElementById('incidentDate').value;
            
            if (!itemName || !description || !location || !incidentDate) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Check if date is not in the future
            var selectedDate = new Date(incidentDate);
            var today = new Date();
            if (selectedDate > today) {
                e.preventDefault();
                alert('Date found cannot be in the future.');
                return false;
            }
        });
    </script>
    
    <?php
        $page_content = ob_get_clean();
        include_once "../includes/admin_layout.php";
    ?>
</body>
</html>
