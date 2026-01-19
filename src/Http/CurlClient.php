<?php

declare(strict_types=1);

namespace UniProtPHP\Http;

use UniProtPHP\Exception\UniProtException;

/**
 * cURL-based HTTP Client
 * 
 * Uses PHP's cURL extension for HTTP communication.
 * Provides the best performance on shared hosting if available.
 */
class CurlClient implements HttpClientInterface
{
    private bool $available = false;
    private static bool $verifySsl = true;

    public function __construct()
    {
        $this->available = extension_loaded('curl') && function_exists('curl_init');
    }

    /**
     * Disable SSL verification (for development/testing only)
     * WARNING: This is insecure and should only be used in development environments.
     * 
     * @param bool $verify
     * @return void
     */
    public static function setVerifySSL(bool $verify): void
    {
        self::$verifySsl = $verify;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getTransportName(): string
    {
        return 'cURL';
    }

    /**
     * Execute a GET request using cURL
     * 
     * @param string $url
     * @param array<string, string> $headers
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function get(string $url, array $headers = []): array
    {
        if (!$this->isAvailable()) {
            throw new UniProtException('cURL extension is not loaded');
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new UniProtException('Failed to initialize cURL');
        }

        try {
            $this->configureCurl($curl, $url, $headers);
            
            $response = curl_exec($curl);
            $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            if ($response === false) {
                throw new UniProtException("cURL error: {$error}");
            }

            return [
                'status' => $statusCode,
                'body' => $response,
                'headers' => [],
            ];
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Execute a POST request using cURL
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
            throw new UniProtException('cURL extension is not loaded');
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new UniProtException('Failed to initialize cURL');
        }

        try {
            $this->configureCurl($curl, $url, $headers);
            
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            
            // Capture response headers
            $responseHeaders = [];
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                if (strpos($header, ':') !== false) {
                    [$name, $value] = explode(':', $header, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return $len;
            });

            $response = curl_exec($curl);
            $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            if ($response === false) {
                throw new UniProtException("cURL error: {$error}");
            }

            return [
                'status' => $statusCode,
                'body' => $response,
                'headers' => $responseHeaders,
            ];
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Configure common cURL options
     * 
     * @param \CurlHandle $curl
     * @param string $url
     * @param array<string, string> $headers
     * @return void
     */
    private function configureCurl(\CurlHandle $curl, string $url, array $headers): void
    {
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, self::$verifySsl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, self::$verifySsl ? 2 : 0);

        // Set User-Agent
        curl_setopt($curl, CURLOPT_USERAGENT, 'UniProtPHP/1.0 (PHP Client Library)');

        // Set headers if provided
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "{$key}: {$value}";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        }
    }
}
