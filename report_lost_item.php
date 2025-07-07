<?php
session_start();

if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['userName'] ?? 'User';
$content_header = "Report Lost Item";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Report Lost Item</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style> /* ayaw hilabti*/
          body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            background-size: cover;
            background-image: url('resources/report_if_lost(1)'); /*lolz */
        }
          .sidebar {
            width: 400px;
            background:rgb(33, 33, 32);
            padding: 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
        }

        .user-profile {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.11);
            border-radius: 15px;
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            border: 3px solid #cb7f00;
            outline: none;
            /**i gyatta figure out how to glow the borders */
        }

        .username {
            font-size: 30px;
            font-weight: 600;
            color:rgb(255, 255, 255);
        }

        .nav-menu {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: rgb(255, 255, 255);
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.6);
        }

    .nav-item i {
    width: 20px;
    height: 20px;
    margin-right: 15px;
    color: currentColor;
    display: inline-block;
    text-align: center;
}

        .nav-item.active {
            background: #cb7f00;
            color: white;
        }

        .logout-button {
            margin-top: auto;
            padding: 15px 20px;
            background: #cb7f00;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .logout-button:hover {
            background:rgb(113, 113, 113);
            transform: translateY(2px);
            transition: all 0.3s;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .form-container {
            background: rgba(255, 227, 142, 0.95);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
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
        }

        .form-group {
            margin-bottom: 30px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color:rgb(10, 10, 10);
            font-weight: 500;
            font-size: 20px;
        }

   .form-group label i {
    width: 20px;
    height: 20px;
    margin-right: 8px;
    color:rgb(10, 10, 10);
    font-size: 16px;
    display: inline-block;
    text-align: center;
}
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            font-family: inherit;
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
            min-height: 100px;
        }

        .image-upload-section {
            position: relative;
            margin-bottom: 25px;
        }

        .image-upload-label {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            color:rgb(10, 10, 10);
            font-weight: 500;
            font-size: 20px;
        }

   .image-upload-label i {
    width: 20px;
    height: 20px;
    margin-right: 8px;
    color:rgb(0, 0, 0);
    font-size: 16px;
    display: inline-block;
    text-align: center;
}

        .image-upload-area {
            border: 2px dashed #cb7f00;
            border-radius: 10px;
            padding: 30px;
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
            padding: 18px;
            background: linear-gradient(45deg, #cb7f00, #e89611);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-button:hover {
            background: linear-gradient(45deg, #bd7800, #d48806);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(203, 127, 0, 0.3);
        }


        #imageInput {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .form-container {
                padding: 20px;
            }

            .form-header h1 {
                font-size: 1.5rem;
            }
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success {
            background: #d4edda;
            color:rgb(0, 38, 112);
            border: 1px solidrgb(182, 255, 250);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <!-- Please dont remove, kay maguba -->  
    <?php
    ob_start();
    ?> 

    <!-- Just add your main content here, basta! just delete below-->  
    
    <div class="sidebar">
        <div class="user-profile">
            <div class="user-avatar">
            <!--user profile pic-->
            </div>
            <div class="username">@user123456</div> 
            <!-- idk yet unsaon that the username sa sidebar dapit kay mo change depending on the username na ni login but for now
            i added a manually inputted username sa html structure -->
        </div>

        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item">
               <i class="fa-solid fa-house"></i>
                Home
            </a>
            <a href="found-items.php" class="nav-item">
                <i class="fa-solid fa-magnifying-glass"></i>
                Found Items
            </a>
            <a href="report-lost-item.php" class="nav-item active">
                <i class="fa-solid fa-plus"></i>
                Report Lost Item
            </a>
            <a href="inbox.php" class="nav-item">
               <i class="fa-solid fa-envelope-open-text"></i>
                Inbox
            </a>
        </nav>

        <button class="logout-button" onclick="logout()">
            Logout
        </button>
    </div>

    <div class="main-content">
        <div class="form-container">
            <div class="form-header">
              <i class="fa-solid fa-bullhorn"></i>
                <h1>REPORT YOUR LOST ITEM</h1>
            </div>
            <form id="reportForm" onsubmit="submitReport(event)">
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
                    <input type="file" id="imageInput" accept="image/*" onchange="handleImageUpload(event)">
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
     /*gi gpt nlng ni nko and JS HASDHAS i give up */
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

        function submitReport(event) {
            event.preventDefault();
            
            const formData = new FormData();
            const itemName = document.getElementById('itemName').value;
            const description = document.getElementById('description').value;
            const location = document.getElementById('location').value;
            const imageFile = document.getElementById('imageInput').files[0];
            
            formData.append('itemName', itemName);
            formData.append('description', description);
            formData.append('location', location);
            if (imageFile) {
                formData.append('itemImage', imageFile);
            }
            showMessage('Your lost item report has been submitted successfully! It will be reviewed by John Franz before being posted.', 'success');
            
            document.getElementById('reportForm').reset();
            handleImageUpload({ target: { files: [] } });

            /**ari lng guro ang backend chuchu */
        }

        function showMessage(message, type) {
            const existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = message;
            
            const form = document.getElementById('reportForm');
            form.parentNode.insertBefore(messageDiv, form);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

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

    <!-- Please dont remove, kay maguba(2) -->  
    <?php
        $page_content = ob_get_clean();
        include_once "includes/general_layout.php";
    ?>

</html>