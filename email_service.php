<?php
/**
 * CLEAN EMAIL SERVICE - Gmail SMTP Support
 * Handles password reset and contact request notifications
 */

class EmailService {
    private $config;
    private $pdo;
    
    public function __construct() {
        $this->config = require __DIR__ . '/email_config.php';
        
        // Database connection
        if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
            $host = "localhost";
            $username = "root";
            $password = "";
            $dbname = "s22102131_foundit";
        } else {
            $host = "localhost";
            $username = "s22102131_foundit";
            $password = "fi_database123";
            $dbname = "s22102131_foundit";
        }
        
        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * Main email sending function
     */
    public function sendEmail($to, $subject, $message, $isHTML = true) {
        try {
            // Validate email
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->logEmailAttempt($to, $subject, 'INVALID_EMAIL');
                return false;
            }
            
            // Choose sending method based on config
            if ($this->config['provider'] === 'gmail_smtp') {
                return $this->sendGmailSMTPEmail($to, $subject, $message, $isHTML);
            } else {
                return $this->storeEmailInDatabase($to, $subject, $message);
            }
            
        } catch (Exception $e) {
            $this->logEmailAttempt($to, $subject, 'ERROR: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via Gmail SMTP
     */
    private function sendGmailSMTPEmail($to, $subject, $message, $isHTML = true) {
        try {
            $this->logEmailAttempt($to, $subject, 'GMAIL_SMTP_ATTEMPT');
            
            // Gmail SMTP connection
            $socket = fsockopen($this->config['smtp_host'], $this->config['smtp_port'], $errno, $errstr, 30);
            
            if (!$socket) {
                throw new Exception("Failed to connect to Gmail SMTP: {$errno} - {$errstr}");
            }
            
            // SMTP handshake
            $this->smtpRead($socket, '220');
            $this->smtpCommand($socket, 'EHLO localhost', '250');
            $this->smtpCommand($socket, 'STARTTLS', '220');
            
            // Enable encryption
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
            
            // Re-authenticate after TLS
            $this->smtpCommand($socket, 'EHLO localhost', '250');
            $this->smtpCommand($socket, 'AUTH LOGIN', '334');
            $this->smtpCommand($socket, base64_encode($this->config['smtp_username']), '334');
            $this->smtpCommand($socket, base64_encode($this->config['smtp_password']), '235');
            
            // Send email
            $this->smtpCommand($socket, "MAIL FROM: <{$this->config['smtp_username']}>", '250');
            $this->smtpCommand($socket, "RCPT TO: <{$to}>", '250');
            $this->smtpCommand($socket, "DATA", '354');
            
            // Email headers and body
            $emailData = "From: {$this->config['from_name']} <{$this->config['smtp_username']}>\r\n";
            $emailData .= "To: {$to}\r\n";
            $emailData .= "Subject: {$subject}\r\n";
            $emailData .= "Date: " . date('r') . "\r\n";
            $emailData .= "MIME-Version: 1.0\r\n";
            
            if ($isHTML) {
                $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            
            $emailData .= "\r\n" . $message . "\r\n.\r\n";
            
            fwrite($socket, $emailData);
            $this->smtpRead($socket, '250');
            
            // Close connection
            $this->smtpCommand($socket, 'QUIT', '221');
            fclose($socket);
            
            $this->logEmailAttempt($to, $subject, 'GMAIL_SMTP_SUCCESS');
            return true;
            
        } catch (Exception $e) {
            $this->logEmailAttempt($to, $subject, 'GMAIL_SMTP_ERROR: ' . $e->getMessage());
            if (isset($socket)) {
                fclose($socket);
            }
            return false;
        }
    }
    
    /**
     * Store email in database (fallback method)
     */
    private function storeEmailInDatabase($to, $subject, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_emails (email, subject, message, created_at, is_read) 
                VALUES (?, ?, ?, NOW(), 0)
            ");
            
            if ($stmt->execute([$to, $subject, $message])) {
                $this->logEmailAttempt($to, $subject, 'STORED_IN_DATABASE');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logEmailAttempt($to, $subject, 'DATABASE_ERROR: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * SMTP command helper
     */
    private function smtpCommand($socket, $command, $expectedCode) {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket, $expectedCode);
    }
    
    /**
     * SMTP response reader
     */
    private function smtpRead($socket, $expectedCode) {
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
     * Log email attempts
     */
    private function logEmailAttempt($to, $subject, $status) {
        if (!isset($this->config['logging']['enabled']) || !$this->config['logging']['enabled']) {
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
    }
    
    /**
     * Get user emails (for database storage method)
     */
    public function getUserEmails($email) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_emails 
                WHERE email = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$email]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Mark email as read
     */
    public function markAsRead($email_id) {
        try {
            $stmt = $this->pdo->prepare("UPDATE user_emails SET is_read = 1 WHERE id = ?");
            return $stmt->execute([$email_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Initialize the email service
$emailService = new EmailService();
?>
