<?php
// auth/register.php
// Updated in T8: CSRF token added to form

require_once "../includes/SessionManager.php";
require_once "../includes/CSRF.php";
SessionManager::start();

// Already logged in? Go to dashboard
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
    <title>Register - Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container {
            max-width: 500px; margin: 50px auto; background: white;
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
        .login-link { text-align: center; margin-top: 15px; }
        .login-link a { color: #1d4ed8; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .required { color: #dc2626; }
        small { color: #6b7280; font-size: 12px; display: block; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h2>🌍 Create Account</h2>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php
                $err = htmlspecialchars($_GET['error']);
                if ($err === 'exists')      echo "Email or username is already taken. Please try another.";
                elseif ($err === 'ratelimit') echo "🔒 Too many registrations from your IP today. Please try again tomorrow.";
                elseif ($err === 'invalid') echo "Invalid input. Please check all fields and try again.";
                elseif ($err === 'failed')  echo "Registration failed. Please try again later.";
                else echo "Something went wrong. Please try again.";
            ?>
        </div>
    <?php endif; ?>

    <form action="register_process.php" method="POST">
        <?php echo CSRF::getTokenField(); ?>
        <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" name="email" required
                   value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Username <span class="required">*</span></label>
            <input type="text" name="username"
                   pattern="[a-zA-Z][a-zA-Z0-9_]{2,19}" required
                   title="3-20 chars, letters/numbers/underscore, must start with a letter">
            <small>3–20 characters, letters/numbers/underscore, must start with a letter</small>
        </div>

        <div class="form-group">
            <label>Password <span class="required">*</span></label>
            <input type="password" name="password" minlength="6" required>
            <small>Minimum 6 characters</small>
        </div>

        <div class="form-group">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Phone <small style="display:inline; color:#6b7280;">(Optional, Indian number)</small></label>
            <input type="tel" name="phone" pattern="[6-9][0-9]{9}"
                   title="10-digit Indian mobile number starting with 6-9">
        </div>

        <div class="form-group">
            <label>City <small style="display:inline; color:#6b7280;">(Optional)</small></label>
            <input type="text" name="city">
        </div>

        <button type="submit">Register</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>
</body>
</html>
