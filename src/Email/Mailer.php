<?php
declare(strict_types=1);

namespace CertificateGenerator\Email;

/**
 * wp_mail wrapper with logging and error handling.
 */
class Mailer {

    public function send(string $to, string $subject, string $message, array $headers = [], array $attachments = []): bool {
        $result = wp_mail($to, $subject, $message, $headers, $attachments);

        if (!$result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[CG Mailer] Failed to send email to %s: %s',
                $to,
                $subject
            ));
        }

        return $result;
    }

    public function send_html(string $to, string $subject, string $html_body, array $attachments = []): bool {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return $this->send($to, $subject, $html_body, $headers, $attachments);
    }
}
