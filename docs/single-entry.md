# Single Entry Retrieval

Retrieve individual UniProtKB entries by accession number.

## Endpoint

```
GET https://rest.uniprot.org/uniprotkb/{accession}
```

## Basic Usage

```php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;

$httpClient = HttpClientFactory::create();
$entry = new UniProtEntry($httpClient);

$protein = $entry->get('P12345');
```

## Response Structure

The response includes rich protein information:

```php
[
    'primaryAccession' => 'P12345',
    'uniProtkbId' => 'AATM_RABIT',
    'organism' => [
        'scientificName' => 'Oryctolagus cuniculus',
        'commonName' => 'Rabbit',
        'taxonId' => 9986
    ],
    'proteinDescription' => [
        'recommendedName' => [
            'fullName' => ['value' => 'Aspartate aminotransferase, mitochondrial']
        ]
    ],
    'sequence' => [
        'value' => 'MALLHSARV...',
        'length' => 430,
        'molWeight' => 47409
    ],
    'comments' => [...],  // Function, catalytic activity, cofactors, etc.
    'features' => [...],  // Binding sites, modified residues, etc.
    'references' => [...]
]
```

## Output Formats

### JSON (Default)

```php
$protein = $entry->get('P12345', 'json');
// Returns parsed PHP array
```

### FASTA

```php
$fasta = $entry->get('P12345', 'fasta');
echo $fasta['body'];
// Output:
// >sp|P12345|AATM_RABIT Aspartate aminotransferase, mitochondrial...
// MALLHSARVLSGVASAFHPGLAAAASARASSWWAHVEMGPPDPILGVTEAYKR...
```

### XML

```php
$xml = $entry->get('P12345', 'xml');
echo $xml['body'];
```

### Tab-separated Values (TXT)

```php
$txt = $entry->get('P12345', 'txt');
echo $txt['body'];
```

### GFF

```php
$gff = $entry->get('P12345', 'gff');
echo $gff['body'];
```

## Retrieve Specific Fields

Only fetch the fields you need to reduce bandwidth:

```php
$data = $entry->getWithFields('P12345', [
    'accession',           // Primary accession
    'protein_name',        // Protein names
    'organism_name',       // Organism
    'sequence',            // Amino acid sequence
    'cc_function',         // Function comment
    'ft_active_site',      // Active site features
    'xref_pdb'            // PDB cross-references
]);
```

### Available Fields

**Identification:**
- `accession` - Primary accession
- `id` - Entry name
- `gene_names` - Gene names
- `organism_name` - Species name
- `organism_id` - Taxonomy ID

**Sequence:**
- `sequence` - Amino acid sequence
- `length` - Sequence length
- `mass` - Molecular weight
- `fragment` - Is fragment?

**Function:**
- `cc_function` - Function description
- `ec` - EC number
- `cc_catalytic_activity` - Catalytic activity
- `cc_cofactor` - Cofactor information

**Structure:**
- `ft_binding` - Binding sites
- `ft_active_site` - Active site
- `ft_domain` - Protein domains

**Cross-references:**
- `xref_pdb` - 3D structures
- `xref_interpro` - InterPro domains
- `xref_kegg` - KEGG pathways

See [UniProt documentation](https://www.uniprot.org/help/return_fields) for complete field list.

## Batch Retrieval

```php
$results = $entry->getBatch([
    'P12345',
    'P00750',
    'P05067'
]);

foreach ($results as $result) {
    if (isset($result['error'])) {
        echo "Error for {$result['accession']}: {$result['error']}\n";
    } else {
        echo $result['primaryAccession'] . "\n";
    }
}
```

## Check Entry Existence

```php
if ($entry->exists('P12345')) {
    $protein = $entry->get('P12345');
} else {
    echo "Entry not found\n";
}
```

## Error Handling

```php
use UniProtPHP\Exception\UniProtException;

try {
    $protein = $entry->get('INVALID');
} catch (UniProtException $e) {
    // Entry not found (404)
    if ($e->getHttpStatus() === 404) {
        echo "Protein not in UniProtKB\n";
    }
    
    // Other HTTP errors
    else if ($e->getHttpStatus() >= 400) {
        echo "Request error: " . $e->getDetailedMessage() . "\n";
    }
    
    // Network/transport errors
    else {
        echo "Connection error: " . $e->getMessage() . "\n";
    }
}
```

## Common Use Cases

### Get Protein Function

```php
$data = $entry->getWithFields('P12345', ['cc_function']);
foreach ($data['comments'] as $comment) {
    if ($comment['commentType'] === 'FUNCTION') {
        echo $comment['texts'][0]['value'];
    }
}
```

### Get Subcellular Location

```php
$data = $entry->getWithFields('P12345', ['cc_subcellular_location']);
foreach ($data['comments'] as $comment) {
    if ($comment['commentType'] === 'SUBCELLULAR LOCATION') {
        foreach ($comment['subcellularLocations'] as $location) {
            echo $location['location']['value'] . "\n";
        }
    }
}
```

### Get GO Annotations

```php
$data = $entry->getWithFields('P12345', ['go', 'go_id']);
foreach ($data['uniProtKBCrossReferences'] as $ref) {
    if ($ref['database'] === 'GO') {
        echo $ref['id'] . "\n";
    }
}
```

### Get PDB Structures

```php
$data = $entry->getWithFields('P12345', ['xref_pdb']);
foreach ($data['uniProtKBCrossReferences'] as $ref) {
    if ($ref['database'] === 'PDB') {
        echo $ref['id'] . "\n";
    }
}
```

## Performance Tips

1. **Request only needed fields** - Reduces response size
2. **Use batch operations** - Combine multiple requests
3. **Cache results locally** - Avoid re-fetching
4. **Check existence before fetching** - Avoid 404 errors
5. **Handle errors gracefully** - Continue with next entry on failure

## API Constraints

- **Max accessions per request**: 1 (use batch retrieval for multiple)
- **Max fields per request**: No hard limit
- **Response time**: Typically < 1 second
- **Rate limiting**: No official limit, but be respectful

## Related

- [Search documentation](search.md) - Search entries
- [ID Mapping documentation](id-mapping.md) - Map identifiers
- [Error Handling](error-handling.md) - Exception handling
