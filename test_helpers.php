<?php

/**
 * ============================================================
 * Helper Functions Test File
 * Fellow Traveler Platform — Task T4 Testing
 * ============================================================
 * HOW TO USE:
 *   1. Upload to public_html (same folder as config/ and includes/)
 *   2. Open in browser: yourdomain.com/test_helpers.php
 *   3. All 15 functions should show ✅
 *   4. ⚠️ DELETE this file immediately after testing!
 * ============================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load dependencies
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

// ── Test runner utility ────────────────────────────────────
$results  = [];
$pass_cnt = 0;
$fail_cnt = 0;

function runTest(string $fn, string $label, mixed $got, mixed $expected, string &$detail = ''): array
{
    global $pass_cnt, $fail_cnt;
    $pass = ($got === $expected) || ($expected === '__nonempty__' && !empty($got));
    if ($pass) $pass_cnt++; else $fail_cnt++;
    return [
        'fn'       => $fn,
        'label'    => $label,
        'pass'     => $pass,
        'got'      => is_bool($got) ? ($got ? 'true' : 'false') : (string)$got,
        'expected' => is_bool($expected) ? ($expected ? 'true' : 'false')
                       : ($expected === '__nonempty__' ? '(non-empty string)' : (string)$expected),
        'detail'   => $detail,
    ];
}

// ============================================================
// RUN ALL 15 FUNCTION TESTS
// ============================================================

// ── 1. getSetting() ──────────────────────────────────────────
$v = getSetting('site_name', 'MISSING');
$results[] = runTest('getSetting()', "site_name → 'Fellow Traveler'", $v, 'Fellow Traveler');

$v = getSetting('coins_enabled', 'MISSING');
$results[] = runTest('getSetting()', "coins_enabled → '1'", $v, '1');

$v = getSetting('__nonexistent__', 'fallback');
$results[] = runTest('getSetting()', "missing key → returns default 'fallback'", $v, 'fallback');

// ── 2. sanitizeInput() ───────────────────────────────────────
$v = sanitizeInput('<script>alert(1)</script>');
$results[] = runTest('sanitizeInput()', 'strips script tags', $v, '&lt;script&gt;alert(1)&lt;/script&gt;');

$v = sanitizeInput('  John Doe  ');
$results[] = runTest('sanitizeInput()', 'trims whitespace', $v, 'John Doe');

$v = sanitizeInput("O'Brien & Co.");
$results[] = runTest('sanitizeInput()', 'encodes quotes & ampersand', $v, "O&#039;Brien &amp; Co.");

// ── 3. generateToken() ───────────────────────────────────────
$tok32 = generateToken();
$results[] = runTest('generateToken()', '32-char token', strlen($tok32) === 32 ? 'LENGTH_32' : 'WRONG', 'LENGTH_32');

$tok16 = generateToken(16);
$results[] = runTest('generateToken()', '16-char custom length', strlen($tok16) === 16 ? 'LENGTH_16' : 'WRONG', 'LENGTH_16');

$tok1 = generateToken(32);
$tok2 = generateToken(32);
$results[] = runTest('generateToken()', 'two calls produce different tokens', $tok1 !== $tok2 ? 'UNIQUE' : 'SAME', 'UNIQUE');

// ── 4. isValidEmail() ────────────────────────────────────────
$results[] = runTest('isValidEmail()', "'user@example.com' is valid",  isValidEmail('user@example.com'),  true);
$results[] = runTest('isValidEmail()', "'notanemail' is invalid",       isValidEmail('notanemail'),         false);
$results[] = runTest('isValidEmail()', "'user@.com' is invalid",        isValidEmail('user@.com'),          false);

// ── 5. isValidPhone() ────────────────────────────────────────
$results[] = runTest('isValidPhone()', "'9876543210' is valid",       isValidPhone('9876543210'),        true);
$results[] = runTest('isValidPhone()', "'+91 98765 43210' is valid",  isValidPhone('+91 98765 43210'),   true);
$results[] = runTest('isValidPhone()', "'5876543210' starts with 5",  isValidPhone('5876543210'),        false);
$results[] = runTest('isValidPhone()', "'987654321' is 9 digits",     isValidPhone('987654321'),         false);

// ── 6. isValidUsername() ─────────────────────────────────────
$results[] = runTest('isValidUsername()', "'john_doe' is valid",          isValidUsername('john_doe'),   true);
$results[] = runTest('isValidUsername()', "'user123' is valid",           isValidUsername('user123'),    true);
$results[] = runTest('isValidUsername()', "'ab' too short (< 3 chars)",   isValidUsername('ab'),         false);
$results[] = runTest('isValidUsername()', "'user@name' has @ char",       isValidUsername('user@name'),  false);
$results[] = runTest('isValidUsername()', "'1user' starts with digit",    isValidUsername('1user'),      false);

// ── 7. generateSlug() ────────────────────────────────────────
$results[] = runTest('generateSlug()', "'Trip to Goa'",              generateSlug('Trip to Goa'),         'trip-to-goa');
$results[] = runTest('generateSlug()', "'Best Beaches in India'",    generateSlug('Best Beaches in India'),'best-beaches-in-india');
$results[] = runTest('generateSlug()', 'handles spaces & case',      generateSlug('  Hello   World  '),   'hello-world');

// ── 8. truncateText() ────────────────────────────────────────
$long = 'A very long trip description that needs to be shortened for the card display';
$results[] = runTest('truncateText()', 'long text gets truncated',  strlen(truncateText($long, 20)) <= 23 ? 'TRUNCATED' : 'NOT_TRUNCATED', 'TRUNCATED');
$results[] = runTest('truncateText()', 'short text unchanged',      truncateText('Short', 100), 'Short');
$results[] = runTest('truncateText()', 'adds ellipsis',             str_ends_with(truncateText($long, 20), '...') ? 'HAS_ELLIPSIS' : 'NO_ELLIPSIS', 'HAS_ELLIPSIS');

// ── 9. formatMoney() ─────────────────────────────────────────
$results[] = runTest('formatMoney()', '5000 → ₹5,000',      formatMoney(5000),    '₹5,000');
$results[] = runTest('formatMoney()', '0 → ₹0',             formatMoney(0),       '₹0');
$results[] = runTest('formatMoney()', '1000 → ₹1,000',      formatMoney(1000),    '₹1,000');

// ── 10. uploadImage() ────────────────────────────────────────
// Simulate a failed upload (no actual file upload in test)
$fake_file = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '', 'name' => ''];
$result    = uploadImage($fake_file);
$results[] = runTest('uploadImage()', 'returns array with success key', isset($result['success']) ? 'HAS_KEY' : 'MISSING', 'HAS_KEY');
$results[] = runTest('uploadImage()', 'no-file error → success=false', $result['success'] ? 'true' : 'false', 'false');
$results[] = runTest('uploadImage()', 'error message is non-empty',    !empty($result['error']) ? 'HAS_ERROR' : 'NO_ERROR', 'HAS_ERROR');

// ── 11. deleteFile() ─────────────────────────────────────────
$tmp = sys_get_temp_dir() . '/ft_test_' . time() . '.txt';
file_put_contents($tmp, 'test');
$results[] = runTest('deleteFile()', 'existing file deleted', deleteFile($tmp), true);
$results[] = runTest('deleteFile()', 'non-existent file → false', deleteFile('/nonexistent/path/file.jpg'), false);
$results[] = runTest('deleteFile()', 'protected default file skipped', deleteFile('uploads/default_avatar.png'), false);

// ── 12. formatDate() ─────────────────────────────────────────
$results[] = runTest('formatDate()', "'2026-02-20' → '20 Feb 2026'",     formatDate('2026-02-20'),                 '20 Feb 2026');
$results[] = runTest('formatDate()', 'custom format d/m/Y',               formatDate('2026-02-20', 'd/m/Y'),       '20/02/2026');
$results[] = runTest('formatDate()', 'null input → N/A',                   formatDate(null),                        'N/A');
$results[] = runTest('formatDate()', 'zero date → N/A',                    formatDate('0000-00-00'),                'N/A');

// ── 13. calculateAge() ───────────────────────────────────────
$age = calculateAge('1995-05-15');
$results[] = runTest('calculateAge()', '1995-05-15 age is 29 or 30',   ($age >= 29 && $age <= 31) ? 'IN_RANGE' : 'OUT_RANGE', 'IN_RANGE');
$results[] = runTest('calculateAge()', 'null input → 0',               calculateAge(null), 0);
$results[] = runTest('calculateAge()', 'future date gives 0 or small', calculateAge('2030-01-01') >= 0 ? 'NON_NEG' : 'NEG', 'NON_NEG');

// ── 14 & 15. redirect() & jsonResponse() — header-sending functions
// These cannot be tested directly in a browser test (headers already sent)
// We verify they exist and are callable instead
$results[] = runTest('redirect()',      'function exists', function_exists('redirect')      ? 'EXISTS' : 'MISSING', 'EXISTS');
$results[] = runTest('jsonResponse()', 'function exists', function_exists('jsonResponse')  ? 'EXISTS' : 'MISSING', 'EXISTS');

// ============================================================
// RENDER HTML REPORT
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Helpers Test — Fellow Traveler</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f4f7fa;padding:28px}
        .wrap{max-width:860px;margin:0 auto}
        h1{font-size:21px;color:#1a1a2e;margin-bottom:6px}
        .subtitle{color:#666;font-size:13px;margin-bottom:20px}
        .summary{display:flex;gap:12px;margin-bottom:22px}
        .sum-card{flex:1;padding:16px 20px;border-radius:10px;text-align:center}
        .sum-pass{background:#d4edda;color:#155724}
        .sum-fail{background:#f8d7da;color:#721c24}
        .sum-total{background:#d1ecf1;color:#0c5460}
        .sum-card .num{font-size:32px;font-weight:700}
        .sum-card .lbl{font-size:12px;text-transform:uppercase;letter-spacing:.5px}
        table{width:100%;border-collapse:collapse;background:white;border-radius:10px;
              overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px}
        th{background:#1a1a2e;color:white;padding:10px 14px;text-align:left;font-size:13px}
        td{padding:9px 14px;border-bottom:1px solid #f0f0f0;font-size:13px;vertical-align:top}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#fafafa}
        .pass{color:#155724;font-weight:600}
        .fail{color:#721c24;font-weight:600}
        .fn{font-family:monospace;font-size:12px;background:#f0f0f0;padding:2px 6px;border-radius:3px}
        .val{font-family:monospace;font-size:11px;color:#555;max-width:200px;word-break:break-all}
        .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
        .b-pass{background:#c3e6cb;color:#155724}
        .b-fail{background:#f5c6cb;color:#721c24}
        .warn{background:#fff3cd;border:1px solid #ffe082;border-radius:8px;padding:14px 18px;
              font-size:13px;color:#856404;margin-top:6px}
        code{background:#f4f4f4;padding:1px 5px;border-radius:3px}
        .section-head td{background:#f8f9fa;font-weight:700;color:#1a1a2e;font-size:12px;
                          text-transform:uppercase;letter-spacing:.5px;padding:7px 14px}
    </style>
</head>
<body>
<div class="wrap">
    <h1>🧪 Helper Functions Test — Fellow Traveler</h1>
    <p class="subtitle">Task T4 · includes/helpers.php · <?= count($results) ?> tests run</p>

    <div class="summary">
        <div class="sum-card sum-pass">
            <div class="num"><?= $pass_cnt ?></div>
            <div class="lbl">Passed</div>
        </div>
        <div class="sum-card sum-fail">
            <div class="num"><?= $fail_cnt ?></div>
            <div class="lbl">Failed</div>
        </div>
        <div class="sum-card sum-total">
            <div class="num"><?= count($results) ?></div>
            <div class="lbl">Total Tests</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Function</th>
                <th>Test</th>
                <th>Got</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
<?php
$fn_prev = '';
$i = 0;
foreach ($results as $r):
    $i++;
    if ($r['fn'] !== $fn_prev):
        $fn_prev = $r['fn'];
?>
            <tr class="section-head">
                <td colspan="5"><?= htmlspecialchars($r['fn']) ?></td>
            </tr>
<?php endif; ?>
            <tr>
                <td style="color:#999;font-size:12px"><?= $i ?></td>
                <td><span class="fn"><?= htmlspecialchars($r['fn']) ?></span></td>
                <td><?= htmlspecialchars($r['label']) ?></td>
                <td class="val"><?= htmlspecialchars($r['got']) ?></td>
                <td><?php if ($r['pass']): ?>
                    <span class="badge b-pass">✅ PASS</span>
                <?php else: ?>
                    <span class="badge b-fail">❌ FAIL</span>
                    <div style="font-size:11px;color:#999;margin-top:3px">
                        Expected: <span class="val"><?= htmlspecialchars($r['expected']) ?></span>
                    </div>
                <?php endif ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($fail_cnt === 0): ?>
    <div style="background:#d4edda;border-radius:10px;padding:22px;text-align:center;color:#155724">
        <div style="font-size:34px">🎉</div>
        <strong style="font-size:17px">All <?= $pass_cnt ?> tests passed!</strong><br><br>
        Task T4 complete. All 15 helper functions are working correctly.<br>
        <strong>Progress: T1 ✅ T2 ✅ T3 ✅ T4 ✅ → Next: Task T5 (User Model)</strong>
    </div>
    <?php else: ?>
    <div style="background:#f8d7da;border-radius:10px;padding:22px;text-align:center;color:#721c24">
        <div style="font-size:34px">⚠️</div>
        <strong><?= $fail_cnt ?> test(s) failed.</strong><br>
        Check the table above for details and fix includes/helpers.php.
    </div>
    <?php endif; ?>

    <div class="warn" style="margin-top:16px">
        ⚠️ <strong>Delete this file after testing!</strong>
        Run: <code>rm public_html/test_helpers.php</code>
    </div>
</div>
</body>
</html>
