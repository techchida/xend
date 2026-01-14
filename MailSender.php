<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailSender {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $useTLS;
    private $fromEmail;
    private $fromName;

    public function __construct($host, $port, $username, $password, $useTls, $fromEmail, $fromName = '') {
        $this->smtpHost = $host;
        $this->smtpPort = $port;
        $this->smtpUsername = $username;
        $this->smtpPassword = $password;
        $this->useTLS = $useTls;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName ?: $fromEmail;
    }

    public function send($toEmail, $toName, $subject, $body, $replyTo = null) {
        try {
            $mail = new PHPMailer(true);

            // SMTP settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->Port = $this->smtpPort;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;

            // TLS/SSL settings
            if ($this->useTLS) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            // Connection timeout
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = true;

            // Enable debug mode for troubleshooting (log to file)
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log('[PHPMailer Debug] ' . $str);
            };

            // From
            $mail->setFrom($this->fromEmail, $this->fromName);

            // To
            $mail->addAddress($toEmail, $toName);

            // Reply-To
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }

            // Subject and body
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $body;

            // Send
            if ($mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to send email'
            ];

        } catch (Exception $e) {
            error_log('Mail Send Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Alternative: Use PHP's mail function with SMTP configured
    public static function sendSimple($toEmail, $toName, $subject, $body, $fromEmail, $fromName, $replyTo = null) {
        $headers = "From: " . (!empty($fromName) ? "\"$fromName\" <$fromEmail>" : $fromEmail) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";

        if ($replyTo) {
            $headers .= "Reply-To: $replyTo\r\n";
        }

        $to = !empty($toName) ? "\"$toName\" <$toEmail>" : $toEmail;

        if (mail($to, $subject, $body, $headers)) {
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to send email'
            ];
        }
    }
}
?>
