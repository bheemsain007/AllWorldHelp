<?php
// =============================================
// Fellow Traveler — Database Config (EXAMPLE)
// =============================================
// Copy this file to database.php and fill in your Hostinger credentials.
// NEVER commit database.php to Git!

define('DB_HOST',    'localhost');             // Hostinger pe 'localhost' hi hota hai
define('DB_NAME',    'uXXXXXXXX_traveler');   // Hostinger DB name (hPanel se milega)
define('DB_USER',    'uXXXXXXXX_ftuser');     // Hostinger DB username
define('DB_PASS',    'YOUR_PASSWORD_HERE');    // Aapka DB password
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    '3306');
define('APP_ENV',    'production');            // 'development' ya 'production'

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true,
    PDO::ATTR_TIMEOUT            => 10,
];

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
    $pdo->exec("SET time_zone = '+05:30'");
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
} catch (PDOException $e) {
    error_log('[FellowTraveler] DB Error: ' . $e->getMessage());
    die('Database connection failed. Please check your credentials.');
}

function getDB(): PDO { global $pdo; return $pdo; }
function dbFetchAll(string $sql, array $params = []): array { $stmt = getDB()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
function dbFetchOne(string $sql, array $params = []): ?array { $stmt = getDB()->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(); return $row ?: null; }
function dbExecute(string $sql, array $params = []): int { $stmt = getDB()->prepare($sql); $stmt->execute($params); return $stmt->rowCount(); }
function dbInsert(string $sql, array $params = []): int { $stmt = getDB()->prepare($sql); $stmt->execute($params); return (int) getDB()->lastInsertId(); }
function getSetting(string $key, mixed $default = null): mixed { $row = dbFetchOne("SELECT setting_value FROM ft_admin_settings WHERE setting_key = ? LIMIT 1", [$key]); return $row ? $row['setting_value'] : $default; }
