<?php
declare(strict_types=1);

/**
 * Example 2: Search with Pagination
 * 
 * Shows how to search UniProtKB with complex queries and handle pagination.
 * Demonstrates both automatic cursor-based pagination and manual offset-based pagination.
 * 
 * Run from command line: php examples/search_entries.php
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\Http\CurlClient;
use UniProtPHP\UniProt\UniProtSearch;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development/testing
CurlClient::setVerifySSL(false);

echo "=== UniProt Search with Pagination Example ===\n\n";

try {
    // Create HTTP client
    $httpClient = HttpClientFactory::create();
    echo "Using transport: " . $httpClient->getTransportName() . "\n\n";

    // Create search object
    $search = new UniProtSearch($httpClient);

    // Example 1: Simple search
    echo "1. Simple search for human insulin...\n";
    $results = $search->search('insulin AND organism_id:9606 AND reviewed:true', [
        'size' => 10
    ]);

    $count = 0;
    foreach ($results as $entry) {
        echo "   " . $entry['primaryAccession'] . " - ";
        echo (isset($entry['uniProtkbId']) ? $entry['uniProtkbId'] : 'N/A') . "\n";
        
        if (++$count >= 5) {
            echo "   ... (limited to 5 results)\n";
            break;
        }
    }
    echo "\n";

    // Example 2: Search with specific fields
    echo "2. Search with selected fields...\n";
    $results = $search->search('organism_id:9606 AND reviewed:true', [
        'size' => 10,
        'fields' => [
            'accession',
            'protein_name',
            'organism_name',
            'length'
        ]
    ]);

    $count = 0;
    foreach ($results as $entry) {
        echo "   " . $entry['primaryAccession'];
        if (isset($entry['proteinName']['recommendedName']['fullName']['value'])) {
            echo " - " . substr($entry['proteinName']['recommendedName']['fullName']['value'], 0, 30);
        }
        echo "\n";
        
        if (++$count >= 3) {
            break;
        }
    }
    echo "\n";

    // Example 3: Complex query with boolean operators
    echo "3. Complex query - kinases in human...\n";
    $results = $search->search(
        'organism_id:9606 AND reviewed:true AND (protein_name:kinase OR gene:*kinase)',
        ['size' => 5]
    );

    $count = 0;
    foreach ($results as $entry) {
        echo "   " . $entry['primaryAccession'];
        if (isset($entry['genes'][0]['geneName']['value'])) {
            echo " (" . $entry['genes'][0]['geneName']['value'] . ")";
        }
        echo "\n";
        
        if (++$count >= 3) {
            break;
        }
    }
    echo "\n";

    // Example 4: Query with filters - proteins by size
    echo "4. Search - proteins with specific mass range...\n";
    $results = $search->search(
        'organism_id:9606 AND reviewed:true AND mass:[50000 TO 100000]',
        ['size' => 5]
    );

    $count = 0;
    foreach ($results as $entry) {
        if (isset($entry['sequence']['molWeight'])) {
            echo "   " . $entry['primaryAccession'] . " - ";
            echo number_format($entry['sequence']['molWeight'], 0) . " Da\n";
        }
        
        if (++$count >= 3) {
            break;
        }
    }
    echo "\n";

    // Example 5: First page only (without iterating all results)
    echo "5. Get first page only...\n";
    $firstPage = $search->getFirstPage('organism_id:9606 AND reviewed:true', [
        'size' => 3,
        'fields' => ['accession', 'protein_name']
    ]);
    
    if (isset($firstPage['results'])) {
        foreach ($firstPage['results'] as $entry) {
            echo "   " . $entry['primaryAccession'] . "\n";
        }
    }
    echo "\n";

    // Example 6: Query with NOT and exclusions
    echo "6. Search - proteins NOT in PDB...\n";
    $results = $search->search(
        'organism_id:9606 AND reviewed:true AND NOT xref:pdb',
        ['size' => 5]
    );

    $count = 0;
    foreach ($results as $entry) {
        echo "   " . $entry['primaryAccession'] . "\n";
        
        if (++$count >= 2) {
            break;
        }
    }
    echo "\n";

    // Example 7: Full pagination - retrieve ALL results (e.g., ~22,400 human proteins)
    // This demonstrates automatic pagination for large result sets
    echo "7. Full pagination example - ALL human reviewed proteins...\n";
    echo "   ⚠️  NOTE: This will fetch ~22,400 results (45 requests of 500 each)\n";
    echo "   Starting pagination (showing first 10 + last 10 results)...\n\n";
    
    $results = $search->search(
        'organism_id:9606 AND reviewed:true',
        ['size' => 500]  // Max allowed per request
    );

    $count = 0;
    $firstResults = [];
    $lastResults = [];
    
    foreach ($results as $entry) {
        $count++;
        
        // Store first 10
        if ($count <= 10) {
            $firstResults[] = $entry['primaryAccession'];
        }
        
        // Keep last 10 in memory
        $lastResults[] = $entry['primaryAccession'];
        if (count($lastResults) > 10) {
            array_shift($lastResults);
        }
    }
    
    echo "   First 10 results:\n";
    foreach ($firstResults as $i => $acc) {
        echo "   " . ($i + 1) . ". $acc\n";
    }
    
    echo "\n   ... [" . ($count - 20) . " results in between] ...\n\n";
    
    echo "   Last 10 results:\n";
    foreach ($lastResults as $i => $acc) {
        echo "   " . ($count - 9 + $i) . ". $acc\n";
    }
    
    echo "\n   ✓ Total retrieved: $count entries\n";
    echo "   ✓ Automatic cursor-based pagination completed!\n\n";

    echo "✓ All search examples completed successfully!\n";

} catch (UniProtException $e) {
    echo "✗ Error: " . $e->getDetailedMessage() . "\n";
    if ($e->getApiErrorMessage()) {
        echo "  API Error: " . $e->getApiErrorMessage() . "\n";
    }
    exit(1);
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}
