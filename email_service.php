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
            
            // Check if we're in test mode
            if (isset($this->config['test_mode']) && $this->config['test_mode']) {
                // In test mode, just log the email and return success
                $this->logEmailAttempt($to, $subject, 'TEST_MODE');
                return true;
            }
            
            // Choose email method based on provider
            if ($this->config['provider'] === 'smtp') {
                return $this->sendSMTPEmail($to, $subject, $message, $isHTML);
            } elseif ($this->config['provider'] === 'file') {
                return $this->saveEmailToFile($to, $subject, $message, $isHTML);
            } else {
                return $this->sendPHPMail($to, $subject, $message, $isHTML);
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
    public function sendContactRequestNotification($to, $claimantName, $itemName, $status, $adminNotes = '', $relatedPostId = null, $relatedContactId = null) {
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
                        <a href="' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . '/foundit/login.php" class="btn">Access Your Account</a>
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
    
    /**
     * Send email using PHP's mail() function
     */
    private function sendPHPMail($to, $subject, $message, $isHTML = true) {
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
    }
    
    /**
     * Send email using SMTP (basic implementation)
     */
    private function sendSMTPEmail($to, $subject, $message, $isHTML = true) {
        // Basic SMTP implementation using PHP's socket functions
        $this->logEmailAttempt($to, $subject, 'SMTP_ATTEMPT');
        
        try {
            $smtp = $this->config['smtp'];
            
            // Create socket connection
            $socket = fsockopen($smtp['host'], $smtp['port'], $errno, $errstr, 30);
            if (!$socket) {
                $this->logEmailAttempt($to, $subject, 'SMTP_CONNECTION_FAILED');
                return false;
            }
            
            // SMTP conversation
            $this->smtpCommand($socket, null, '220'); // Server greeting
            $this->smtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME'], '250');
            
            if ($smtp['secure'] === 'tls') {
                $this->smtpCommand($socket, "STARTTLS", '220');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME'], '250');
            }
            
            if ($smtp['auth']) {
                $this->smtpCommand($socket, "AUTH LOGIN", '334');
                $this->smtpCommand($socket, base64_encode($smtp['username']), '334');
                $this->smtpCommand($socket, base64_encode($smtp['password']), '235');
            }
            
            // Send email
            $this->smtpCommand($socket, "MAIL FROM: <" . $this->config['from']['email'] . ">", '250');
            $this->smtpCommand($socket, "RCPT TO: <$to>", '250');
            $this->smtpCommand($socket, "DATA", '354');
            
            // Email headers and body
            $headers = "From: " . $this->config['from']['name'] . " <" . $this->config['from']['email'] . ">\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            if ($isHTML) {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $headers .= "\r\n";
            
            $emailData = $headers . $message . "\r\n.";
            $this->smtpCommand($socket, $emailData, '250');
            
            $this->smtpCommand($socket, "QUIT", '221');
            fclose($socket);
            
            $this->logEmailAttempt($to, $subject, 'SMTP_SUCCESS');
            return true;
            
        } catch (Exception $e) {
            $this->logEmailAttempt($to, $subject, 'SMTP_ERROR: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper function for SMTP commands
     */
    private function smtpCommand($socket, $command, $expectedCode) {
        if ($command !== null) {
            fwrite($socket, $command . "\r\n");
        }
        
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        
        $responseCode = substr($response, 0, 3);
        if ($responseCode !== $expectedCode) {
            throw new Exception("SMTP Error: Expected $expectedCode, got $responseCode - $response");
        }
        
        return $response;
    }
    
    /**
     * Save email to file for development
     */
    private function saveEmailToFile($to, $subject, $message, $isHTML = true) {
        $emailDir = 'logs/emails';
        
        // Create emails directory if it doesn't exist
        if (!is_dir($emailDir)) {
            mkdir($emailDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $emailDir . '/email_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $to) . '.html';
        
        $emailContent = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Email: $subject</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .email-header { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .email-body { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='email-header'>
        <h2>📧 Simulated Email</h2>
        <p><strong>To:</strong> $to</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>Type:</strong> " . ($isHTML ? 'HTML' : 'Plain Text') . "</p>
    </div>
    <div class='email-body'>
        $message
    </div>
</body>
</html>";
        
        file_put_contents($filename, $emailContent);
        
        $this->logEmailAttempt($to, $subject, 'SAVED_TO_FILE');
        
        // Always return true for file saving unless there's an error
        return true;
    }
    
}

// Initialize the email service
$emailService = new EmailService();
?>
