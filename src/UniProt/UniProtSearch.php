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
     * @return array<string, mixed> First page response with headers
     * @throws UniProtException
     */
    public function getFirstPage(string $query, array $options = []): array
    {
        $url = $this->buildUrl($query, $options);
        
        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $format = $options['format'] ?? 'json';
            $data = $format === 'json' ? $this->parseJsonResponse($response['body']) : ['body' => $response['body'], 'format' => $format];
            
            // Include headers (especially Link for pagination)
            $data['headers'] = $response['headers'] ?? [];
            
            return $data;
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
     * Get total count of search results efficiently
     * 
     * Makes a single API call with size=1 to get total result count
     * using the x-total-results header without fetching all entries.
     * 
     * @param string $query UniProt query string
     * @param array<string, mixed> $options Search options
     * @return int Total number of results
     * @throws UniProtException
     */
    public function getTotalCount(string $query, array $options = []): int
    {
        if (empty($query)) {
            throw new UniProtException('Query cannot be empty');
        }

        // Make single API call with size=1 to get total count from header
        $url = $this->buildUrl($query, array_merge($options, ['size' => 1]));
        
        $response = $this->httpClient->get($url);
        
        // Extract total count from x-total-results header
        if (isset($response['headers']) && is_array($response['headers'])) {
            // Headers might be case-insensitive, check various cases
            foreach ($response['headers'] as $name => $value) {
                if (strtolower($name) === 'x-total-results') {
                    return (int)$value;
                }
            }
        }
        
        // Fallback: count results in response if header not available
        $data = $this->parseJsonResponse($response['body']);
        return isset($data['results']) && is_array($data['results']) 
            ? count($data['results']) 
            : 0;
    }


    /**
     * Get paginated results with manual offset control
     * 
     * **Optimization for common use case:** Pagination is optimized for the first 500 results (first 50 pages with size=10).
     * These pages require only 1 API call and work instantly.
     * 
     * Pages beyond 500 (offset >=500) require iterating through cursor pagination to reach that batch,
     * which means multiple API calls. For these cases, prefer using automatic pagination with search()
     * and stopping when you have enough results.
     * 
     * Strategy for first batch (offset < 500): 
     * 1. Get total count with size=1 (one API call)
     * 2. Fetch first 500 results (one API call)
     * 3. Extract the page slice from that batch
     * 
     * Strategy for later batches (offset >= 500):
     * 1. Get total count (one API call)
     * 2. Iterate through cursor links until reaching target batch
     * 3. Extract the page slice
     * 
     * @param string $query UniProt query string
     * @param int $offset Number of results to skip (0-based)
     * @param int $pageSize Results per page (10, 20, or 50)
     * @param array<string, mixed> $options Search options
     * @return array<string, mixed> Paginated results with metadata
     * @throws UniProtException
     */
    public function getPaginatedResults(
        string $query,
        int $offset = 0,
        int $pageSize = 10,
        array $options = []
    ): array {
        if (empty($query)) {
            throw new UniProtException('Query cannot be empty');
        }

        // Validate page size
        $validSizes = [10, 20, 50];
        if (!in_array($pageSize, $validSizes, true)) {
            $pageSize = 10;
        }

        if ($offset < 0) {
            $offset = 0;
        }

        // Get total count with one efficient API call
        $totalResults = $this->getTotalCount($query, $options);
        
        if ($totalResults === 0) {
            return [
                'results' => [],
                'offset' => 0,
                'pageSize' => $pageSize,
                'currentPage' => 1,
                'totalPages' => 0,
                'totalResults' => 0,
                'previousOffset' => null,
                'nextOffset' => null,
                'pageLinks' => [],
                'hasNextPage' => false,
                'hasPreviousPage' => false,
            ];
        }

        $totalPages = (int)ceil($totalResults / $pageSize);
        $currentPage = (int)floor($offset / $pageSize) + 1;

        // If offset exceeds total results, return empty page
        if ($offset >= $totalResults) {
            $pageLinks = [];
            for ($i = 1; $i <= $totalPages; $i++) {
                $pageOffset = ($i - 1) * $pageSize;
                $pageLinks[$i] = $pageOffset;
            }

            return [
                'results' => [],
                'offset' => $offset,
                'pageSize' => $pageSize,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalResults' => $totalResults,
                'previousOffset' => $offset >= $pageSize ? $offset - $pageSize : null,
                'nextOffset' => null,
                'pageLinks' => $pageLinks,
                'hasNextPage' => false,
                'hasPreviousPage' => true,
            ];
        }

        // Fetch results using cursor-based pagination
        // Optimization: use size=500 to skip batches quickly, then extract user's pageSize
        $pageResults = [];
        
        try {
            // Use batch size of 500 for efficient cursor navigation
            $batchSize = 500;
            $completeBatches = (int)floor($offset / $batchSize);
            $offsetInBatch = $offset % $batchSize;
            
            // Build URL with batchSize=500 for efficient API navigation
            $url = $this->buildUrl($query, array_merge($options, ['size' => $batchSize]));
            
            // Skip complete batches via cursor navigation
            for ($i = 0; $i < $completeBatches; $i++) {
                $response = $this->httpClient->get($url);
                $this->validateResponse($response);
                
                // Get next URL from Link header
                $headers = $response['headers'] ?? [];
                $nextUrl = null;
                if (isset($headers['link']) && preg_match('/<([^>]+)>;\s*rel="next"/', $headers['link'], $matches)) {
                    $nextUrl = $matches[1];
                }
                
                if ($nextUrl === null) break;
                $url = $nextUrl;
            }
            
            // Gap of 0.5 seconds between API fetches
            usleep(500000);
            
            // Fetch the target batch and extract results
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);
            $data = $this->parseJsonResponse($response['body']);
            $results = $data['results'] ?? [];
            
            // Extract pageSize results starting at offsetInBatch
            $position = 0;
            foreach ($results as $entry) {
                if ($position >= $offsetInBatch && count($pageResults) < $pageSize) {
                    $pageResults[] = $entry;
                }
                $position++;
                if (count($pageResults) >= $pageSize) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            // Return empty results on error
        }

        // Determine previous and next page offsets
        $previousOffset = $offset >= $pageSize ? $offset - $pageSize : null;
        $nextOffset = ($offset + $pageSize) < $totalResults ? $offset + $pageSize : null;

        // Build page navigation links (store offsets for each page)
        $pageLinks = [];
        for ($i = 1; $i <= $totalPages; $i++) {
            $pageOffset = ($i - 1) * $pageSize;
            $pageLinks[$i] = $pageOffset;
        }

        return [
            'results' => $pageResults,
            'offset' => $offset,
            'pageSize' => $pageSize,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalResults' => $totalResults,
            'previousOffset' => $previousOffset,
            'nextOffset' => $nextOffset,
            'pageLinks' => $pageLinks,
            'hasNextPage' => $nextOffset !== null,
            'hasPreviousPage' => $previousOffset !== null,
        ];
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

        // Extract next link from Link header
        $headers = $response['headers'] ?? [];
        $this->nextUrl = $this->extractNextLink($headers);
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
    public function extractNextLink(array $headers): ?string
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
