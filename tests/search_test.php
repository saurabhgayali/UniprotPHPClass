<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Exception/UniProtException.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientInterface.php';
require_once dirname(__DIR__) . '/src/Http/CurlClient.php';
require_once dirname(__DIR__) . '/src/Http/StreamClient.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientFactory.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtSearch.php';

use UniProtPHP\Http\CurlClient;
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtSearch;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development
CurlClient::setVerifySSL(false);

$result = null;
$error = null;
$query = 'organism_id:9606 AND reviewed:true';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (empty($query)) {
        $error = 'Please enter a search query';
    } else {
        try {
            $httpClient = HttpClientFactory::create();
            $search = new UniProtSearch($httpClient);
            $result = $search->getFirstPage($query, ['size' => 10]);
        } catch (UniProtException $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - UniProt PHP Library</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header h1 {
            margin-bottom: 10px;
        }
        
        .back-btn {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .search-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .search-box h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .input-group input {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .input-group button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity 0.3s;
        }
        
        .input-group button:hover {
            opacity: 0.9;
        }
        
        .examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .example-btn {
            padding: 8px 12px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .example-btn:hover {
            background: #e8e8e8;
        }
        
        .hint {
            color: #888;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #999;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .json-viewer {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            line-height: 1.6;
            color: #333;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .results-table th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        .results-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .results-table tr:hover {
            background: #f9f9f9;
        }
        
        .accession-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .accession-link:hover {
            text-decoration: underline;
        }
        
        .accordion {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .accordion-item {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .accordion-btn {
            width: 100%;
            padding: 15px 20px;
            background: #f8f9fa;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-align: left;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        
        .accordion-btn:hover {
            background: #f0f0f0;
        }
        
        .accordion-btn.active {
            background: #667eea;
            color: white;
        }
        
        .accordion-content {
            display: none;
            padding: 20px;
            background: #f9f9f9;
            border-top: 1px solid #ddd;
        }
        
        .accordion-content.active {
            display: block;
        }
        
        .accordion-content pre {
            background: white;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.9em;
            line-height: 1.5;
            border: 1px solid #ddd;
        }
        
        .pagination {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
            margin: 30px 0;
        }
        
        .pagination button {
            padding: 10px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity 0.3s;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination button:hover:not(:disabled) {
            opacity: 0.9;
        }
        
        .pagination-info {
            color: #666;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔎 Advanced Search</h1>
            <p>Query UniProt database with powerful search syntax</p>
            <a href="index.php" class="back-btn">← Back to Home</a>
        </div>
        
        <div class="search-box">
            <h2>Search Query</h2>
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="query" value="<?php echo htmlspecialchars($query); ?>" placeholder="e.g., organism_id:9606 AND reviewed:true" required>
                    <button type="submit">Search</button>
                </div>
                <div class="hint">💡 Use boolean operators: AND, OR, NOT. Field search: field_name:value</div>
                
                <div class="examples">
                    <button type="button" class="example-btn" onclick="setQuery('organism_id:9606 AND reviewed:true')">Human Proteins</button>
                    <button type="button" class="example-btn" onclick="setQuery('keyword:cancer')">Cancer Related</button>
                    <button type="button" class="example-btn" onclick="setQuery('length:[100 TO 200]')">Length 100-200</button>
                    <button type="button" class="example-btn" onclick="setQuery('organism_id:9606 AND mass:[5000 TO 10000]')">Human (5-10kDa)</button>
                </div>
            </form>
        </div>
        
        <div class="accordion">
            <div class="accordion-item">
                <button class="accordion-btn" onclick="toggleAccordion(this)">
                    <span>🔍 Search Code Example</span>
                    <span>▼</span>
                </button>
                <div class="accordion-content">
                    <pre>&lt;?php
require_once 'src/Http/HttpClientFactory.php';
require_once 'src/UniProt/UniProtSearch.php';

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtSearch;
use UniProtPHP\Exception\UniProtException;

// Create HTTP client and search instance
$httpClient = HttpClientFactory::create();
$search = new UniProtSearch($httpClient);

// Get first page of results (10 per page)
$result = $search->getFirstPage(
    'organism_id:9606 AND reviewed:true',
    ['size' => 10]
);

// Process results
if (isset($result['results'])) {
    foreach ($result['results'] as $entry) {
        echo $entry['primaryAccession'] . ': ';
        echo $entry['proteinDescription']['recommendedName']
                   ['fullName']['value'] . "\n";
    }
}

// Check for pagination (next page available)
if (isset($result['links']['next'])) {
    echo "More results available!\n";
}
?&gt;</pre>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($result && isset($result['results'])): ?>
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('table')">📊 Table View</button>
                <button class="tab-btn" onclick="switchTab('list')">📋 List View</button>
                <button class="tab-btn" onclick="switchTab('raw')">📄 Raw JSON</button>
            </div>
            
            <div id="table" class="tab-content active">
                <h3>Search Results (<?php echo count($result['results']); ?> entries)</h3>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Accession</th>
                            <th>Protein Name</th>
                            <th>Organism</th>
                            <th>Length</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['results'] as $entry): ?>
                        <tr>
                            <td>
                                <a href="entry_test.php?accession=<?php echo htmlspecialchars($entry['primaryAccession'] ?? ''); ?>" class="accession-link">
                                    <?php echo htmlspecialchars($entry['primaryAccession'] ?? 'N/A'); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                    $name = 'N/A';
                                    if (isset($entry['proteinDescription']['recommendedName'])) {
                                        $name = $entry['proteinDescription']['recommendedName']['fullName']['value'] ?? 'N/A';
                                    }
                                    echo htmlspecialchars(substr($name, 0, 50));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($entry['organism']['scientificName'] ?? 'N/A'); ?></td>
                            <td><?php echo $entry['sequence']['length'] ?? 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div id="list" class="tab-content">
                <h3>Search Results (<?php echo count($result['results']); ?> entries)</h3>
                <div style="line-height: 1.8;">
                    <?php foreach ($result['results'] as $index => $entry): ?>
                    <div style="padding: 15px; background: #f9f9f9; margin-bottom: 10px; border-radius: 6px; border-left: 4px solid #667eea;">
                        <strong><?php echo ($index + 1); ?>.</strong>
                        <a href="entry_test.php?accession=<?php echo htmlspecialchars($entry['primaryAccession'] ?? ''); ?>" style="color: #667eea; text-decoration: none;">
                            <strong><?php echo htmlspecialchars($entry['primaryAccession'] ?? 'N/A'); ?></strong>
                        </a>
                        - 
                        <?php 
                            $name = 'N/A';
                            if (isset($entry['proteinDescription']['recommendedName'])) {
                                $name = $entry['proteinDescription']['recommendedName']['fullName']['value'] ?? 'N/A';
                            }
                            echo htmlspecialchars(substr($name, 0, 100));
                        ?>
                        <br>
                        <small style="color: #999;">
                            🧬 Length: <?php echo $entry['sequence']['length'] ?? 'N/A'; ?> | 
                            🌍 <?php echo htmlspecialchars($entry['organism']['scientificName'] ?? 'N/A'); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="raw" class="tab-content">
                <h3>Raw JSON Response</h3>
                <div class="json-viewer">
                    <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function setQuery(q) {
            document.querySelector('input[name="query"]').value = q;
            document.querySelector('form').submit();
        }
        
        function toggleAccordion(btn) {
            btn.classList.toggle('active');
            btn.nextElementSibling.classList.toggle('active');
        }
    </script>
</body>
</html>
