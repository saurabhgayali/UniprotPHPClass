<?php

declare(strict_types=1);

namespace UniProtPHP\Http;

use UniProtPHP\Exception\UniProtException;

/**
 * HTTP Client Factory
 * 
 * Creates the best available HTTP client automatically.
 * Tries cURL first, falls back to streams if cURL is unavailable.
 */
class HttpClientFactory
{
    private static ?HttpClientInterface $instance = null;

    /**
     * Get an HTTP client instance
     * 
     * Automatically selects the best available transport.
     * 
     * @param bool $forceCurl Force using cURL even if suboptimal
     * @return HttpClientInterface
     * @throws UniProtException If no HTTP client is available
     */
    public static function create(bool $forceCurl = false): HttpClientInterface
    {
        if ($forceCurl) {
            $curlClient = new CurlClient();
            if (!$curlClient->isAvailable()) {
                throw new UniProtException(
                    'cURL is not available. Enable the cURL extension or use stream-based client.'
                );
            }
            return $curlClient;
        }

        // Try cURL first (better performance)
        $curlClient = new CurlClient();
        if ($curlClient->isAvailable()) {
            return $curlClient;
        }

        // Fall back to streams
        $streamClient = new StreamClient();
        if ($streamClient->isAvailable()) {
            return $streamClient;
        }

        throw new UniProtException(
            'No HTTP transport available. Install cURL or enable stream context support.'
        );
    }

    /**
     * Get the singleton instance
     * 
     * @return HttpClientInterface
     * @throws UniProtException
     */
    public static function getInstance(): HttpClientInterface
    {
        if (self::$instance === null) {
            self::$instance = self::create();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get information about available transports
     * 
     * @return array<string, bool>
     */
    public static function getAvailableTransports(): array
    {
        return [
            'cURL' => (new CurlClient())->isAvailable(),
            'PHP Streams' => (new StreamClient())->isAvailable(),
        ];
    }
}
