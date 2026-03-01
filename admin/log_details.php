<?php
// admin/log_details.php
// Task T38 — Single log entry detail view

require_once '../includes/SessionManager.php';
SessionManager::start();
require_once '../auth/auth_check.php';
require_once '../config/database.php';
require_once '../includes/Logger.php';
require_once '../includes/CSRF.php';
require_once '../includes/helpers.php';

// Admin guard
$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare('SELECT role FROM ft_users WHERE user_id = ?');
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect('../dashboard.php?error=unauthorized');

$logId = (int) ($_GET['id'] ?? 0);
if (!$logId) redirect('logs.php');

$log = Logger::findById($logId);
if (!$log) redirect('logs.php?error=not_found');

// Parse JSON context
$context = null;
if (!empty($log['context'])) {
    $context = json_decode($log['context'], true);
}

// Level colors
$levelColor = Logger::levelColor($log['level']);
$badgeClass = Logger::levelBadgeClass($log['level']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log #<?= $logId ?> — Fellow Traveler Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; color: #333; }

.wrapper { display: flex; min-height: 100vh; }
.sidebar {
    width: 240px; background: #1a1a2e; color: #ccc; padding: 24px 0;
    position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto;
}
.sidebar .logo { padding: 0 24px 24px; font-size: 1.2rem; font-weight: 700; color: #fff; border-bottom: 1px solid #2d2d4e; margin-bottom: 16px; }
.sidebar a { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: #bbb; text-decoration: none; font-size: 0.9rem; transition: background .2s; }
.sidebar a:hover, .sidebar a.active { background: #2d2d4e; color: #fff; }
.sidebar .nav-section { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #666; padding: 12px 24px 4px; }

.main { margin-left: 240px; flex: 1; padding: 32px; max-width: 900px; }

.breadcrumb { font-size: 0.85rem; color: #888; margin-bottom: 20px; }
.breadcrumb a { color: #667eea; text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }

.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.page-title { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; }

.log-card { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
.log-hero {
    padding: 24px 28px;
    border-left: 6px solid <?= $levelColor ?>;
    background: linear-gradient(135deg, <?= $levelColor ?>18, #fff 60%);
}
.level-badge {
    display: inline-block; padding: 4px 14px; border-radius: 20px;
    font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    background: <?= $levelColor ?>22; color: <?= $levelColor ?>;
    margin-bottom: 12px;
}
.log-message { font-size: 1.05rem; font-weight: 600; color: #1a1a2e; line-height: 1.5; margin-bottom: 10px; }
.log-time { font-size: 0.84rem; color: #888; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.info-row { display: flex; flex-direction: column; padding: 14px 28px; border-bottom: 1px solid #f0f0f0; }
.info-row:nth-child(odd) { border-right: 1px solid #f0f0f0; }
.info-label { font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #aaa; margin-bottom: 4px; }
.info-value { font-size: 0.9rem; color: #333; word-break: break-all; }
.info-value a { color: #667eea; text-decoration: none; }
.info-value a:hover { text-decoration: underline; }

.context-section { padding: 20px 28px; border-top: 1px solid #f0f0f0; }
.context-title { font-size: 0.85rem; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }
.context-pre {
    background: #1a1a2e; color: #e2e8f0;
    border-radius: 10px; padding: 18px 20px;
    font-family: 'Courier New', monospace; font-size: 0.83rem;
    line-height: 1.6; overflow-x: auto; white-space: pre-wrap; word-break: break-all;
}

.actions { display: flex; gap: 12px; padding: 20px 28px; border-top: 1px solid #f0f0f0; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .2s; }
.btn:hover { opacity: .85; }
.btn-secondary { background: #6c757d; color: #fff; }
.btn-danger    { background: #dc3545; color: #fff; }

/* Navigation arrows */
.nav-between { display: flex; justify-content: space-between; margin-top: 20px; }
.nav-between a { color: #667eea; text-decoration: none; font-size: 0.88rem; font-weight: 600; }
.nav-between a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="wrapper">

<nav class="sidebar">
    <div class="logo">🌍 FT Admin</div>
    <div class="nav-section">Main</div>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="users.php">👥 Users</a>
    <a href="trips.php">✈️ Trips</a>
    <div class="nav-section">Analytics</div>
    <a href="analytics.php">📊 Analytics</a>
    <a href="revenue.php">💰 Revenue</a>
    <a href="reports_dashboard.php">🚨 Reports</a>
    <div class="nav-section">System</div>
    <a href="settings.php">⚙️ Settings</a>
    <a href="backup.php">💾 Backup</a>
    <a href="logs.php" class="active">📋 Logs</a>
    <a href="../dashboard.php">← Back to Site</a>
</nav>

<main class="main">
    <div class="breadcrumb">
        <a href="logs.php">← Logs</a> / Log #<?= $logId ?>
    </div>

    <div class="page-header">
        <div class="page-title">Log Entry #<?= $logId ?></div>
    </div>

    <div class="log-card">

        <!-- Hero -->
        <div class="log-hero">
            <div class="level-badge"><?= strtoupper($log['level']) ?></div>
            <div class="log-message"><?= htmlspecialchars($log['message']) ?></div>
            <div class="log-time">
                🕐 <?= date('F j, Y \a\t g:i:s A', strtotime($log['created_at'])) ?>
                &nbsp;·&nbsp; <?= Logger::timeAgo($log['created_at']) ?>
            </div>
        </div>

        <!-- Metadata grid -->
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Category</div>
                <div class="info-value" style="text-transform:capitalize"><?= htmlspecialchars($log['category']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Log ID</div>
                <div class="info-value">#<?= $log['log_id'] ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">User</div>
                <div class="info-value">
                    <?php if ($log['user_id']): ?>
                        <a href="user_details.php?id=<?= $log['user_id'] ?>">
                            <?= htmlspecialchars($log['user_name'] ?? 'User #' . $log['user_id']) ?>
                        </a>
                        <?php if ($log['user_email']): ?>
                            <br><span style="color:#aaa;font-size:.8rem"><?= htmlspecialchars($log['user_email']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#bbb">System / Cron</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">IP Address</div>
                <div class="info-value" style="font-family:monospace"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></div>
            </div>
            <div class="info-row" style="grid-column:1/-1">
                <div class="info-label">URL / Request</div>
                <div class="info-value" style="font-family:monospace;font-size:.83rem"><?= htmlspecialchars($log['url'] ?? '—') ?></div>
            </div>
        </div>

        <!-- Context / Stack trace -->
        <?php if ($context): ?>
        <div class="context-section">
            <div class="context-title">Context / Stack Trace</div>
            <?php if (isset($context['trace'])): ?>
                <pre class="context-pre"><?= htmlspecialchars($context['trace']) ?></pre>
                <?php
                // Show other context keys separately
                $others = array_filter($context, fn($k) => $k !== 'trace', ARRAY_FILTER_USE_KEY);
                if ($others):
                ?>
                <pre class="context-pre" style="margin-top:10px"><?= htmlspecialchars(json_encode($others, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                <?php endif; ?>
            <?php else: ?>
                <pre class="context-pre"><?= htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="actions">
            <a href="logs.php" class="btn btn-secondary">← Back to Logs</a>
            <form method="POST" action="delete_log.php" style="display:inline"
                  onsubmit="return confirm('Delete this log entry?')">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="log_id" value="<?= $logId ?>">
                <button type="submit" class="btn btn-danger">🗑 Delete Entry</button>
            </form>
        </div>
    </div>

    <!-- Prev / Next navigation -->
    <div class="nav-between">
        <?php
        $prevId = $db->query("SELECT log_id FROM ft_logs WHERE log_id < {$logId} ORDER BY log_id DESC LIMIT 1")->fetchColumn();
        $nextId = $db->query("SELECT log_id FROM ft_logs WHERE log_id > {$logId} ORDER BY log_id ASC LIMIT 1")->fetchColumn();
        ?>
        <?php if ($prevId): ?>
            <a href="log_details.php?id=<?= $prevId ?>">‹ Previous Entry</a>
        <?php else: ?><span></span><?php endif; ?>
        <?php if ($nextId): ?>
            <a href="log_details.php?id=<?= $nextId ?>">Next Entry ›</a>
        <?php endif; ?>
    </div>

</main>
</div>
</body>
</html>
