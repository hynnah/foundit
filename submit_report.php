<?php
require_once 'auth_check.php';
require_once 'functions.php';

// Database connection
require_once 'dbh.inc.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

// CSRF protection
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    header("Location: dashboard.php?error=invalid_token");
    exit();
}

// Get form data
$reportType = $_POST['report_type'] ?? '';
$itemName = trim($_POST['item_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$location = trim($_POST['location'] ?? '');
$incidentDate = $_POST['incident_date'] ?? '';
$userId = getUserId();

// Note: Both users and admins can submit found item reports

// Input validation
$errors = [];

if (empty($reportType) || !in_array($reportType, ['Lost', 'Found'])) {
    $errors[] = "Invalid report type.";
}

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

if (empty($incidentDate)) {
    $errors[] = "Incident date is required.";
} elseif (!strtotime($incidentDate)) {
    $errors[] = "Invalid date format.";
} elseif (strtotime($incidentDate) > time()) {
    $errors[] = "Incident date cannot be in the future.";
}

// Validate image if uploaded
if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if (!isValidImage($_FILES['item_image'])) {
        $errors[] = "Invalid image file. Only JPEG, PNG, GIF, and WebP files under 5MB are allowed.";
    }
}

if (!empty($errors)) {
    $errorString = implode('|', $errors);
    if ($reportType === 'Found') {
        // Check if user is admin to determine redirect
        if (isAdmin()) {
            header("Location: admin/log_found_item.php?error=" . urlencode($errorString));
        } else {
            header("Location: dashboard.php?error=" . urlencode($errorString));
        }
    } else {
        header("Location: report_lost_item.php?error=" . urlencode($errorString));
    }
    exit();
}

// Handle image upload
$imagePath = null;
if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = generateSecureFilename($_FILES['item_image']['name']);
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['item_image']['tmp_name'], $destination)) {
        $imagePath = $destination;
    } else {
        if ($reportType === 'Found') {
            if (isAdmin()) {
                header("Location: admin/log_found_item.php?error=upload_failed");
            } else {
                header("Location: dashboard.php?error=upload_failed");
            }
        } else {
            header("Location: report_lost_item.php?error=upload_failed");
        }
        exit();
    }
}

// Start transaction
mysqli_autocommit($connection, FALSE);

try {
    // Different logic for admins vs regular users
    if (isAdmin()) {
        // Admins can approve their own reports immediately
        $sql_report = "INSERT INTO Report (UserID_submitter, AdminID_reviewer, report_type, item_name, description, incident_date, submission_date, ApprovalStatusID, image_path) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW(), 2, ?)";
        $stmt_report = mysqli_prepare($connection, $sql_report);
        mysqli_stmt_bind_param($stmt_report, "iisssss", $userId, $userId, $reportType, $itemName, $description, $incidentDate, $imagePath);
    } else {
        // Regular users submit reports that need admin approval
        $sql_report = "INSERT INTO Report (UserID_submitter, report_type, item_name, description, incident_date, submission_date, ApprovalStatusID, image_path) 
                      VALUES (?, ?, ?, ?, ?, NOW(), 1, ?)";
        $stmt_report = mysqli_prepare($connection, $sql_report);
        mysqli_stmt_bind_param($stmt_report, "isssss", $userId, $reportType, $itemName, $description, $incidentDate, $imagePath);
    }
    
    if (!mysqli_stmt_execute($stmt_report)) {
        throw new Exception("Error inserting into Report table: " . mysqli_error($connection));
    }
    
    // Get the inserted report ID
    $reportId = mysqli_insert_id($connection);
    mysqli_stmt_close($stmt_report);
    
    // Insert into appropriate subtype table
    if ($reportType === 'Lost') {
        $sql_subtype = "INSERT INTO Lost (ReportID, location_last_seen) VALUES (?, ?)";
    } else {
        $sql_subtype = "INSERT INTO Found (ReportID, location_found) VALUES (?, ?)";
    }
    
    $stmt_subtype = mysqli_prepare($connection, $sql_subtype);
    mysqli_stmt_bind_param($stmt_subtype, "is", $reportId, $location);
    
    if (!mysqli_stmt_execute($stmt_subtype)) {
        throw new Exception("Error inserting into subtype table: " . mysqli_error($connection));
    }
    mysqli_stmt_close($stmt_subtype);
    
    // For Found items, create FeedPost entry immediately
    if ($reportType === 'Found') {
        $sql_feed = "INSERT INTO FeedPost (ReportID, post_date, post_status) VALUES (?, NOW(), 'Active')";
        $stmt_feed = mysqli_prepare($connection, $sql_feed);
        mysqli_stmt_bind_param($stmt_feed, "i", $reportId);
        
        if (!mysqli_stmt_execute($stmt_feed)) {
            throw new Exception("Error inserting into FeedPost table: " . mysqli_error($connection));
        }
        mysqli_stmt_close($stmt_feed);
    }
    
    // Commit transaction
    mysqli_commit($connection);
    
    // Log the activity
    logActivity($userId, 'REPORT_SUBMITTED', "Report Type: $reportType, Item: $itemName, Report ID: $reportId");
    
    // Redirect with success message
    if ($reportType === 'Found') {
        header("Location: admin/log_found_item.php?success=found_item_logged");
    } else {
        header("Location: report_lost_item.php?success=lost_item_reported");
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($connection);
    
    // Log the error
    error_log("Report submission error: " . $e->getMessage());
    
    // Redirect with error message
    header("Location: " . ($reportType === 'Found' ? 'admin/log_found_item.php' : 'report_lost_item.php') . "?error=database_error");
}

// Restore autocommit
mysqli_autocommit($connection, TRUE);
exit();
?>
