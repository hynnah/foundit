<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Sign Up</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="resources/logo.png">
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
        
        .validation-message {
            font-size: 12px;
            color: #dc3545;
            margin-top: 5px;
            padding: 5px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        }
        
        .form-group input:valid + .validation-message,
        .form-group input:focus:valid + .validation-message {
            display: none !important;
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
                        echo '<p class="error-message">Please fill in all required fields.</p>';
                    } else if ($error === "invalidemail") {
                        echo '<p class="error-message">Invalid email format or too long.</p>';
                    } else if ($error === "invalidname") {
                        echo '<p class="error-message">Name must be between 2-100 characters.</p>';
                    } else if ($error === "invalidphone") {
                        echo '<p class="error-message">Phone number must be 10-15 digits (numbers only).</p>';
                    } else if ($error === "passwordweak") {
                        echo '<p class="error-message">Password must be at least 6 characters long.</p>';
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
                           maxlength="100" minlength="2"
                           value="<?php echo isset($_GET["name"]) ? htmlspecialchars($_GET["name"]) : ''; ?>">
                    <div class="validation-message" id="name-validation" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email"
                           maxlength="100" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                           value="<?php echo isset($_GET["email"]) ? htmlspecialchars($_GET["email"]) : ''; ?>">
                    <div class="validation-message" id="email-validation" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" 
                           pattern="[0-9]{10,15}" title="Please enter 10-15 digits only (no letters, spaces, or special characters)"
                           maxlength="15" minlength="10"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           value="<?php echo isset($_GET["phone"]) ? htmlspecialchars($_GET["phone"]) : ''; ?>">
                    <div class="validation-message" id="phone-validation" style="display: none;"></div>
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
                    <div class="validation-message" id="password-validation" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label for="password-repeat">Confirm Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password-repeat" name="password-repeat" required placeholder="Confirm your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password-repeat')">
                            <i class="fas fa-eye" id="password-repeat-toggle-icon"></i>
                        </button>
                    </div>
                    <div class="validation-message" id="password-repeat-validation" style="display: none;"></div>
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
        
        // Real-time validation functions
        function showValidationMessage(fieldId, message) {
            const validationDiv = document.getElementById(fieldId + '-validation');
            if (validationDiv) {
                validationDiv.textContent = message;
                validationDiv.style.display = 'block';
            }
        }
        
        function hideValidationMessage(fieldId) {
            const validationDiv = document.getElementById(fieldId + '-validation');
            if (validationDiv) {
                validationDiv.style.display = 'none';
            }
        }
        
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Validate length
            if (this.value.length > 0 && this.value.length < 10) {
                showValidationMessage('phone', 'Phone number must be at least 10 digits');
            } else if (this.value.length > 15) {
                showValidationMessage('phone', 'Phone number cannot exceed 15 digits');
            } else if (this.value.length >= 10 && this.value.length <= 15) {
                hideValidationMessage('phone');
            }
        });
        
        // Name validation
        document.getElementById('name').addEventListener('input', function(e) {
            const name = this.value.trim();
            if (name.length > 0 && name.length < 2) {
                showValidationMessage('name', 'Name must be at least 2 characters');
            } else if (name.length > 100) {
                showValidationMessage('name', 'Name cannot exceed 100 characters');
            } else if (name.length >= 2 && name.length <= 100) {
                hideValidationMessage('name');
            }
        });
        
        // Email validation
        document.getElementById('email').addEventListener('input', function(e) {
            const email = this.value.trim();
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (email.length > 0 && !emailPattern.test(email)) {
                showValidationMessage('email', 'Please enter a valid email address');
            } else if (email.length > 100) {
                showValidationMessage('email', 'Email cannot exceed 100 characters');
            } else if (email.length > 0 && emailPattern.test(email) && email.length <= 100) {
                hideValidationMessage('email');
            }
        });
        
        // Password validation
        document.getElementById('password').addEventListener('input', function(e) {
            const password = this.value;
            const confirmPassword = document.getElementById('password-repeat').value;
            
            if (password.length > 0 && password.length < 8) {
                showValidationMessage('password', 'Password must be at least 8 characters');
            } else if (password.length >= 8) {
                hideValidationMessage('password');
            }
            
            // Check confirm password match
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                showValidationMessage('password-repeat', 'Passwords do not match');
            } else if (confirmPassword.length > 0 && password === confirmPassword) {
                hideValidationMessage('password-repeat');
            }
        });
        
        // Confirm password validation
        document.getElementById('password-repeat').addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                showValidationMessage('password-repeat', 'Passwords do not match');
            } else if (confirmPassword.length > 0 && password === confirmPassword) {
                hideValidationMessage('password-repeat');
            }
        });
        
        // Form validation on blur (when user leaves field)
        document.getElementById('name').addEventListener('blur', function(e) {
            const name = this.value.trim();
            if (name.length === 0) {
                hideValidationMessage('name');
            }
        });
        
        document.getElementById('email').addEventListener('blur', function(e) {
            const email = this.value.trim();
            if (email.length === 0) {
                hideValidationMessage('email');
            }
        });
        
        document.getElementById('phone').addEventListener('blur', function(e) {
            const phone = this.value.trim();
            if (phone.length === 0) {
                hideValidationMessage('phone');
            }
        });
    </script>
</body>
</html>

