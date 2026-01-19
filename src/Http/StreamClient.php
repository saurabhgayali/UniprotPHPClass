<?php

declare(strict_types=1);

namespace UniProtPHP\Http;

use UniProtPHP\Exception\UniProtException;

/**
 * Stream-based HTTP Client
 * 
 * Uses PHP's stream context for HTTP communication.
 * Works on shared hosting without cURL extension.
 * More portable but potentially slower than cURL.
 */
class StreamClient implements HttpClientInterface
{
    private bool $available = false;

    public function __construct()
    {
        // Check if stream_context_create is available
        $this->available = function_exists('stream_context_create') 
            && function_exists('file_get_contents');
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getTransportName(): string
    {
        return 'PHP Streams';
    }

    /**
     * Execute a GET request using PHP streams
     * 
     * @param string $url
     * @param array<string, string> $headers
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function get(string $url, array $headers = []): array
    {
        if (!$this->isAvailable()) {
            throw new UniProtException('Stream context creation is not available');
        }

        $context = $this->createStreamContext('GET', $headers);

        try {
            $response = @file_get_contents($url, false, $context);
            $metadata = stream_get_meta_data($http_response_header ?? []);

            if ($response === false) {
                $error = error_get_last();
                $message = $error ? $error['message'] : 'Unknown stream error';
                throw new UniProtException("Stream error: {$message}");
            }

            $statusCode = $this->extractStatusCode($http_response_header ?? []);
            $responseHeaders = $this->extractHeaders($http_response_header ?? []);

            return [
                'status' => $statusCode,
                'body' => $response,
                'headers' => $responseHeaders,
            ];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Stream request failed: {$e->getMessage()}");
        }
    }

    /**
     * Execute a POST request using PHP streams
     * 
     * @param string $url
     * @param array<string, string|array> $data
     * @param array<string, string> $headers
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        if (!$this->isAvailable()) {
            throw new UniProtException('Stream context creation is not available');
        }

        $postData = $this->encodePostData($data);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $headers['Content-Length'] = (string)strlen($postData);

        $context = $this->createStreamContext('POST', $headers, $postData);

        try {
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                $message = $error ? $error['message'] : 'Unknown stream error';
                throw new UniProtException("Stream error: {$message}");
            }

            $statusCode = $this->extractStatusCode($http_response_header ?? []);
            $responseHeaders = $this->parseResponseHeaders($http_response_header ?? []);

            return [
                'status' => $statusCode,
                'body' => $response,
                'headers' => $responseHeaders,
            ];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Stream request failed: {$e->getMessage()}");
        }
    }

    /**
     * Create a stream context for HTTP requests
     * 
     * @param string $method HTTP method
     * @param array<string, string> $headers
     * @param string|null $postData
     * @return resource
     */
    private function createStreamContext(
        string $method,
        array $headers = [],
        ?string $postData = null
    ) {
        $options = [
            'http' => [
                'method' => $method,
                'timeout' => 30,
                'ignore_errors' => true,
                'user_agent' => 'UniProtPHP/1.0 (PHP Client Library)',
            ],
        ];

        if (!empty($headers)) {
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "{$key}: {$value}\r\n";
            }
            $options['http']['header'] = $headerString;
        }

        if ($postData !== null) {
            $options['http']['content'] = $postData;
        }

        // SSL verification
        $options['ssl'] = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];

        return stream_context_create($options);
    }

    /**
     * Encode POST data for application/x-www-form-urlencoded
     * 
     * @param array<string, string|array> $data
     * @return string
     */
    private function encodePostData(array $data): string
    {
        $encoded = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            }
            $encoded[] = urlencode((string)$key) . '=' . urlencode((string)$value);
        }
        return implode('&', $encoded);
    }

    /**
     * Extract HTTP status code from response headers
     * 
     * @param array<string> $headers
     * @return int
     */
    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $parts = explode(' ', $header);
                if (count($parts) >= 2) {
                    return (int)$parts[1];
                }
            }
        }
        return 200;
    }

    /**
     * Extract response headers from header array
     * 
     * @param array<string> $headers
     * @return array<string, string>
     */
    private function extractHeaders(array $headers): array
    {
        $responseHeaders = [];
        foreach ($headers as $header) {
            // Skip status line and empty headers
            if (strpos($header, 'HTTP/') === 0 || empty(trim($header))) {
                continue;
            }
            
            // Parse header line
            if (strpos($header, ':') !== false) {
                [$name, $value] = explode(':', $header, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }
        return $responseHeaders;
    }

    /**
     * Parse response headers
     * 
     * @param array<string> $headers
     * @return array<string, string>
     */
    private function parseResponseHeaders(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            if (strpos($header, ':') !== false && strpos($header, 'HTTP/') !== 0) {
                [$name, $value] = explode(':', $header, 2);
                $parsed[strtolower(trim($name))] = trim($value);
            }
        }
        return $parsed;
    }
}
