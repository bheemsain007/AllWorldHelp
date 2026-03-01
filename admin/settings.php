<?php
// admin/settings.php
// Task T36 — Platform settings management UI

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Settings.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect("../dashboard.php?error=unauthorized");

$activeCategory = trim($_GET['category'] ?? 'general');
$categories     = Settings::getCategories();
if (!in_array($activeCategory, $categories)) {
    $activeCategory = $categories[0] ?? 'general';
}
$settings = Settings::getByCategory($activeCategory);
$success  = $_GET['success'] ?? '';
$csrf     = CSRF::getTokenField();

$catIcons = [
    'general'     => '⚙️',
    'features'    => '🔧',
    'revenue'     => '💰',
    'limits'      => '🔒',
    'email'       => '📧',
    'security'    => '🛡️',
    'maintenance' => '🔨',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings — Admin</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; background:#f1f5f9; }
        nav { background:#0f172a; color:white; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; }
        nav .brand { font-size:20px; font-weight:bold; text-decoration:none; color:white; }
        nav a { color:#94a3b8; text-decoration:none; margin-left:18px; font-size:14px; }
        nav a:hover { color:white; }

        .page { max-width:1100px; margin:28px auto; padding:0 20px; }
        h1 { font-size:24px; color:#0f172a; margin-bottom:24px; }

        .alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; padding:13px 18px; border-radius:8px; margin-bottom:18px; font-size:14px; }

        .layout { display:grid; grid-template-columns:220px 1fr; gap:22px; }

        /* Sidebar */
        .sidebar { background:white; border-radius:12px; padding:18px; box-shadow:0 1px 4px rgba(0,0,0,.07); height:fit-content; position:sticky; top:20px; }
        .cat-link { display:flex; align-items:center; gap:10px; padding:11px 14px; margin-bottom:6px; border-radius:8px; text-decoration:none; color:#374151; font-size:14px; font-weight:600; transition:all .15s; }
        .cat-link:hover { background:#f1f5f9; }
        .cat-link.active { background:#0f172a; color:white; }

        /* Panel */
        .panel { background:white; border-radius:12px; padding:28px; box-shadow:0 1px 4px rgba(0,0,0,.07); }
        .panel h2 { font-size:18px; color:#0f172a; margin-bottom:24px; }

        .setting-item { margin-bottom:22px; padding-bottom:22px; border-bottom:1px solid #f1f5f9; }
        .setting-item:last-of-type { border-bottom:none; }
        .setting-label { font-weight:700; color:#0f172a; font-size:14px; margin-bottom:5px; }
        .setting-desc  { font-size:12px; color:#94a3b8; margin-bottom:8px; }

        .setting-input { width:100%; max-width:480px; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; font-family:inherit; }
        .setting-input:focus { outline:none; border-color:#0f172a; }
        textarea.setting-input { resize:vertical; min-height:90px; }

        /* Toggle */
        .toggle-wrap { display:flex; align-items:center; gap:12px; }
        .toggle { position:relative; display:inline-block; width:48px; height:26px; }
        .toggle input { opacity:0; width:0; height:0; }
        .slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#cbd5e1; transition:.3s; border-radius:26px; }
        .slider:before { position:absolute; content:""; height:18px; width:18px; left:4px; bottom:4px; background:white; transition:.3s; border-radius:50%; }
        input:checked + .slider { background:#16a34a; }
        input:checked + .slider:before { transform:translateX(22px); }
        .toggle-label { font-size:14px; color:#64748b; }

        .btn-save { padding:13px 32px; background:#0f172a; color:white; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
        .btn-save:hover { background:#1e293b; }

        .empty-cat { color:#94a3b8; font-size:14px; font-style:italic; }

        @media(max-width:700px) { .layout { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="settings.php">Settings</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <h1>⚙️ Platform Settings</h1>

    <?php if ($success): ?><div class="alert-success">✅ Settings saved successfully.</div><?php endif; ?>

    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <?php foreach ($categories as $cat): ?>
                <a href="settings.php?category=<?php echo urlencode($cat); ?>"
                   class="cat-link <?php echo $cat === $activeCategory ? 'active' : ''; ?>">
                    <?php echo $catIcons[$cat] ?? '⚙️'; ?>
                    <?php echo htmlspecialchars(ucfirst($cat)); ?>
                </a>
            <?php endforeach; ?>
        </aside>

        <!-- Settings panel -->
        <main class="panel">
            <h2><?php echo ($catIcons[$activeCategory] ?? '⚙️'); ?> <?php echo htmlspecialchars(ucfirst($activeCategory)); ?> Settings</h2>

            <?php if (empty($settings)): ?>
                <p class="empty-cat">No settings found in this category.</p>
            <?php else: ?>
            <form method="POST" action="update_settings.php">
                <?php echo $csrf; ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($activeCategory); ?>">

                <?php foreach ($settings as $setting): ?>
                <div class="setting-item">
                    <div class="setting-label"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $setting['setting_key']))); ?></div>
                    <?php if (!empty($setting['description'])): ?>
                        <div class="setting-desc"><?php echo htmlspecialchars($setting['description']); ?></div>
                    <?php endif; ?>

                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                        <div class="toggle-wrap">
                            <label class="toggle">
                                <input type="checkbox"
                                       name="<?php echo htmlspecialchars($setting['setting_key']); ?>"
                                       value="1"
                                       <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="toggle-label"><?php echo $setting['setting_value'] == '1' ? 'Enabled' : 'Disabled'; ?></span>
                        </div>
                    <?php elseif ($setting['setting_type'] === 'select'): ?>
                        <select name="<?php echo htmlspecialchars($setting['setting_key']); ?>" class="setting-input">
                            <?php
                            $opts = json_decode($setting['options'] ?? '{}', true) ?: [];
                            foreach ($opts as $val => $lbl):
                            ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"
                                    <?php echo $setting['setting_value'] == $val ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lbl); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                        <textarea name="<?php echo htmlspecialchars($setting['setting_key']); ?>" class="setting-input"><?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?></textarea>
                    <?php else: ?>
                        <input
                            type="<?php echo $setting['setting_type'] === 'number' ? 'number' : 'text'; ?>"
                            name="<?php echo htmlspecialchars($setting['setting_key']); ?>"
                            class="setting-input"
                            value="<?php echo htmlspecialchars($setting['setting_value'] ?? ''); ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-save">💾 Save Changes</button>
            </form>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
