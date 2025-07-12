<?php
/**
 * Email Configuration for FoundIt System
 * 
 * This file contains email configuration settings.
 * Copy this file to email_config.php and customize for your environment.
 */

return [
    // Email service provider: 'mail', 'smtp', 'sendgrid', 'mailgun'
    'provider' => 'mail',
    
    // SMTP Configuration (if using SMTP)
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => 'tls', // 'tls' or 'ssl'
        'auth' => true,
        'username' => '', // Your SMTP username
        'password' => '', // Your SMTP password
    ],
    
    // SendGrid Configuration (if using SendGrid)
    'sendgrid' => [
        'api_key' => '', // Your SendGrid API key
    ],
    
    // Mailgun Configuration (if using Mailgun)
    'mailgun' => [
        'api_key' => '', // Your Mailgun API key
        'domain' => '',  // Your Mailgun domain
    ],
    
    // Default sender information
    'from' => [
        'email' => 'noreply@foundit.com',
        'name' => 'FoundIt System'
    ],
    
    // Admin email for notifications
    'admin_email' => 'admin@foundit.com',
    
    // Email templates directory
    'templates_dir' => 'email_templates/',
    
    // Email logging
    'logging' => [
        'enabled' => true,
        'log_file' => 'logs/email_log.txt',
        'log_level' => 'info' // 'debug', 'info', 'warning', 'error'
    ],
    
    // Email queue settings (for bulk sending)
    'queue' => [
        'enabled' => false,
        'batch_size' => 50,
        'delay_between_batches' => 1 // seconds
    ],
    
    // Email content settings
    'settings' => [
        'charset' => 'UTF-8',
        'word_wrap' => 70,
        'priority' => 3, // 1 = High, 3 = Normal, 5 = Low
    ]
];
?>
