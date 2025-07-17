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
        $vagueItemName = trim($_POST['vagueItemName'] ?? '');
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
        
        if (empty($vagueItemName)) {
            $errors[] = "Vague item name is required.";
        } elseif (strlen($vagueItemName) > 255) {
            $errors[] = "Vague item name cannot exceed 255 characters.";
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
                $sql_found = "INSERT INTO Found (ReportID, location_found, vague_item_name) VALUES (?, ?, ?)";
                $stmt_found = mysqli_prepare($connection, $sql_found);
                mysqli_stmt_bind_param($stmt_found, "iss", $reportId, $location, $vagueItemName);
                
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
        /* Modern, clean form style from login/register, for use inside admin layout - LIGHT THEME */
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 2.5rem 2rem 2rem 2rem;
            background: linear-gradient(135deg, #fff 0%, #f7f7ff 100%);
            border-radius: 12px;
            box-shadow: 0 8px 40px 0 rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04);
            border: 1.5px solid #e3e6f0;
        }
        .form-container h2 {
            color: #232323;
            font-weight: 700;
            letter-spacing: 1.1px;
            margin-bottom: 8px;
            font-size: 1.7rem;
            text-shadow: none;
            font-family: 'Segoe UI', 'Nunito', Arial, sans-serif;
        }
        .form-container p {
            color: #666;
            margin-bottom: 24px;
            font-size: 1.01rem;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #232323;
            font-size: 1.01rem;
            letter-spacing: 0.1px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 13px 15px;
            border: 1.5px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            background: #f7f7ff;
            color: #232323;
            font-family: inherit;
            box-shadow: none;
            outline: none;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 2px #4a90e233;
        }
        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }
        .form-group small {
            color: #888;
            font-size: 12px;
        }
        .char-counter {
            font-size: 11px;
            color: #888;
            margin-top: 2px;
            text-align: right;
            font-family: 'Nunito', Arial, sans-serif;
        }
        .char-counter.warning {
            color: #4a90e2;
        }
        .char-counter.error {
            color: #e74c3c;
        }
        .required {
            color: #e74c3c;
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
            padding: 13px 20px;
            background: #f7f7ff;
            border: 1.5px dashed #ccc;
            border-radius: 6px;
            color: #232323;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            transition: background 0.2s, color 0.2s;
        }
        .file-upload label:hover {
            background: #fffbe6;
            color: #4a90e2;
        }
        .submit-btn {
            background: linear-gradient(90deg, #4a90e2 60%, #232323 100%);
            color: #fff;
            padding: 15px 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.12rem;
            font-weight: 700;
            width: 100%;
            box-shadow: 0 2px 16px #4a90e211;
            letter-spacing: 1.1px;
            margin-top: 10px;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .submit-btn:hover {
            background: linear-gradient(90deg, #232323 60%, #4a90e2 100%);
            color: #fff;
            box-shadow: 0 4px 24px #4a90e233;
        }
        .success-message {
            background: #e9fbe5;
            color: #155724;
            padding: 13px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #4a90e2;
            font-size: 1rem;
            font-family: 'Nunito', Arial, sans-serif;
            box-shadow: 0 2px 8px #4a90e211;
        }
        .error-message {
            background: #fbe9e9;
            color: #e74c3c;
            padding: 13px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
            font-size: 1rem;
            font-family: 'Nunito', Arial, sans-serif;
            box-shadow: 0 2px 8px #e74c3c11;
        }
        @media (max-width: 600px) {
            .form-container {
                padding: 1.2rem 0.5rem 1.5rem 0.5rem;
                max-width: 98vw;
            }
        }
    </style>
</head>
<body>
    <?php ob_start(); ?>
    
    <div class="form-container">
        <h2 style="color: #232323; font-weight: 800; letter-spacing: 1px; margin-bottom: 10px;">Log Found Item</h2>
        <p style="color: #666; margin-bottom: 24px; font-size: 15px;">Use this form to log items that have been brought to the office or found on campus.</p>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message"> <?php echo $success_message; ?> </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"> <?php echo $error_message; ?> </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="itemName">Item Name (Detailed) <span class="required">*</span></label>
                <input type="text" id="itemName" name="itemName" required maxlength="100"
                       value="<?php echo htmlspecialchars($_POST['itemName'] ?? ''); ?>"
                       placeholder="e.g., iPhone 12 Pro Max 256GB Space Gray, Black Leather Wallet with Cards"
                       oninput="updateCharCounter(this, 100)">
                <div class="char-counter" id="itemName_counter">0/100 characters</div>
            </div>
            
            <div class="form-group">
                <label for="vagueItemName">Vague Item Name (Public Display) <span class="required">*</span></label>
                <input type="text" id="vagueItemName" name="vagueItemName" required maxlength="255"
                       value="<?php echo htmlspecialchars($_POST['vagueItemName'] ?? ''); ?>"
                       placeholder="e.g., Phone, Wallet, ID, Keys (Generic name shown to users)"
                       oninput="updateCharCounter(this, 255)">
                <div class="char-counter" id="vagueItemName_counter">0/255 characters</div>
                <small>This is what users will see when browsing found items</small>
            </div>
            
            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" required maxlength="1000"
                          placeholder="Provide detailed description including color, brand, distinguishing features, contents, etc."
                          oninput="updateCharCounter(this, 1000)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                <div class="char-counter" id="description_counter">0/1000 characters</div>
            </div>
            
            <div class="form-group">
                <label for="location">Location Found <span class="required">*</span></label>
                <input type="text" id="location" name="location" required maxlength="255"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                       placeholder="e.g., Room 301, Computer Lab, Hallway near elevator"
                       oninput="updateCharCounter(this, 255)">
                <div class="char-counter" id="location_counter">0/255 characters</div>
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
        function updateCharCounter(element, maxLength) {
            const current = element.value.length;
            const counter = document.getElementById(element.id + '_counter');
            
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

        // Initialize character counters on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCounter(document.getElementById('itemName'), 100);
            updateCharCounter(document.getElementById('vagueItemName'), 255);
            updateCharCounter(document.getElementById('description'), 1000);
            updateCharCounter(document.getElementById('location'), 255);
        });
        
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
