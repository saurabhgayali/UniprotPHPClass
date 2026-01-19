# Pagination

Cursor-based pagination using HTTP Link headers.

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

## Manual Pagination

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

### Page Size Selection

```php
// Small pages - more requests
$results = $search->search(
    'organism_id:9606',
    ['size' => 10]
);

// Large pages - fewer requests, more memory
$results = $search->search(
    'organism_id:9606',
    ['size' => 500]  // max
);

// Balanced
$results = $search->search(
    'organism_id:9606',
    ['size' => 100]
);
```

### Stop Early

```php
$results = $search->search('organism_id:9606', ['size' => 50]);

$count = 0;
foreach ($results as $entry) {
    // Process entry
    
    // Stop after 1000
    if (++$count >= 1000) {
        break;
    }
}
```

### Count Results

```php
// Simple iteration count
$count = 0;
foreach ($results as $entry) {
    $count++;
}
echo "Total: $count\n";
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
