<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoundIt - <?php echo isset($content_header) ? htmlspecialchars($content_header) : 'Dashboard'; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="resources/logo.png">
</head>
<body>
    <div class='main-content'>
        <div class='side-bar'>
            <div class="user-menu" style="margin-bottom: 32px; align-items: center; display: flex; flex-direction: column;">
                <img src="./resources/user.png" alt="user icon" style="width:64px; height:auto; border-radius:50%; object-fit:cover; margin-bottom:13px; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08);">
                <button class="user-button" onclick="toggleDropdown()" style="width:100%; justify-content:center;">
                    <?php echo htmlspecialchars($user_name); ?>
                    <span style="margin-left:8px; font-size:1.1em;">&#9662;</span>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <a href="settings.php"><img src="./resources/settings.png" alt="Settings Icon">Settings</a>
                    <a href="logout.php" class="logout"><img src="./resources/user-logout.png" alt="Logout Icon">Logout</a>
                </div>
            </div>
            <a href="dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <img src="./resources/home.png" alt="Home Icon">Home
            </a>
            <a href="report_lost_item.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'report_lost_item.php') ? 'active' : ''; ?>">
                <img src="./resources/location-exclamation.png" alt="Report Lost Items Icon">Report
            </a>
            <a href="reports.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                <img src="./resources/megaphone-magnifying-glass.png" alt="My Reports Icon">Reports History
            </a>
            <a href="inbox.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inbox.php') ? 'active' : ''; ?>">
                <img src="./resources/env.png" alt="Contact Requests Icon">Contact Requests
            </a>
        </div>
        <div class="container" style="flex:1; display:flex; flex-direction:column; min-height:100vh;">
            <header>
                <div style="width:100%; display:flex; justify-content:center; align-items:center;">
                    <img src="./resources/logo.png" alt="FoundIt Logo">
                </div>
            </header>
            <div class='content'>
                <div class='content-header'>
                    <?php echo isset($content_header) ? htmlspecialchars($content_header) : ''; ?>
                </div>
                <div class='page-content'>
                    <?php echo isset($page_content) ? $page_content : ''; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    function toggleDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }

    document.addEventListener('click', function(event) {
        const userMenu = document.querySelector('.user-menu');
        const dropdown = document.getElementById('userDropdown');
        
        if (!userMenu.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
    </script>
</body>
</html>