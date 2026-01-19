<?php
declare(strict_types=1);

/**
 * Example 1: Single Entry Retrieval
 * 
 * Shows how to fetch individual UniProtKB entries by accession number.
 * Demonstrates basic retrieval, field selection, and batch operations.
 * 
 * Run from command line: php examples/get_entry.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\Http\CurlClient;
use UniProtPHP\UniProt\UniProtEntry;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development/testing
CurlClient::setVerifySSL(false);

echo "=== UniProt Entry Retrieval Example ===\n\n";

try {
    // Create HTTP client (automatically chooses cURL or streams)
    $httpClient = HttpClientFactory::create();
    echo "Using transport: " . $httpClient->getTransportName() . "\n\n";

    // Create entry retriever
    $entry = new UniProtEntry($httpClient);

    // Example 1: Get a single entry (from P12345.json in reference)
    echo "1. Retrieving entry P12345...\n";
    $protein = $entry->get('P12345');
    echo "   Accession: " . $protein['primaryAccession'] . "\n";
    echo "   Name: " . $protein['uniProtkbId'] . "\n";
    echo "   Organism: " . $protein['organism']['commonName'] . "\n";
    if (isset($protein['sequence'])) {
        echo "   Sequence length: " . $protein['sequence']['length'] . " amino acids\n";
    }
    echo "\n";

    // Example 2: Check if entry exists
    echo "2. Checking if entries exist...\n";
    $exists = $entry->exists('P12345');
    echo "   P12345 exists: " . ($exists ? 'Yes' : 'No') . "\n";
    
    $notExists = $entry->exists('P99999999');
    echo "   P99999999 exists: " . ($notExists ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Example 3: Get specific fields
    echo "3. Getting specific fields for P12345...\n";
    $data = $entry->getWithFields('P12345', [
        'accession',
        'protein_name',
        'organism_name',
        'sequence'
    ]);
    echo "   Protein: " . $data['uniProtkbId'] . "\n";
    echo "   Sequence: " . substr($data['sequence']['value'], 0, 50) . "...\n";
    echo "\n";

    // Example 4: Get entry in different formats
    echo "4. Retrieving entry in different formats...\n";
    
    $fasta = $entry->get('P12345', 'fasta');
    echo "   FASTA format (first 100 chars):\n";
    echo "   " . substr($fasta['body'], 0, 100) . "...\n";
    echo "\n";

    // Example 5: Batch retrieval
    echo "5. Batch retrieving multiple entries...\n";
    $results = $entry->getBatch(['P12345', 'P00750', 'INVALID123']);
    foreach ($results as $result) {
        if (isset($result['error'])) {
            echo "   " . $result['accession'] . ": ERROR - " . $result['error'] . "\n";
        } else {
            echo "   " . $result['primaryAccession'] . " - " . $result['organism']['commonName'] . "\n";
        }
    }
    echo "\n";

    echo "✓ All examples completed successfully!\n";

} catch (UniProtException $e) {
    echo "✗ Error: " . $e->getDetailedMessage() . "\n";
    if ($e->getHttpStatus() > 0) {
        echo "  HTTP Status: " . $e->getHttpStatus() . "\n";
    }
    exit(1);
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}
