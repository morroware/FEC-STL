<?php
/**
 * API Test Page - Test printer profile endpoints
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .test { margin: 20px 0; padding: 15px; background: #2a2a2a; border-radius: 5px; }
        .success { color: #0f0; }
        .error { color: #f00; }
        pre { background: #000; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>API Endpoint Tests</h1>

    <?php
    // Test 1: Get Printers
    echo '<div class="test">';
    echo '<h2>Test 1: Get Printers</h2>';
    try {
        $printers = getPrinters();
        echo '<p class="success">✓ Success: Retrieved ' . count($printers) . ' printers</p>';
        echo '<pre>' . htmlspecialchars(json_encode($printers, JSON_PRETTY_PRINT)) . '</pre>';
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // Test 2: Get Filaments
    echo '<div class="test">';
    echo '<h2>Test 2: Get Filaments</h2>';
    try {
        $filaments = getFilaments();
        echo '<p class="success">✓ Success: Retrieved ' . count($filaments) . ' filaments</p>';
        echo '<pre>' . htmlspecialchars(json_encode($filaments, JSON_PRETTY_PRINT)) . '</pre>';
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // Test 3: Get Print Profiles
    echo '<div class="test">';
    echo '<h2>Test 3: Get Print Profiles (for any model)</h2>';
    try {
        // Get a sample model first
        $models = getAllModels(1);
        if (!empty($models['models'])) {
            $modelId = $models['models'][0]['id'];
            echo '<p>Testing with model ID: ' . htmlspecialchars($modelId) . '</p>';
            $profiles = getPrintProfiles($modelId);
            echo '<p class="success">✓ Success: Retrieved ' . count($profiles) . ' profiles</p>';
            echo '<pre>' . htmlspecialchars(json_encode($profiles, JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            echo '<p class="error">No models found to test with</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    echo '</div>';

    // Test 4: Check API endpoint accessibility
    echo '<div class="test">';
    echo '<h2>Test 4: API Endpoint Response (JavaScript fetch)</h2>';
    echo '<button onclick="testAPI()">Test API via JavaScript</button>';
    echo '<div id="api-result"></div>';
    echo '</div>';
    ?>

    <script>
        async function testAPI() {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<p>Testing...</p>';

            try {
                // Test get_printers endpoint
                const response = await fetch('api.php?action=get_printers');
                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = `<p class="success">✓ API Success: Retrieved ${data.printers.length} printers</p>`;
                    resultDiv.innerHTML += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } else {
                    resultDiv.innerHTML = `<p class="error">✗ API Error: ${data.error || 'Unknown error'}</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="error">✗ JavaScript Error: ${error.message}</p>`;
            }
        }
    </script>

    <div class="test">
        <h2>Console Debugging Tips</h2>
        <p>Open browser console (F12) and check for:</p>
        <ul>
            <li>JavaScript errors</li>
            <li>Failed network requests</li>
            <li>API response errors</li>
        </ul>
    </div>
</body>
</html>
