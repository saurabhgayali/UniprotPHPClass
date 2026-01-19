<?php

declare(strict_types=1);

namespace UniProtPHP\UniProt;

use UniProtPHP\Exception\UniProtException;
use UniProtPHP\Http\HttpClientInterface;

/**
 * UniProtEntry - Single Entry Retrieval
 * 
 * Retrieves individual UniProtKB entries by accession number.
 * Supports various output formats (JSON, XML, FASTA, etc.)
 * 
 * API Reference: https://rest.uniprot.org/uniprotkb/{accession}
 */
class UniProtEntry
{
    private const API_BASE_URL = 'https://rest.uniprot.org';
    private const ENDPOINT = '/uniprotkb';

    private HttpClientInterface $httpClient;

    /**
     * @param HttpClientInterface $httpClient
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Retrieve a single UniProtKB entry by accession number
     * 
     * @param string $accession UniProtKB accession number (e.g., "P12345")
     * @param string $format Output format: json, xml, fasta, gff, txt
     * @return array<string, mixed> Parsed entry data (for JSON format)
     * @throws UniProtException
     */
    public function get(string $accession, string $format = 'json'): array
    {
        $accession = trim($accession);
        if (empty($accession)) {
            throw new UniProtException('Accession number cannot be empty');
        }

        $url = $this->buildUrl($accession, $format);
        
        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            if ($format === 'json') {
                return $this->parseJsonResponse($response['body']);
            }

            return ['body' => $response['body'], 'format' => $format];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to retrieve entry {$accession}: {$e->getMessage()}");
        }
    }

    /**
     * Retrieve multiple entries by accession numbers
     * 
     * @param array<string> $accessions List of accession numbers
     * @param string $format Output format
     * @return array<int, array<string, mixed>> List of entries
     * @throws UniProtException
     */
    public function getBatch(array $accessions, string $format = 'json'): array
    {
        if (empty($accessions)) {
            throw new UniProtException('Accession list cannot be empty');
        }

        $results = [];
        foreach ($accessions as $accession) {
            try {
                $results[] = $this->get($accession, $format);
            } catch (UniProtException $e) {
                // Log error but continue with next
                $results[] = ['error' => $e->getMessage(), 'accession' => $accession];
            }
        }

        return $results;
    }

    /**
     * Check if an entry exists
     * 
     * @param string $accession
     * @return bool
     */
    public function exists(string $accession): bool
    {
        try {
            $url = $this->buildUrl($accession, 'json');
            $response = $this->httpClient->get($url);
            return $response['status'] === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get entry in JSON format with specific fields
     * 
     * @param string $accession
     * @param array<string> $fields Specific fields to retrieve
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function getWithFields(string $accession, array $fields = []): array
    {
        if (empty($fields)) {
            return $this->get($accession, 'json');
        }

        $fieldStr = implode(',', array_map('urlencode', $fields));
        $url = $this->buildUrl($accession, 'json') . "?fields={$fieldStr}";
        
        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);
            return $this->parseJsonResponse($response['body']);
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to retrieve entry with fields: {$e->getMessage()}");
        }
    }

    /**
     * Build the API URL for entry retrieval
     * 
     * @param string $accession
     * @param string $format
     * @return string
     */
    private function buildUrl(string $accession, string $format): string
    {
        $accession = urlencode($accession);
        
        if ($format === 'json') {
            return self::API_BASE_URL . self::ENDPOINT . "/{$accession}";
        }

        return self::API_BASE_URL . self::ENDPOINT . "/{$accession}.{$format}";
    }

    /**
     * Validate HTTP response
     * 
     * @param array<string, mixed> $response
     * @return void
     * @throws UniProtException
     */
    private function validateResponse(array $response): void
    {
        $status = $response['status'] ?? 0;

        if ($status === 404) {
            $exception = new UniProtException('Entry not found (404)');
            $exception->setHttpStatus(404)->setApiResponse($response['body'] ?? '');
            throw $exception;
        }

        if ($status >= 400) {
            $body = $response['body'] ?? '';
            $exception = new UniProtException("HTTP {$status} error");
            $exception->setHttpStatus($status)->setApiResponse($body);
            throw $exception;
        }

        if ($status < 200 || $status >= 300) {
            throw new UniProtException("Unexpected HTTP status: {$status}");
        }
    }

    /**
     * Parse JSON response
     * 
     * @param string $json
     * @return array<string, mixed>
     * @throws UniProtException
     */
    private function parseJsonResponse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UniProtException('Failed to parse JSON response: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new UniProtException('Invalid JSON response: expected object');
        }

        return $decoded;
    }
}
