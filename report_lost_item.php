<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$content_header = "Report Lost Item";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $itemName = trim($_POST['itemName'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $userId = $_SESSION['userId'];
    
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
        $errors[] = "Location is required.";
    } elseif (strlen($location) > 255) {
        $errors[] = "Location cannot exceed 255 characters.";
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
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('item_', true) . '.' . $extension;
            $destination = $uploadDir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                $imagePath = $destination;
            }
        }
        
        // Start transaction
        mysqli_autocommit($connection, FALSE);
        
        try {
            // Insert into report table first
            $sql_report = "INSERT INTO Report (UserID_submitter, report_type, item_name, description, incident_date, submission_date, ApprovalStatusID) 
                          VALUES (?, 'Lost', ?, ?, CURDATE(), NOW(), 1)";
            
            $stmt_report = mysqli_prepare($connection, $sql_report);
            mysqli_stmt_bind_param($stmt_report, "iss", $userId, $itemName, $description);
            
            if (!mysqli_stmt_execute($stmt_report)) {
                throw new Exception("Error inserting into report table: " . mysqli_error($connection));
            }
            
            // Get the inserted report ID
            $reportId = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt_report);
            
            // Insert into lost table
            $sql_lost = "INSERT INTO Lost (ReportID, location_last_seen) VALUES (?, ?)";
            $stmt_lost = mysqli_prepare($connection, $sql_lost);
            mysqli_stmt_bind_param($stmt_lost, "is", $reportId, $location);
            
            if (!mysqli_stmt_execute($stmt_lost)) {
                throw new Exception("Error inserting into lost table: " . mysqli_error($connection));
            }
            mysqli_stmt_close($stmt_lost);
            
            if ($imagePath) {
                $sql_image = "UPDATE Report SET image_path = ? WHERE ReportID = ?";
                $stmt_image = mysqli_prepare($connection, $sql_image);
                if ($stmt_image) {
                    mysqli_stmt_bind_param($stmt_image, "si", $imagePath, $reportId);
                    mysqli_stmt_execute($stmt_image);
                    mysqli_stmt_close($stmt_image);
                }
            }
            
            // Commit transaction
            mysqli_commit($connection);
            $success_message = "Your lost item report has been submitted successfully!";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($connection);
            $error_message = "Database error: " . $e->getMessage();
        }
        
        // Restore autocommit
        mysqli_autocommit($connection, TRUE);
    }
}

// Start output buffering to capture the page content
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
    .report-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .report-form {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .form-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .form-header h1 {
        color: #cb7f00;
        font-size: 2rem;
        margin-bottom: 10px;
    }
    
    .form-header p {
        color: #666;
        font-size: 1.1rem;
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        color: #333;
        font-weight: 600;
        font-size: 16px;
    }
    
    .form-group label i {
        margin-right: 8px;
        color: #cb7f00;
        width: 20px;
        text-align: center;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e1e1e1;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: #fff;
        font-family: inherit;
        box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #cb7f00;
        box-shadow: 0 0 0 3px rgba(203, 127, 0, 0.1);
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }
    
    .file-upload-wrapper {
        position: relative;
        border: 2px dashed #e1e1e1;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .file-upload-wrapper:hover {
        border-color: #cb7f00;
        background: rgba(203, 127, 0, 0.05);
    }
    
    .file-upload-wrapper input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    
    .file-upload-text {
        color: #666;
        font-size: 14px;
    }
    
    .file-upload-icon {
        font-size: 2rem;
        color: #cb7f00;
        margin-bottom: 10px;
    }
    
    .submit-btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(45deg, #cb7f00, #e89611);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 20px;
    }
    
    .submit-btn:hover {
        background: linear-gradient(45deg, #bd7800, #d48806);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(203, 127, 0, 0.3);
    }
    
    .submit-btn:active {
        transform: translateY(0);
    }
    
    @media (max-width: 768px) {
        .report-container {
            padding: 10px;
        }
        
        .report-form {
            padding: 20px;
        }
        
        .form-header h1 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="report-container">
    <div class="report-form">
        <div class="form-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Report Lost Item</h1>
            <p>Help us help you find your lost item by providing detailed information</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="itemName">
                    <i class="fas fa-tag"></i> Item Name
                </label>
                <input type="text" id="itemName" name="itemName" 
                       placeholder="What did you lose? (e.g., iPhone, Wallet, Keys)"
                       value="<?php echo htmlspecialchars($_POST['itemName'] ?? ''); ?>" 
                       required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="description">
                    <i class="fas fa-align-left"></i> Description
                </label>
                <textarea id="description" name="description" 
                          placeholder="Provide a detailed description of your lost item (color, size, brand, distinguishing features, etc.)"
                          required maxlength="1000"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="location">
                    <i class="fas fa-map-marker-alt"></i> Last Known Location
                </label>
                <input type="text" id="location" name="location" 
                       placeholder="Where did you last see/have your item?"
                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                       required maxlength="255">
            </div>
            
            <div class="form-group">
                <label>
                    <i class="fas fa-camera"></i> Item Photo (Optional)
                </label>
                <div class="file-upload-wrapper">
                    <input type="file" id="itemImage" name="itemImage" accept="image/*">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        Click to upload or drag and drop<br>
                        <small>JPG, PNG, GIF, WebP up to 5MB</small>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Submit Report
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('itemImage').addEventListener('change', function(e) {
    const wrapper = e.target.closest('.file-upload-wrapper');
    const textDiv = wrapper.querySelector('.file-upload-text');
    
    if (e.target.files.length > 0) {
        textDiv.innerHTML = `Selected: ${e.target.files[0].name}`;
        wrapper.style.borderColor = '#cb7f00';
        wrapper.style.background = 'rgba(203, 127, 0, 0.05)';
    } else {
        textDiv.innerHTML = 'Click to upload or drag and drop<br><small>JPG, PNG, GIF, WebP up to 5MB</small>';
        wrapper.style.borderColor = '#e1e1e1';
        wrapper.style.background = 'transparent';
    }
});
</script>

<?php
// Capture the page content and include the layout
$page_content = ob_get_clean();
include_once "includes/general_layout.php";
?>