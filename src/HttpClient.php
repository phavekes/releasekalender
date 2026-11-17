<?php

declare(strict_types=1);

/**
 * HTTP client abstraction for consistent HTTP requests with error handling.
 */
class HttpClient
{
    private int $timeout;
    private array $defaultHeaders;

    public function __construct(int $timeout = 10, array $defaultHeaders = [])
    {
        $this->timeout = $timeout;
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * Make a GET request.
     *
     * @param string $url The URL to request
     * @param array $headers Additional headers
     * @param bool $followRedirects Whether to follow redirects
     * @return string|null The response body, or null on failure
     */
    public function get(string $url, array $headers = [], bool $followRedirects = true): ?string
    {
        return $this->request('GET', $url, null, $headers, $followRedirects);
    }

    /**
     * Make a POST request.
     *
     * @param string $url The URL to request
     * @param array|string|null $body The request body
     * @param array $headers Additional headers
     * @param bool $followRedirects Whether to follow redirects
     * @return string|null The response body, or null on failure
     */
    public function post(string $url, array|string|null $body, array $headers = [], bool $followRedirects = true): ?string
    {
        return $this->request('POST', $url, $body, $headers, $followRedirects);
    }

    /**
     * Make an HTTP request.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url The URL to request
     * @param array|string|null $body The request body
     * @param array $headers Additional headers
     * @param bool $followRedirects Whether to follow redirects
     * @return string|null The response body, or null on failure
     */
    public function request(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = [],
        bool $followRedirects = true
    ): ?string {
        $ch = curl_init($url);
        if ($ch === false) {
            error_log("HttpClient: Failed to initialize cURL for URL: $url");
            return null;
        }

        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $curlHeaders = [];
        foreach ($allHeaders as $key => $value) {
            $curlHeaders[] = is_int($key) ? $value : "$key: $value";
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_HTTPHEADER => $curlHeaders,
        ];

        if ($body !== null) {
            if (is_array($body)) {
                $options[CURLOPT_POSTFIELDS] = http_build_query($body);
            } else {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            error_log("HttpClient: cURL error for $url: $error");
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($status < 200 || $status >= 300) {
            error_log("HttpClient: HTTP $status for $url");
            return null;
        }

        return $response;
    }

    /**
     * Get the HTTP status code from the last request.
     * Note: This requires the response to be captured.
     *
     * @param string $url The URL that was requested
     * @param array $headers Additional headers
     * @param bool $followRedirects Whether to follow redirects
     * @return int HTTP status code, or -1 on failure
     */
    public function getStatusCode(string $url, array $headers = [], bool $followRedirects = true): int
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return -1;
        }

        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $curlHeaders = [];
        foreach ($allHeaders as $key => $value) {
            $curlHeaders[] = is_int($key) ? $value : "$key: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return $status ?: -1;
    }
}
