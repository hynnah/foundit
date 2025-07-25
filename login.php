<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Login</title>
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
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal form {
            padding: 20px;
        }
        
        .modal-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        
        .modal .form-group {
            margin-bottom: 15px;
        }
        
        .modal .login-button {
            width: 100%;
            margin-top: 10px;
        }
        
        /* Floating Particles Styles */
        .floating-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(203, 127, 0, 0.3);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        .particle:nth-child(even) {
            background: rgba(255, 179, 71, 0.2);
            animation-duration: 25s;
        }
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        .right-panel {
            position: relative;
            overflow: hidden;
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
        <!-- Floating Particles Background -->
        <div class="floating-particles">
            <div class="particle" style="left: 10%; animation-delay: 0s;"></div>
            <div class="particle" style="left: 20%; animation-delay: 2s;"></div>
            <div class="particle" style="left: 30%; animation-delay: 4s;"></div>
            <div class="particle" style="left: 40%; animation-delay: 6s;"></div>
            <div class="particle" style="left: 50%; animation-delay: 8s;"></div>
            <div class="particle" style="left: 60%; animation-delay: 10s;"></div>
            <div class="particle" style="left: 70%; animation-delay: 12s;"></div>
            <div class="particle" style="left: 80%; animation-delay: 14s;"></div>
            <div class="particle" style="left: 90%; animation-delay: 16s;"></div>
        </div>
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
                } else if ($error === "accountdeactivated") {
                    echo '<p class="error-message">Your account has been deactivated. Please contact an administrator.</p>';
                } else if ($error === "sessionexpired") {
                    echo '<p class="error-message">Your session has expired. Please log in again.</p>';
                }
            }
            
            // Check for success messages
            if (isset($_GET["success"])) {
                $success = $_GET["success"];
                if ($success === "passwordreset") {
                    echo '<p class="success-message">Password reset successfully! You can now log in with your new password.</p>';
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

        // Floating particles scroll animation (from index.php)
        window.addEventListener('scroll', function() {
            const particles = document.querySelectorAll('.right-panel .particle');
            const scrolled = window.pageYOffset;
            particles.forEach((particle, index) => {
                const speed = 0.5 + (index * 0.1);
                particle.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>
</body>
</html>