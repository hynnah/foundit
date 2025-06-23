<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - Login</title>
    <link rel="stylesheet" href="style.css">
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
                } else if ($error === "wrongcredentials") {
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
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit" name="login-submit" class="login-button">Login</button>
            </form>
            
            <p class="register-link">
                Don't have an account? <a href="register.php">Register</a>
            </p>
        </div>
    </div>
</body>
</html>