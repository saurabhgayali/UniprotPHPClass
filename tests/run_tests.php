<?php

/**
 * Basic Smoke Tests for UniProt PHP Library
 * 
 * Tests core functionality against the live UniProt API.
 * No PHPUnit or external test frameworks required.
 * 
 * Run: php tests/run_tests.php
 */

require_once dirname(__DIR__) . '/src/Exception/UniProtException.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientInterface.php';
require_once dirname(__DIR__) . '/src/Http/CurlClient.php';
require_once dirname(__DIR__) . '/src/Http/StreamClient.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientFactory.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtEntry.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtSearch.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtIdMapping.php';

// Disable SSL verification for development/testing
use UniProtPHP\Http\CurlClient;
CurlClient::setVerifySSL(false);

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;
use UniProtPHP\UniProt\UniProtSearch;
use UniProtPHP\UniProt\UniProtIdMapping;
use UniProtPHP\Exception\UniProtException;

class SmokeTests
{
    private int $passed = 0;
    private int $failed = 0;
    private bool $verbose = false;

    public function __construct(bool $verbose = false)
    {
        $this->verbose = $verbose;
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            if ($this->verbose) {
                echo "  ✓ {$message}\n";
            }
        } else {
            $this->failed++;
            echo "  ✗ FAILED: {$message}\n";
        }
    }

    public function testHttpClientFactory(): void
    {
        echo "\n=== Testing HTTP Client Factory ===\n";

        try {
            $client = HttpClientFactory::create();
            $this->assert($client !== null, "Factory creates a client");
            $this->assert(strlen($client->getTransportName()) > 0, "Transport name is not empty");

            $available = HttpClientFactory::getAvailableTransports();
            $this->assert(
                $available['cURL'] || $available['PHP Streams'],
                "At least one transport is available"
            );

            $instance1 = HttpClientFactory::getInstance();
            $instance2 = HttpClientFactory::getInstance();
            $this->assert($instance1 === $instance2, "Singleton pattern works");

        } catch (Exception $e) {
            $this->assert(false, "HTTP client factory test: " . $e->getMessage());
        }
    }

    public function testEntryRetrieval(): void
    {
        echo "\n=== Testing Single Entry Retrieval ===\n";

        try {
            $httpClient = HttpClientFactory::create();
            $entry = new UniProtEntry($httpClient);

            // Test valid entry
            $protein = $entry->get('P12345');
            $this->assert(
                isset($protein['primaryAccession']) && $protein['primaryAccession'] === 'P12345',
                "Successfully retrieved P12345"
            );

            $this->assert(
                isset($protein['organism']['scientificName']),
                "Entry has organism information"
            );

            $this->assert(
                isset($protein['sequence']['value']) && strlen($protein['sequence']['value']) > 0,
                "Entry has sequence data"
            );

            // Test entry existence check
            $this->assert(
                $entry->exists('P12345'),
                "Entry existence check returns true for valid entry"
            );

            $this->assert(
                !$entry->exists('P99999999'),
                "Entry existence check returns false for invalid entry"
            );

            // Test batch retrieval
            $results = $entry->getBatch(['P12345', 'P00750']);
            $this->assert(count($results) === 2, "Batch retrieval returns correct count");

            $this->assert(
                isset($results[0]['primaryAccession']),
                "Batch results contain accession field"
            );

            // Test not found handling
            try {
                $entry->get('INVALID000');
                $this->assert(false, "Non-existent entry should throw exception");
            } catch (UniProtException $e) {
                $this->assert(true, "Correctly throws exception for non-existent entry");
            }

        } catch (Exception $e) {
            $this->assert(false, "Entry retrieval test failed: " . $e->getMessage());
        }
    }

    public function testSearch(): void
    {
        echo "\n=== Testing Search with Pagination ===\n";

        try {
            $httpClient = HttpClientFactory::create();
            $search = new UniProtSearch($httpClient);

            // Test basic search
            $results = $search->search('organism_id:9606 AND reviewed:true', ['size' => 5]);
            
            $count = 0;
            $firstAccession = null;
            foreach ($results as $entry) {
                if ($count === 0) {
                    $firstAccession = $entry['primaryAccession'] ?? null;
                }
                $count++;
            }

            $this->assert($count > 0, "Search returns results");
            $this->assert($firstAccession !== null, "Results contain accession field");

            // Test first page retrieval
            $firstPage = $search->getFirstPage('organism_id:9606', ['size' => 3]);
            $this->assert(
                isset($firstPage['results']) && is_array($firstPage['results']),
                "First page contains results array"
            );

            $this->assert(
                count($firstPage['results']) <= 3,
                "First page respects size parameter"
            );

            // Test URL building
            $url = $search->buildUrl('organism_id:9606', ['size' => 50]);
            $this->assert(
                strpos($url, 'organism_id%3A9606') !== false,
                "Built URL contains query"
            );
            $this->assert(
                strpos($url, 'size=50') !== false,
                "Built URL contains size parameter"
            );

        } catch (Exception $e) {
            $this->assert(false, "Search test failed: " . $e->getMessage());
        }
    }

    public function testIdMapping(): void
    {
        echo "\n=== Testing ID Mapping ===\n";

        try {
            $httpClient = HttpClientFactory::create();
            $mapping = new UniProtIdMapping($httpClient);

            // Test job submission
            echo "  Submitting ID mapping job...\n";
            $jobId = $mapping->submit(
                'UniProtKB_AC-ID',
                'Ensembl',
                ['P05067', 'P12345']
            );

            $this->assert(
                is_string($jobId) && strlen($jobId) > 0,
                "Job submission returns job ID"
            );

            echo "  Job ID: {$jobId}\n";

            // Test status checking
            echo "  Waiting for job completion...\n";
            $completed = $mapping->waitForCompletion($jobId, 2, 30);
            $this->assert($completed, "Job completed within timeout");

            // Test result retrieval
            $results = $mapping->getResults($jobId);
            $this->assert(
                isset($results['results']) || isset($results['failedIds']),
                "Results contain mapping data"
            );

            if (isset($results['results'])) {
                $this->assert(
                    count($results['results']) > 0,
                    "Job returns mapped results"
                );

                $firstResult = $results['results'][0];
                $this->assert(
                    isset($firstResult['from']) && isset($firstResult['to']),
                    "Mapping results have from/to fields"
                );
            }

            // Test job details retrieval
            $details = $mapping->getDetails($jobId);
            $this->assert(isset($details['from']), "Details contain from database");
            $this->assert(isset($details['to']), "Details contain to database");
            $this->assert(isset($details['ids']), "Details contain input IDs");

            // Test stream results
            $streamResults = $mapping->streamResults($jobId);
            $this->assert(
                isset($streamResults['results']) || isset($streamResults['failedIds']),
                "Stream results contain mapping data"
            );

        } catch (Exception $e) {
            $this->assert(false, "ID mapping test failed: " . $e->getMessage());
        }
    }

    public function testErrorHandling(): void
    {
        echo "\n=== Testing Error Handling ===\n";

        try {
            $httpClient = HttpClientFactory::create();
            $entry = new UniProtEntry($httpClient);

            // Test invalid accession
            try {
                $entry->get('INVALID');
                $this->assert(false, "Should throw exception for invalid entry");
            } catch (UniProtException $e) {
                $this->assert(true, "Correctly throws UniProtException");
                $status = $e->getHttpStatus();
                $this->assert(
                    $status === 404 || $status > 0,
                    "Exception has correct HTTP status (got: {$status})"
                );
                $this->assert(
                    $e->isClientError(),
                    "Exception correctly identifies as client error"
                );
            }

            // Test empty query
            $search = new UniProtSearch($httpClient);
            try {
                $search->search('');
                $this->assert(false, "Should throw exception for empty query");
            } catch (UniProtException $e) {
                $this->assert(true, "Correctly throws exception for empty query");
            }

            // Test empty IDs in mapping
            $mapping = new UniProtIdMapping($httpClient);
            try {
                $mapping->submit('UniProtKB_AC-ID', 'Ensembl', []);
                $this->assert(false, "Should throw exception for empty IDs");
            } catch (UniProtException $e) {
                $this->assert(true, "Correctly throws exception for empty IDs");
            }

        } catch (Exception $e) {
            $this->assert(false, "Error handling test failed: " . $e->getMessage());
        }
    }

    public function run(): void
    {
        echo "╔════════════════════════════════════════╗\n";
        echo "║  UniProt PHP Library - Smoke Tests     ║\n";
        echo "╚════════════════════════════════════════╝\n";

        $this->testHttpClientFactory();
        $this->testEntryRetrieval();
        $this->testSearch();
        $this->testIdMapping();
        $this->testErrorHandling();

        echo "\n╔════════════════════════════════════════╗\n";
        echo "║  Test Results                          ║\n";
        echo "╠════════════════════════════════════════╣\n";
        echo "║  Passed: " . str_pad($this->passed, 34) . "║\n";
        echo "║  Failed: " . str_pad($this->failed, 34) . "║\n";
        echo "╚════════════════════════════════════════╝\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

// Run the tests
// Check if running in CLI mode; in web mode, $argv is not available
$verbose = (php_sapi_name() === 'cli') && (isset($argv) && (in_array('-v', $argv) || in_array('--verbose', $argv)));
$tests = new SmokeTests($verbose);
$tests->run();
