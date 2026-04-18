<?php
declare(strict_types=1);

namespace CertificateGenerator\Integrations;

/**
 * GEMA MERN backend API client.
 */
class GemaAPI {

    private string $base_url;
    private string $api_key;

    public function __construct(string $base_url, string $api_key) {
        $this->base_url = rtrim($base_url, '/');
        $this->api_key = $api_key;
    }

    public function get_students(array $params = []): array {
        return $this->request('GET', '/students', $params);
    }

    public function get_teachers(array $params = []): array {
        return $this->request('GET', '/teachers', $params);
    }

    public function get_schools(array $params = []): array {
        return $this->request('GET', '/schools', $params);
    }

    public function get_certificates(array $params = []): array {
        return $this->request('GET', '/certificates', $params);
    }

    public function sync_student(int $student_id, array $data): array {
        return $this->request('PUT', "/students/{$student_id}", $data);
    }

    public function sync_certificate(int $cert_id, array $data): array {
        return $this->request('PUT', "/certificates/{$cert_id}", $data);
    }

    private function request(string $method, string $path, array $data = []): array {
        $url = $this->base_url . $path;
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($data);
        } elseif (!empty($data)) {
            $url = add_query_arg($data, $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException("GEMA API request failed: " . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code >= 400) {
            throw new \RuntimeException("GEMA API error ({$code}): " . ($decoded['message'] ?? $body));
        }

        return $decoded ?: [];
    }
}
