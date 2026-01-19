<?php

declare(strict_types=1);

namespace UniProtPHP\Http;

/**
 * HTTP Client Interface
 * 
 * Defines the contract for HTTP communication with UniProt API
 */
interface HttpClientInterface
{
    /**
     * Execute a GET request
     * 
     * @param string $url The full URL to request
     * @param array<string, string> $headers Optional HTTP headers
     * @return array<string, mixed> Array with 'status' and 'body' keys
     */
    public function get(string $url, array $headers = []): array;

    /**
     * Execute a POST request
     * 
     * @param string $url The full URL to request
     * @param array<string, string|array> $data POST data/form fields
     * @param array<string, string> $headers Optional HTTP headers
     * @return array<string, mixed> Array with 'status', 'body', and 'headers' keys
     */
    public function post(string $url, array $data = [], array $headers = []): array;

    /**
     * Check if the client is available (can make HTTP requests)
     * 
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the name of the transport mechanism
     * 
     * @return string
     */
    public function getTransportName(): string;
}
