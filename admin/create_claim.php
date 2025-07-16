<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Claim Management";

// Get contact request ID
$contactId = $_GET['contact_id'] ?? '';

if (!$contactId || !is_numeric($contactId)) {
    header("Location: inbox.php");
    exit;
}

// Get contact request and claim details
$sql = "SELECT 
            cr.*,
            cr.UserID_claimant,
            cl.ClaimID,
            cl.claim_status,
            cl.interrogation_notes,
            cl.passed_interrogationYN,
            p_claimant.name as claimant_name,
            p_claimant.email as claimant_email,
            p_claimant.phone_number as claimant_phone,
            u_claimant.role as claimant_role,
            r.item_name,
            r.description as item_description,
            r.image_path,
            r.ReportID,
            fp.PostID
        FROM ContactRequest cr
        LEFT JOIN Claim cl ON cr.ContactID = cl.ContactID
        JOIN User u_claimant ON cr.UserID_claimant = u_claimant.UserID
        JOIN Person p_claimant ON u_claimant.UserID = p_claimant.PersonID
        JOIN FeedPost fp ON cr.PostID = fp.PostID
        JOIN Report r ON fp.ReportID = r.ReportID
        WHERE cr.ContactID = ? AND cr.review_status = 'Approved'";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $contactId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    header("Location: inbox.php");
    exit;
}

$contact = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid CSRF token. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $interrogation_notes = trim($_POST['interrogation_notes'] ?? '');
        $passed_interrogationYN = $_POST['passed_interrogationYN'] ?? '';
        $adminId = getUserId();
        
        if ($action === 'create_claim' || $action === 'update_claim') {
            $claim_status = ($_POST['claim_status'] ?? '') === 'complete' ? 'Completed' : 'Processing';
            $passed = ($passed_interrogationYN === 'yes') ? 1 : (($passed_interrogationYN === 'no') ? 0 : null);
            
            // Begin transaction
            mysqli_begin_transaction($connection);
            
            try {
                if ($action === 'create_claim' && !$contact['ClaimID']) {
                    // Create a new claim
                    $sql_create = "INSERT INTO Claim (ContactID, UserID_claimant, AdminID_processor, claim_status, interrogation_notes, passed_interrogationYN, claim_date" . 
                                  ($claim_status === 'Completed' ? ', resolution_date' : '') . ") 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW()" . 
                                   ($claim_status === 'Completed' ? ', NOW()' : '') . ")";
                    
                    $stmt_create = mysqli_prepare($connection, $sql_create);
                    mysqli_stmt_bind_param($stmt_create, "iiissi", $contactId, $contact['UserID_claimant'], $adminId, $claim_status, $interrogation_notes, $passed);
                    
                    if (!mysqli_stmt_execute($stmt_create)) {
                        throw new Exception("Error creating claim: " . mysqli_error($connection));
                    }
                    
                    $success_message = "Claim created successfully!";
                } else {
                    // Update existing claim
                    $sql_update = "UPDATE Claim SET 
                                  claim_status = ?,
                                  interrogation_notes = ?,
                                  passed_interrogationYN = ?" . 
                                  ($claim_status === 'Completed' ? ', resolution_date = NOW()' : ', resolution_date = NULL') . "
                                  WHERE ContactID = ?";
                    
                    $stmt_update = mysqli_prepare($connection, $sql_update);
                    mysqli_stmt_bind_param($stmt_update, "ssii", $claim_status, $interrogation_notes, $passed, $contactId);
                    
                    if (!mysqli_stmt_execute($stmt_update)) {
                        throw new Exception("Error updating claim: " . mysqli_error($connection));
                    }
                    
                    $success_message = "Claim updated successfully!";
                }
                
                // Update the Report table with claim status
                $sql_report = "UPDATE Report SET 
                              claimedYN = " . ($claim_status === 'Completed' ? '1' : '0') . "
                              WHERE ReportID = ?";
                
                $stmt_report = mysqli_prepare($connection, $sql_report);
                mysqli_stmt_bind_param($stmt_report, "i", $contact['ReportID']);
                
                if (!mysqli_stmt_execute($stmt_report)) {
                    throw new Exception("Error updating report: " . mysqli_error($connection));
                }
                
                // If completed, update the post status
                if ($claim_status === 'Completed') {
                    $sql_post = "UPDATE FeedPost SET post_status = 'Claimed' WHERE PostID = ?";
                    $stmt_post = mysqli_prepare($connection, $sql_post);
                    mysqli_stmt_bind_param($stmt_post, "i", $contact['PostID']);
                    
                    if (!mysqli_stmt_execute($stmt_post)) {
                        throw new Exception("Error updating post status");
                    }
                }
                
                // Commit transaction
                mysqli_commit($connection);
                
                // Refresh the data
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $contact = mysqli_fetch_assoc($result);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($connection);
                $error_message = "Error " . ($action === 'create_claim' ? 'creating' : 'updating') . " claim: " . $e->getMessage();
            }
        }
    }
}

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Create Claim</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .claim-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .claim-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .claim-header h1 {
            margin: 0;
            color: #2c3e50;
        }
        
        .claim-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .claim-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e1e8ed;
        }
        
        .claim-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.3em;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #34495e;
            min-width: 120px;
            margin-right: 15px;
        }
        
        .info-value {
            color: #2c3e50;
            flex: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-processing { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-rejected { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-pending { background: #cce5ff; color: #004085; border: 1px solid #b3d9ff; }
        
        .claim-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid #e1e8ed;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.1em;
            border-left: 4px solid #3498db;
            padding-left: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .radio-group {
            display: flex;
            gap: 25px;
            margin-top: 8px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .radio-option:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 8px;
            width: auto;
        }
        
        .radio-option input[type="radio"]:checked + label {
            color: #3498db;
            font-weight: bold;
        }
        
        .radio-option.selected {
            background: #e3f2fd;
            border-color: #3498db;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .item-image {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .evidence-section {
            margin-top: 20px;
        }
        
        .evidence-file {
            display: inline-block;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            margin-top: 10px;
        }
        
        .evidence-file a {
            color: #3498db;
            text-decoration: none;
        }
        
        .evidence-file a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .claim-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .claim-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="claim-container">
        <div class="claim-header">
            <h1><?php echo $contact['ClaimID'] ? 'Process Claim' : 'Create Claim'; ?></h1>
            <a href="inbox.php" class="btn btn-secondary">← Back to Inbox</a>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="claim-grid">
            <!-- Contact Request Details -->
            <div class="claim-card">
                <h2>Contact Request Details</h2>
                <div class="info-item">
                    <span class="info-label">Item:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['item_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Description:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['item_description']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Request Date:</span>
                    <span class="info-value"><?php echo formatDate($contact['submission_date']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Review Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo strtolower($contact['review_status']); ?>">
                            <?php echo $contact['review_status']; ?>
                        </span>
                    </span>
                </div>
                
                <?php if ($contact['image_path']): ?>
                <div class="info-item">
                    <span class="info-label">Item Image:</span>
                    <span class="info-value">
                        <?php
                        // Handle different image path formats
                        $imagePath = $contact['image_path'];
                        if (strpos($imagePath, 'uploads/') === 0 || strpos($imagePath, 'admin/uploads/') === 0) {
                            // Full path stored
                            $imageUrl = '../' . $imagePath;
                        } else {
                            // Just filename stored
                            $imageUrl = '../uploads/' . $imagePath;
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="Item Image" class="item-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display:none; padding:10px; background:#f8f9fa; border-radius:4px; text-align:center; color:#666; font-size:12px;">
                            <p>Image not found</p>
                        </div>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Claimant Information -->
            <div class="claim-card">
                <h2>Claimant Information</h2>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['claimant_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['claimant_email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['claimant_phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['claimant_role']); ?></span>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Ownership Description:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['ownership_description']); ?></span>
                </div>
                
                <?php if ($contact['item_appearance']): ?>
                <div class="info-item">
                    <span class="info-label">Item Appearance:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['item_appearance']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($contact['location_lost']): ?>
                <div class="info-item">
                    <span class="info-label">Location Lost:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['location_lost']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($contact['date_lost']): ?>
                <div class="info-item">
                    <span class="info-label">Date Lost:</span>
                    <span class="info-value"><?php echo formatDate($contact['date_lost']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($contact['unique_marks']): ?>
                <div class="info-item">
                    <span class="info-label">Unique Marks:</span>
                    <span class="info-value"><?php echo htmlspecialchars($contact['unique_marks']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($contact['evidence_file_path']): ?>
                <div class="evidence-section">
                    <span class="info-label">Evidence File:</span>
                    <div class="evidence-file">
                        <a href="../<?php echo htmlspecialchars($contact['evidence_file_path']); ?>" 
                           target="_blank">
                            <?php echo htmlspecialchars($contact['evidence_file_name'] ?? 'View Evidence'); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Claim Processing Form -->
        <div class="claim-form">
            <h2>
                <?php if ($contact['ClaimID']): ?>
                    Claim Processing - ID: <?php echo $contact['ClaimID']; ?>
                <?php else: ?>
                    Create New Claim
                <?php endif; ?>
            </h2>
            
            <?php if ($contact['ClaimID']): ?>
                <div class="info-item">
                    <span class="info-label">Current Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo strtolower($contact['claim_status']); ?>">
                            <?php echo $contact['claim_status']; ?>
                        </span>
                    </span>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="claimForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $contact['ClaimID'] ? 'update_claim' : 'create_claim'; ?>">
                
                <div class="form-section">
                    <h3>Interrogation Results</h3>
                    
                    <div class="form-group">
                        <label for="passed_interrogationYN">Did the claimant pass interrogation?</label>
                        <div class="radio-group">
                            <div class="radio-option <?php echo ($contact['passed_interrogationYN'] == 1) ? 'selected' : ''; ?>">
                                <input type="radio" name="passed_interrogationYN" value="yes" id="passed_yes"
                                       <?php echo ($contact['passed_interrogationYN'] == 1) ? 'checked' : ''; ?>>
                                <label for="passed_yes">Yes - Passed</label>
                            </div>
                            <div class="radio-option <?php echo ($contact['passed_interrogationYN'] == 0) ? 'selected' : ''; ?>">
                                <input type="radio" name="passed_interrogationYN" value="no" id="passed_no"
                                       <?php echo ($contact['passed_interrogationYN'] == 0) ? 'checked' : ''; ?>>
                                <label for="passed_no">No - Failed</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="interrogation_notes">Interrogation Notes:</label>
                        <textarea name="interrogation_notes" id="interrogation_notes" 
                                  placeholder="Document the interrogation process, questions asked, claimant's responses, and any observations..."><?php echo htmlspecialchars($contact['interrogation_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>Claim Status</h3>
                    
                    <div class="form-group">
                        <label for="claim_status">Current Claim Status:</label>
                        <select name="claim_status" id="claim_status">
                            <option value="processing" <?php echo ($contact['claim_status'] === 'Processing' || !$contact['claim_status']) ? 'selected' : ''; ?>>
                                Processing - In Progress
                            </option>
                            <option value="complete" <?php echo ($contact['claim_status'] === 'Completed') ? 'selected' : ''; ?>>
                                Complete - Resolved
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <?php echo $contact['ClaimID'] ? 'Update Claim' : 'Create Claim'; ?>
                    </button>
                    <a href="inbox.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Add interactivity to radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove selected class from all radio options in the same group
                document.querySelectorAll(`input[name="${this.name}"]`).forEach(r => {
                    r.closest('.radio-option').classList.remove('selected');
                });
                
                // Add selected class to the chosen option
                this.closest('.radio-option').classList.add('selected');
            });
        });
        
        // Form validation
        document.getElementById('claimForm').addEventListener('submit', function(e) {
            const interrogationResult = document.querySelector('input[name="passed_interrogationYN"]:checked');
            const interrogationNotes = document.getElementById('interrogation_notes').value.trim();
            
            if (!interrogationResult) {
                alert('Please select whether the claimant passed interrogation.');
                e.preventDefault();
                return;
            }
            
            if (!interrogationNotes) {
                alert('Please provide interrogation notes.');
                e.preventDefault();
                return;
            }
            
            // Confirm before submitting
            const action = '<?php echo $contact['ClaimID'] ? 'update' : 'create'; ?>';
            const confirmMessage = `Are you sure you want to ${action} this claim?`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
<?php
// Get the buffered content
$page_content = ob_get_clean();

// Include the layout
require_once '../includes/admin_layout.php';
?>
