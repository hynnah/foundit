<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Sign Up</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        .password-input-container {
            position: relative;
        }
        
        .password-input-container input {
            padding-right: 50px;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 18px;
            padding: 5px;
        }
        
        .toggle-password:hover {
            color: #cb7f00;
        }
    </style>
</head>
<body>
    <div class="signup-content">
        <div class="signup-container">
            <form action="register.inc.php" method="post">
                <h1>Sign Up</h1>
                <p class="subtitle">Create your account</p>
                
                <?php 
                if (isset($_GET["error"])) {
                    $error = $_GET["error"];
                    if ($error === "emptyfields") {
                        echo '<p class="error-message">Please fill in all fields.</p>';
                    } else if ($error === "invalidemail") {
                        echo '<p class="error-message">Invalid email format.</p>';
                    } else if ($error === "passwordmismatch") {
                        echo '<p class="error-message">Passwords do not match.</p>';
                    } else if ($error === "emailtaken") {
                        echo '<p class="error-message">Email already in use.</p>';
                    } else if ($error === "sqlerror") {
                        echo '<p class="error-message">Database error. Please try again.</p>';
                    }
                }
                
                if (isset($_GET["signup"])) {
                    if ($_GET["signup"] === "success") {
                        echo '<p class="success-message">Registration successful! You can now <a href="login.php">login</a>.</p>';
                    }
                }
                ?>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter your full name" 
                           value="<?php echo isset($_GET["name"]) ? htmlspecialchars($_GET["name"]) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email"
                           value="<?php echo isset($_GET["email"]) ? htmlspecialchars($_GET["email"]) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" pattern="[0-9]+" title="Please enter numbers only"
                           value="<?php echo isset($_GET["phone"]) ? htmlspecialchars($_GET["phone"]) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select your role</option>
                        <option value="student" <?php echo (isset($_GET["role"]) && $_GET["role"] === "student") ? 'selected' : ''; ?>>Student</option>
                        <option value="staff" <?php echo (isset($_GET["role"]) && $_GET["role"] === "staff") ? 'selected' : ''; ?>>Staff</option>
                        <option value="faculty" <?php echo (isset($_GET["role"]) && $_GET["role"] === "faculty") ? 'selected' : ''; ?>>Faculty</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password-repeat">Confirm Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password-repeat" name="password-repeat" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password-repeat')">
                            <i class="fas fa-eye" id="password-repeat-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="signup-submit" class="signup-button">Sign Up</button>
                
                <p class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId + '-toggle-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>

