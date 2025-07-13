<?php
/**
 * Email Configuration for FoundIt System
 * 
 * Basic configuration for local development
 */

return [
    // Email service provider: 'mail', 'smtp', 'file', 'sendgrid', 'mailgun'
    'provider' => 'file', // Use file logging for development
    
    // SMTP Configuration (for when you have SMTP configured)
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => 'tls', // 'tls' or 'ssl'
        'auth' => true,
        'username' => 'your-email@gmail.com', // Replace with your Gmail address
        'password' => 'your-app-password', // Replace with your Gmail app password
    ],
    
    // From email settings
    'from' => [
        'email' => 'noreply@foundit.local',
        'name' => 'FoundIt System'
    ],
    
    // Admin email
    'admin_email' => 'admin@foundit.local',
    
    // Logging settings
    'logging' => [
        'enabled' => true,
        'log_file' => 'logs/email_log.txt',
        'log_level' => 'info'
    ],
    
    // Development mode (for testing)
    'development_mode' => true,
    'test_mode' => false, // Set to true to use test email service like Mailtrap
    
    // Development notice
    'dev_notice' => 'Emails are being saved to logs/emails/ folder instead of being sent. To send real emails, configure SMTP settings and change provider to "smtp" or "mail".'
];
?>
