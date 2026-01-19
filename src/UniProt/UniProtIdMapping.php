<?php

declare(strict_types=1);

namespace UniProtPHP\UniProt;

use UniProtPHP\Exception\UniProtException;
use UniProtPHP\Http\HttpClientInterface;

/**
 * UniProtIdMapping - ID Mapping Service
 * 
 * Maps identifiers between databases using UniProt's async job model.
 * Supports submit -> poll -> retrieve workflow.
 * 
 * API Reference: https://www.uniprot.org/help/id_mapping_prog
 */
class UniProtIdMapping
{
    private const API_BASE_URL = 'https://rest.uniprot.org';
    private const ENDPOINT_RUN = '/idmapping/run';
    private const ENDPOINT_STATUS = '/idmapping/status';
    private const ENDPOINT_RESULTS = '/idmapping/results';
    private const ENDPOINT_STREAM = '/idmapping/stream';
    private const ENDPOINT_DETAILS = '/idmapping/details';

    private const DEFAULT_POLL_INTERVAL = 3; // seconds
    private const DEFAULT_MAX_POLLS = 60; // max 3 minutes
    private const MAX_IDS_PER_JOB = 100000;

    private HttpClientInterface $httpClient;

    /**
     * @param HttpClientInterface $httpClient
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Submit an ID mapping job
     * 
     * @param string $fromDb Source database (e.g., "UniProtKB_AC-ID")
     * @param string $toDb Target database (e.g., "Ensembl")
     * @param array<string> $ids Identifiers to map (comma-separated or array)
     * @param ?int $taxId Optional taxonomy ID filter
     * @return string Job ID
     * @throws UniProtException
     */
    public function submit(string $fromDb, string $toDb, array $ids, ?int $taxId = null): string
    {
        if (empty($ids)) {
            throw new UniProtException('IDs list cannot be empty');
        }

        if (count($ids) > self::MAX_IDS_PER_JOB) {
            throw new UniProtException(
                "Maximum " . self::MAX_IDS_PER_JOB . " IDs per job exceeded. Got " . count($ids)
            );
        }

        $idString = implode(',', array_map('trim', $ids));

        $postData = [
            'from' => $fromDb,
            'to' => $toDb,
            'ids' => $idString,
        ];

        if ($taxId !== null) {
            $postData['taxId'] = (string)$taxId;
        }

        try {
            $response = $this->httpClient->post(
                self::API_BASE_URL . self::ENDPOINT_RUN,
                $postData
            );

            $this->validateResponse($response);
            $data = json_decode($response['body'], true);

            if (!isset($data['jobId'])) {
                throw new UniProtException('No jobId in response');
            }

            return $data['jobId'];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Job submission failed: {$e->getMessage()}");
        }
    }

    /**
     * Check job status
     * 
     * @param string $jobId
     * @return array<string, mixed> Status response
     * @throws UniProtException
     */
    public function status(string $jobId): array
    {
        if (empty($jobId)) {
            throw new UniProtException('Job ID cannot be empty');
        }

        $url = self::API_BASE_URL . self::ENDPOINT_STATUS . '/' . urlencode($jobId);

        try {
            $response = $this->httpClient->get($url);
            
            // 303 redirect is normal, parse response anyway
            if ($response['status'] === 303 || $response['status'] === 200) {
                $data = json_decode($response['body'], true);
                return $data ?? [];
            }

            throw new UniProtException("Unexpected status code: {$response['status']}");
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Status check failed: {$e->getMessage()}");
        }
    }

    /**
     * Wait for job completion with polling
     * 
     * Polls job status until completion or timeout.
     * 
     * @param string $jobId
     * @param int $pollInterval Seconds between polls
     * @param int $maxPolls Maximum number of polls
     * @return bool True if completed, false if timeout
     * @throws UniProtException
     */
    public function waitForCompletion(
        string $jobId,
        int $pollInterval = self::DEFAULT_POLL_INTERVAL,
        int $maxPolls = self::DEFAULT_MAX_POLLS
    ): bool {
        $polls = 0;

        while ($polls < $maxPolls) {
            $status = $this->status($jobId);

            if (isset($status['jobStatus'])) {
                if ($status['jobStatus'] === 'FINISHED') {
                    return true;
                }
                if ($status['jobStatus'] === 'ERROR' || $status['jobStatus'] === 'FAILED') {
                    throw new UniProtException(
                        "Job failed with status: {$status['jobStatus']}"
                    );
                }
                // RUNNING or NEW - continue polling
            }

            if (isset($status['results']) || isset($status['failedIds'])) {
                // Results are ready
                return true;
            }

            sleep($pollInterval);
            $polls++;
        }

        return false;
    }

    /**
     * Get job details
     * 
     * @param string $jobId
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function getDetails(string $jobId): array
    {
        if (empty($jobId)) {
            throw new UniProtException('Job ID cannot be empty');
        }

        $url = self::API_BASE_URL . self::ENDPOINT_DETAILS . '/' . urlencode($jobId);

        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $data = json_decode($response['body'], true);
            return $data ?? [];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to get job details: {$e->getMessage()}");
        }
    }

    /**
     * Get paginated results of a completed job
     * 
     * @param string $jobId
     * @param int $pageSize Results per page
     * @param int $pageNumber Page number (0-based)
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function getResults(
        string $jobId,
        int $pageSize = 25,
        int $pageNumber = 0
    ): array {
        if (empty($jobId)) {
            throw new UniProtException('Job ID cannot be empty');
        }

        $cursor = '';
        if ($pageNumber > 0) {
            $cursor = base64_encode("page_{$pageNumber}");
        }

        $url = self::API_BASE_URL . self::ENDPOINT_RESULTS . '/' . urlencode($jobId);
        $params = [
            'size' => (string)$pageSize,
        ];

        if (!empty($cursor)) {
            $params['cursor'] = $cursor;
        }

        $url .= '?' . http_build_query($params);

        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $data = json_decode($response['body'], true);
            return $data ?? [];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to get results: {$e->getMessage()}");
        }
    }

    /**
     * Get streamed results (all at once)
     * 
     * More demanding on API but useful for large result sets.
     * 
     * @param string $jobId
     * @param array<string, string> $options Query options (format, fields, etc.)
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function streamResults(string $jobId, array $options = []): array
    {
        if (empty($jobId)) {
            throw new UniProtException('Job ID cannot be empty');
        }

        $url = self::API_BASE_URL . self::ENDPOINT_STREAM . '/' . urlencode($jobId);

        $params = [];
        if (!empty($options['format'])) {
            $params['format'] = $options['format'];
        }
        if (!empty($options['fields'])) {
            $params['fields'] = is_array($options['fields']) 
                ? implode(',', $options['fields']) 
                : $options['fields'];
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        try {
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $data = json_decode($response['body'], true);
            return $data ?? [];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to stream results: {$e->getMessage()}");
        }
    }

    /**
     * Convenience method: submit and wait for completion
     * 
     * @param string $fromDb
     * @param string $toDb
     * @param array<string> $ids
     * @param ?int $taxId
     * @param int $pollInterval
     * @param int $maxPolls
     * @return string Job ID
     * @throws UniProtException
     */
    public function submitAndWait(
        string $fromDb,
        string $toDb,
        array $ids,
        ?int $taxId = null,
        int $pollInterval = self::DEFAULT_POLL_INTERVAL,
        int $maxPolls = self::DEFAULT_MAX_POLLS
    ): string {
        $jobId = $this->submit($fromDb, $toDb, $ids, $taxId);

        if (!$this->waitForCompletion($jobId, $pollInterval, $maxPolls)) {
            throw new UniProtException("Job {$jobId} did not complete within timeout");
        }

        return $jobId;
    }

    /**
     * Get available from/to database pairs
     * 
     * @return array<string, mixed>
     * @throws UniProtException
     */
    public function getAvailableDatabases(): array
    {
        try {
            $url = self::API_BASE_URL . '/configure/idmapping/fields';
            $response = $this->httpClient->get($url);
            $this->validateResponse($response);

            $data = json_decode($response['body'], true);
            return $data ?? [];
        } catch (UniProtException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new UniProtException("Failed to get database configuration: {$e->getMessage()}");
        }
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
            try {
                $error = json_decode($body, true);
                if (isset($error['messages'])) {
                    throw new UniProtException(implode('; ', $error['messages']));
                }
            } catch (UniProtException $e) {
                throw $e;
            } catch (\Throwable $e) {
                // Fall through
            }

            $exception = new UniProtException("HTTP {$status} error");
            $exception->setHttpStatus($status)->setApiResponse($body);
            throw $exception;
        }

        if ($status < 200 || ($status >= 300 && $status < 303)) {
            throw new UniProtException("Unexpected HTTP status: {$status}");
        }
    }
}
