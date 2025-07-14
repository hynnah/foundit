<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Login</title>
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
    <div class="left-panel">
        <div class="logo-container">
            <img src="resources/logo.png" alt="FoundIt Logo" class="logo">
            <div class="welcome-text">
                <h2>Welcome to FoundIt!</h2>
                <p>Your campus lost & found made easy!</p>
            </div>
        </div>
    </div>

    <div class="right-panel">
        <div class="login-container">
            <h1>Login</h1>
            <p class="subtitle">Sign in to continue</p>
            
            <?php 
            if (isset($_GET["error"])) {
                $error = $_GET["error"];
                if ($error === "emptyfields") {
                    echo '<p class="error-message">Please fill in all fields.</p>';
                } else if ($error === "authfailed") {
                    echo '<p class="error-message">Invalid email or password.</p>';
                } else if ($error === "sqlerror") {
                    echo '<p class="error-message">Database error. Please try again.</p>';
                }
            }
            ?>
            
            <form action="login.inc.php" method="post">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email"
                           value="<?php echo isset($_GET["email"]) ? htmlspecialchars($_GET["email"]) : ''; ?>">
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
                
                <button type="submit" name="login-submit" class="login-button">Login</button>
            </form>
            
            <p class="register-link">
                Don't have an account? <a href="register.php">Register</a>
            </p>
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
    </script>
</body>
</html>