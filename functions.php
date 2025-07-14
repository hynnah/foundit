<?php
// Helper functions for the FoundIt system

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (digits only)
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]+$/', $phone);
}

/**
 * Generate secure filename for uploads
 */
function generateSecureFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid('item_', true) . '.' . strtolower($extension);
}

/**
 * Check if file is valid image
 */
function isValidImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    return true;
}

/**
 * Log system activities
 */
function logActivity($userId, $action, $details = '') {
    $logFile = 'logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] User $userId: $action - $details\n";
    
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Send email notification using the email service
 */
function sendEmailNotification($to, $subject, $message, $isHTML = true) {
    require_once 'email_service.php';
    $emailService = new EmailService();
    
    return $emailService->sendEmail($to, $subject, $message, $isHTML);
}

/**
 * Send contact request notification to claimant
 */
function sendContactRequestNotification($to, $claimantName, $itemName, $status, $adminNotes = '') {
    require_once 'email_service.php';
    $emailService = new EmailService();
    
    return $emailService->sendContactRequestNotification($to, $claimantName, $itemName, $status, $adminNotes);
}

/**
 * Send admin notification about new contact request
 */
function sendAdminNotification($adminEmail, $claimantName, $itemName, $contactId) {
    require_once 'email_service.php';
    $emailService = new EmailService();
    
    return $emailService->sendAdminNotification($adminEmail, $claimantName, $itemName, $contactId);
}

/**
 * Calculate days since date
 */
function daysSince($date) {
    $now = new DateTime();
    $past = new DateTime($date);
    $diff = $now->diff($past);
    return $diff->days;
}

/**
 * Check if report is expired (over 30 days)
 */
function isReportExpired($submissionDate) {
    return daysSince($submissionDate) > 30;
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - 3) . '...';
}

/**
 * Get user's role display name
 */
function getRoleDisplayName($role) {
    $roles = [
        'Student' => 'Student',
        'Teacher' => 'Teacher',
        'Staff' => 'Staff',
        'Visitor' => 'Visitor',
        'Cashier' => 'Cashier',
        'Guard' => 'Security Guard',
        'Janitor' => 'Janitor'
    ];
    return $roles[$role] ?? 'Unknown';
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
