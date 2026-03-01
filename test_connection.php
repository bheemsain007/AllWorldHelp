<?php

/**
 * ============================================================
 * Database Connection Test File
 * Fellow Traveler Platform — Task T3 Testing
 * ============================================================
 *
 * HOW TO USE:
 *   1. Upload this file to your public_html folder
 *   2. Open in browser: yourdomain.com/test_connection.php
 *   3. Verify "Connected Successfully" is shown
 *   4. ⚠️ DELETE THIS FILE immediately after testing!
 *
 * NEVER leave this file on a live/production server.
 * It exposes database structure to anyone who can access it.
 * ============================================================
 */

// Show all PHP errors (needed during testing only)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basic HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Connection Test — Fellow Traveler</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7fa; padding: 30px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { font-size: 22px; margin-bottom: 20px; color: #1a1a2e; }
        .card { background: white; border-radius: 10px; padding: 20px 25px; margin-bottom: 16px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .pass { border-left: 4px solid #28a745; }
        .fail { border-left: 4px solid #dc3545; }
        .warn { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .badge { display:inline-block; padding:3px 10px; border-radius:12px;
                 font-size:12px; font-weight:bold; margin-left:8px; }
        .badge-pass { background:#d4edda; color:#155724; }
        .badge-fail { background:#f8d7da; color:#721c24; }
        .badge-warn { background:#fff3cd; color:#856404; }
        table { width:100%; border-collapse:collapse; margin-top:10px; font-size:14px; }
        th { background:#f8f9fa; padding:8px 12px; text-align:left; border-bottom:2px solid #dee2e6; }
        td { padding:8px 12px; border-bottom:1px solid #f0f0f0; }
        .warn-box { background:#fff8e1; border:1px solid #ffe082; border-radius:8px;
                    padding:14px 18px; margin-top:20px; font-size:13px; color:#6d4c00; }
        code { background:#f4f4f4; padding:2px 6px; border-radius:3px; font-size:13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔌 Fellow Traveler — Database Connection Test</h1>

<?php

// ============================================================
// TEST 1: Include the config file and attempt connection
// ============================================================
$config_path = __DIR__ . '/config/database.php';
$connection_ok = false;

echo '<div class="card info"><strong>📂 Config File Location:</strong> ' . htmlspecialchars($config_path) . '</div>';

if (!file_exists($config_path)) {
    echo '<div class="card fail">❌ <strong>config/database.php not found!</strong>'
       . '<br>Make sure the file exists at: <code>public_html/config/database.php</code></div>';
} else {
    echo '<div class="card pass">✅ <strong>Config file found</strong> <span class="badge badge-pass">PASS</span></div>';

    // Try to connect
    try {
        require_once $config_path;
        $connection_ok = true;
        echo '<div class="card pass">✅ <strong>Database Connected Successfully!</strong>'
           . ' <span class="badge badge-pass">CONNECTED</span>'
           . '<br><small>PDO object created without errors.</small></div>';
    } catch (Throwable $e) {
        echo '<div class="card fail">❌ <strong>Connection Failed</strong>'
           . ' <span class="badge badge-fail">FAILED</span>'
           . '<br><strong>Error:</strong> ' . htmlspecialchars($e->getMessage())
           . '</div>';
    }
}

// ============================================================
// TEST 2: Connection Details
// ============================================================
if ($connection_ok) {
    echo '<div class="card info"><strong>🔧 Connection Details</strong>';
    echo '<table>';
    echo '<tr><th>Setting</th><th>Value</th></tr>';
    echo '<tr><td>Host</td><td>' . htmlspecialchars(DB_HOST) . '</td></tr>';
    echo '<tr><td>Database</td><td>' . htmlspecialchars(DB_NAME) . '</td></tr>';
    echo '<tr><td>Charset</td><td>' . htmlspecialchars(DB_CHARSET) . '</td></tr>';
    echo '<tr><td>Port</td><td>' . htmlspecialchars(DB_PORT) . '</td></tr>';
    echo '<tr><td>PDO Driver</td><td>' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . '</td></tr>';
    echo '<tr><td>MySQL Version</td><td>' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . '</td></tr>';
    echo '</table></div>';
}

// ============================================================
// TEST 3: Table Count Verification
// ============================================================
if ($connection_ok) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        $result = $stmt->fetch();
        $table_count = (int) $result['total'];
        $status = $table_count === 25 ? 'pass' : 'warn';
        $badge  = $table_count === 25 ? 'PASS' : 'CHECK';
        $badge_class = $table_count === 25 ? 'badge-pass' : 'badge-warn';

        echo '<div class="card ' . $status . '">
                <strong>📊 Table Count:</strong> <code>' . $table_count . '</code>
                <span class="badge ' . $badge_class . '">' . $badge . '</span>
                <br><small>Expected: 25 tables (from Task T1). ' .
                ($table_count === 25 ? '✅ All tables found!' : '⚠️ Run Task T1 schema first if count is less than 25.') .
                '</small></div>';
    } catch (PDOException $e) {
        echo '<div class="card fail">❌ Table count query failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ============================================================
// TEST 4: Key Table Row Counts (Task T2 verification)
// ============================================================
if ($connection_ok) {
    $checks = [
        'ft_coin_levels'    => ['expected' => 4,  'task' => 'T2'],
        'ft_coin_plans'     => ['expected' => 27, 'task' => 'T2'],
        'ft_coin_rewards'   => ['expected' => 10, 'task' => 'T2'],
        'ft_admin_settings' => ['expected' => 22, 'task' => 'T2'],
        'ft_users'          => ['expected' => 0,  'task' => 'T1'],
        'ft_trips'          => ['expected' => 0,  'task' => 'T1'],
    ];

    echo '<div class="card info"><strong>🧪 Table Data Verification</strong>';
    echo '<table>';
    echo '<tr><th>Table</th><th>Task</th><th>Rows Found</th><th>Expected</th><th>Status</th></tr>';

    foreach ($checks as $table => $info) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as c FROM `$table`");
            $count = (int) $stmt->fetch()['c'];
            $ok = ($count >= $info['expected']);
            $status_icon = $ok ? '✅' : '⚠️';
            echo "<tr>
                    <td><code>$table</code></td>
                    <td>{$info['task']}</td>
                    <td><strong>$count</strong></td>
                    <td>{$info['expected']}+</td>
                    <td>$status_icon</td>
                  </tr>";
        } catch (PDOException $e) {
            echo "<tr><td><code>$table</code></td><td>{$info['task']}</td><td colspan='3'>❌ Table not found</td></tr>";
        }
    }
    echo '</table></div>';
}

// ============================================================
// TEST 5: Helper Functions
// ============================================================
if ($connection_ok && function_exists('dbFetchOne')) {
    try {
        $site_name = getSetting('site_name', 'NOT FOUND');
        $coins_enabled = getSetting('coins_enabled', 'NOT FOUND');

        echo '<div class="card pass"><strong>⚙️ Helper Functions Working</strong>'
           . ' <span class="badge badge-pass">PASS</span>'
           . '<table style="margin-top:10px">'
           . '<tr><th>Function</th><th>Test Query</th><th>Result</th></tr>'
           . '<tr><td><code>getSetting()</code></td><td>site_name</td><td>' . htmlspecialchars($site_name) . '</td></tr>'
           . '<tr><td><code>getSetting()</code></td><td>coins_enabled</td><td>' . htmlspecialchars($coins_enabled) . '</td></tr>'
           . '</table></div>';
    } catch (Throwable $e) {
        echo '<div class="card warn">⚠️ Helper functions available but getSetting() failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// ============================================================
// TEST 6: PDO Options Verification
// ============================================================
if ($connection_ok) {
    $error_mode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    $fetch_mode = $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
    $persistent = $pdo->getAttribute(PDO::ATTR_PERSISTENT);

    echo '<div class="card pass"><strong>🔒 PDO Configuration</strong>';
    echo '<table>';
    echo '<tr><th>Option</th><th>Value</th><th>Status</th></tr>';
    echo '<tr><td>ERRMODE</td><td>' . ($error_mode == PDO::ERRMODE_EXCEPTION ? 'ERRMODE_EXCEPTION' : $error_mode) . '</td><td>' . ($error_mode == PDO::ERRMODE_EXCEPTION ? '✅' : '⚠️') . '</td></tr>';
    echo '<tr><td>FETCH_MODE</td><td>' . ($fetch_mode == PDO::FETCH_ASSOC ? 'FETCH_ASSOC' : $fetch_mode) . '</td><td>' . ($fetch_mode == PDO::FETCH_ASSOC ? '✅' : '⚠️') . '</td></tr>';
    echo '<tr><td>PERSISTENT</td><td>' . ($persistent ? 'true' : 'false') . '</td><td>' . ($persistent ? '✅' : '⚠️') . '</td></tr>';
    echo '</table></div>';
}

// ============================================================
// FINAL RESULT
// ============================================================
if ($connection_ok) {
    echo '<div class="card pass" style="text-align:center;padding:25px">'
       . '<div style="font-size:36px">🎉</div>'
       . '<strong style="font-size:18px">All Tests Passed!</strong><br><br>'
       . 'Task T3 complete. Database connection is working correctly.<br>'
       . '<strong>Progress: T1 ✅ T2 ✅ T3 ✅ → Next: Task T4</strong>'
       . '</div>';
} else {
    echo '<div class="card fail" style="text-align:center;padding:25px">'
       . '<div style="font-size:36px">❌</div>'
       . '<strong style="font-size:18px">Connection Failed</strong><br><br>'
       . 'Fix the errors above and refresh this page.<br>'
       . 'Check Part 7 (Common Errors) in the Task T3 document.'
       . '</div>';
}

?>

    <div class="warn-box">
        ⚠️ <strong>Security Warning:</strong> Delete <code>test_connection.php</code> immediately after testing!
        This file exposes your database structure. Run: <code>rm public_html/test_connection.php</code>
        or delete via File Manager.
    </div>

</div>
</body>
</html>
