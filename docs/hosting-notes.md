# Shared Hosting Compatibility

This library is designed to work on shared hosting environments with minimal dependencies.

## Transport Layer

The library automatically selects the best available HTTP transport:

### 1. cURL (Preferred)

- **Fastest** - Optimized C implementation
- **Most Compatible** - Works everywhere
- **Required**: `php.ini` has `extension=curl`

```php
$httpClient = HttpClientFactory::create();
echo $httpClient->getTransportName();  // "cURL"
```

### 2. PHP Streams (Fallback)

- **Always Available** - Part of core PHP
- **Slower** - Pure PHP implementation
- **Required**: Nothing (enabled by default)

```php
$httpClient = HttpClientFactory::create();
echo $httpClient->getTransportName();  // "PHP Streams"
```

## Checking Available Transports

```php
$available = HttpClientFactory::getAvailableTransports();

echo "cURL: " . ($available['cURL'] ? 'Available' : 'Not available') . "\n";
echo "Streams: " . ($available['PHP Streams'] ? 'Available' : 'Not available') . "\n";

if (!$available['cURL'] && !$available['PHP Streams']) {
    die("No HTTP transport available on this server\n");
}
```

## Enabling cURL on Shared Hosting

### Check if Installed

```php
php -m | grep curl
```

Or via PHP:

```php
if (extension_loaded('curl')) {
    echo "cURL is available\n";
} else {
    echo "cURL is not available\n";
}
```

### Enable in php.ini

1. Access your hosting control panel (cPanel, Plesk, etc.)
2. Find PHP configuration
3. Ensure `extension=curl.so` (Linux) or `extension=php_curl.dll` (Windows)
4. Restart PHP

### If Not Available

Contact your hosting provider - most modern hosts enable cURL by default.

## Shared Hosting Considerations

### 1. File Permissions

```php
// Don't need write permissions, only read
$httpClient = HttpClientFactory::create();
```

### 2. Outbound Network Access

Ensure your host allows outbound HTTPS:

```php
// Test connectivity
try {
    $httpClient = HttpClientFactory::create();
    $entry = new UniProtEntry($httpClient);
    $protein = $entry->get('P12345');
    echo "✓ Network access working\n";
} catch (UniProtException $e) {
    if ($e->isTransportError()) {
        echo "✗ No outbound network access\n";
    }
}
```

### 3. Timeout Considerations

Shared hosting may have strict timeouts:

```php
// Use shorter polling intervals
$mapping->waitForCompletion(
    $jobId,
    2,   // 2 second polling interval
    15   // max 15 polls (30 seconds total)
);
```

### 4. Memory Usage

Large search results can consume memory:

```php
// Stop iteration early
$results = $search->search('organism_id:9606');

$count = 0;
foreach ($results as $entry) {
    // Process entry
    
    if (++$count >= 1000) {
        break;  // Stop to save memory
    }
}
```

### 5. CPU Limits

Batch operations in smaller chunks:

```php
// Process in smaller batches
$allIds = [...]; // millions of IDs

foreach (array_chunk($allIds, 10000) as $chunk) {
    $jobId = $mapping->submitAndWait(
        'UniProtKB_AC-ID',
        'Ensembl',
        $chunk
    );
    
    // Process results
}
```

## Performance Optimization

### Cache Results

```php
$cacheDir = sys_get_temp_dir() . '/uniprot_cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

function getCachedEntry($accession, $maxAge = 86400) {
    global $cacheDir;
    
    $cacheFile = $cacheDir . '/' . $accession . '.json';
    
    if (file_exists($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        if ($age < $maxAge) {
            return json_decode(file_get_contents($cacheFile), true);
        }
    }
    
    // Fetch from API
    $httpClient = HttpClientFactory::create();
    $entry = new UniProtEntry($httpClient);
    $protein = $entry->get($accession);
    
    // Cache result
    file_put_contents($cacheFile, json_encode($protein));
    
    return $protein;
}
```

### Batch Operations

```php
// Combine requests
$results = [];

foreach (array_chunk($accessions, 10) as $batch) {
    $batchResults = $entry->getBatch($batch);
    $results = array_merge($results, $batchResults);
    
    // Small delay between batches
    sleep(1);
}
```

### Efficient Pagination

```php
// Use larger page sizes when possible
$results = $search->search('organism_id:9606', [
    'size' => 500  // Maximum
]);

foreach ($results as $entry) {
    // Process
}
```

## Debugging on Shared Hosting

### Check Transport

```php
echo "Using transport: " . HttpClientFactory::getInstance()->getTransportName() . "\n";
```

### Verify API Access

```php
try {
    $httpClient = HttpClientFactory::create();
    $entry = new UniProtEntry($httpClient);
    $protein = $entry->get('P12345');
    echo "✓ API access working\n";
} catch (UniProtException $e) {
    echo "✗ API access failed: " . $e->getMessage() . "\n";
}
```

### Check Available Functions

```php
echo "stream_context_create: " . (function_exists('stream_context_create') ? 'Yes' : 'No') . "\n";
echo "file_get_contents: " . (function_exists('file_get_contents') ? 'Yes' : 'No') . "\n";
echo "curl_init: " . (function_exists('curl_init') ? 'Yes' : 'No') . "\n";
echo "json_decode: " . (function_exists('json_decode') ? 'Yes' : 'No') . "\n";
```

### Error Logging

```php
ini_set('log_errors', '1');
ini_set('error_log', '/path/to/error.log');

try {
    // API call
} catch (UniProtException $e) {
    error_log($e->getDetailedMessage());
}
```

## Common Issues

### "No HTTP transport available"

**Cause:** Neither cURL nor streams are available.

**Solution:** Contact hosting provider or enable in php.ini.

### "Connection refused"

**Cause:** Outbound network access blocked.

**Solution:** Check firewall settings with hosting provider.

### "Timeout exceeded"

**Cause:** Large ID mapping or search taking too long.

**Solution:** Reduce batch sizes or increase timeout limits.

### "Out of memory"

**Cause:** Loading too many results at once.

**Solution:** Use pagination with smaller page sizes.

### "SSL certificate error"

**Cause:** SSL verification issue.

**Solution:** Ensure PHP has up-to-date CA certificates.

## Best Practices for Shared Hosting

1. **Test early** - Verify API access on deployment
2. **Use caches** - Reduce API calls
3. **Batch wisely** - Balance between requests and memory
4. **Handle timeouts** - Expect longer response times
5. **Monitor resources** - Watch CPU/memory/connections
6. **Implement retry** - Handle transient failures gracefully
7. **Log errors** - Track issues for debugging
8. **Set limits** - Don't iterate forever
9. **Use short polling** - For ID mapping jobs
10. **Test on target host** - Before going live

## Related

- [Installation](../docs/overview.md#installation)
- [Error Handling](error-handling.md)
- [Performance Tips](search.md#performance-tips)
