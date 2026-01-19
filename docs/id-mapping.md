# ID Mapping

Map identifiers between databases using UniProt's async job model.

## Endpoint

```
POST https://rest.uniprot.org/idmapping/run
GET  https://rest.uniprot.org/idmapping/status/{jobId}
GET  https://rest.uniprot.org/idmapping/results/{jobId}
GET  https://rest.uniprot.org/idmapping/stream/{jobId}
```

## Basic Usage

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtIdMapping;

$httpClient = HttpClientFactory::create();
$mapping = new UniProtIdMapping($httpClient);

// One-step submit and wait
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345']
);

// Get results
$results = $mapping->getResults($jobId);
```

## Job Workflow

1. **Submit** - Submit IDs and source/target databases
2. **Poll** - Check job status until completion
3. **Retrieve** - Download results

### Step-by-Step Example

```php
// 1. Submit job
$jobId = $mapping->submit(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345']
);

echo "Job submitted: {$jobId}\n";

// 2. Poll for completion
if ($mapping->waitForCompletion($jobId)) {
    echo "Job completed!\n";
    
    // 3. Get results
    $results = $mapping->getResults($jobId);
    print_r($results);
}
```

## Submit a Job

### Basic Submission

```php
$jobId = $mapping->submit(
    'UniProtKB_AC-ID',  // from database
    'Ensembl',          // to database
    ['P05067', 'P12345'] // IDs to map
);
```

### With Taxonomy Filter

```php
// Map only to human sequences
$jobId = $mapping->submit(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067'],
    9606  // Human taxonomy ID
);
```

### Submit and Wait

Convenience method that submits and polls:

```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345'],
    null,   // no taxId filter
    2,      // poll every 2 seconds
    60      // max 60 polls (2 minutes)
);

// Results are guaranteed to be ready
$results = $mapping->getResults($jobId);
```

## Check Job Status

### Automatic Polling

```php
// Wait with automatic polling
$completed = $mapping->waitForCompletion(
    $jobId,
    3,    // seconds between polls
    20    // max 20 polls
);

if ($completed) {
    echo "Job done\n";
}
```

### Manual Polling

```php
$maxAttempts = 20;
$attempt = 0;

while ($attempt < $maxAttempts) {
    $status = $mapping->status($jobId);
    
    if (isset($status['jobStatus'])) {
        echo "Status: " . $status['jobStatus'] . "\n";
        
        if ($status['jobStatus'] === 'FINISHED') {
            break;
        }
        
        if ($status['jobStatus'] === 'ERROR') {
            throw new Exception("Job failed");
        }
    }
    
    if (isset($status['results'])) {
        echo "Results available\n";
        break;
    }
    
    sleep(2);
    $attempt++;
}
```

### Job Status Values

- **NEW** - Just submitted
- **RUNNING** - In progress
- **FINISHED** - Completed successfully
- **ERROR** - Failed
- **FAILED** - Failed (alternative)

## Retrieve Results

### Paginated Results

```php
// Get first page (25 results)
$page1 = $mapping->getResults($jobId, 25, 0);

// Get second page
$page2 = $mapping->getResults($jobId, 25, 1);

// Process results
foreach ($page1['results'] as $mapping) {
    echo $mapping['from'] . " → " . $mapping['to']['id'] . "\n";
}

if (!empty($page1['failedIds'])) {
    echo "Failed: " . implode(', ', $page1['failedIds']) . "\n";
}
```

### Stream All Results

More demanding on API, but gets everything at once:

```php
$allResults = $mapping->streamResults($jobId);

foreach ($allResults['results'] as $mapping) {
    echo $mapping['from'] . " → " . $mapping['to']['id'] . "\n";
}
```

### Result Structure

```php
[
    'results' => [
        [
            'from' => 'P05067',
            'to' => [
                'id' => 'ENSP00000000003',
                'name' => 'T cell leukemia protein 1...',
                // Additional fields if enriched with UniProt data
            ]
        ],
        // ... more results
    ],
    'failedIds' => []  // IDs that couldn't be mapped
]
```

## Get Job Details

```php
$details = $mapping->getDetails($jobId);

echo "From: " . $details['from'] . "\n";      // UniProtKB_AC-ID
echo "To: " . $details['to'] . "\n";          // Ensembl
echo "IDs: " . $details['ids'] . "\n";        // P05067,P12345
echo "TaxId: " . $details['taxId'] . "\n";    // null or taxonomy ID
```

## Available Database Pairs

### Get Configuration

```php
$config = $mapping->getAvailableDatabases();

// Groups by category
foreach ($config['groups'] as $group) {
    echo $group['groupName'] . ":\n";
    
    foreach ($group['items'] as $item) {
        if ($item['from']) {
            echo "  FROM: " . $item['displayName'] . "\n";
        }
    }
}

// Rules define valid to/from combinations
foreach ($config['rules'] as $rule) {
    echo "From databases mapped to: " . implode(', ', $rule['tos']) . "\n";
}
```

### Common From/To Pairs

| From | To |
|------|-----|
| UniProtKB_AC-ID | Ensembl, Gene_Name, PDB, RefSeq... |
| Gene_Name | UniProtKB |
| UniParc | UniProtKB, UniParc, UniRef |
| RefSeq | UniProtKB |
| EMBL | UniProtKB |

## Common Mapping Scenarios

### UniProt to Ensembl

```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    ['P05067', 'P12345', 'P00750']
);

$results = $mapping->getResults($jobId);
foreach ($results['results'] as $r) {
    echo "{$r['from']} → {$r['to']['id']}\n";
}
```

### UniProt to Gene Name

```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Gene_Name',
    ['P05067']
);
// Output: P05067 → APP (Amyloid Beta Precursor Protein)
```

### Gene Name to UniProt

```php
$jobId = $mapping->submitAndWait(
    'Gene_Name',
    'UniProtKB',
    ['TP53', 'BRCA1', 'APP']
);
```

### UniProt to PDB Structures

```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'PDB',
    ['P05067']
);
```

### UniParc to UniProtKB

```php
$jobId = $mapping->submitAndWait(
    'UniParc',
    'UniProtKB',
    ['UPI0000000001', 'UPI0000000002']
);
```

## Large ID Lists

### Chunking Strategy

For lists > 100,000 IDs, split into chunks:

```php
$allIds = [...]; // 200,000 IDs
$chunkSize = 50000;
$allResults = [];

foreach (array_chunk($allIds, $chunkSize) as $chunk) {
    $jobId = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        $chunk,
        null,
        2,
        30
    );
    
    $results = $mapping->getResults($jobId);
    $allResults = array_merge($allResults, $results['results'] ?? []);
}
```

### Pagination for Large Results

```php
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    $largeIdList
);

// Fetch in pages
$pageSize = 100;
$pageNum = 0;
$allMappings = [];

while (true) {
    $page = $mapping->getResults($jobId, $pageSize, $pageNum);
    
    if (empty($page['results'])) {
        break;
    }
    
    $allMappings = array_merge($allMappings, $page['results']);
    $pageNum++;
}
```

## Error Handling

```php
use UniProtPHP\Exception\UniProtException;

try {
    $jobId = $mapping->submit(
        'UniProtKB_AC-ID',
        'Ensembl',
        []  // ERROR: Empty list
    );
} catch (UniProtException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    $mapping->waitForCompletion($jobId, 2, 10);
} catch (UniProtException $e) {
    echo "Job failed: " . $e->getMessage() . "\n";
}
```

## API Limits

| Limit | Value |
|-------|-------|
| Max IDs per job | 100,000 |
| Max mapped results | 500,000 |
| Max enriched results | 100,000 |
| Max filtered results | 25,000 |

### Handling Limits

```php
// Check list size
$ids = [...];
if (count($ids) > 100000) {
    echo "Warning: Large ID list, consider chunking\n";
}

// Catch limit errors
try {
    $jobId = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        $hugeIdList
    );
} catch (UniProtException $e) {
    if (strpos($e->getMessage(), 'limit') !== false) {
        echo "ID limit exceeded, split into smaller batches\n";
    }
}
```

## Performance Tips

1. **Batch large lists** - Split > 100K IDs
2. **Check job completion early** - Stop polling once finished
3. **Use appropriate page size** - Balance memory vs requests
4. **Cache results** - Store mapped data locally
5. **Handle failures gracefully** - Log and retry failed IDs
6. **Monitor API limits** - Track results size

## Related

- [Single Entry Retrieval](single-entry.md) - Fetch entry details
- [Search](search.md) - Find entries
- [Pagination Details](pagination.md) - Cursor-based pagination
- [Error Handling](error-handling.md) - Exception handling
