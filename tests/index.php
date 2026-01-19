<?php
/**
 * UniProt PHP Library - Web Interface
 * Interactive testing and demonstration
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniProt PHP Library - Interactive Testing</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .card-header {
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-header h2 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .card-body ul {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .card-body li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        
        .card-body li:last-child {
            border-bottom: none;
        }
        
        .card-body li:before {
            content: "✓ ";
            color: #667eea;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: opacity 0.3s ease;
            width: 100%;
            text-align: center;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .stats {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stats h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .stat-items {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧬 UniProt PHP Library</h1>
            <p>Interactive Testing & Demonstration Interface</p>
        </div>
        
        <div class="grid">
            <!-- Entry Retrieval Card -->
            <div class="card">
                <div class="card-header">
                    <h2>🔍 Entry Retrieval</h2>
                    <p>Search UniProtKB by Accession</p>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Retrieve single entry details</li>
                        <li>View parsed JSON results</li>
                        <li>Display raw API response</li>
                        <li>Field-by-field breakdown</li>
                    </ul>
                    <a href="entry_test.php" class="btn">Open Entry Retrieval</a>
                </div>
            </div>
            
            <!-- Search Card -->
            <div class="card">
                <div class="card-header">
                    <h2>🔎 Advanced Search</h2>
                    <p>Query UniProt Database</p>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Pre-built search examples</li>
                        <li>View raw JSON results</li>
                        <li>Parse as table (ID + Name)</li>
                        <li>Pagination support</li>
                    </ul>
                    <a href="search_test.php" class="btn">Open Search</a>
                </div>
            </div>
            
            <!-- ID Mapping Card -->
            <div class="card">
                <div class="card-header">
                    <h2>🔄 ID Mapping</h2>
                    <p>Map Database IDs</p>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Submit mapping jobs</li>
                        <li>Get batch number</li>
                        <li>Retrieve results</li>
                        <li>Job status tracking</li>
                    </ul>
                    <a href="mapping_test.php" class="btn">Open ID Mapping</a>
                </div>
            </div>
        </div>
        
        <div class="stats">
            <h3>Library Statistics</h3>
            <div class="stat-items">
                <div class="stat-item">
                    <div class="stat-number">13</div>
                    <div class="stat-label">Source Files</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">3500+</div>
                    <div class="stat-label">Lines of Code</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">3</div>
                    <div class="stat-label">Core APIs</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">32</div>
                    <div class="stat-label">Tests Passing</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
