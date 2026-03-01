<?php
// auth/login.php
// Updated in T8: CSRF token added to form

require_once "../includes/SessionManager.php";
require_once "../includes/CSRF.php";
SessionManager::start();

// Already logged in? Go straight to dashboard
if (SessionManager::has('user_id')) {
    header("Location: ../dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container {
            max-width: 400px; margin: 80px auto; background: white;
            padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #1d4ed8; margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 5px; font-size: 16px;
        }
        input:focus { outline: none; border-color: #1d4ed8; }
        button {
            width: 100%; padding: 12px; background: #1d4ed8; color: white;
            border: none; border-radius: 5px; font-size: 16px; cursor: pointer;
        }
        button:hover { background: #1e40af; }
        .error   { background: #fee2e2; color: #c00; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #fca5a5; }
        .success { background: #dcfce7; color: #166534; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #86efac; }
        .links   { text-align: center; margin-top: 15px; }
        .links a { color: #1d4ed8; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h2>🌍 Login</h2>

    <?php if (isset($_GET['registered'])): ?>
        <div class="success">✅ Registration successful! Please login.</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php
                $err       = $_GET['error'] ?? '';
                $remaining = isset($_GET['remaining']) ? (int)$_GET['remaining'] : null;
                $wait      = isset($_GET['wait'])      ? (int)$_GET['wait']      : null;

                if ($err === 'ratelimit') {
                    echo "🔒 Too many failed attempts. Please try again in <strong>{$wait} minute(s)</strong>.";
                } elseif ($err === 'invalid') {
                    $msg = "❌ Invalid email or password.";
                    if ($remaining !== null && $remaining > 0) {
                        $msg .= " <strong>{$remaining} attempt(s)</strong> remaining before lockout.";
                    } elseif ($remaining === 0) {
                        $msg .= " Account temporarily locked. Try again later.";
                    }
                    echo $msg;
                } elseif ($err === 'deleted') {
                    echo "❌ This account has been deactivated.";
                } elseif ($err === 'timeout') {
                    echo "⏰ Your session expired. Please log in again.";
                } else {
                    echo "❌ Login failed. Please try again.";
                }
            ?>
        </div>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
        <?php echo CSRF::getTokenField(); ?>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required autofocus
                   value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit">Login</button>
    </form>

    <div class="links">
        Don't have an account? <a href="register.php">Register here</a>
    </div>
</div>
</body>
</html>
