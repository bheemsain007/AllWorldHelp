<?php
// profile/password_change.php
// Task T12 — Password change form + handler (GET shows form; POST processes change)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

$error      = null;
$successMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF Validation ───────────────────────────────────────────────────────
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token expired. Please try again.";
    } else {
        $userId          = (int) SessionManager::get('user_id');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch user
        $user = User::findById($userId);

        // Verify current password
        if (!$user || !User::checkPassword($currentPassword, $user['password'])) {
            $error = "Current password is incorrect.";

        } elseif (mb_strlen($newPassword) < 6) {
            $error = "New password must be at least 6 characters.";

        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";

        } else {
            // All checks passed — update password
            $pwCheck = InputValidator::validatePassword($newPassword, 6);
            if (!$pwCheck['valid']) {
                $error = $pwCheck['message'] ?? "Password does not meet requirements.";
            } else {
                $hashedPassword = User::hashPassword($newPassword);
                $success        = User::update($userId, ['password' => $hashedPassword]);

                if ($success) {
                    $successMsg = "Password changed successfully!";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
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
    <title>Change Password — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        .container { max-width: 480px; margin: 60px auto; padding: 0 20px; }

        .form-card {
            background: white; padding: 32px; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 { color: #1d4ed8; margin-bottom: 22px; font-size: 22px; }

        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #374151; }
        input[type="password"] {
            width: 100%; padding: 11px 14px; border: 1px solid #d1d5db;
            border-radius: 5px; font-size: 15px;
        }
        input:focus { outline: none; border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29,78,216,0.15); }

        .hint { font-size: 13px; color: #6b7280; margin-top: 4px; }

        /* Password strength bar */
        .strength-bar { height: 4px; border-radius: 2px; margin-top: 6px; background: #e5e7eb; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }

        button {
            width: 100%; padding: 13px; background: #1d4ed8; color: white;
            border: none; border-radius: 5px; font-size: 16px; cursor: pointer;
            font-weight: 600; margin-top: 6px;
        }
        button:hover { background: #1e40af; }

        .success {
            background: #d1fae5; color: #065f46; padding: 14px 18px;
            border-radius: 5px; margin-bottom: 20px; border: 1px solid #6ee7b7;
        }
        .error {
            background: #fee2e2; color: #b91c1c; padding: 14px 18px;
            border-radius: 5px; margin-bottom: 20px; border: 1px solid #fca5a5;
        }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #1d4ed8; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="edit.php">← Edit Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="form-card">
        <h2>🔒 Change Password</h2>

        <?php if ($successMsg): ?>
            <div class="success">✅ <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="pwForm">
            <?php echo CSRF::getTokenField(); ?>

            <div class="form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label>New Password * <span class="hint">(minimum 6 characters)</span></label>
                <input type="password" name="new_password" id="newPw" minlength="6"
                       required autocomplete="new-password">
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <p class="hint" id="strengthLabel"></p>
            </div>

            <div class="form-group">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" id="confirmPw"
                       minlength="6" required autocomplete="new-password">
                <p class="hint" id="matchLabel"></p>
            </div>

            <button type="submit">Change Password</button>
        </form>

        <div class="back-link">
            <a href="edit.php">← Back to Edit Profile</a>
        </div>
    </div>
</div>

<script>
    // Simple password strength indicator
    const newPw       = document.getElementById('newPw');
    const fill        = document.getElementById('strengthFill');
    const lbl         = document.getElementById('strengthLabel');
    const confirmPw   = document.getElementById('confirmPw');
    const matchLbl    = document.getElementById('matchLabel');

    newPw.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 6)  score++;
        if (v.length >= 10) score++;
        if (/[A-Z]/.test(v))  score++;
        if (/[0-9]/.test(v))  score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;

        const levels = [
            { pct: '0%',   color: '#e5e7eb', text: '' },
            { pct: '25%',  color: '#ef4444', text: 'Weak' },
            { pct: '50%',  color: '#f97316', text: 'Fair' },
            { pct: '75%',  color: '#eab308', text: 'Good' },
            { pct: '100%', color: '#22c55e', text: 'Strong' },
        ];
        const lvl = levels[Math.min(score, 4)];
        fill.style.width      = lvl.pct;
        fill.style.background = lvl.color;
        lbl.textContent       = lvl.text;
        checkMatch();
    });

    function checkMatch() {
        if (confirmPw.value === '') { matchLbl.textContent = ''; return; }
        if (newPw.value === confirmPw.value) {
            matchLbl.textContent = '✅ Passwords match';
            matchLbl.style.color = '#22c55e';
        } else {
            matchLbl.textContent = '❌ Passwords do not match';
            matchLbl.style.color = '#ef4444';
        }
    }
    confirmPw.addEventListener('input', checkMatch);
</script>
</body>
</html>
