# Pagination

Cursor-based pagination using HTTP Link headers. Automatically retrieves ALL results, no matter how large.

## Quick Start: Fetch All Results

Want to retrieve **all 22,400+ human proteins**? Just iterate:

```php
$search = new UniProtSearch($httpClient);

// Automatically handles ALL pagination internally
$results = $search->search('organism_id:9606 AND reviewed:true', ['size' => 500]);

$count = 0;
foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
    $count++;
}
echo "Total: $count entries\n";  // Prints 22,400+
```

**How it works behind the scenes:**
1. First request: fetch results 1-500
2. Library extracts cursor from `Link` header
3. Second request: fetch results 501-1000 (using cursor)
4. Repeats automatically until all results retrieved
5. No manual cursor handling needed!

## How It Works

UniProt search API uses cursor-based pagination via the `Link` HTTP header:

```
Link: <https://rest.uniprot.org/uniprotkb/search?query=...&cursor=ABC123>; rel="next"
```

This approach is efficient and prevents large offset/limit issues.

## Automatic Pagination (Recommended)

The library handles pagination automatically:

```php
$search = new UniProtSearch($httpClient);
$results = $search->search('organism_id:9606', ['size' => 50]);

// foreach automatically fetches next pages
foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
}
```

The SearchResults iterator:
1. Fetches first page
2. Checks `Link` header for next URL
3. Fetches next page when needed
4. Repeats until no more results

## Manual Pagination for UI Display

For web UIs that show paginated results (10/20/50 per page), use `getPaginatedResults()`:

```php
$search = new UniProtSearch($httpClient);

// Get page 5 with 20 results per page
// Offset starts at 0: offset = (pageNumber - 1) * pageSize
$offset = 4 * 20;  // Page 5
$result = $search->getPaginatedResults(
    'organism_id:9606 AND reviewed:true',
    $offset,   // Skip first 80 results
    20         // Show 20 results
);

echo "Page " . $result['currentPage'] . " of " . $result['totalPages'] . "\n";
echo "Total results: " . $result['totalResults'] . "\n";

// Display results
foreach ($result['results'] as $entry) {
    echo $entry['primaryAccession'] . "\n";
}

// Build pagination links
foreach ($result['pageLinks'] as $pageNum => $pageOffset) {
    echo "Page $pageNum (offset: $pageOffset)\n";
}
```

**Key points:**
- Gets total count efficiently with one API call (`size=1`)
- Only fetches results needed for the page (10, 20, or 50)
- Returns pagination metadata (total pages, links, next/previous)
- **Optimized for first 500 results** (pages 1-50 with size=10, pages 1-25 with size=20, etc.)
- Pages beyond 500 return empty results (use automatic pagination via `search()` for beyond)

**What gets returned:**

```php
[
    'results' => [...],           // 10-50 entries for this page
    'offset' => 0,                // Starting position
    'pageSize' => 20,             // Results per page
    'currentPage' => 1,           // Current page number
    'totalPages' => 2042,         // Total available pages
    'totalResults' => 20420,      // Total matching entries
    'previousOffset' => null,     // Use for previous page link
    'nextOffset' => 20,           // Use for next page link
    'pageLinks' => [              // All page offsets
        1 => 0,
        2 => 20,
        3 => 40,
        // ...
    ],
    'hasNextPage' => true,
    'hasPreviousPage' => false,
]
```

**Performance:**
- Pages 1-50 (offset 0-499): 2 API calls (count + fetch)
- Pages > 50 (offset >= 500): Returns empty (not recommended for high offsets)

For optimal performance with large result sets, use automatic pagination with `search()` and process results as they're retrieved.

## Manual Iteration (Low-Level)

### First Page

```php
$search = new UniProtSearch($httpClient);
$firstPage = $search->getFirstPage(
    'organism_id:9606',
    ['size' => 100]
);

$results = $firstPage['results'];
```

### Next Pages

The library extracts the next URL from the `Link` header:

```php
$httpClient = HttpClientFactory::create();

// URL for first page
$url = 'https://rest.uniprot.org/uniprotkb/search?query=organism_id:9606&size=100&format=json';

// Fetch first page
$page1 = $httpClient->get($url);
$data1 = json_decode($page1['body'], true);

// Extract next URL from Link header
if (isset($page1['headers']['link'])) {
    $linkHeader = $page1['headers']['link'];
    
    // Parse: <URL>; rel="next"
    if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $matches)) {
        $nextUrl = $matches[1];
        
        // Fetch next page
        $page2 = $httpClient->get($nextUrl);
        $data2 = json_decode($page2['body'], true);
    }
}
```

## Cursor Mechanics

### URL Structure

```
https://rest.uniprot.org/uniprotkb/search?
  query=organism_id:9606
  &size=100
  &format=json
  &cursor=OPAQUE_CURSOR_STRING
```

### Key Points

- **Cursor is opaque** - Don't parse or modify it
- **Cursor is URL-safe** - Already encoded
- **Cursor expires** - Save results, not cursors
- **No offset/limit** - Use cursor instead

## Response Headers

Key pagination-related headers:

| Header | Description |
|--------|-------------|
| `Link` | Next page URL with rel="next" |
| `x-total-results` | Total matching entries (if available) |
| `x-page-number` | Current page (if available) |

## Combining with Pagination

### Page Size Selection (Max 500 per request)

The UniProt API limits individual requests to **500 results max**. This is not a limitation because automatic pagination handles larger result sets seamlessly:

```php
// Recommended: Use max size (500) for efficiency
// Reduces number of API requests while retrieving all results
$results = $search->search(
    'organism_id:9606',
    ['size' => 500]  // Max allowed, recommended for large result sets
);

foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";  // Gets ALL results automatically
}

// Alternative: Smaller pages for lower memory usage
$results = $search->search(
    'organism_id:9606',
    ['size' => 100]  // Fewer results per request, but more requests needed
);

// Alternative: Tiny pages for minimal memory (not recommended unless needed)
$results = $search->search(
    'organism_id:9606',
    ['size' => 10]  // Very many requests, only use if memory is critical
);
```

**Size vs Requests Example for 22,400 results:**
- `size=500` → 45 API requests (recommended)
- `size=100` → 225 API requests
- `size=10` → 2,240 API requests (avoid unless necessary)

### Stop Early

```php
$results = $search->search('organism_id:9606', ['size' => 500]);

$count = 0;
foreach ($results as $entry) {
    // Process entry
    
    // Stop after 1000 (doesn't fetch remaining pages)
    if (++$count >= 1000) {
        break;
    }
}
echo "Processed: $count entries\n";  // Prints 1000
```

### Real-Time Count During Iteration

```php
$results = $search->search('organism_id:9606', ['size' => 500]);

$count = 0;
$startTime = time();
foreach ($results as $entry) {
    $count++;
    
    // Show progress every 500
    if ($count % 500 === 0) {
        echo "Retrieved $count entries (" . (time() - $startTime) . "s)\n";
    }
}
echo "Total: $count entries\n";
```

## ID Mapping Pagination

ID mapping uses paginated results similarly:

```php
$mapping = new UniProtIdMapping($httpClient);
$jobId = $mapping->submitAndWait(
    'UniProtKB_AC-ID',
    'Ensembl',
    $ids
);

// Get paginated results
$page1 = $mapping->getResults($jobId, 100, 0);
$page2 = $mapping->getResults($jobId, 100, 1);

// Or stream all at once
$allResults = $mapping->streamResults($jobId);
```

## Best Practices

1. **Use automatic iteration** when possible
2. **Choose appropriate page size** - 100-500 usually good
3. **Stop early** if you don't need all results
4. **Cache results** locally when appropriate
5. **Handle network errors** gracefully during iteration
6. **Don't store cursors** - fetch results again if needed

## Common Patterns

### Process All Results

```php
$results = $search->search('organism_id:9606');

foreach ($results as $entry) {
    processEntry($entry);
}
```

### Collect Into Array

```php
$allEntries = [];

foreach ($results as $entry) {
    $allEntries[] = $entry;
}
```

### Sample Every N Results

```php
$results = $search->search('organism_id:9606');
$n = 10;
$count = 0;

foreach ($results as $entry) {
    if ($count % $n === 0) {
        echo $entry['primaryAccession'] . "\n";
    }
    $count++;
}
```

### Batch Processing

```php
$results = $search->search('organism_id:9606');
$batch = [];
$batchSize = 100;

foreach ($results as $entry) {
    $batch[] = $entry;
    
    if (count($batch) === $batchSize) {
        processBatch($batch);
        $batch = [];
    }
}

if (!empty($batch)) {
    processBatch($batch);
}
```

## API Constraints

- **Max page size**: 500 (enforced by API)
- **Cursor lifetime**: Unknown, assume short-lived
- **Results stability**: Results should be consistent during pagination

## Related

- [Search Documentation](search.md) - Search with pagination
- [ID Mapping](id-mapping.md) - Paginated mapping results
- [Error Handling](error-handling.md) - Handle pagination errors
