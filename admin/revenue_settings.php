<?php
// admin/revenue_settings.php
// Task T34 — Configure commission rates and platform fees

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Revenue.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect("../dashboard.php?error=unauthorized");

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $msg = 'error:csrf';
    } else {
        $keys = ['commission_rate','platform_fee','premium_monthly','featured_listing'];
        $ok   = true;
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '');
            if (!is_numeric($val) || (float)$val < 0) { $ok = false; break; }
            Revenue::updateSetting($k, $val);
        }
        $msg = $ok ? 'success' : 'error:invalid';
    }
}

$settings = Revenue::getAllSettings();
$csrf     = CSRF::getTokenField();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Settings — Admin</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; background:#f1f5f9; }
        nav { background:#0f172a; color:white; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; }
        nav .brand { font-size:20px; font-weight:bold; text-decoration:none; color:white; }
        nav a { color:#94a3b8; text-decoration:none; margin-left:18px; font-size:14px; }
        nav a:hover { color:white; }
        .page { max-width:640px; margin:36px auto; padding:0 20px; }
        h1 { font-size:24px; color:#0f172a; margin-bottom:24px; }
        .alert { padding:13px 18px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .alert-error   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .card { background:white; border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-weight:700; margin-bottom:6px; color:#374151; font-size:14px; }
        .form-group .hint { font-size:12px; color:#94a3b8; margin-bottom:8px; }
        .input-wrap { display:flex; align-items:center; gap:8px; }
        .prefix { font-size:16px; font-weight:700; color:#64748b; }
        input[type="number"] { flex:1; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; }
        input[type="number"]:focus { outline:none; border-color:#0f172a; }
        .btn-save { width:100%; padding:13px; background:#0f172a; color:white; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
        .btn-save:hover { background:#1e293b; }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="revenue.php">Revenue</a>
        <a href="transactions.php">Transactions</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <h1>⚙️ Revenue Settings</h1>

    <?php if ($msg === 'success'): ?>
        <div class="alert alert-success">✅ Settings saved successfully.</div>
    <?php elseif (str_starts_with($msg, 'error')): ?>
        <div class="alert alert-error">❌ Failed to save settings. Please check your values.</div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="revenue_settings.php">
            <?php echo $csrf; ?>

            <div class="form-group">
                <label for="commission_rate">Commission Rate</label>
                <div class="hint">Percentage of trip cost charged as platform commission.</div>
                <div class="input-wrap">
                    <input type="number" id="commission_rate" name="commission_rate" min="0" max="100" step="0.1"
                           value="<?php echo htmlspecialchars($settings['commission_rate'] ?? '5'); ?>" required>
                    <span class="prefix">%</span>
                </div>
            </div>

            <div class="form-group">
                <label for="platform_fee">Platform Fee (per booking)</label>
                <div class="hint">Fixed fee added to every booking transaction.</div>
                <div class="input-wrap">
                    <span class="prefix">₹</span>
                    <input type="number" id="platform_fee" name="platform_fee" min="0" step="1"
                           value="<?php echo htmlspecialchars($settings['platform_fee'] ?? '100'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="premium_monthly">Premium Subscription (monthly)</label>
                <div class="hint">Monthly subscription price for premium users.</div>
                <div class="input-wrap">
                    <span class="prefix">₹</span>
                    <input type="number" id="premium_monthly" name="premium_monthly" min="0" step="1"
                           value="<?php echo htmlspecialchars($settings['premium_monthly'] ?? '499'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="featured_listing">Featured Listing Fee</label>
                <div class="hint">One-time fee to feature a trip at the top of search results.</div>
                <div class="input-wrap">
                    <span class="prefix">₹</span>
                    <input type="number" id="featured_listing" name="featured_listing" min="0" step="1"
                           value="<?php echo htmlspecialchars($settings['featured_listing'] ?? '299'); ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-save">💾 Save Settings</button>
        </form>
    </div>
</div>
</body>
</html>
