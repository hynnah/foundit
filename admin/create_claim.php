<?php
require_once '../auth_check.php';
requireAdmin(); // Only admins can access this page

require_once '../functions.php';
require_once '../dbh.inc.php';

$user_name = getUserName();
$content_header = "Create Claim";

// Get contact request ID
$contactId = $_GET['contact_id'] ?? '';

if (!$contactId || !is_numeric($contactId)) {
    header("Location: inbox.php");
    exit;
}

// Get contact request and claim details
$sql = "SELECT 
            cr.*,
            cl.ClaimID,
            cl.claim_status,
            cl.interrogation_notes,
            cl.passed_interrogation,
            p_claimant.name as claimant_name,
            p_claimant.email as claimant_email,
            p_claimant.phone_number as claimant_phone,
            u_claimant.role as claimant_role,
            r.item_name,
            r.description as item_description,
            r.image_path,
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
        $passed_interrogation = $_POST['passed_interrogation'] ?? '';
        $adminId = getUserId();
        
        if ($action === 'update_claim') {
            $claim_status = ($_POST['claim_status'] ?? '') === 'complete' ? 'Completed' : 'Processing';
            $passed = ($passed_interrogation === 'yes') ? 1 : 0;
            
            $sql_update = "UPDATE Claim SET 
                          claim_status = ?,
                          interrogation_notes = ?,
                          passed_interrogation = ?,
                          resolution_date = " . ($claim_status === 'Completed' ? 'NOW()' : 'NULL') . "
                          WHERE ContactID = ?";
            
            $stmt_update = mysqli_prepare($connection, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssii", $claim_status, $interrogation_notes, $passed, $contactId);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $success_message = "Claim updated successfully!";
                
                // If completed, update the post status
                if ($claim_status === 'Completed') {
                    $sql_post = "UPDATE FeedPost SET post_status = 'Claimed' WHERE PostID = ?";
                    $stmt_post = mysqli_prepare($connection, $sql_post);
                    mysqli_stmt_bind_param($stmt_post, "i", $contact['PostID']);
                    mysqli_stmt_execute($stmt_post);
                }
                
                // Refresh the data
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $contact = mysqli_fetch_assoc($result);
            } else {
                $error_message = "Error updating claim. Please try again.";
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .claim-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .claim-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-processing { background: #ffeaa7; color: #2d3436; }
        .status-completed { background: #00b894; color: white; }
        .status-rejected { background: #e17055; color: white; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #cb7f00;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 5px;
            width: auto;
        }
    </style>
</head>
<body>
    <div class="claim-container">
        <div style="margin-bottom: 20px;">
            <a href="inbox.php" class="btn btn-secondary">← Back to Inbox</a>
        </div>
        
        <h1>Claim Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- Contact Request Info -->
        <div class="claim-info">
            <h2>Contact Request Details</h2>
            <p><strong>Item:</strong> <?php echo htmlspecialchars($contact['item_name']); ?></p>
            <p><strong>Claimant:</strong> <?php echo htmlspecialchars($contact['claimant_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($contact['claimant_email']); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($contact['claimant_role']); ?></p>
            <p><strong>Request Date:</strong> <?php echo formatDate($contact['submission_date']); ?></p>
            <p><strong>Ownership Description:</strong> <?php echo htmlspecialchars($contact['ownership_description']); ?></p>
        </div>
        
        <!-- Claim Form -->
        <div class="claim-form">
            <h2>Claim Processing</h2>
            
            <?php if ($contact['ClaimID']): ?>
                <p><strong>Claim ID:</strong> <?php echo $contact['ClaimID']; ?></p>
                <p><strong>Current Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($contact['claim_status']); ?>">
                        <?php echo $contact['claim_status']; ?>
                    </span>
                </p>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_claim">
                
                <div class="form-group">
                    <label for="passed_interrogation">Passed Interrogation:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="passed_interrogation" value="yes" 
                                   <?php echo ($contact['passed_interrogation'] == 1) ? 'checked' : ''; ?>>
                            Yes
                        </label>
                        <label>
                            <input type="radio" name="passed_interrogation" value="no" 
                                   <?php echo ($contact['passed_interrogation'] == 0) ? 'checked' : ''; ?>>
                            No
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="interrogation_notes">Interrogation Notes:</label>
                    <textarea name="interrogation_notes" id="interrogation_notes" 
                              placeholder="Add notes about the interrogation process..."><?php echo htmlspecialchars($contact['interrogation_notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="claim_status">Claim Status:</label>
                    <select name="claim_status" id="claim_status">
                        <option value="processing" <?php echo ($contact['claim_status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="complete" <?php echo ($contact['claim_status'] === 'Completed') ? 'selected' : ''; ?>>Complete</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Claim</button>
                <a href="inbox.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
require_once '../includes/admin_layout.php';
?>
