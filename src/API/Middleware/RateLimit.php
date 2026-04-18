<?php
declare(strict_types=1);

namespace CertificateGenerator\API\Middleware;

class RateLimit {

    private int $max_requests;
    private int $window_seconds;

    public function __construct(int $max_requests = 60, int $window_seconds = 3600) {
        $this->max_requests = $max_requests;
        $this->window_seconds = $window_seconds;
    }

    public function check(string $identifier): bool {
        $key = "cg_rate_limit:{$identifier}";
        $count = (int) get_transient($key);

        if ($count >= $this->max_requests) {
            return false;
        }

        set_transient($key, $count + 1, $this->window_seconds);
        return true;
    }

    public function get_ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
