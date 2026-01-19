<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Exception/UniProtException.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientInterface.php';
require_once dirname(__DIR__) . '/src/Http/CurlClient.php';
require_once dirname(__DIR__) . '/src/Http/StreamClient.php';
require_once dirname(__DIR__) . '/src/Http/HttpClientFactory.php';
require_once dirname(__DIR__) . '/src/UniProt/UniProtIdMapping.php';

use UniProtPHP\Http\CurlClient;
use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtIdMapping;
use UniProtPHP\Exception\UniProtException;

// Disable SSL verification for development
CurlClient::setVerifySSL(false);

$submitResult = null;
$retrieveResult = null;
$error = null;
$jobId = '';
$ids = "P05067\nP12345";
$fromDb = 'UniProtKB_AC-ID';
$toDb = 'Ensembl';

// Handle ID submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'submit') {
        $ids = trim($_POST['ids']);
        $fromDb = $_POST['from_db'] ?? 'UniProtKB_AC-ID';
        $toDb = $_POST['to_db'] ?? 'Ensembl';
        
        if (empty($ids)) {
            $error = 'Please enter at least one ID';
        } else {
            try {
                $idArray = array_filter(array_map('trim', explode("\n", $ids)));
                if (empty($idArray)) {
                    throw new Exception('No valid IDs provided');
                }
                
                $httpClient = HttpClientFactory::create();
                $mapping = new UniProtIdMapping($httpClient);
                $jobId = $mapping->submit($fromDb, $toDb, $idArray);
                $submitResult = ['jobId' => $jobId];
            } catch (UniProtException $e) {
                $error = $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } 
    elseif ($action === 'retrieve') {
        $jobId = trim($_POST['job_id']);
        
        if (empty($jobId)) {
            $error = 'Please enter a job ID';
        } else {
            try {
                $httpClient = HttpClientFactory::create();
                $mapping = new UniProtIdMapping($httpClient);
                
                // Check status first
                $statusResponse = $mapping->status($jobId);
                $status = $statusResponse['jobStatus'] ?? 'UNKNOWN';
                
                if ($status !== 'FINISHED') {
                    $error = "Job status: {$status}. Please wait for job to complete.";
                } else {
                    $results = $mapping->getResults($jobId);
                    $details = $mapping->getDetails($jobId);
                    $retrieveResult = [
                        'details' => $details,
                        'results' => $results
                    ];
                }
            } catch (UniProtException $e) {
                $error = $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Mapping - UniProt PHP Library</title>
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
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        button {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity 0.3s;
        }
        
        button:hover {
            opacity: 0.9;
        }
        
        .hint {
            color: #888;
            font-size: 0.9em;
            margin-top: 8px;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3c3;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #c33;
        }
        
        .job-result {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #667eea;
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
        }
        
        .tab-content.active {
            display: block;
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
        
        .copyable {
            user-select: all;
            padding: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            font-family: monospace;
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
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 ID Mapping</h1>
            <p>Map protein IDs between different databases</p>
            <a href="index.php" class="back-btn">← Back to Home</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="accordion">
            <div class="accordion-item">
                <button class="accordion-btn" onclick="toggleAccordion(this)">
                    <span>🔄 ID Mapping Code Example</span>
                    <span>▼</span>
                </button>
                <div class="accordion-content">
                    <pre>&lt;?php
require_once 'src/Http/HttpClientFactory.php';
require_once 'src/UniProt/UniProtIdMapping.php';

use UniProtPHP\Http\HttpClientFactory;
use UniProtPHP\UniProt\UniProtIdMapping;
use UniProtPHP\Exception\UniProtException;

// Create HTTP client and mapping instance
$httpClient = HttpClientFactory::create();
$mapping = new UniProtIdMapping($httpClient);

// Step 1: Submit mapping job
$jobId = $mapping->submit(
    'UniProtKB_AC-ID',  // from database
    'Ensembl',          // to database
    ['P05067', 'P12345'] // protein IDs
);

echo "Job ID: {$jobId}\n";

// Step 2: Check job status (wait for completion)
$statusResponse = $mapping->status($jobId);
$status = $statusResponse['jobStatus']; // 'RUNNING' or 'FINISHED'

// Step 3: Wait for job to complete and get results
$results = $mapping->waitForCompletion($jobId);

// Step 4: Get detailed mapping information
$details = $mapping->getDetails($jobId);

// Process results
foreach ($results as $mapping_entry) {
    echo $mapping_entry['from'] . " -> " . 
         $mapping_entry['to'] . "\n";
}
?&gt;</pre>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Submit Mapping -->
            <div class="card">
                <h2>📤 Submit Mapping Job</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="submit">
                    
                    <div class="form-group">
                        <label>From Database</label>
                        <select name="from_db">
                            <option value="UniProtKB_AC-ID" selected>UniProtKB AC/ID</option>
                            <option value="Ensembl">Ensembl</option>
                            <option value="GeneID">GeneID (NCBI)</option>
                        </select>
                        <div class="hint">Source database for your IDs</div>
                    </div>
                    
                    <div class="form-group">
                        <label>To Database</label>
                        <select name="to_db">
                            <option value="UniProtKB_AC-ID">UniProtKB AC/ID</option>
                            <option value="Ensembl" selected>Ensembl</option>
                            <option value="GeneID">GeneID (NCBI)</option>
                        </select>
                        <div class="hint">Target database for mapping</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Input IDs (one per line)</label>
                        <textarea name="ids" required><?php echo htmlspecialchars($ids); ?></textarea>
                        <div class="hint">Max 100,000 IDs per job. Examples: P05067, P12345, Q96QH7</div>
                    </div>
                    
                    <button type="submit">Submit Mapping Job</button>
                </form>
                
                <?php if ($submitResult): ?>
                <div class="success">
                    ✅ Job submitted successfully!
                    <div class="job-result">
                        <strong>Job ID:</strong><br>
                        <div class="copyable"><?php echo htmlspecialchars($submitResult['jobId']); ?></div>
                        <div class="hint" style="margin-top: 10px;">💡 Copy this ID to retrieve results. Check back in a few seconds!</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Retrieve Results -->
            <div class="card">
                <h2>📥 Retrieve Results</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="retrieve">
                    
                    <div class="form-group">
                        <label>Job ID</label>
                        <input type="text" name="job_id" value="<?php echo htmlspecialchars($jobId); ?>" placeholder="Paste job ID here" required>
                        <div class="hint">The job ID returned from the submission above</div>
                    </div>
                    
                    <button type="submit">Retrieve Results</button>
                </form>
                
                <?php if ($retrieveResult): ?>
                <div class="success">
                    ✅ Results retrieved!
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Results Display -->
        <?php if ($retrieveResult): ?>
        <div class="card" style="margin-top: 30px;">
            <h2>📊 Mapping Results</h2>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('table')">📋 Results Table</button>
                <button class="tab-btn" onclick="switchTab('details')">ℹ️ Job Details</button>
                <button class="tab-btn" onclick="switchTab('raw')">📄 Raw JSON</button>
            </div>
            
            <div id="table" class="tab-content active">
                <?php 
                $results = $retrieveResult['results']['results'] ?? [];
                if (!empty($results)):
                ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>From ID</th>
                            <th>To ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['from'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($result['to'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #999;">No mapping results found.</p>
                <?php endif; ?>
                
                <?php if (!empty($retrieveResult['results']['failedIds'])): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fef3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
                    <strong>⚠️ Failed IDs:</strong>
                    <ul>
                        <?php foreach ($retrieveResult['results']['failedIds'] as $failed): ?>
                        <li><?php echo htmlspecialchars($failed); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="details" class="tab-content">
                <table class="results-table">
                    <tr>
                        <td><strong>From Database:</strong></td>
                        <td><?php echo htmlspecialchars($retrieveResult['details']['from'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>To Database:</strong></td>
                        <td><?php echo htmlspecialchars($retrieveResult['details']['to'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Input IDs:</strong></td>
                        <td><?php echo count($retrieveResult['details']['ids'] ?? []); ?> IDs</td>
                    </tr>
                    <tr>
                        <td><strong>Mapped:</strong></td>
                        <td><?php echo count($retrieveResult['results']['results'] ?? []); ?> results</td>
                    </tr>
                </table>
            </div>
            
            <div id="raw" class="tab-content">
                <div class="json-viewer">
                    <pre><?php echo htmlspecialchars(json_encode($retrieveResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                </div>
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
        
        function toggleAccordion(btn) {
            btn.classList.toggle('active');
            btn.nextElementSibling.classList.toggle('active');
        }
    </script>
</body>
</html>
