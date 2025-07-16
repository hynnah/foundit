<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$user_id = getUserId();
$content_header = "Contact Administrator";

// Get post ID from URL
$post_id = $_GET['post_id'] ?? '';

if (!$post_id || !is_numeric($post_id)) {
    header("Location: found_items.php");
    exit();
}

// Get post details
$sql = "SELECT r.*, fp.*, f.location_found, p.name as submitter_name
        FROM FeedPost fp
        JOIN Report r ON fp.ReportID = r.ReportID
        JOIN Found f ON r.ReportID = f.ReportID
        LEFT JOIN User u ON r.UserID_submitter = u.UserID
        LEFT JOIN Person p ON u.UserID = p.PersonID
        WHERE fp.PostID = ? AND fp.post_status = 'Active'";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: found_items.php");
    exit();
}

$post = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $ownership_description = trim($_POST['ownership_description'] ?? '');
        $item_appearance = trim($_POST['item_appearance'] ?? '');
        $location_lost = trim($_POST['location_lost'] ?? '');
        $date_lost = $_POST['date_lost'] ?? null;
        $unique_marks = trim($_POST['unique_marks'] ?? '');
        
        // Input validation
        $errors = [];
        
        if (empty($ownership_description)) {
            $errors[] = "Please describe your ownership of this item.";
        }
        
        if (strlen($ownership_description) > 1000) {
            $errors[] = "Ownership description cannot exceed 1000 characters.";
        }
        
        if (!empty($item_appearance) && strlen($item_appearance) > 1000) {
            $errors[] = "Item appearance description cannot exceed 1000 characters.";
        }
        
        if (!empty($location_lost) && strlen($location_lost) > 255) {
            $errors[] = "Location lost cannot exceed 255 characters.";
        }
        
        if (!empty($unique_marks) && strlen($unique_marks) > 1000) {
            $errors[] = "Unique marks description cannot exceed 1000 characters.";
        }
        
        if (!empty($date_lost) && !strtotime($date_lost)) {
            $errors[] = "Invalid date format.";
        }
        
        // Handle file upload for evidence
        $evidence_file_path = null;
        $evidence_file_name = null;
        
        if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if ($_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Error uploading evidence file.";
            } elseif (!in_array($_FILES['evidence_file']['type'], $allowedTypes)) {
                $errors[] = "Invalid file type. Only JPEG, PNG, GIF, WebP, and PDF are allowed.";
            } elseif ($_FILES['evidence_file']['size'] > $maxSize) {
                $errors[] = "File size cannot exceed 10MB.";
            } else {
                $uploadDir = 'uploads/evidence/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['evidence_file']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('evidence_', true) . '.' . $extension;
                $destination = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['evidence_file']['tmp_name'], $destination)) {
                    $evidence_file_path = $destination;
                    $evidence_file_name = $_FILES['evidence_file']['name'];
                } else {
                    $errors[] = "Error uploading evidence file.";
                }
            }
        }
        
        if (!empty($errors)) {
            $error_message = implode('<br>', $errors);
        } else {
            // Insert contact request with new fields
            $sql_insert = "INSERT INTO ContactRequest (UserID_claimant, PostID, ownership_description, item_appearance, location_lost, date_lost, evidence_file_path, evidence_file_name, unique_marks, submission_date, review_status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";
            
            $stmt_insert = mysqli_prepare($connection, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "iisssssss", $user_id, $post_id, $ownership_description, $item_appearance, $location_lost, $date_lost, $evidence_file_path, $evidence_file_name, $unique_marks);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $success_message = "Your contact request has been submitted successfully! The administrator will review your request.";
            } else {
                $error_message = "Error submitting contact request. Please try again.";
            }
        }
    }
}

// Start output buffering
ob_start();
?>
<style>
    .contact-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .item-details {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }
    
    .item-details h2 {
        color: #cb7f00;
        margin-bottom: 20px;
    }
    
    .item-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-weight: bold;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .info-value {
        color: #333;
    }
    
    .contact-form {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
    }
    
    .form-group input[type="date"] {
        padding: 10px 12px;
    }
    
    .form-group input[type="file"] {
        padding: 8px;
        border: 2px dashed #e0e0e0;
        background: #f9f9f9;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #cb7f00;
    }
    
    .form-group small {
        display: block;
        margin-top: 5px;
        color: #666;
        font-size: 12px;
    }
    
    .char-counter {
        float: right;
        color: #999;
        font-size: 11px;
        margin-top: 2px;
    }
    
    .char-counter.warning {
        color: #ff6b35;
    }
    
    .char-counter.error {
        color: #dc3545;
    }
    
    .form-group input:invalid,
    .form-group textarea:invalid {
        border-color: #dc3545;
    }
    
    .form-group input:valid,
    .form-group textarea:valid {
        border-color: #28a745;
    }
    
    .submit-btn {
        background: #cb7f00;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .submit-btn:hover {
        background: #a66600;
    }
    
    .back-btn {
        background: #6c757d;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-right: 10px;
        transition: background 0.3s ease;
    }
    
    .back-btn:hover {
        background: #5a6268;
        text-decoration: none;
        color: white;
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
    
    @media (max-width: 768px) {
        .item-info {
            grid-template-columns: 1fr;
        }
        
        .contact-container {
            padding: 10px;
        }
    }
</style>

<div class="contact-container">
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

    <!-- Item Details -->
    <div class="item-details">
        <h2>Item Details</h2>
        <div class="item-info">
            <div class="info-item">
                <span class="info-label">Item Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($post['item_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Location Found:</span>
                <span class="info-value"><?php echo htmlspecialchars($post['location_found']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date Found:</span>
                <span class="info-value"><?php echo formatDate($post['incident_date'], 'M d, Y'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Posted by:</span>
                <span class="info-value"><?php echo htmlspecialchars($post['submitter_name'] ?? 'System'); ?></span>
            </div>
        </div>
        <div class="info-item">
            <span class="info-label">Description:</span>
            <span class="info-value"><?php echo htmlspecialchars($post['description']); ?></span>
        </div>
    </div>

    <!-- Contact Form -->
    <div class="contact-form">
        <h2>Contact Administrator</h2>
        <p>Please provide detailed information about your ownership of this item. This information will be reviewed by an administrator.</p>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="ownership_description">Ownership Description *</label>
                <textarea name="ownership_description" id="ownership_description" required maxlength="1000"
                          placeholder="Please describe how this item belongs to you, including circumstances of loss, purchase details, etc."
                          oninput="updateCharCounter(this, 1000)"></textarea>
                <div class="char-counter" id="ownership_description_counter">0/1000 characters</div>
            </div>
            
            <div class="form-group">
                <label for="item_appearance">Item Appearance</label>
                <textarea name="item_appearance" id="item_appearance" maxlength="1000"
                          placeholder="Describe the physical appearance of the item (color, size, condition, etc.)"
                          oninput="updateCharCounter(this, 1000)"></textarea>
                <div class="char-counter" id="item_appearance_counter">0/1000 characters</div>
            </div>
            
            <div class="form-group">
                <label for="location_lost">Location Lost</label>
                <input type="text" name="location_lost" id="location_lost" maxlength="255"
                       placeholder="Where did you lose this item?"
                       oninput="updateCharCounter(this, 255)">
                <div class="char-counter" id="location_lost_counter">0/255 characters</div>
            </div>
            
            <div class="form-group">
                <label for="date_lost">Date Lost</label>
                <input type="date" name="date_lost" id="date_lost" max="<?php echo date('Y-m-d'); ?>"
                       placeholder="When did you lose this item?">
                <small>Please select the date you lost the item</small>
            </div>
            
            <div class="form-group">
                <label for="unique_marks">Unique Marks/Features</label>
                <textarea name="unique_marks" id="unique_marks" maxlength="1000"
                          placeholder="Describe any unique marks, scratches, engravings, or identifying features"
                          oninput="updateCharCounter(this, 1000)"></textarea>
                <div class="char-counter" id="unique_marks_counter">0/1000 characters</div>
            </div>
            
            <div class="form-group">
                <label for="evidence_file">Evidence File (Optional)</label>
                <input type="file" name="evidence_file" id="evidence_file" accept="image/*,.pdf">
                <small>Upload proof of ownership (receipt, photos, etc.). Max 10MB. Supports: JPEG, PNG, GIF, WebP, PDF</small>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <a href="found_items.php" class="back-btn">← Back to Items</a>
            <button type="submit" class="submit-btn">Submit Request</button>
        </form>
    </div>
</div>

<script>
function updateCharCounter(textarea, maxLength) {
    const current = textarea.value.length;
    const counter = document.getElementById(textarea.id + '_counter');
    
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
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(function(textarea) {
        const maxLength = parseInt(textarea.getAttribute('maxlength'));
        updateCharCounter(textarea, maxLength);
    });
    
    const inputs = document.querySelectorAll('input[type="text"][maxlength]');
    inputs.forEach(function(input) {
        const maxLength = parseInt(input.getAttribute('maxlength'));
        updateCharCounter(input, maxLength);
    });
});

// File upload preview
document.getElementById('evidence_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
        const fileName = file.name;
        const fileType = file.type;
        
        // Show file info
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        fileInfo.innerHTML = `
            <small>
                <strong>Selected:</strong> ${fileName} (${fileSize} MB)
                <br><strong>Type:</strong> ${fileType}
            </small>
        `;
        
        // Remove existing file info
        const existingInfo = this.parentNode.querySelector('.file-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        this.parentNode.appendChild(fileInfo);
        
        // Validate file size
        if (file.size > 10 * 1024 * 1024) { // 10MB
            alert('File size must be less than 10MB');
            this.value = '';
            fileInfo.remove();
        }
    }
});
</script>

<?php
$page_content = ob_get_clean();
include_once 'includes/general_layout.php';
?>
