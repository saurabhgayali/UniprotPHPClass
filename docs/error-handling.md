# Error Handling

Comprehensive error handling with structured exception information.

## Exception Structure

All errors throw `UniProtException` with detailed information:

```php
use UniProtPHP\Exception\UniProtException;

try {
    $entry->get('P99999999');
} catch (UniProtException $e) {
    // Get basic message
    echo $e->getMessage();
    
    // Get detailed information
    echo $e->getDetailedMessage();
    echo $e->getHttpStatus();
    echo $e->getApiErrorCode();
    echo $e->getApiErrorMessage();
    echo $e->getApiResponse();
}
```

## Error Types

### Client Errors (4xx)

```php
try {
    $entry->get('INVALID');
} catch (UniProtException $e) {
    if ($e->isClientError()) {
        echo "Client error: " . $e->getMessage() . "\n";
        
        // Check specific status
        switch ($e->getHttpStatus()) {
            case 400:
                echo "Bad request\n";
                break;
            case 404:
                echo "Entry not found\n";
                break;
            case 429:
                echo "Rate limited\n";
                break;
        }
    }
}
```

### Server Errors (5xx)

```php
try {
    $search->search('organism_id:9606');
} catch (UniProtException $e) {
    if ($e->isServerError()) {
        echo "Server error: " . $e->getHttpStatus() . "\n";
        // Retry after delay
        sleep(5);
    }
}
```

### Transport Errors (Network)

```php
try {
    $entry->get('P12345');
} catch (UniProtException $e) {
    if ($e->isTransportError()) {
        echo "Network error: " . $e->getMessage() . "\n";
    }
}
```

## Common Scenarios

### Entry Not Found

```php
try {
    $entry->get('P99999999');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 404) {
        echo "Entry not in UniProtKB\n";
    }
}
```

### Invalid Query

```php
try {
    $search->search('invalid::syntax[[');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 400) {
        echo "Invalid search query\n";
        echo "Details: " . $e->getApiErrorMessage() . "\n";
    }
}
```

### Rate Limiting

```php
try {
    $entry->get('P12345');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 429) {
        echo "Rate limited\n";
        echo "Retrying in 10 seconds...\n";
        sleep(10);
        // Retry operation
    }
}
```

### Job Failed

```php
try {
    $mapping->waitForCompletion($jobId);
} catch (UniProtException $e) {
    echo "Job failed: " . $e->getMessage() . "\n";
}
```

### Validation Error

```php
try {
    $entry->get('');  // Empty accession
} catch (UniProtException $e) {
    echo "Validation: " . $e->getMessage() . "\n";
}
```

## Batch Operations

### Skip Failures

```php
$accessions = ['P12345', 'P00750', 'INVALID'];

$results = $entry->getBatch($accessions);

foreach ($results as $result) {
    if (isset($result['error'])) {
        echo "Skipping failed entry: " . $result['error'] . "\n";
    } else {
        echo "Processing: " . $result['primaryAccession'] . "\n";
    }
}
```

### Log Failures

```php
$failed = [];

foreach ($accessions as $accession) {
    try {
        $protein = $entry->get($accession);
        // Process protein
    } catch (UniProtException $e) {
        $failed[$accession] = [
            'status' => $e->getHttpStatus(),
            'message' => $e->getMessage()
        ];
    }
}

if (!empty($failed)) {
    echo "Failed entries:\n";
    print_r($failed);
}
```

## Retry Logic

### Exponential Backoff

```php
function retryWithBackoff($callable, $maxRetries = 3) {
    $attempt = 0;
    $delay = 1;

    while ($attempt < $maxRetries) {
        try {
            return $callable();
        } catch (UniProtException $e) {
            if ($e->isServerError() || $e->isTransportError()) {
                $attempt++;
                if ($attempt < $maxRetries) {
                    echo "Retry in {$delay} seconds...\n";
                    sleep($delay);
                    $delay *= 2;  // Exponential backoff
                }
            } else {
                throw;  // Don't retry client errors
            }
        }
    }

    throw new Exception("Max retries exceeded");
}

// Usage
$protein = retryWithBackoff(function() use ($entry) {
    return $entry->get('P12345');
});
```

### Simple Retry

```php
function getWithRetry($entry, $accession, $maxRetries = 3) {
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            return $entry->get($accession);
        } catch (UniProtException $e) {
            if ($i === $maxRetries - 1) {
                throw;
            }
            sleep(2);
        }
    }
}
```

## Detailed Debugging

### Full Error Information

```php
try {
    // Some operation
} catch (UniProtException $e) {
    echo "=== Error Details ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "HTTP Status: " . $e->getHttpStatus() . "\n";
    echo "API Error Code: " . $e->getApiErrorCode() . "\n";
    echo "API Error Message: " . $e->getApiErrorMessage() . "\n";
    echo "Full Response: " . $e->getApiResponse() . "\n";
    echo "Detailed: " . $e->getDetailedMessage() . "\n";
    echo "Exception Class: " . get_class($e) . "\n";
}
```

### Logging

```php
function logException(UniProtException $e, $context = '') {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'context' => $context,
        'message' => $e->getMessage(),
        'httpStatus' => $e->getHttpStatus(),
        'apiErrorCode' => $e->getApiErrorCode(),
        'apiErrorMessage' => $e->getApiErrorMessage(),
        'detailedMessage' => $e->getDetailedMessage()
    ];

    error_log(json_encode($log));
}

// Usage
try {
    $protein = $entry->get('P12345');
} catch (UniProtException $e) {
    logException($e, 'entry_retrieval');
}
```

## Handling Specific Errors

### Check HTTP Status

```php
try {
    // API call
} catch (UniProtException $e) {
    $status = $e->getHttpStatus();
    
    if ($status === 400) {
        // Bad request - likely validation error
    } elseif ($status === 404) {
        // Not found
    } elseif ($status === 429) {
        // Rate limited
    } elseif ($status >= 500) {
        // Server error - retry later
    } elseif ($status === 0) {
        // Network error
    }
}
```

### Check Error Type

```php
try {
    // API call
} catch (UniProtException $e) {
    if ($e->isClientError()) {
        // Client-side error, likely won't retry
    } elseif ($e->isServerError()) {
        // Server error, retry recommended
    } elseif ($e->isTransportError()) {
        // Network error, check connectivity
    }
}
```

## Error Prevention

### Validate Input

```php
function safeGet($entry, $accession) {
    // Validate accession format
    if (empty($accession)) {
        throw new InvalidArgumentException('Accession cannot be empty');
    }

    if (!preg_match('/^[A-Z0-9]+$/', $accession)) {
        throw new InvalidArgumentException('Invalid accession format');
    }

    return $entry->get($accession);
}
```

### Check Availability First

```php
$available = HttpClientFactory::getAvailableTransports();
if (!$available['cURL'] && !$available['PHP Streams']) {
    die("No HTTP transport available\n");
}
```

### Safe Iteration

```php
try {
    foreach ($results as $entry) {
        try {
            processEntry($entry);
        } catch (Exception $e) {
            echo "Error processing entry: " . $e->getMessage() . "\n";
            continue;  // Continue with next entry
        }
    }
} catch (UniProtException $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}
```

## Best Practices

1. **Always catch UniProtException** - Specific error handling
2. **Check error type** - Client vs Server vs Network
3. **Log errors** - Track for debugging
4. **Implement retry logic** - For transient errors
5. **Validate input** - Prevent preventable errors
6. **Graceful degradation** - Continue when possible
7. **User-friendly messages** - Don't expose full error details to users
8. **Timeout handling** - Set reasonable timeouts

## Related

- [Single Entry Retrieval](single-entry.md) - Entry-specific errors
- [Search](search.md) - Search-specific errors
- [ID Mapping](id-mapping.md) - Job-specific errors
