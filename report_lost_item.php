<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Report Lost Item";

// Database connection
require_once 'dbh.inc.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $itemName = $_POST['itemName'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $userId = $_SESSION['userId'];
    
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
        $sql_report = "INSERT INTO report (UserID_submitter, report_type, item_name, description, incident_date, submission_date, ApprovalStatusID) 
                      VALUES (?, 'lost', ?, ?, CURDATE(), NOW(), 1)";
        
        $stmt_report = mysqli_prepare($connection, $sql_report);
        mysqli_stmt_bind_param($stmt_report, "iss", $userId, $itemName, $description);
        
        if (!mysqli_stmt_execute($stmt_report)) {
            throw new Exception("Error inserting into report table: " . mysqli_error($connection));
        }
        
        // Get the inserted report ID
        $reportId = mysqli_insert_id($connection);
        mysqli_stmt_close($stmt_report);
        
        // Insert into lost table
        $sql_lost = "INSERT INTO lost (ReportID, location_last_seen) VALUES (?, ?)";
        $stmt_lost = mysqli_prepare($connection, $sql_lost);
        mysqli_stmt_bind_param($stmt_lost, "is", $reportId, $location);
        
        if (!mysqli_stmt_execute($stmt_lost)) {
            throw new Exception("Error inserting into lost table: " . mysqli_error($connection));
        }
        mysqli_stmt_close($stmt_lost);
        
        if ($imagePath) {
          
            $sql_image = "UPDATE report SET image_path = ? WHERE ReportID = ?";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Report Lost Item</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        .main-wrapper {
            padding: 40px;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .form-container {
            background: rgba(255, 227, 142, 0.95);
            border-radius: 20px;
            padding: 50px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
        }

        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-header i {
            width: 50px;
            height: 40px;
            color: #cb7f00;
            margin-right: 15px;
            font-size: 50px;
            display: inline-block;
            text-align: center;
        }

        .form-header h1 {
            font-size: 30px;
            color: #333;
            font-weight: 600;
            margin: 0;
        }

        .form-group {
            margin-bottom: 35px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #333;
            font-weight: 600;
            font-size: 18px;
        }

        .form-group label i {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            color: #333;
            font-size: 16px;
            display: inline-block;
            text-align: center;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 18px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
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
            transform: translateY(-1px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .image-upload-section {
            position: relative;
            margin-bottom: 35px;
        }

        .image-upload-label {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #333;
            font-weight: 600;
            font-size: 18px;
        }

        .image-upload-label i {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            color: #333;
            font-size: 16px;
            display: inline-block;
            text-align: center;
        }

        .image-upload-area {
            border: 2px dashed #cb7f00;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .image-upload-area:hover {
            background: #fef9f0;
            border-color: #bd7800;
        }

        .image-upload-area.has-image {
            border-style: solid;
            background: #f8f9fa;
        }

        .upload-button {
            background: #cb7f00;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .upload-button:hover {
            background: #bd7800;
        }

        .upload-text {
            color: #666;
            font-size: 0.9rem;
        }

        .image-preview {
            display: none;
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin: 10px auto;
        }

        .submit-button {
            width: 100%;
            padding: 20px;
            background: linear-gradient(45deg, #cb7f00, #e89611);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
        }

        .submit-button:hover {
            background: linear-gradient(45deg, #bd7800, #d48806);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(203, 127, 0, 0.3);
        }

        #imageInput {
            display: none;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .main-wrapper {
                padding: 20px;
            }
            
            .form-container {
                padding: 30px;
                margin: 10px;
            }

            .form-header h1 {
                font-size: 24px;
            }

            .form-header i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Please dont remove -->  
    <?php
    ob_start();
    ?> 

    <!-- Main content -->
    <div class="main-wrapper">
        <div class="form-container">
            <div class="form-header">
                <i class="fa-solid fa-bullhorn"></i>
                <h1>REPORT YOUR LOST ITEM</h1>
            </div>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form id="reportForm" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="itemName">
                    <i class="fa-solid fa-tag"></i>
                    Item Name:
                </label>
                <input type="text" id="itemName" name="itemName" placeholder="maximum 50 characters" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="description">
                    <i class="fa-solid fa-paperclip"></i>
                    Description:
                </label>
                <textarea id="description" name="description" placeholder="maximum 100 characters" maxlength="100" required></textarea>
            </div>

            <div class="image-upload-section">
                <div class="image-upload-label">
                    <i class="fa-solid fa-image"></i>
                    Item Image (not required):
                </div>
                <div class="image-upload-area" onclick="document.getElementById('imageInput').click()">
                    <div class="upload-content">
                        <button type="button" class="upload-button">
                            <i class="fa-solid fa-file"></i>
                            Choose an image
                        </button>
                        <div class="upload-text" id="uploadText">No image selected</div>
                        <img id="imagePreview" class="image-preview" alt="Preview">
                    </div>
                </div>
                <input type="file" id="imageInput" name="itemImage" accept="image/*" onchange="handleImageUpload(event)">
            </div>

            <div class="form-group">
                <label for="location">
                    <i class="fa-solid fa-location-dot"></i>
                    Location last seen:
                </label>
                <input type="text" id="location" name="location" placeholder="Where did you last see your item?" required>
            </div>

            <button type="submit" class="submit-button">
                Submit Post Request
            </button>
        </form>
    </div>
</div>

    <script>
        function handleImageUpload(event) {
            const file = event.target.files[0];
            const uploadText = document.getElementById('uploadText');
            const imagePreview = document.getElementById('imagePreview');
            const uploadArea = document.querySelector('.image-upload-area');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    uploadText.textContent = `Selected: ${file.name}`;
                    uploadArea.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
                uploadText.textContent = 'No image selected';
                uploadArea.classList.remove('has-image');
            }
        }

        // Character count indicators
        document.getElementById('itemName').addEventListener('input', function() {
            const remaining = 50 - this.value.length;
            if (remaining < 10) {
                this.style.borderColor = remaining < 5 ? '#e74c3c' : '#f39c12';
            } else {
                this.style.borderColor = '#e1e1e1';
            }
        });

        document.getElementById('description').addEventListener('input', function() {
            const remaining = 100 - this.value.length;
            if (remaining < 20) {
                this.style.borderColor = remaining < 10 ? '#e74c3c' : '#f39c12';
            } else {
                this.style.borderColor = '#e1e1e1';
            }
        });
    </script>

    <!-- Please dont remove -->  
    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>
</body>
</html>