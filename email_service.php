<?php
/**
 * Email Service for FoundIt System
 * Handles sending emails for notifications, contact requests, etc.
 */

class EmailService {
    private $config;
    
    public function __construct() {
        // Load configuration from file if it exists
        $configFile = __DIR__ . '/email_config.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            // Default configuration
            $this->config = [
                'provider' => 'mail',
                'smtp' => [
                    'host' => 'smtp.gmail.com',
                    'port' => 587,
                    'secure' => 'tls',
                    'auth' => true,
                    'username' => '',
                    'password' => ''
                ],
                'from' => [
                    'email' => 'noreply@foundit.com',
                    'name' => 'FoundIt System'
                ],
                'admin_email' => 'admin@foundit.com',
                'logging' => [
                    'enabled' => true,
                    'log_file' => 'logs/email_log.txt',
                    'log_level' => 'info'
                ]
            ];
        }
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($to, $subject, $message, $isHTML = true) {
        try {
            // Validate email address
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: $to");
            }
            
            // For development/testing, we'll use PHP's mail() function
            // In production, this should use SMTP or email service API
            
            $headers = [];
            $headers[] = 'From: ' . $this->config['from']['name'] . ' <' . $this->config['from']['email'] . '>';
            $headers[] = 'Reply-To: ' . $this->config['from']['email'];
            $headers[] = 'X-Mailer: PHP/' . phpversion();
            $headers[] = 'MIME-Version: 1.0';
            
            if ($isHTML) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            }
            
            // Log the email attempt
            $this->logEmailAttempt($to, $subject, 'ATTEMPT');
            
            // Try to send the email
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if ($result) {
                $this->logEmailAttempt($to, $subject, 'SUCCESS');
                return true;
            } else {
                $this->logEmailAttempt($to, $subject, 'FAILED');
                return false;
            }
            
            // For production with SMTP, you would use PHPMailer or similar:
            /*
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = $this->config['smtp_auth'];
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port = $this->config['smtp_port'];
            
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to);
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            return $mail->send();
            */
            
        } catch (Exception $e) {
            logActivity(0, 'EMAIL_ERROR', "Failed to send email to $to: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send contact request notification to claimant
     */
    public function sendContactRequestNotification($to, $claimantName, $itemName, $status, $adminNotes = '') {
        $subject = "FoundIt - Contact Request Update";
        
        $message = $this->buildContactRequestEmailTemplate($claimantName, $itemName, $status, $adminNotes);
        
        return $this->sendEmail($to, $subject, $message, true);
    }
    
    /**
     * Send admin notification about new contact request
     */
    public function sendAdminNotification($adminEmail, $claimantName, $itemName, $contactId) {
        $subject = "FoundIt - New Contact Request Requires Review";
        
        $message = $this->buildAdminNotificationTemplate($claimantName, $itemName, $contactId);
        
        return $this->sendEmail($adminEmail, $subject, $message, true);
    }
    
    /**
     * Build contact request email template
     */
    private function buildContactRequestEmailTemplate($claimantName, $itemName, $status, $adminNotes = '') {
        $statusColor = $status === 'Approved' ? '#28a745' : ($status === 'Rejected' ? '#dc3545' : '#ffc107');
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>FoundIt - Contact Request Update</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; background: ' . $statusColor . '; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>FoundIt System</h1>
                    <p>Contact Request Update</p>
                </div>
                
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($claimantName) . ',</h2>
                    
                    <p>We have an update regarding your contact request for the item: <strong>' . htmlspecialchars($itemName) . '</strong></p>
                    
                    <p>Status: <span class="status-badge">' . htmlspecialchars($status) . '</span></p>
                    
                    ' . ($status === 'Approved' ? '
                    <p>Great news! Your contact request has been approved. You can now proceed with claiming the item.</p>
                    <p>Please log in to your FoundIt account to continue the claim process.</p>
                    ' : '') . '
                    
                    ' . ($status === 'Rejected' ? '
                    <p>Unfortunately, your contact request has been rejected. Please review the details below.</p>
                    ' : '') . '
                    
                    ' . ($adminNotes ? '
                    <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;">
                        <h4>Admin Notes:</h4>
                        <p>' . nl2br(htmlspecialchars($adminNotes)) . '</p>
                    </div>
                    ' : '') . '
                    
                    <p>
                        <a href="' . $_SERVER['HTTP_HOST'] . '/foundit/login.php" class="btn">Access Your Account</a>
                    </p>
                    
                    <p>If you have any questions, please contact our support team.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from the FoundIt Lost & Found System.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Build admin notification template
     */
    private function buildAdminNotificationTemplate($claimantName, $itemName, $contactId) {
        $reviewUrl = $_SERVER['HTTP_HOST'] . '/foundit/admin/review_contact_request.php?id=' . $contactId;
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>FoundIt - New Contact Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
                .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
                .urgent { background: #dc3545; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>FoundIt Admin</h1>
                    <p>New Contact Request</p>
                </div>
                
                <div class="content">
                    <h2>New Contact Request Requires Review</h2>
                    
                    <p>A new contact request has been submitted and requires admin review:</p>
                    
                    <ul>
                        <li><strong>Claimant:</strong> ' . htmlspecialchars($claimantName) . '</li>
                        <li><strong>Item:</strong> ' . htmlspecialchars($itemName) . '</li>
                        <li><strong>Contact ID:</strong> #' . $contactId . '</li>
                        <li><strong>Submitted:</strong> ' . date('M d, Y h:i A') . '</li>
                    </ul>
                    
                    <p>Please review this request as soon as possible.</p>
                    
                    <p>
                        <a href="' . $reviewUrl . '" class="btn urgent">Review Request Now</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from the FoundIt Admin System.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Log email attempts for debugging and monitoring
     */
    private function logEmailAttempt($to, $subject, $status) {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        $logFile = $this->config['logging']['log_file'];
        $logDir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $status - To: $to, Subject: $subject\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log using the existing logActivity function if available
        if (function_exists('logActivity')) {
            logActivity(0, 'EMAIL_' . $status, "To: $to, Subject: $subject");
        }
    }
}

// Initialize the email service
$emailService = new EmailService();
?>
