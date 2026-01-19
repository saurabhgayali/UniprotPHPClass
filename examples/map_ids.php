<?php
declare(strict_types=1);

/**
 * Example 3: ID Mapping
 * 
 * Shows how to submit ID mapping jobs, poll for completion,
 * and retrieve results using UniProt's async job model.
 * 
 * Run from command line: php examples/map_ids.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\Http\CurlClient;
use UniProtPHP\UniProt\UniProtIdMapping;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development/testing
CurlClient::setVerifySSL(false);

echo "=== UniProt ID Mapping Example ===\n\n";

try {
    // Create HTTP client
    $httpClient = HttpClientFactory::create();
    echo "Using transport: " . $httpClient->getTransportName() . "\n\n";

    // Create mapping object
    $mapping = new UniProtIdMapping($httpClient);

    // Example 1: Simple submit and wait
    echo "1. Submitting ID mapping job (UniProtKB → Ensembl)...\n";
    echo "   Mapping: P05067, P12345\n";
    
    $jobId = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        ['P05067', 'P12345'],
        null,    // no taxId filter
        2,       // poll every 2 seconds
        30       // max 30 polls (1 minute)
    );
    
    echo "   Job ID: {$jobId}\n";
    echo "   Status: COMPLETED\n\n";

    // Example 2: Get results
    echo "2. Retrieving mapping results...\n";
    $results = $mapping->getResults($jobId);
    
    if (isset($results['results'])) {
        foreach ($results['results'] as $mapping_result) {
            echo "   " . $mapping_result['from'] . " → ";
            if (isset($mapping_result['to']['id'])) {
                echo $mapping_result['to']['id'];
            } else {
                echo "NOT MAPPED";
            }
            echo "\n";
        }
    }
    
    if (isset($results['failedIds']) && !empty($results['failedIds'])) {
        echo "   Failed IDs: " . implode(', ', $results['failedIds']) . "\n";
    }
    echo "\n";

    // Example 3: Get job details
    echo "3. Retrieving job details...\n";
    $details = $mapping->getDetails($jobId);
    echo "   From DB: " . $details['from'] . "\n";
    echo "   To DB: " . $details['to'] . "\n";
    echo "   Input IDs: " . $details['ids'] . "\n";
    echo "\n";

    // Example 4: Different mapping (UniProtKB → Ensembl with different IDs)
    echo "4. Submitting another mapping (UniProtKB → Gene_Name)...\n";
    $jobId2 = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Gene_Name',
        ['P05067'],
        null,
        2,
        30
    );
    
    $results2 = $mapping->getResults($jobId2);
    if (isset($results2['results']) && !empty($results2['results'])) {
        echo "   Mapping result: ";
        echo $results2['results'][0]['from'] . " → " . $results2['results'][0]['to'];
        echo "\n";
    }
    echo "\n";

    // Example 5: Manual submit and poll (more control)
    echo "5. Manual submit and poll workflow...\n";
    $jobId3 = $mapping->submit(
        'UniProtKB_AC-ID',
        'Ensembl',
        ['P12345']
    );
    echo "   Submitted job: {$jobId3}\n";

    // Manual polling
    $pollCount = 0;
    $maxPolls = 20;
    
    while ($pollCount < $maxPolls) {
        $status = $mapping->status($jobId3);
        
        if (isset($status['jobStatus'])) {
            echo "   Poll {$pollCount}: Status = " . $status['jobStatus'] . "\n";
            
            if ($status['jobStatus'] === 'FINISHED') {
                echo "   Job completed!\n";
                break;
            }
        } else if (isset($status['results'])) {
            echo "   Results ready!\n";
            break;
        }
        
        sleep(2);
        $pollCount++;
    }
    
    if ($pollCount >= $maxPolls) {
        echo "   WARNING: Job still pending after timeout\n";
    }
    echo "\n";

    // Example 6: Stream results
    echo "6. Retrieving results via stream...\n";
    $streamResults = $mapping->streamResults($jobId);
    
    if (isset($streamResults['results']) && !empty($streamResults['results'])) {
        echo "   Retrieved " . count($streamResults['results']) . " mappings via stream\n";
    }
    echo "\n";

    echo "✓ All ID mapping examples completed successfully!\n";

} catch (UniProtException $e) {
    echo "✗ Error: " . $e->getDetailedMessage() . "\n";
    if ($e->getHttpStatus() > 0) {
        echo "  HTTP Status: " . $e->getHttpStatus() . "\n";
    }
    exit(1);
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
