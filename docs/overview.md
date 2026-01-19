# UniProt PHP Library - Complete Documentation

A production-ready PHP library for programmatic access to the UniProt REST API. Supports single entry retrieval, advanced search with pagination, and async ID mapping.

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Single Entry Retrieval](#single-entry-retrieval)
- [Search with Pagination](#search-with-pagination)
- [ID Mapping](#id-mapping)
- [Error Handling](#error-handling)
- [Shared Hosting Compatibility](#shared-hosting-compatibility)
- [API Limits](#api-limits)
- [Examples](#examples)

## Overview

UniProt PHP provides full programmatic access to the UniProt REST API with the following features:

- **Single Entry Retrieval**: Fetch individual protein entries by accession number
- **Search**: Advanced text search across UniProtKB with support for complex queries
- **Pagination**: Built-in cursor-based pagination for large result sets
- **ID Mapping**: Map identifiers between databases using UniProt's async job model
- **Transport Flexibility**: Automatic fallback from cURL to PHP streams
- **No External Dependencies**: Pure PHP with no composer requirements
- **Strict Error Handling**: Comprehensive exception system with API error information
- **PHP 7.4+ Compatible**: Works on shared hosting environments

## Installation

1. Clone or download the library into your project:

```bash
git clone https://github.com/your-repo/uniprot-php.git
```

2. Include the autoloader in your PHP script:

```php
<?php
require_once 'uniprot-php/src/autoload.php';
```

Or manually include files:

```php
<?php
require_once 'src/Exception/UniProtException.php';
require_once 'src/Http/HttpClientInterface.php';
require_once 'src/Http/CurlClient.php';
require_once 'src/Http/StreamClient.php';
require_once 'src/Http/HttpClientFactory.php';
require_once 'src/UniProt/UniProtEntry.php';
require_once 'src/UniProt/UniProtSearch.php';
require_once 'src/UniProt/UniProtIdMapping.php';
```

## Quick Start

```php
<?php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;
use UniProtPHP\UniProt\UniProtSearch;
use UniProtPHP\UniProt\UniProtIdMapping;

// Create HTTP client (automatically selects cURL or streams)
$httpClient = HttpClientFactory::create();

// Get a single entry
$entry = new UniProtEntry($httpClient);
$protein = $entry->get('P12345');
echo $protein['primaryAccession']; // P12345

// Search for entries
$search = new UniProtSearch($httpClient);
$results = $search->search('organism_id:9606 AND reviewed:true', ['size' => 50]);

foreach ($results as $result) {
    echo $result['primaryAccession'] . "\n";
}

// Map IDs
$mapping = new UniProtIdMapping($httpClient);
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345']
);
$mappedData = $mapping->getResults($jobId);
```

## Architecture

The library is organized into clear, focused modules:

```
src/
├── Exception/
│   └── UniProtException.php          # Exception with API error tracking
├── Http/
│   ├── HttpClientInterface.php       # HTTP client contract
│   ├── CurlClient.php                # cURL implementation
│   ├── StreamClient.php              # Stream-based implementation
│   └── HttpClientFactory.php         # Factory for selecting transport
└── UniProt/
    ├── UniProtEntry.php              # Single entry retrieval
    ├── UniProtSearch.php             # Search with pagination
    └── UniProtIdMapping.php          # ID mapping with async polling
```

### Design Principles

1. **Interface-based**: HTTP client implementations follow a strict interface
2. **Automatic Transport Selection**: Factory chooses best available HTTP transport
3. **No Global State**: All classes accept dependencies via constructor
4. **Cursor-based Pagination**: Uses Link header pattern for efficient result traversal
5. **Async-aware**: ID mapping includes built-in polling mechanism
6. **Strict Types**: PHP 7.4+ type declarations throughout
7. **Structured Errors**: Exceptions include HTTP status, API error code, and full response

## Single Entry Retrieval

### Basic Usage

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;

$httpClient = HttpClientFactory::create();
$entry = new UniProtEntry($httpClient);

// Get entry as JSON (default)
$protein = $entry->get('P12345');

// Check what we got
echo $protein['primaryAccession'];           // P12345
echo $protein['organism']['scientificName']; // Oryctolagus cuniculus
```

### Output Formats

```php
// JSON (default)
$protein = $entry->get('P12345', 'json');

// FASTA
$fasta = $entry->get('P12345', 'fasta');
echo $fasta['body']; // Raw FASTA format

// XML
$xml = $entry->get('P12345', 'xml');

// Tab-separated values
$tsv = $entry->get('P12345', 'txt');
```

### Retrieve Specific Fields

```php
// Get only accession and protein name
$data = $entry->getWithFields('P12345', [
    'accession',
    'protein_name',
    'organism_name'
]);
```

### Batch Retrieval

```php
// Get multiple entries at once
$results = $entry->getBatch([
    'P12345',
    'P00750',
    'P05067'
]);

// Results include errors for failed accessions
foreach ($results as $result) {
    if (isset($result['error'])) {
        echo "Error for {$result['accession']}: {$result['error']}\n";
    } else {
        echo "Got entry: {$result['primaryAccession']}\n";
    }
}
```

### Check Entry Existence

```php
if ($entry->exists('P12345')) {
    echo "Entry exists\n";
}
```

### Common Use Cases

**Get protein sequence:**
```php
$data = $entry->getWithFields('P12345', ['sequence']);
echo $data['sequence']['value'];
```

**Get protein function:**
```php
$data = $entry->getWithFields('P12345', ['cc_function']);
foreach ($data['comments'] as $comment) {
    if ($comment['commentType'] === 'FUNCTION') {
        echo $comment['texts'][0]['value'];
    }
}
```

## Search with Pagination

### Basic Search

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtSearch;

$httpClient = HttpClientFactory::create();
$search = new UniProtSearch($httpClient);

// Simple search
$results = $search->search('organism_id:9606 AND reviewed:true');

// Iterate through all results automatically
foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
}
```

### Query Syntax

UniProt supports a powerful query language:

```php
// All entries containing both terms
$search->search('human AND antigen');

// Exact phrase
$search->search('"human antigen"');

// Boolean logic
$search->search('human OR mouse');

// Exclude terms
$search->search('human -obsolete');

// Wildcards
$search->search('kinase*');

// Field-specific search
$search->search('gene:BRCA1');

// Range queries
$search->search('length:[500 TO 700]');

// Complex queries
$search->search('(organism_id:9606 OR organism_id:10090) AND reviewed:true AND mass:[50000 TO 100000]');
```

### Search Options

```php
$results = $search->search(
    'insulin AND reviewed:true',
    [
        'size' => 100,                // Results per page (max 500)
        'format' => 'json',           // Output format
        'fields' => [                 // Specific fields to return
            'accession',
            'protein_name',
            'organism_name'
        ],
        'includeIsoform' => true,     // Include protein isoforms
        'compressed' => false         // Request gzip compression
    ]
);
```

### Pagination Control

The SearchResults object automatically handles pagination:

```php
$results = $search->search('organism_id:9606', ['size' => 50]);

$count = 0;
foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
    
    if (++$count >= 100) {
        break; // Stop after 100 results
    }
}
```

### Get Total Count

```php
// Get first page only to check total available
$firstPage = $search->getFirstPage('organism_id:9606');
echo "Total results available: " . count($firstPage['results']);
```

### Common Queries

**Human proteins:**
```php
$results = $search->search('organism_id:9606 AND reviewed:true');
```

**Insulin and related:**
```php
$results = $search->search('insulin AND organism_id:9606');
```

**Kinases with structures:**
```php
$results = $search->search('kinase AND xref_count_pdb:[1 TO *]');
```

**Recent entries:**
```php
$results = $search->search('date_created:[2024-01-01 TO *]');
```

### Build Custom URLs

```php
// Build API URL directly if needed
$url = $search->buildUrl(
    'organism_id:9606',
    [
        'size' => 100,
        'format' => 'tsv',
        'fields' => ['accession', 'protein_name']
    ]
);
echo $url; // https://rest.uniprot.org/uniprotkb/search?query=...
```

## ID Mapping

### Basic Mapping

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtIdMapping;

$httpClient = HttpClientFactory::create();
$mapping = new UniProtIdMapping($httpClient);

// Submit a mapping job
$jobId = $mapping->submit(
    'UniProtKB_AC-ID',  // from database
    'Ensembl',          // to database
    ['P05067', 'P12345'] // IDs to map
);

// Poll for completion (with automatic retry)
if ($mapping->waitForCompletion($jobId)) {
    $results = $mapping->getResults($jobId);
    print_r($results);
}
```

### Submit and Wait (Convenience)

```php
// One call handles everything
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345'],
    null,                          // taxId (optional)
    3,                             // poll interval in seconds
    60                             // max polls
);

$results = $mapping->getResults($jobId);
```

### Manual Polling

```php
// Submit job
$jobId = $mapping->submit('UniProtKB_AC-ID', 'Ensembl', ['P05067']);

// Check status
do {
    $status = $mapping->status($jobId);
    
    if (isset($status['jobStatus'])) {
        echo "Status: {$status['jobStatus']}\n";
        
        if ($status['jobStatus'] === 'FINISHED') {
            break;
        }
    }
    
    sleep(2);
} while (true);

// Get results
$results = $mapping->getResults($jobId);
```

### Get Results in Batches

```php
// Paginated results (default: 25 per page)
$page1 = $mapping->getResults($jobId, 25, 0);
$page2 = $mapping->getResults($jobId, 25, 1);

// Stream all results at once (more demanding)
$allResults = $mapping->streamResults($jobId);
```

### Retrieve Job Details

```php
// Get information about submitted job
$details = $mapping->getDetails($jobId);

echo "From: " . $details['from'];     // UniProtKB_AC-ID
echo "To: " . $details['to'];         // Ensembl
echo "IDs: " . $details['ids'];       // P05067,P12345
```

### Available Database Pairs

```php
// Get valid from/to combinations
$config = $mapping->getAvailableDatabases();

foreach ($config['groups'] as $group) {
    echo $group['groupName'] . ":\n";
    foreach ($group['items'] as $item) {
        if ($item['from']) {
            echo "  FROM: {$item['displayName']}\n";
        }
    }
}
```

### Common Mapping Scenarios

**UniProt to Ensembl:**
```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067']
);
$results = $mapping->getResults($jobId);
```

**UniProt to Gene Name:**
```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Gene_Name',
    ['P05067']
);
```

**Gene Name to UniProt:**
```php
$jobId = $mapping->submitAndWait(
    'Gene_Name',
    'UniProtKB',
    ['TP53', 'BRCA1']
);
```

**UniParc to UniProtKB:**
```php
$jobId = $mapping->submitAndWait(
    'UniParc',
    'UniProtKB',
    ['UPI0000000001']
);
```

### Large ID Lists

For lists larger than 100,000 IDs, split into chunks:

```php
$allIds = [...]; // 200,000 IDs
$chunkSize = 50000;

foreach (array_chunk($allIds, $chunkSize) as $chunk) {
    $jobId = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        $chunk
    );
    
    $results = $mapping->getResults($jobId);
    // Process results...
}
```

## Error Handling

### Basic Exception Handling

```php
use UniProtPHP\Exception\UniProtException;
use UniProtPHP\UniProt\UniProtEntry;

$entry = new UniProtEntry($httpClient);

try {
    $protein = $entry->get('INVALID');
} catch (UniProtException $e) {
    echo "Error: " . $e->getMessage();
}
```

### Detailed Error Information

```php
try {
    $protein = $entry->get('P00000');
} catch (UniProtException $e) {
    echo "Message: " . $e->getMessage();
    echo "HTTP Status: " . $e->getHttpStatus();
    echo "Error Code: " . $e->getApiErrorCode();
    echo "API Response: " . $e->getApiResponse();
    echo "Detailed: " . $e->getDetailedMessage();
    
    // Check error type
    if ($e->isClientError()) {
        echo "Client error (4xx)";
    } elseif ($e->isServerError()) {
        echo "Server error (5xx)";
    } elseif ($e->isTransportError()) {
        echo "Network/transport error";
    }
}
```

### Common Error Scenarios

**Entry not found:**
```php
try {
    $entry->get('P99999999');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 404) {
        echo "Entry does not exist\n";
    }
}
```

**Network error:**
```php
try {
    $search->search('organism_id:9606');
} catch (UniProtException $e) {
    if ($e->isTransportError()) {
        echo "Network error, check connectivity\n";
    }
}
```

**Invalid query:**
```php
try {
    $search->search('invalid::query[[');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() >= 400 && $e->getHttpStatus() < 500) {
        echo "Invalid query: " . $e->getApiErrorMessage();
    }
}
```

**API rate limiting:**
```php
try {
    $entry->get('P12345');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 429) {
        echo "Rate limited, retry after delay\n";
        sleep(5);
    }
}
```

## Shared Hosting Compatibility

### Automatic Transport Selection

The library automatically selects the best available HTTP transport:

```php
// First tries cURL (faster)
// Falls back to PHP streams if cURL unavailable
$httpClient = HttpClientFactory::create();
echo "Using: " . $httpClient->getTransportName();
```

### Force Specific Transport

```php
try {
    // Force cURL (will throw if not available)
    $httpClient = HttpClientFactory::create(true);
} catch (UniProtException $e) {
    echo "cURL not available, use streams instead\n";
}
```

### Check Available Transports

```php
$available = HttpClientFactory::getAvailableTransports();
echo "cURL available: " . ($available['cURL'] ? 'Yes' : 'No');
echo "Streams available: " . ($available['PHP Streams'] ? 'Yes' : 'No');
```

### Shared Hosting Best Practices

1. **Test HTTP access:**
```php
$transports = HttpClientFactory::getAvailableTransports();
if (!$transports['cURL'] && !$transports['PHP Streams']) {
    die("No HTTP transport available on this host");
}
```

2. **Handle timeouts gracefully:**
```php
try {
    $mapping->waitForCompletion($jobId, 3, 20); // Short timeout
} catch (UniProtException $e) {
    echo "Job still running, check later\n";
}
```

3. **Batch large operations:**
```php
// Don't submit 100K IDs at once
$ids = [...];
foreach (array_chunk($ids, 10000) as $chunk) {
    $results = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        $chunk
    );
}
```

4. **Cache results:**
```php
// Store results to avoid re-fetching
$cacheFile = "/tmp/uniprot_cache_{$accession}.json";
if (file_exists($cacheFile)) {
    $data = json_decode(file_get_contents($cacheFile), true);
} else {
    $data = $entry->get($accession);
    file_put_contents($cacheFile, json_encode($data));
}
```

## API Limits

### UniProtKB Search

- **Max results per page**: 500
- **Query complexity**: Reasonable limits, very complex queries may timeout
- **Result set size**: No hard limit, but practical limit around 500K results

### ID Mapping

- **Max IDs per job**: 100,000
- **Max mapped results**: 500,000
- **Max enriched results**: 100,000 (with UniProt data)
- **Max filtered results**: 25,000

### Rate Limiting

- No official rate limit published, but be respectful:
  - Add delays between requests if running many queries
  - Use reasonable page sizes (100-500)
  - Combine queries where possible
  - Cache results when appropriate

### Timeout Considerations

- Default timeout: 30 seconds per request
- ID mapping jobs: Default max 3 minutes polling
- Paginated search: Each page respects timeout, but can continue

## Examples

See the [examples/](examples/) directory for:

- [get_entry.php](examples/get_entry.php) - Single entry retrieval
- [search_entries.php](examples/search_entries.php) - Search with pagination
- [map_ids.php](examples/map_ids.php) - ID mapping workflow

## Testing

Basic smoke tests are provided in [tests/basic_smoke_tests.php](tests/basic_smoke_tests.php).

Run tests:
```bash
php tests/basic_smoke_tests.php
```

Tests verify:
- Entry retrieval
- Search pagination
- ID mapping lifecycle
- Error handling

## Support

For issues or questions:
- Check the [examples/](examples/) directory
- Review error messages from UniProtException
- Refer to [UniProt API documentation](https://www.uniprot.org/help/api)

## License

This library is provided as-is for programmatic access to the UniProt REST API.
UniProt data is licensed under CC BY 4.0.
