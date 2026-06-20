<?php

namespace App\Services\WooCommerce;

class Client
{
    private string $baseUrl;
    private string $key;
    private string $secret;
    private ?string $hostHeader;

    public function __construct(string $baseUrl, string $key, string $secret)
    {
        $baseUrl = rtrim($baseUrl, '/');

        // Optional override for tunneling / local proxy
        $override = env('WOO_API_BASE_URL');
        if (is_string($override) && $override !== '') {
            $this->baseUrl = rtrim($override, '/');
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $this->hostHeader = $host ? ('Host: '.$host) : null;
        } else {
            $this->baseUrl = $baseUrl;
            $this->hostHeader = null;
        }

        $this->key = $key;
        $this->secret = $secret;
    }

    public function get(string $endpoint, array $query = [])
    {
        $url = $this->buildSignedUrl($endpoint, $query);
        return $this->execWithRetries('GET', $url, null, 20, 5);
    }

    public function post(string $endpoint, array $data = [])
    {
        $url = $this->buildSignedUrl($endpoint, []);
        return $this->execWithRetries('POST', $url, $data, 20, 5);
    }

    public function put(string $endpoint, array $data = [])
    {
        $url = $this->buildSignedUrl($endpoint, []);
        return $this->execWithRetries('PUT', $url, $data, 20, 5);
    }

    public function delete(string $endpoint, array $query = [])
    {
        $url = $this->buildSignedUrl($endpoint, $query);
        return $this->execWithRetries('DELETE', $url, null, 20, 5);
    }

    // Compatibility (your service calls these)
    public function getNoRetry(string $endpoint, array $query = [], int $timeoutSeconds = 20, int $connectTimeoutSeconds = 5)
    {
        $url = $this->buildSignedUrl($endpoint, $query);
        return $this->execWithRetries('GET', $url, null, $timeoutSeconds, $connectTimeoutSeconds);
    }

    public function postNoRetry(string $endpoint, array $data = [], int $timeoutSeconds = 20, int $connectTimeoutSeconds = 5)
    {
        $url = $this->buildSignedUrl($endpoint, []);
        return $this->execWithRetries('POST', $url, $data, $timeoutSeconds, $connectTimeoutSeconds);
    }

    public function putNoRetry(string $endpoint, array $data = [], int $timeoutSeconds = 20, int $connectTimeoutSeconds = 5)
    {
        $url = $this->buildSignedUrl($endpoint, []);
        return $this->execWithRetries('PUT', $url, $data, $timeoutSeconds, $connectTimeoutSeconds);
    }

    public function deleteNoRetry(string $endpoint, array $query = [], int $timeoutSeconds = 20, int $connectTimeoutSeconds = 5)
    {
        $url = $this->buildSignedUrl($endpoint, $query);
        return $this->execWithRetries('DELETE', $url, null, $timeoutSeconds, $connectTimeoutSeconds);
    }

    private function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return $this->baseUrl.'/wp-json/wc/v3/'.$endpoint;
    }

    private function withAuthQuery(array $query = []): array
    {
        $query['consumer_key'] = $this->key;
        $query['consumer_secret'] = $this->secret;
        return $query;
    }

    private function buildSignedUrl(string $endpoint, array $query): string
    {
        $url = $this->buildUrl($endpoint);
        $query = $this->withAuthQuery($query);

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }
        return $url;
    }

    /**
     * Windows-safe, NEVER hangs:
     * - CURLOPT_TIMEOUT_MS + CONNECTTIMEOUT_MS
     * - curl_multi loop + hard wall-clock deadline
     */
    private function execNoHang(string $method, string $url, ?array $data, int $timeoutSeconds, int $connectTimeoutSeconds)
    {
        $timeoutSeconds = max(1, (int) $timeoutSeconds);
        $connectTimeoutSeconds = max(1, (int) $connectTimeoutSeconds);

        $timeoutMs = $timeoutSeconds * 1000;
        $connectTimeoutMs = $connectTimeoutSeconds * 1000;
        $deadlineMs = $timeoutMs; // hard wall clock

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($this->hostHeader) {
            $headers[] = $this->hostHeader;
        }

        $payload = null;
        if ($data !== null) {
            $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                throw new \RuntimeException('JSON encode failed: '.json_last_error_msg());
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            // Capture headers so callers can read X-WP-Total / X-WP-TotalPages, etc.
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,

            // timeouts
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,

            // prevent “forever” stalls
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => min(10, $timeoutSeconds),
            CURLOPT_NOSIGNAL => 1,

            // reduce weird keep-alive issues
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $start = microtime(true);
        $running = null;

        do {
            $mrc = curl_multi_exec($mh, $running);
            if ($mrc > CURLM_OK) {
                break;
            }

            $elapsedMs = (microtime(true) - $start) * 1000;
            if ($elapsedMs >= $deadlineMs) {
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                curl_multi_close($mh);
                throw new \RuntimeException('Hard deadline exceeded');
            }

            $rc = curl_multi_select($mh, 0.2);
            if ($rc === -1) {
                usleep(100000);
            }
        } while ($running > 0);

        $raw = (string) curl_multi_getcontent($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $errno = (int) curl_errno($ch);
        $error = (string) curl_error($ch);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        curl_multi_close($mh);

        $headerStr = $headerSize > 0 ? substr($raw, 0, $headerSize) : '';
        $body = $headerSize > 0 ? substr($raw, $headerSize) : $raw;

        $headersAssoc = [];
        if ($headerStr !== '') {
            // Handle multiple header blocks (rare); keep the last block by splitting.
            $blocks = preg_split("/\r\n\r\n/", trim($headerStr));
            $lastBlock = is_array($blocks) && count($blocks) > 0 ? $blocks[count($blocks) - 1] : $headerStr;
            $lines = preg_split("/\r\n/", (string) $lastBlock);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || stripos($line, 'HTTP/') === 0) {
                        continue;
                    }
                    $pos = strpos($line, ':');
                    if ($pos === false) {
                        continue;
                    }
                    $k = strtolower(trim(substr($line, 0, $pos)));
                    $v = trim(substr($line, $pos + 1));
                    if ($k === '') continue;
                    if (!isset($headersAssoc[$k])) {
                        $headersAssoc[$k] = [];
                    }
                    $headersAssoc[$k][] = $v;
                }
            }
        }

        return new class($status, $body, $headersAssoc, $errno, $error, $durationMs) {
            private int $status;
            private string $body;
            private array $headers;
            private int $errno;
            private string $error;
            private int $durationMs;

            public function __construct(int $status, string $body, array $headers, int $errno, string $error, int $durationMs)
            {
                $this->status = $status;
                $this->body = $body;
                $this->headers = $headers;
                $this->errno = $errno;
                $this->error = $error;
                $this->durationMs = $durationMs;
            }

            public function successful(): bool { return $this->status >= 200 && $this->status < 300; }
            public function status(): int { return $this->status; }
            public function body(): string { return $this->body; }
            public function json() { return json_decode($this->body, true); }
            public function header(string $name): ?string
            {
                $k = strtolower(trim($name));
                if ($k === '' || !isset($this->headers[$k]) || !is_array($this->headers[$k]) || count($this->headers[$k]) === 0) {
                    return null;
                }
                return (string) $this->headers[$k][0];
            }
            public function headers(): array { return $this->headers; }
            public function error(): ?string { return $this->error !== '' ? $this->error : null; }
            public function errno(): int { return $this->errno; }
            public function durationMs(): int { return $this->durationMs; }
        };
    }

    private function execWithRetries(string $method, string $url, ?array $data, int $timeoutSeconds, int $connectTimeoutSeconds)
    {
        $maxRetries = (int) env('WOO_HTTP_RETRIES', 2);
        $maxRetries = max(0, min(10, $maxRetries));
        $baseSleepMs = (int) env('WOO_HTTP_RETRY_BASE_MS', 300);
        $baseSleepMs = max(0, min(10000, $baseSleepMs));

        $attempt = 0;
        $last = null;

        while (true) {
            $attempt++;

            $last = $this->execNoHang($method, $url, $data, $timeoutSeconds, $connectTimeoutSeconds);

            // success => return
            if ($last->successful()) {
                return $last;
            }

            // no retry left
            if ($attempt > (1 + $maxRetries)) {
                return $last;
            }

            $status = (int) $last->status();
            $errno = (int) $last->errno();

            $shouldRetry = false;

            // Transport-level failure (timeout/DNS/etc)
            if ($errno !== 0) {
                $shouldRetry = true;
            }

            // HTTP transient failures (rate limit / maintenance / gateway timeout)
            if (!$shouldRetry && in_array($status, [408, 429, 500, 502, 503, 504], true)) {
                // Be conservative with POST retries: only retry likely-transient statuses
                if (strtoupper($method) === 'POST') {
                    $shouldRetry = in_array($status, [408, 429, 502, 503, 504], true);
                } else {
                    $shouldRetry = true;
                }
            }

            if (!$shouldRetry) {
                return $last;
            }

            // Exponential backoff with small jitter
            $sleepMs = (int) round($baseSleepMs * (2 ** max(0, $attempt - 2)));
            $sleepMs = min(3000, max(0, $sleepMs));
            $sleepMs += random_int(0, 125);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }
    }
}
