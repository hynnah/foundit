<?php
// Check email configuration
echo "<h2>Email Configuration Check</h2>";

// Check if mail() function is available
if (function_exists('mail')) {
    echo "✓ PHP mail() function is available<br>";
    
    // Check basic mail configuration
    echo "SMTP setting: " . ini_get('SMTP') . "<br>";
    echo "smtp_port: " . ini_get('smtp_port') . "<br>";
    echo "sendmail_from: " . ini_get('sendmail_from') . "<br>";
    echo "sendmail_path: " . ini_get('sendmail_path') . "<br>";
    
} else {
    echo "✗ PHP mail() function is not available<br>";
}

// Test email configuration
require_once 'email_config.php';
$config = require 'email_config.php';

echo "<h3>Current Email Configuration:</h3>";
echo "Provider: " . $config['provider'] . "<br>";
echo "From email: " . $config['from']['email'] . "<br>";
echo "Test mode: " . ($config['test_mode'] ? 'ON' : 'OFF') . "<br>";

// Test basic email
echo "<h3>Testing Email Send:</h3>";
$to = 'test@example.com';
$subject = 'Test Email from FoundIt';
$message = 'This is a test email from the FoundIt system.';
$headers = 'From: ' . $config['from']['email'];

if ($config['test_mode']) {
    echo "⚠️ Test mode is ON - no actual email will be sent<br>";
} else {
    echo "Attempting to send test email...<br>";
    $result = mail($to, $subject, $message, $headers);
    if ($result) {
        echo "✓ Email sent successfully!<br>";
    } else {
        echo "✗ Email sending failed<br>";
        echo "Error: " . error_get_last()['message'] . "<br>";
    }
}

// Check if logs directory exists
if (is_dir('logs')) {
    echo "<br>✓ Logs directory exists<br>";
    if (is_writable('logs')) {
        echo "✓ Logs directory is writable<br>";
    } else {
        echo "✗ Logs directory is not writable<br>";
    }
} else {
    echo "<br>✗ Logs directory does not exist<br>";
}

?>
