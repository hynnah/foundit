<?php
require_once 'auth_check.php';
require_once 'functions.php';
require_once 'dbh.inc.php';

$user_name = getUserName();
$user_id = getUserId();
$content_header = "Contact Owner";

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
        
        if (empty($ownership_description)) {
            $error_message = "Please describe your ownership of this item.";
        } else {
            // Insert contact request
            $sql_insert = "INSERT INTO ContactRequest (UserID_claimant, PostID, ownership_description, submission_date, review_status)
                           VALUES (?, ?, ?, NOW(), 'Pending')";
            
            $stmt_insert = mysqli_prepare($connection, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "iis", $user_id, $post_id, $ownership_description);
            
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
    
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        resize: vertical;
        min-height: 100px;
        font-family: inherit;
    }
    
    .form-group textarea:focus {
        outline: none;
        border-color: #cb7f00;
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
        <h2>Request Contact Information</h2>
        <p>Please describe how this item belongs to you. This information will be reviewed by an administrator.</p>
        
        <form method="post">
            <div class="form-group">
                <label for="ownership_description">Ownership Description *</label>
                <textarea name="ownership_description" id="ownership_description" required
                          placeholder="Please describe the item in detail, including any unique features, where/when you lost it, etc."></textarea>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <a href="found_items.php" class="back-btn">← Back to Items</a>
            <button type="submit" class="submit-btn">Submit Request</button>
        </form>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include_once 'includes/general_layout.php';
?>
