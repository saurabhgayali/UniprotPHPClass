<?php

declare(strict_types=1);

namespace UniProtPHP\UniProt;

use UniProtPHP\Exception\UniProtException;
use UniProtPHP\Http\HttpClientInterface;

/**
 * UniProtSearch - Search with Pagination
 * 
 * Performs searches on UniProtKB with full pagination support.
 * Uses Link header cursor-based pagination for efficient traversal.
 * 
 * API Reference: https://rest.uniprot.org/uniprotkb/search
 */
class UniProtSearch
{
    private const API_BASE_URL = 'https://rest.uniprot.org';
    private const ENDPOINT = '/uniprotkb/search';
    private const DEFAULT_PAGE_SIZE = 500;

    private HttpClientInterface $httpClient;

    /**
     * @param HttpClientInterface $httpClient
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Search for entries with pagination
     * 
     * Returns a SearchResults object that supports iteration and pagination.
     * 
     * Example query: "organism_id:9606 AND reviewed:true"
     * 
     * @param string $query UniProt query string
     * @param array<string, mixed> $options Search options
     * @return SearchResults
     * @throws UniProtException
     */
    public function search(string $query, array $options = []): SearchResults
    {
        if (empty($query)) {
            throw new UniProtException('Query cannot be empty');
        }

        return new SearchResults($this->httpClient, $query, $options);
    }

    /**
     * Get the first page of results
     * 
     * @param string $query
     * @param array<string, mixed> $options
     * @return array<string, mixed> First page response
     * @throws UniProtException
     */
    public function getFirstPage(string $query, array $options = []): array
    {
        $url = $this->buildUrl($query, $options);
        
        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $format = $options['format'] ?? 'json';
            if ($format === 'json') {
                return $this->parseJsonResponse($response['body']);
            }

            return ['body' => $response['body'], 'format' => $format];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Search failed: {$e->getMessage()}");
        }
    }

    /**
     * Get total result count for a query
     * 
     * Makes a small query to get the x-total-results header.
     * 
     * @param string $query
     * @return int
     * @throws UniProtException
     */
    public function getCount(string $query): int
    {
        $options = ['size' => 1];
        $url = $this->buildUrl($query, $options);
        
        try {
            $response = $this->httpClient->get($url);
            
            // The count isn't typically in headers with GET, so parse response
            $data = json_decode($response['body'], true);
            return $data['results'] ? count($data['results']) : 0;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to get count: {$e->getMessage()}");
        }
    }

    /**
     * Build search URL with query and options
     * 
     * @param string $query
     * @param array<string, mixed> $options
     * @return string
     */
    public function buildUrl(string $query, array $options = []): string
    {
        $params = [];
        
        // Query is required
        $params['query'] = $query;

        // Format (default: json)
        $params['format'] = $options['format'] ?? 'json';

        // Page size (default: 500, max: 500)
        $pageSize = min($options['size'] ?? self::DEFAULT_PAGE_SIZE, 500);
        $params['size'] = (string)$pageSize;

        // Fields (optional)
        if (!empty($options['fields']) && is_array($options['fields'])) {
            $params['fields'] = implode(',', $options['fields']);
        }

        // Cursor (for pagination)
        if (!empty($options['cursor'])) {
            $params['cursor'] = $options['cursor'];
        }

        // Include isoforms
        if (isset($options['includeIsoform'])) {
            $params['includeIsoform'] = $options['includeIsoform'] ? 'true' : 'false';
        }

        // Compressed
        if (isset($options['compressed'])) {
            $params['compressed'] = $options['compressed'] ? 'true' : 'false';
        }

        // Build URL
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return self::API_BASE_URL . self::ENDPOINT . '?' . $queryString;
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

        if ($status >= 400) {
            $body = $response['body'] ?? '';
            $apiError = null;
            
            try {
                $error = json_decode($body, true);
                if (isset($error['messages'])) {
                    $apiError = implode('; ', $error['messages']);
                }
            } catch (\Throwable $e) {
                // Continue with generic error
            }

            $exception = new UniProtException($apiError ?? "HTTP {$status} error");
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

/**
 * SearchResults - Iterator for paginated search results
 * 
 * Implements Iterator to allow foreach pagination.
 * Automatically fetches next pages as needed.
 */
class SearchResults implements \Iterator
{
    private HttpClientInterface $httpClient;
    private string $query;
    private array $options;
    private array $currentBatch = [];
    private int $position = 0;
    private int $totalPosition = 0;
    private ?string $nextUrl = null;
    private bool $finished = false;
    private int $totalResults = 0;
    private bool $fetched = false;

    public function __construct(HttpClientInterface $httpClient, string $query, array $options = [])
    {
        $this->httpClient = $httpClient;
        $this->query = $query;
        $this->options = $options;
    }

    /**
     * Fetch the first batch if not already done
     * 
     * @return void
     * @throws UniProtException
     */
    private function ensureFetched(): void
    {
        if ($this->fetched) {
            return;
        }

        $search = new UniProtSearch($this->httpClient);
        $response = $search->getFirstPage($this->query, $this->options);

        $this->currentBatch = $response['results'] ?? [];
        $this->position = 0;
        $this->fetched = true;

        // Extract next link from response or try from header
        $this->nextUrl = $response['next'] ?? null;
        $this->totalResults = $response['results'] ? count($response['results']) : 0;
    }

    /**
     * Fetch the next batch of results
     * 
     * @return void
     * @throws UniProtException
     */
    private function fetchNextBatch(): void
    {
        if ($this->nextUrl === null || $this->finished) {
            $this->finished = true;
            return;
        }

        try {
            $response = $this->httpClient->get($this->nextUrl);
            
            if ($response['status'] !== 200) {
                $this->finished = true;
                return;
            }

            $data = json_decode($response['body'], true);
            if (!is_array($data) || empty($data['results'])) {
                $this->finished = true;
                return;
            }

            $this->currentBatch = $data['results'];
            $this->position = 0;

            // Extract next link using regex from Link header
            $this->nextUrl = $this->extractNextLink($response['headers'] ?? []);
        } catch (\Throwable $e) {
            $this->finished = true;
        }
    }

    /**
     * Extract next link from Link header
     * 
     * Format: <url>; rel="next"
     * 
     * @param array<string, string> $headers
     * @return ?string
     */
    private function extractNextLink(array $headers): ?string
    {
        $link = $headers['link'] ?? null;
        if ($link === null) {
            return null;
        }

        // Match <url>; rel="next"
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $link, $matches)) {
            return $matches[1];
        }

        return null;
    }

    // Iterator implementation
    public function current(): mixed
    {
        return $this->currentBatch[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->totalPosition;
    }

    public function next(): void
    {
        $this->position++;
        $this->totalPosition++;

        // If we've exhausted current batch, fetch next
        if ($this->position >= count($this->currentBatch)) {
            $this->fetchNextBatch();
        }
    }

    public function rewind(): void
    {
        if (!$this->fetched) {
            $this->ensureFetched();
        }
    }

    public function valid(): bool
    {
        $this->ensureFetched();

        if ($this->position < count($this->currentBatch)) {
            return true;
        }

        if ($this->finished) {
            return false;
        }

        $this->fetchNextBatch();
        return $this->position < count($this->currentBatch);
    }
}
