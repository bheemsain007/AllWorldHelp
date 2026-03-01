# 🚀 Fellow Traveler — Hostinger Deployment Guide

## Step 1: GitHub pe Push Karo

```bash
# GitHub pe nayi repository banao (github.com/new)
# Phir ye commands chalao:

git remote add origin https://github.com/AAPKA_USERNAME/fellow-traveler.git
git branch -M main
git push -u origin main
```

---

## Step 2: Hostinger hPanel mein MySQL Database Banao

1. **hPanel** login karo → **Databases** → **MySQL Databases**
2. **Create Database** karo:
   - Database Name: `traveler` (poora naam hoga: `uXXXXXXXX_traveler`)
3. **Create User** karo:
   - Username: `ftuser` (poora: `uXXXXXXXX_ftuser`)
   - Password: strong password daalo
4. **User ko Database se link karo** → All Privileges

---

## Step 3: Database Import Karo

1. hPanel → **phpMyAdmin** open karo
2. Left mein apna database select karo (`uXXXXXXXX_traveler`)
3. **Import** tab pe click karo
4. Pehle `fellow_traveler_schema.sql` upload karo → **Go**
5. Phir `indexes.sql` upload karo → **Go**
6. Phir `seed_data.sql` upload karo → **Go** (optional: test data)

---

## Step 4: Config File Banao

Hostinger File Manager ya SSH se `config/database.php` banao:

```php
<?php
define('DB_HOST',    'localhost');
define('DB_NAME',    'uXXXXXXXX_traveler');   // apna DB naam
define('DB_USER',    'uXXXXXXXX_ftuser');      // apna DB user
define('DB_PASS',    'AAPKA_PASSWORD');         // apna password
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    '3306');
define('APP_ENV',    'production');

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
} catch (PDOException $e) {
    error_log('[FellowTraveler] ' . $e->getMessage());
    die('Database connection failed.');
}

function getDB(): PDO { global $pdo; return $pdo; }
function dbFetchAll(string $sql, array $params = []): array { $stmt = getDB()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
function dbFetchOne(string $sql, array $params = []): ?array { $stmt = getDB()->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(); return $row ?: null; }
function dbExecute(string $sql, array $params = []): int { $stmt = getDB()->prepare($sql); $stmt->execute($params); return $stmt->rowCount(); }
function dbInsert(string $sql, array $params = []): int { $stmt = getDB()->prepare($sql); $stmt->execute($params); return (int) getDB()->lastInsertId(); }
function getSetting(string $key, mixed $default = null): mixed { $row = dbFetchOne("SELECT setting_value FROM ft_admin_settings WHERE setting_key = ? LIMIT 1", [$key]); return $row ? $row['setting_value'] : $default; }
```

---

## Step 5: Files Upload Karo

### Option A — Git se (recommended)
Hostinger SSH terminal mein:
```bash
cd public_html
git clone https://github.com/AAPKA_USERNAME/fellow-traveler.git .
```

### Option B — File Manager se
1. Saari files ZIP karo
2. hPanel → File Manager → public_html mein upload karo
3. Extract karo

---

## Step 6: Folders aur Permissions

SSH ya File Manager se:
```bash
mkdir -p uploads logs cache backups
chmod 755 uploads logs cache backups
```

---

## Step 7: Test Karo

Browser mein `https://aapkadomain.com/test_connection.php` open karo.
Agar "Connection OK" aaye toh sab theek hai! ✅

Phir `test_connection.php` delete karo (security ke liye).

---

## ✅ Done! App live hai!

