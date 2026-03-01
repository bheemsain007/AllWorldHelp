<?php
/**
 * Test File: User Model (Task T5)
 * Fellow Traveler - User.php Model Verification
 *
 * Run in browser: http://localhost/fellow_traveler/test_user_model.php
 * DELETE THIS FILE after testing in production!
 */

define('APP_ENV', 'development');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/models/User.php';

// ─── Test Runner ────────────────────────────────────────────────────────────
$results = [];
$pass    = 0;
$fail    = 0;
$created_user_ids = []; // track for cleanup

function test(string $label, bool $condition, string $detail = ''): void {
    global $results, $pass, $fail;
    if ($condition) {
        $pass++;
        $results[] = ['status' => 'PASS', 'label' => $label, 'detail' => $detail];
    } else {
        $fail++;
        $results[] = ['status' => 'FAIL', 'label' => $label, 'detail' => $detail ?: 'Condition returned false'];
    }
}

function testException(string $label, callable $fn, string $expectContains = ''): void {
    global $results, $pass, $fail;
    try {
        $fn();
        $fail++;
        $results[] = ['status' => 'FAIL', 'label' => $label, 'detail' => 'Expected exception but none was thrown'];
    } catch (Throwable $e) {
        $ok = $expectContains === '' || stripos($e->getMessage(), $expectContains) !== false;
        if ($ok) {
            $pass++;
            $results[] = ['status' => 'PASS', 'label' => $label, 'detail' => 'Exception: ' . $e->getMessage()];
        } else {
            $fail++;
            $results[] = ['status' => 'FAIL', 'label' => $label, 'detail' => 'Wrong exception: ' . $e->getMessage()];
        }
    }
}

// ─── Helper: unique test email/username ─────────────────────────────────────
$ts = time();
function u(string $base): string {
    global $ts;
    return $base . '_' . $ts;
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 1 — Class & Method Existence
// ════════════════════════════════════════════════════════════════════════════
$section1 = 'Section 1: Class & Method Existence';

test('User class exists', class_exists('User'));

$requiredMethods = [
    'create', 'findById', 'findByEmail', 'findByUsername',
    'getAll', 'exists', 'update', 'delete',
    'hashPassword', 'checkPassword'
];
foreach ($requiredMethods as $m) {
    test("Method User::{$m}() exists", method_exists('User', $m));
}

// Bonus methods
$bonusMethods = ['count', 'updatePassword', 'hardDelete', 'needsRehash', 'sanitize'];
foreach ($bonusMethods as $m) {
    test("BONUS - Method User::{$m}() exists", method_exists('User', $m));
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 2 — hashPassword & checkPassword
// ════════════════════════════════════════════════════════════════════════════
$section2 = 'Section 2: hashPassword & checkPassword';

$plain  = 'TestPass@1234';
$hashed = User::hashPassword($plain);

test('hashPassword() returns non-empty string',   !empty($hashed) && is_string($hashed));
test('hashPassword() produces bcrypt hash',        str_starts_with($hashed, '$2y$'));
test('hashPassword() uses cost=12',               str_contains($hashed, '$2y$12$'));
test('hashPassword() never returns plain text',    $hashed !== $plain);
test('hashPassword() result is always different', User::hashPassword($plain) !== $hashed, 'Each call produces unique salt');

test('checkPassword() returns true for correct password',  User::checkPassword($plain, $hashed));
test('checkPassword() returns false for wrong password',   !User::checkPassword('WrongPass', $hashed));
test('checkPassword() returns false for empty password',   !User::checkPassword('', $hashed));

if (method_exists('User', 'needsRehash')) {
    test('BONUS - needsRehash() returns bool',   is_bool(User::needsRehash($hashed)));
    test('BONUS - needsRehash() false on fresh', !User::needsRehash($hashed), 'Fresh bcrypt cost=12 should not need rehash');
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 3 — exists() before insert
// ════════════════════════════════════════════════════════════════════════════
$section3 = 'Section 3: exists() check';

$testEmail = u('testuser') . '@example.com';
$testUser  = u('testuser');

try {
    $existsBefore = User::exists($testEmail, $testUser);
    test('exists() returns false for non-existent user', $existsBefore === false);
} catch (Throwable $e) {
    test('exists() runs without DB error', false, $e->getMessage());
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 4 — create()
// ════════════════════════════════════════════════════════════════════════════
$section4 = 'Section 4: create() — insert new user';

$userData = [
    'name'       => 'Test User T5',
    'email'      => $testEmail,
    'username'   => $testUser,
    'password'   => 'SecurePass@123',
    'phone'      => '9876543210',
    'city'       => 'Mumbai',
    'country'    => 'India',
    'gender'     => 'male',
];

$newId = false;
try {
    $newId = User::create($userData);
    test('create() returns int user ID', is_int($newId) && $newId > 0, "Got ID: $newId");
    if ($newId) $created_user_ids[] = $newId;
} catch (Throwable $e) {
    test('create() runs without DB error', false, $e->getMessage());
}

// Attempt duplicate insert
if ($newId) {
    try {
        $dupId = User::create($userData);
        test('create() returns false/null on duplicate email', $dupId === false || $dupId === null || $dupId === 0);
    } catch (Throwable $e) {
        // Acceptable — some implementations throw on duplicate
        test('create() rejects duplicate email (exception)', true, 'Exception: ' . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 5 — findById, findByEmail, findByUsername
// ════════════════════════════════════════════════════════════════════════════
$section5 = 'Section 5: findById / findByEmail / findByUsername';

$foundUser = null;
if ($newId) {
    try {
        $foundUser = User::findById($newId);
        test('findById() returns array for valid ID',       is_array($foundUser));
        test('findById() has correct id field',             isset($foundUser['id']) && (int)$foundUser['id'] === $newId);
        test('findById() has name field',                   isset($foundUser['name']) && $foundUser['name'] === 'Test User T5');
        test('findById() has email field',                  isset($foundUser['email']) && $foundUser['email'] === $testEmail);
        test('findById() password is hashed (not plain)',   isset($foundUser['password']) && !in_array($foundUser['password'], ['SecurePass@123', '']));
        test('findById() returns null for invalid ID',      User::findById(999999999) === null);
    } catch (Throwable $e) {
        test('findById() runs without error', false, $e->getMessage());
    }

    try {
        $byEmail = User::findByEmail($testEmail);
        test('findByEmail() returns array',                 is_array($byEmail));
        test('findByEmail() matches correct user',          isset($byEmail['id']) && (int)$byEmail['id'] === $newId);
        test('findByEmail() returns null for unknown email', User::findByEmail('nobody_' . $ts . '@nowhere.invalid') === null);
    } catch (Throwable $e) {
        test('findByEmail() runs without error', false, $e->getMessage());
    }

    try {
        $byUser = User::findByUsername($testUser);
        test('findByUsername() returns array',              is_array($byUser));
        test('findByUsername() matches correct user',       isset($byUser['id']) && (int)$byUser['id'] === $newId);
        test('findByUsername() returns null for unknown',   User::findByUsername('nobody_xyz_' . $ts) === null);
    } catch (Throwable $e) {
        test('findByUsername() runs without error', false, $e->getMessage());
    }
}

// exists() AFTER insert
if ($newId) {
    try {
        $existsAfter = User::exists($testEmail, $testUser);
        test('exists() returns true after user created', $existsAfter === true);
    } catch (Throwable $e) {
        test('exists() post-create runs without error', false, $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 6 — update()
// ════════════════════════════════════════════════════════════════════════════
$section6 = 'Section 6: update()';

if ($newId) {
    try {
        $updated = User::update($newId, [
            'bio'  => 'Updated bio from T5 test',
            'city' => 'Delhi',
        ]);
        test('update() returns true on success', $updated === true);

        // Verify persisted
        $afterUpdate = User::findById($newId);
        test('update() bio change persisted',  isset($afterUpdate['bio'])  && $afterUpdate['bio']  === 'Updated bio from T5 test');
        test('update() city change persisted', isset($afterUpdate['city']) && $afterUpdate['city'] === 'Delhi');
    } catch (Throwable $e) {
        test('update() runs without error', false, $e->getMessage());
    }

    // update() should ignore non-whitelisted fields (email, username, password)
    try {
        $sneaky = User::update($newId, [
            'email'    => 'hacker@evil.com',  // should be blocked by whitelist
            'city'     => 'Kolkata',           // allowed
        ]);
        $afterSneaky = User::findById($newId);
        test('update() whitelist blocks email change', $afterSneaky['email'] !== 'hacker@evil.com');
        test('update() whitelist allows city change',  $afterSneaky['city'] === 'Kolkata');
    } catch (Throwable $e) {
        test('update() whitelist guard test ran', true, 'Exception (acceptable): ' . $e->getMessage());
    }

    // updatePassword (bonus)
    if (method_exists('User', 'updatePassword')) {
        try {
            $pwUpdated = User::updatePassword($newId, 'NewSecure@789');
            test('BONUS - updatePassword() returns true', $pwUpdated === true);
            $afterPw = User::findById($newId);
            test('BONUS - updatePassword() new hash verifiable', User::checkPassword('NewSecure@789', $afterPw['password']));
        } catch (Throwable $e) {
            test('BONUS - updatePassword() runs without error', false, $e->getMessage());
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 7 — getAll() & count()
// ════════════════════════════════════════════════════════════════════════════
$section7 = 'Section 7: getAll() & count()';

try {
    $allUsers = User::getAll(10, 0, []);
    test('getAll() returns array',                       is_array($allUsers));
    test('getAll() limit respected (≤ 10)',              count($allUsers) <= 10);
    test('getAll() each item is array',                  empty($allUsers) || is_array($allUsers[0]));
    test('getAll() items have id key',                   empty($allUsers) || isset($allUsers[0]['id']));

    // With status filter
    $activeUsers = User::getAll(5, 0, ['status' => 'active']);
    test('getAll() status filter works',                 is_array($activeUsers));
} catch (Throwable $e) {
    test('getAll() runs without error', false, $e->getMessage());
}

if (method_exists('User', 'count')) {
    try {
        $total = User::count([]);
        test('BONUS - count() returns non-negative int',  is_int($total) && $total >= 0);
        test('BONUS - count() >= 1 (created test user)',  $total >= 1);

        $activeCount = User::count(['status' => 'active']);
        test('BONUS - count() with filter returns int',   is_int($activeCount) && $activeCount >= 0);
    } catch (Throwable $e) {
        test('BONUS - count() runs without error', false, $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 8 — sanitize() bonus
// ════════════════════════════════════════════════════════════════════════════
if (method_exists('User', 'sanitize') && $foundUser) {
    $sanitized = User::sanitize($foundUser);
    test('BONUS - sanitize() returns array',               is_array($sanitized));
    test('BONUS - sanitize() removes password key',        !array_key_exists('password', $sanitized));
    test('BONUS - sanitize() keeps non-sensitive fields',  isset($sanitized['id']) && isset($sanitized['email']));
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 9 — delete() (soft) & hardDelete()
// ════════════════════════════════════════════════════════════════════════════
$section9 = 'Section 9: delete() soft & hardDelete()';

// Create a second user for delete tests
$delEmail = u('deluser') . '@example.com';
$delUser  = u('deluser');
$delId    = false;
try {
    $delId = User::create([
        'name'     => 'Delete Test User',
        'email'    => $delEmail,
        'username' => $delUser,
        'password' => 'DeleteMe@999',
    ]);
    if ($delId) $created_user_ids[] = $delId;
} catch (Throwable $e) { /* skip */ }

if ($delId) {
    try {
        $softDel = User::delete($delId);
        test('delete() returns true',                      $softDel === true);
        $afterDel = User::findById($delId);
        // Soft delete: row still exists with status='deleted'
        $isDeleted = ($afterDel === null) || (isset($afterDel['status']) && $afterDel['status'] === 'deleted');
        test('delete() soft-deletes (status=deleted or hidden)', $isDeleted);
    } catch (Throwable $e) {
        test('delete() runs without error', false, $e->getMessage());
    }
}

// hardDelete
if (method_exists('User', 'hardDelete') && $delId) {
    try {
        $hard = User::hardDelete($delId);
        test('BONUS - hardDelete() returns bool',           is_bool($hard));
        // Row should be physically gone
        $gone = User::findById($delId);
        test('BONUS - hardDelete() physically removes row', $gone === null);
        // Remove from cleanup list
        $created_user_ids = array_filter($created_user_ids, fn($id) => $id !== $delId);
    } catch (Throwable $e) {
        test('BONUS - hardDelete() runs without error', false, $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 10 — Coin Wallet auto-init
// ════════════════════════════════════════════════════════════════════════════
$section10 = 'Section 10: Coin Wallet auto-init on create()';

if ($newId) {
    try {
        $db     = getDB();
        $stmt   = $db->prepare("SELECT * FROM ft_user_coins WHERE user_id = ?");
        $stmt->execute([$newId]);
        $wallet = $stmt->fetch();
        test('Coin wallet row created for new user',  is_array($wallet));
        test('Wallet user_id matches created user',   isset($wallet['user_id']) && (int)$wallet['user_id'] === $newId);
        test('Wallet starts with 0 coins',            isset($wallet['total_coins']) && (int)$wallet['total_coins'] === 0);
    } catch (Throwable $e) {
        test('Coin wallet check ran without error', false, $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// CLEANUP — hard-delete all test users created in this run
// ════════════════════════════════════════════════════════════════════════════
$cleanedUp = [];
foreach ($created_user_ids as $cid) {
    try {
        if (method_exists('User', 'hardDelete')) {
            User::hardDelete($cid);
        } else {
            $db = getDB();
            $db->prepare("DELETE FROM ft_user_coins WHERE user_id = ?")->execute([$cid]);
            $db->prepare("DELETE FROM ft_users WHERE id = ?")->execute([$cid]);
        }
        $cleanedUp[] = $cid;
    } catch (Throwable $e) { /* best-effort */ }
}

// ════════════════════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════════════════════
$total    = $pass + $fail;
$pct      = $total > 0 ? round(($pass / $total) * 100) : 0;
$allGreen = $fail === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task T5 — User Model Test</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 2rem; }

        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { font-size: 2rem; font-weight: 700; color: #f8fafc; }
        .header p  { color: #94a3b8; margin-top: 0.4rem; }

        .summary-card {
            display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .card {
            background: #1e293b; border-radius: 12px; padding: 1.25rem 2rem;
            text-align: center; min-width: 140px;
            border: 1px solid #334155;
        }
        .card .num  { font-size: 2.5rem; font-weight: 800; }
        .card .lbl  { font-size: 0.75rem; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; margin-top: 4px; }
        .card.green .num { color: #4ade80; }
        .card.red   .num { color: #f87171; }
        .card.blue  .num { color: #60a5fa; }
        .card.amber .num { color: #fbbf24; }

        .progress-bar {
            background: #1e293b; border-radius: 99px; height: 14px;
            margin: 0 auto 2rem; max-width: 700px; overflow: hidden;
            border: 1px solid #334155;
        }
        .progress-bar-inner {
            height: 100%; border-radius: 99px;
            background: <?= $allGreen ? 'linear-gradient(90deg,#4ade80,#22c55e)' : 'linear-gradient(90deg,#f87171,#ef4444)' ?>;
            width: <?= $pct ?>%;
            transition: width 1s ease;
        }

        .section-title {
            font-size: 1rem; font-weight: 700; color: #cbd5e1;
            padding: 0.5rem 0.75rem; background: #1e293b;
            border-left: 4px solid #6366f1; border-radius: 0 6px 6px 0;
            margin: 1.5rem 0 0.5rem;
        }

        table { width: 100%; border-collapse: collapse; }
        tr:nth-child(even) td { background: #162032; }
        td { padding: 0.55rem 0.75rem; font-size: 0.875rem; border-bottom: 1px solid #1e293b; }
        td:first-child { width: 70px; }
        td:last-child  { color: #94a3b8; font-size: 0.78rem; }

        .badge {
            display: inline-block; padding: 2px 10px; border-radius: 99px;
            font-size: 0.72rem; font-weight: 700; letter-spacing: .05em;
        }
        .badge.pass { background: #14532d; color: #4ade80; }
        .badge.fail { background: #450a0a; color: #f87171; }

        .warning {
            background: #451a03; border: 1px solid #92400e; border-radius: 8px;
            padding: 1rem 1.25rem; margin-top: 2rem; color: #fcd34d; font-size: 0.875rem;
        }
        .cleanup-note {
            background: #0c4a6e; border: 1px solid #0ea5e9; border-radius: 8px;
            padding: 0.75rem 1.25rem; margin-top: 1rem; color: #7dd3fc; font-size: 0.82rem;
        }
        .result-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
<div class="header">
    <div class="result-icon"><?= $allGreen ? '✅' : '⚠️' ?></div>
    <h1>Task T5 — User Model Test</h1>
    <p>Fellow Traveler &nbsp;·&nbsp; models/User.php &nbsp;·&nbsp; <?= date('d M Y, H:i:s') ?></p>
</div>

<div class="summary-card">
    <div class="card green"><div class="num"><?= $pass ?></div><div class="lbl">Passed</div></div>
    <div class="card red"><div class="num"><?= $fail ?></div><div class="lbl">Failed</div></div>
    <div class="card blue"><div class="num"><?= $total ?></div><div class="lbl">Total</div></div>
    <div class="card amber"><div class="num"><?= $pct ?>%</div><div class="lbl">Score</div></div>
</div>

<div class="progress-bar"><div class="progress-bar-inner"></div></div>

<?php
// Group results by section headings
$sections = [
    'Section 1: Class & Method Existence'      => [],
    'Section 2: hashPassword & checkPassword'  => [],
    'Section 3: exists() check'                => [],
    'Section 4: create()'                      => [],
    'Section 5: findById / findByEmail / findByUsername' => [],
    'Section 6: update()'                      => [],
    'Section 7: getAll() & count()'            => [],
    'Section 8: sanitize() bonus'              => [],
    'Section 9: delete() soft & hardDelete()'  => [],
    'Section 10: Coin Wallet auto-init'        => [],
];

// We'll just dump all results in order since we can't retroactively group them
?>

<table>
<?php foreach ($results as $r): ?>
    <tr>
        <td><span class="badge <?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
        <td><?= htmlspecialchars($r['label']) ?></td>
        <td><?= htmlspecialchars($r['detail']) ?></td>
    </tr>
<?php endforeach; ?>
</table>

<?php if (!empty($cleanedUp)): ?>
<div class="cleanup-note">
    🧹 <strong>Cleanup:</strong> Test user IDs [<?= implode(', ', $cleanedUp) ?>] were deleted from the database after testing.
</div>
<?php endif; ?>

<div class="warning">
    ⚠️ <strong>Security Notice:</strong> This file exposes internal model behaviour.
    <strong>Delete <code>test_user_model.php</code></strong> from your server before going live.
</div>

</body>
</html>
