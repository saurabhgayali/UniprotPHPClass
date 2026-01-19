# Pagination Implementation Guide

## Architecture

The UniProt PHP Class implements cursor-based pagination using the UniProt REST API's `Link` header for navigation.

### Key Concepts

**User's Page Size (NOT 500)**
- User selects page size: 10, 20, or 50 results per page
- Each API call fetches exactly the number of results user requested
- We **never** query 500 results automatically

**Internal Optimization (Hidden from User)**
- Internally, we use batch size of 500 for efficient cursor navigation
- This reduces the number of API calls needed to reach a specific offset
- User still sees their requested page size in results

**Cursor-Based Pagination**
- API provides `Link` header with `rel="next"` URL
- We follow these cursors to navigate through batches
- Each cursor points to the next batch of 500 results

### Algorithm

When requesting offset=1390, pageSize=10:

```
1. Calculate which 500-result batch contains offset 1390
   completeBatches = floor(1390 / 500) = 2
   offsetInBatch = 1390 % 500 = 390

2. Make first API call: size=500, cursor position 0
   
3. Skip 2 complete batches by following cursors from Link headers
   - Call 1: Gets batch 1 (results 0-499)
   - Extract cursor for next batch from Link header
   - Wait 0.5 seconds (rate limiting)
   - Call 2: Gets batch 2 (results 500-999)
   - Extract cursor for next batch from Link header
   - Wait 0.5 seconds

4. Fetch batch 3 (results 1000-1499)
   - Contains offset 1390
   - Extract starting at position 390

5. Return results 390-399 (10 results from pageSize)
```

## Implementation Details

### getPaginatedResults($query, $offset, $pageSize)

**Parameters:**
- `$query` (string): UniProt search query
- `$offset` (int): Starting position (0-based)
- `$pageSize` (int): Results per page (10, 20, or 50)

**Returns:**
```php
[
    'results' => [...],          // Array of entry objects
    'total' => 20420,            // Total results available
    'offset' => 1390,            // Requested offset
    'pageSize' => 10,            // Results returned
    'currentPage' => 140,        // Current page number
    'totalPages' => 2042,        // Total pages
    'previousOffset' => 1380,    // Previous page offset (or null)
    'nextOffset' => 1400,        // Next page offset (or null)
    'pageLinks' => [1400, 1410], // Array of suggested next offsets
]
```

## Async Search Implementation

### Server-Side (search_test.php)

Receives URL parameters:
- `query` - Search query string
- `offset` - Starting position
- `size` - Results per page (10, 20, or 50)

Example: `search_test.php?query=organism_id:9606&offset=0&size=10`

Returns JSON response with results and pageLinks.

### Client-Side (search_async.html)

HTML/JavaScript interface that:
1. Calls `search_test.php` asynchronously
2. Displays results
3. Shows pagination links
4. Allows navigation to next/previous pages

## Rate Limiting

- 0.5 second delay between consecutive API calls
- Prevents API rate limiting on large result sets
- Automatic with `usleep(500000)` in getPaginatedResults

## Error Handling

- 30 second timeout per API request (increased from 20s default)
- 10 second connection timeout
- Empty results returned on errors instead of throwing exceptions
- Graceful fallback if Link header is missing

## Usage Examples

### PHP Backend
```php
$search = new UniProtSearch();
$result = $search->getPaginatedResults(
    'organism_id:9606 AND reviewed:true',
    1390,  // Page 140 with pageSize=10
    10
);

foreach ($result['results'] as $entry) {
    echo $entry['primaryAccession'] . "\n";
}

// Navigate to next page
if ($result['nextOffset'] !== null) {
    $nextPage = $search->getPaginatedResults(
        'organism_id:9606 AND reviewed:true',
        $result['nextOffset'],
        10
    );
}
```

### JavaScript/Async
```javascript
// Fetch page asynchronously
const response = await fetch(
    'search_test.php?query=organism_id:9606&offset=0&size=10'
);
const data = await response.json();

// Use pageLinks to build navigation
data.pageLinks.forEach(offset => {
    const pageNum = Math.floor(offset / 10) + 1;
    console.log(`Page ${pageNum} available at offset ${offset}`);
});
```

## Performance Notes

- Page 1 (offset 0): ~1-2 seconds
- Page 50 (offset 490): ~2-3 seconds (1 batch skip + 0.5s wait)
- Page 140 (offset 1390): ~5-8 seconds (2 batch skips + 1s wait)
- Page 204 (offset 2030): ~10+ seconds (4 batch skips + 2s wait)

The time increases as we skip more batches, which is expected due to:
1. More API calls to follow cursors
2. Rate limiting delays between calls
3. Larger result sets (500 results per batch) take longer to process
