# Search with Pagination

Advanced text search across UniProtKB with cursor-based pagination.

## Endpoint

```
GET https://rest.uniprot.org/uniprotkb/search
```

## Basic Usage

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtSearch;

$httpClient = HttpClientFactory::create();
$search = new UniProtSearch($httpClient);

// Search returns iterable results that auto-paginate
$results = $search->search('organism_id:9606 AND reviewed:true');

foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
}
```

## Query Syntax

### Simple Terms

```php
// All entries containing all terms
$search->search('human kinase');

// Any order is fine
$search->search('kinase human');
```

### Exact Phrase

```php
$search->search('"human kinase"');
```

### Boolean Operators

```php
// AND (explicit or implicit)
$search->search('human AND kinase');
$search->search('human && kinase');

// OR
$search->search('human OR mouse');
$search->search('human || mouse');

// NOT / Exclusion
$search->search('human NOT obsolete');
$search->search('human -obsolete');
$search->search('human !obsolete');

// Complex with parentheses
$search->search('(human OR mouse) AND kinase AND reviewed:true');
```

### Wildcards

```php
// Start or end
$search->search('kinase*');      // kinase, kinases, kinase-like...
$search->search('*kinase');      // kinase, protein kinase...
$search->search('*kinase*');     // Contains kinase

// Single character
$search->search('p53');          // Also matches P53, P-53...
```

### Field-Specific Search

```php
// Query specific fields
$search->search('gene:TP53');                    // Gene name
$search->search('organism_id:9606');            // Taxonomy ID
$search->search('organism_name:human');         // Organism name
$search->search('protein_name:insulin');        // Protein name
$search->search('lit_author:Smith');            // Author name
$search->search('database:pdb');                // Has PDB reference
$search->search('xref:pdb-1aut');              // Specific cross-ref
```

### Range Queries

```php
// Numeric ranges
$search->search('length:[500 TO 1000]');       // 500-1000 amino acids
$search->search('mass:[50000 TO 100000]');     // 50-100 kDa
$search->search('xref_count_pdb:[20 TO *]');   // 20+ PDB entries

// Date ranges
$search->search('date_created:[2020-01-01 TO *]');           // Since 2020
$search->search('date_modified:[2023-01-01 TO 2023-12-31]'); // In 2023
```

## Search Options

```php
$results = $search->search(
    'organism_id:9606',
    [
        'size' => 100,                // Results per page (max 500)
        'format' => 'json',           // Output format
        'fields' => [                 // Specific columns
            'accession',
            'protein_name',
            'organism_name'
        ],
        'includeIsoform' => true,     // Include isoforms
        'compressed' => false         // gzip compression
    ]
);
```

### Parameters

| Parameter | Default | Max | Description |
|-----------|---------|-----|-------------|
| size | 25 | 500 | Results per page |
| format | json | - | json, tsv, xml, fasta, gff |
| fields | - | - | Comma-separated field names |
| includeIsoform | false | - | Include protein isoforms |
| compressed | false | - | Request gzip compression |
| cursor | - | - | For pagination |

## Pagination

### Automatic Iteration

The SearchResults object auto-handles pagination:

```php
$results = $search->search('organism_id:9606', ['size' => 50]);

// Automatically fetches next pages as needed
foreach ($results as $entry) {
    echo $entry['primaryAccession'] . "\n";
}
```

### First Page Only

```php
$firstPage = $search->getFirstPage('organism_id:9606', ['size' => 10]);

foreach ($firstPage['results'] as $entry) {
    echo $entry['primaryAccession'] . "\n";
}
```

### Manual Pagination

```php
$pageSize = 100;

// Page 1
$page1 = $search->getFirstPage('organism_id:9606', ['size' => $pageSize]);
foreach ($page1['results'] as $entry) {
    // Process...
}

// Next page (use cursor from Link header)
// The library handles this automatically via SearchResults iterator
```

### Cursor-Based Pagination

Internally uses Link header with rel="next":

```
Link: <https://rest.uniprot.org/uniprotkb/search?...&cursor=ABC123>; rel="next"
```

The library extracts and follows this automatically.

## Search Options

### Specific Fields

Reduce response size by requesting only needed fields:

```php
$results = $search->search(
    'insulin',
    [
        'fields' => [
            'accession',
            'id',
            'protein_name',
            'organism_name',
            'length',
            'mass'
        ]
    ]
);
```

### Alternative Formats

```php
// TSV (Tab-Separated Values)
$results = $search->search('organism_id:9606', [
    'format' => 'tsv',
    'fields' => ['accession', 'protein_name', 'organism_name']
]);

// FASTA
$results = $search->search('kinase AND organism_id:9606', [
    'format' => 'fasta',
    'size' => 10
]);

// XML
$results = $search->search('insulin', ['format' => 'xml']);
```

## Common Queries

### Human Proteins

```php
// All reviewed human proteins
$results = $search->search('organism_id:9606 AND reviewed:true');

// Human proteins for specific gene
$results = $search->search('organism_id:9606 AND gene:BRCA1');
```

### Kinases

```php
// All kinases
$results = $search->search('(protein_name:kinase OR keyword:KW-0597)');

// Human kinases with structures
$results = $search->search(
    'organism_id:9606 AND protein_name:kinase AND xref_count_pdb:[1 TO *]'
);
```

### Disease-Related

```php
// Cancer-related proteins
$results = $search->search('cc_disease:cancer');

// Diabetes markers
$results = $search->search('cc_disease:diabetes AND reviewed:true');
```

### Recently Updated

```php
// Added in last 30 days
$results = $search->search('date_created:[2024-12-20 TO *]');

// Modified recently
$results = $search->search('date_modified:[2024-12-15 TO *]');
```

### Sequence Properties

```php
// Large proteins
$results = $search->search('length:[1000 TO *]');

// Small proteins
$results = $search->search('length:[1 TO 100]');

// Specific mass range
$results = $search->search('mass:[50000 TO 100000]');
```

### With Structures

```php
// Has PDB structure
$results = $search->search('xref:pdb');

// Has multiple structures
$results = $search->search('xref_count_pdb:[5 TO *]');
```

### Complex Multi-Condition

```php
$results = $search->search(
    '(organism_id:9606 OR organism_id:10090) ' .   // Human or Mouse
    'AND reviewed:true ' .                          // Only reviewed
    'AND protein_name:kinase ' .                    // Must be kinase
    'AND NOT fragment:true ' .                      // Not fragments
    'AND mass:[40000 TO 150000]'                    // Size range
);
```

## Error Handling

```php
use UniProtPHP\Exception\UniProtException;

try {
    $results = $search->search('invalid::syntax[[');
} catch (UniProtException $e) {
    if ($e->getHttpStatus() === 400) {
        echo "Invalid query syntax\n";
    }
}
```

## Performance Tips

1. **Use specific fields** - Reduces response size
2. **Increase page size** - Fewer requests (max 500)
3. **Use field searches** - More precise queries
4. **Limit results** - Stop iteration early if not needed
5. **Cache results** - Store locally when reasonable
6. **Use appropriate page size** - Balance size vs memory usage

## API Constraints

- **Max page size**: 500 results
- **Query length**: No stated limit, but very complex queries may timeout
- **Result set size**: Practical limit around 500K-1M results
- **Timeout**: Default 30 seconds per request
- **Rate limiting**: No official limit

## Response Headers

The search includes useful response headers:

```php
$firstPage = $search->getFirstPage('organism_id:9606');

// x-total-results: Total matching entries (if available)
// Link: Next page cursor (auto-handled by SearchResults)
```

## Building Custom URLs

For custom integration:

```php
$url = $search->buildUrl(
    'organism_id:9606 AND reviewed:true',
    [
        'size' => 100,
        'format' => 'json',
        'fields' => ['accession', 'protein_name']
    ]
);

// https://rest.uniprot.org/uniprotkb/search?query=...&size=100&format=json&fields=...
```

## Related

- [Single Entry Retrieval](single-entry.md) - Fetch individual entries
- [ID Mapping](id-mapping.md) - Map identifiers
- [Pagination Details](pagination.md) - Cursor-based pagination
- [Error Handling](error-handling.md) - Exception handling
