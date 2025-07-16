<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $ownership_description = trim($_POST['ownership_description'] ?? '');
        $item_appearance = trim($_POST['item_appearance'] ?? '');
        $location_lost = trim($_POST['location_lost'] ?? '');
        $date_lost = $_POST['date_lost'] ?? null;
        $unique_marks = trim($_POST['unique_marks'] ?? '');

        // please check this part
        if (empty($ownership_description)) {
            $error_message = "Please describe your ownership of this item.";
        } else {
            // Handle file upload if provided
            $evidence_file_name = null;
            $evidence_file_path = null;
            if (isset($_FILES['evidence_file_name']) && $_FILES['evidence_file_name']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_tmp_path = $_FILES['evidence_file_name']['tmp_name'];
                $file_name = basename($_FILES['evidence_file_name']['name']);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt'];

                if (in_array($file_ext, $allowed_exts)) {
                    $new_file_name = 'item_' . uniqid() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;
                    if (move_uploaded_file($file_tmp_path, $dest_path)) {
                        $evidence_file_name = $new_file_name;
                        $evidence_file_path = 'uploads/' . $new_file_name;
                    } else {
                        $error_message = "Error uploading the file. Please try again.";
                    }
                } else {
                    $error_message = "Invalid file type. Allowed types: " . implode(', ', $allowed_exts);
                }
            }

            if (!isset($error_message)) {
                // Insert contact request
                $sql_insert = "INSERT INTO ContactRequest (UserID_claimant, PostID, ownership_description, item_appearance, location_lost, date_lost, evidence_file_name, evidence_file_path, unique_marks, submission_date, review_status)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";

                $stmt_insert = mysqli_prepare($connection, $sql_insert);
                mysqli_stmt_bind_param($stmt_insert, "iisssssss", $user_id, $post_id, $ownership_description, $item_appearance, $location_lost, $date_lost, $evidence_file_name, $evidence_file_path, $unique_marks);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $success_message = "Your contact request has been submitted successfully! The administrator will review your request.";
                } else {
                    $error_message = "Error submitting contact request. Please try again.";
                }
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
    
    @media (max-width: 768px) {
        .item-info {
            grid-template-columns: 1fr;
        }
        
        .contact-container {
            padding: 10px;
        }
    }
    
    .contact-form {
        background: white;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
        margin-bottom: 40px;
    }
    
    .form-group {
        margin-bottom: 30px;
    }
    
    .form-group label {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        color: #333;
        font-weight: 600;
        font-size: 16px;
    }
    
    .form-group label i {
        margin-right: 10px;
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
        background: linear-gradient(45deg, #cb7f00, #e89611);;
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
    
    .submit-btn:hover {
        background: linear-gradient(45deg, #bd7800, #d48806);
        text-decoration: none;
        color: white;
    }
    
    .submit-btn:active {
        transform: translateY(0);
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
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .alert-success {
        background: #d4edda;
        color: #51cf66;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    @media (max-width: 768px) {
        .item-info {
            grid-template-columns: 1fr;
        }
        
        .contact-container {
            padding: 10px;
        }
        
        .contact-form {
            padding: 25px;
            margin-top: 10px;
            margin-bottom: 20px;
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
        <p>Please describe how this item belongs to you. This information will be reviewed by an administrator.</p><br>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="ownership_description">Ownership Description *</label>
                <textarea name="ownership_description" id="ownership_description" required
                          placeholder="Why do you think the item is yours?"></textarea>
            </div>

            <div class="form-group">
                <label for="item_appearance">Item Appearance</label>
                <input type="text" name="item_appearance" id="item_appearance" placeholder="What does the item look like?" >
            </div>

            <div class="form-group">
                <label for="location_lost">Where was it lost?</label>
                <input type="text" name="location_lost" id="location_lost" placeholder="Where did you lose it?" required>
            </div>

            <div class="form-group">
                <label for="date_lost">When was it lost?</label>
                <input type="date" name="date_lost" id="date_lost">
            </div>

            <div class="form-group">
                <label for="evidence_file_name">Upload a photo or document (if available)</label>
                <div class="file-upload-wrapper">
                    <input type="file" name="evidence_file_name" id="evidence_file_name" accept="image/*,.pdf,.doc,.docx,.txt">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        Click to upload or drag and drop<br>
                        <small>JPG, PNG, GIF, WebP up to 5MB</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="unique_marks">Is there any mark or detail that makes it unique?</label>
                <input type="text" name="unique_marks" id="unique_marks" placeholder="Unique marks or details" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
<a href="view_item_details.php?post_id=<?php echo $post_id; ?>" class="back-btn">← Back</a>
            <button type="submit" class="submit-btn">Submit Request</button>
        </form>
    </div>
</div>

<script>
document.getElementById('evidence_file_name').addEventListener('change', function(e) {
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
$page_content = ob_get_clean();
include_once 'includes/general_layout.php';
?>
