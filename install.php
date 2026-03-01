<?php
/**
 * Fellow Traveler — One-Click Installer
 * Ye file database.php create karegi aur phir khud delete ho jayegi.
 * ⚠️ USE ONCE ONLY — delete after setup!
 */

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host    = trim($_POST['db_host'] ?? 'localhost');
    $name    = trim($_POST['db_name'] ?? '');
    $user    = trim($_POST['db_user'] ?? '');
    $pass    = $_POST['db_pass'] ?? '';
    $appenv  = 'production';

    // Test connection first
    try {
        $dsn = "mysql:host=$host;port=3306;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Create database.php
        $content = "<?php\n"
            . "define('DB_HOST',    '$host');\n"
            . "define('DB_NAME',    '$name');\n"
            . "define('DB_USER',    '$user');\n"
            . "define('DB_PASS',    '$pass');\n"
            . "define('DB_CHARSET', 'utf8mb4');\n"
            . "define('DB_PORT',    '3306');\n"
            . "define('APP_ENV',    'production');\n\n"
            . "\$pdo_options = [\n"
            . "    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
            . "    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
            . "    PDO::ATTR_EMULATE_PREPARES   => false,\n"
            . "    PDO::ATTR_PERSISTENT         => true,\n"
            . "    PDO::ATTR_TIMEOUT            => 10,\n"
            . "];\n\n"
            . "try {\n"
            . "    \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;\n"
            . "    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$pdo_options);\n"
            . "    \$pdo->exec(\"SET time_zone = '+05:30'\");\n"
            . "    \$pdo->exec(\"SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'\");\n"
            . "} catch (PDOException \$e) {\n"
            . "    error_log('[FellowTraveler] DB Error: ' . \$e->getMessage());\n"
            . "    die('Database connection failed.');\n"
            . "}\n\n"
            . "function getDB(): PDO { global \$pdo; return \$pdo; }\n"
            . "function dbFetchAll(string \$sql, array \$params = []): array { \$stmt = getDB()->prepare(\$sql); \$stmt->execute(\$params); return \$stmt->fetchAll(); }\n"
            . "function dbFetchOne(string \$sql, array \$params = []): ?array { \$stmt = getDB()->prepare(\$sql); \$stmt->execute(\$params); \$row = \$stmt->fetch(); return \$row ?: null; }\n"
            . "function dbExecute(string \$sql, array \$params = []): int { \$stmt = getDB()->prepare(\$sql); \$stmt->execute(\$params); return \$stmt->rowCount(); }\n"
            . "function dbInsert(string \$sql, array \$params = []): int { \$stmt = getDB()->prepare(\$sql); \$stmt->execute(\$params); return (int) getDB()->lastInsertId(); }\n"
            . "function getSetting(string \$key, mixed \$default = null): mixed { \$row = dbFetchOne(\"SELECT setting_value FROM ft_admin_settings WHERE setting_key = ? LIMIT 1\", [\$key]); return \$row ? \$row['setting_value'] : \$default; }\n";

        file_put_contents(__DIR__ . '/config/database.php', $content);
        
        // Self-delete this installer
        @unlink(__FILE__);
        $done = true;

    } catch (PDOException $e) {
        $error = 'DB Connection failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<title>Fellow Traveler — Setup</title>
<style>
  body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
  .box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 400px; }
  h2 { color: #2d3748; margin-bottom: 24px; text-align: center; }
  label { display: block; margin-bottom: 6px; color: #4a5568; font-weight: bold; font-size: 14px; }
  input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; box-sizing: border-box; margin-bottom: 16px; }
  button { width: 100%; padding: 12px; background: #4f46e5; color: white; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
  button:hover { background: #4338ca; }
  .success { background: #f0fff4; border: 2px solid #48bb78; padding: 20px; border-radius: 8px; text-align: center; color: #276749; }
  .error { background: #fff5f5; border: 2px solid #fc8181; padding: 12px; border-radius: 6px; color: #c53030; margin-bottom: 16px; font-size: 14px; }
  .warn { background: #fffbeb; border: 1px solid #f6ad55; padding: 10px; border-radius: 6px; color: #744210; font-size: 12px; margin-bottom: 20px; }
</style>
</head>
<body>
<div class="box">
  <?php if ($done): ?>
    <div class="success">
      <h2>✅ Setup Complete!</h2>
      <p><strong>database.php</strong> successfully create ho gayi!</p>
      <p>Installer automatically delete ho gaya (secure).</p>
      <p style="margin-top:16px"><a href="/" style="color:#4f46e5">→ Site pe jaao</a></p>
    </div>
  <?php else: ?>
    <h2>🚀 Fellow Traveler Setup</h2>
    <div class="warn">⚠️ Ye page use karne ke baad automatically delete ho jayega.</div>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label>DB Host</label>
      <input name="db_host" value="localhost" required>
      <label>DB Name</label>
      <input name="db_name" value="u888159614_fellow_t" required>
      <label>DB Username</label>
      <input name="db_user" value="u888159614_fellow_t" required>
      <label>DB Password</label>
      <input name="db_pass" type="password" placeholder="Aapka DB password" required>
      <button type="submit">✅ Setup Karo</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
