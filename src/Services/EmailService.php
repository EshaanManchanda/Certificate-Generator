<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Exception\EmailSendingException;

/**
 * Email service for certificate delivery.
 */
class EmailService {

    private string $from_name;
    private string $from_email;
    private string $subject_template;
    private string $body_template;

    public function __construct() {
        $this->from_name = get_option('cg_email_from_name', get_bloginfo('name'));
        $this->from_email = get_option('cg_email_from_email', get_bloginfo('admin_email'));
        $this->subject_template = get_option('cg_email_subject', 'Your Certificate: {certificate_title}');
        $this->body_template = get_option('cg_email_body', '');
    }

    public function send_certificate(string $to, string $name, array $cert_data, array $attachments = []): bool {
        if (!is_email($to)) {
            throw new EmailSendingException("Invalid email address: {$to}");
        }

        $subject = $this->replace_placeholders($this->subject_template, $name, $cert_data);
        $body = $this->replace_placeholders($this->body_template, $name, $cert_data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
        ];

        $result = wp_mail($to, $subject, $body, $headers, $attachments);

        if (!$result) {
            throw new EmailSendingException("Failed to send certificate email to {$to}");
        }

        return true;
    }

    public function send_bulk(array $recipients, array $cert_data, callable $progress_callback = null): array {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
        $total = count($recipients);

        foreach ($recipients as $i => $recipient) {
            try {
                $this->send_certificate(
                    $recipient['email'],
                    $recipient['name'] ?? $recipient['email'],
                    $cert_data,
                    $recipient['attachments'] ?? []
                );
                $results['sent']++;
            } catch (EmailSendingException $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $recipient['email'],
                    'message' => $e->getMessage(),
                ];
            }

            if ($progress_callback && ($i + 1) % 10 === 0) {
                call_user_func($progress_callback, $i + 1, $total, $results);
            }
        }

        return $results;
    }

    private function replace_placeholders(string $template, string $name, array $data): string {
        $placeholders = [
            '{name}' => $name,
            '{certificate_title}' => $data['certificate_title'] ?? '',
            '{serial_number}' => $data['serial_number'] ?? 'N/A',
            '{expires_at}' => $data['expires_at'] ?? 'Never',
            '{issue_date}' => $data['issue_date'] ?? current_time('mysql'),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
}
