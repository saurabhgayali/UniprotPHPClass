<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Exception/UniProtException.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientInterface.php';
require_once dirname(__DIR__) . '/src/Http/CurlClient.php';
require_once dirname(__DIR__) . '/src/Http/StreamClient.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientFactory.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtEntry.php';

use UniProtPHP\Http\CurlClient;
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development
CurlClient::setVerifySSL(false);

$result = null;
$error = null;
$accession = 'P12345';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accession'])) {
    $accession = trim($_POST['accession']);
    
    if (empty($accession)) {
        $error = 'Please enter an accession number';
    } else {
        try {
            $httpClient = HttpClientFactory::create();
            $entry = new UniProtEntry($httpClient);
            $result = $entry->get($accession);
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
    <title>Entry Retrieval - UniProt PHP Library</title>
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
        
        .hint {
            color: #888;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .accordion {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .accordion h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .accordion-item {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .accordion-btn {
            width: 100%;
            padding: 15px;
            background: #f8f9fa;
            border: none;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            text-align: left;
            font-weight: 600;
            color: #333;
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .accordion-btn:hover {
            background: #f0f0f0;
        }
        
        .accordion-btn.active {
            background: #667eea;
            color: white;
            border-bottom-color: #667eea;
        }
        
        .accordion-content {
            display: none;
            padding: 15px;
            background: #f9f9f9;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            line-height: 1.6;
            overflow-x: auto;
            color: #333;
        }
        
        .accordion-content.active {
            display: block;
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
            font-size: 0.9em;
            line-height: 1.6;
            color: #333;
        }
        
        .table-view {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-view th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        
        .table-view td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .table-view tr:hover {
            background: #f9f9f9;
        }
        
        .field-label {
            color: #667eea;
            font-weight: 600;
        }
        
        .section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .section:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Entry Retrieval</h1>
            <p>Search for UniProtKB entries by accession number</p>
            <a href="index.php" class="back-btn">← Back to Home</a>
        </div>
        
        <div class="search-box">
            <h2>Search Entry</h2>
            <form method="POST">
                <div class="input-group">
                    <input type="text" name="accession" value="<?php echo htmlspecialchars($accession); ?>" placeholder="e.g., P12345" required>
                    <button type="submit">Search</button>
                </div>
                <div class="hint">💡 Examples: P12345 (APP), P05067 (Amyloid Beta), P00750 (Tissue plasminogen)</div>
            </form>
        </div>
        
        <div class="accordion">
            <h3>📚 How It Works</h3>
            
            <div class="accordion-item">
                <button class="accordion-btn" onclick="toggleAccordion(this)">
                    <span>🔍 Entry Retrieval Code</span>
                    <span>▼</span>
                </button>
                <div class="accordion-content">
                    <pre>&lt;?php
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtEntry;

// Create HTTP client (auto-selects cURL or Streams)
$httpClient = HttpClientFactory::create();

// Create entry retrieval instance
$entry = new UniProtEntry($httpClient);

// Get single entry by accession
$protein = $entry->get('P12345');
echo $protein['primaryAccession'];  // P12345
echo $protein['sequence']['value']; // Amino acid sequence

// Check if entry exists
if ($entry->exists('P12345')) {
    echo "Entry found!";
}

// Get batch of entries
$results = $entry->getBatch(['P12345', 'P00750']);
foreach ($results as $p) {
    echo $p['primaryAccession'];
}

// Get specific fields only
$protein = $entry->get('P12345', 'json');
?&gt;</pre>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($result): ?>
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('parsed')">📊 Parsed Data</button>
                <button class="tab-btn" onclick="switchTab('raw')">📋 Raw JSON</button>
            </div>
            
            <div id="parsed" class="tab-content active">
                <?php 
                // Display key fields
                $primaryAccession = $result['primaryAccession'] ?? 'N/A';
                $proteinName = '';
                if (isset($result['proteinDescription']['recommendedName'])) {
                    $proteinName = $result['proteinDescription']['recommendedName']['fullName']['value'] ?? 'N/A';
                }
                $organism = $result['organism']['scientificName'] ?? 'N/A';
                $sequence = $result['sequence']['value'] ?? '';
                $sequenceLength = $result['sequence']['length'] ?? 0;
                $mass = $result['sequence']['molWeight'] ?? 'N/A';
                ?>
                
                <div class="section">
                    <h3>📌 Basic Information</h3>
                    <table class="table-view">
                        <tr>
                            <td><span class="field-label">Primary Accession:</span></td>
                            <td><strong><?php echo htmlspecialchars($primaryAccession); ?></strong></td>
                        </tr>
                        <tr>
                            <td><span class="field-label">Protein Name:</span></td>
                            <td><?php echo htmlspecialchars($proteinName); ?></td>
                        </tr>
                        <tr>
                            <td><span class="field-label">Organism:</span></td>
                            <td><?php echo htmlspecialchars($organism); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="section">
                    <h3>🧬 Sequence Information</h3>
                    <table class="table-view">
                        <tr>
                            <td><span class="field-label">Length:</span></td>
                            <td><?php echo $sequenceLength; ?> amino acids</td>
                        </tr>
                        <tr>
                            <td><span class="field-label">Molecular Weight:</span></td>
                            <td><?php echo number_format($mass, 2); ?> Da</td>
                        </tr>
                        <tr>
                            <td><span class="field-label">First 100 amino acids:</span></td>
                            <td><code><?php echo htmlspecialchars(substr($sequence, 0, 100)); ?>...</code></td>
                        </tr>
                    </table>
                </div>
                
                <?php if (isset($result['comments'])): ?>
                <div class="section">
                    <h3>📝 Comments</h3>
                    <table class="table-view">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Text</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($result['comments'], 0, 5) as $comment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comment['commentType'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($comment['text'] ?? '', 0, 100)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="raw" class="tab-content">
                <div class="json-viewer">
                    <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function toggleAccordion(btn) {
            btn.classList.toggle('active');
            btn.nextElementSibling.classList.toggle('active');
        }
    </script>
</body>
</html>
