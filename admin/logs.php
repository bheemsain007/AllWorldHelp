<?php
// admin/logs.php
// Task T38 — Application logs viewer dashboard

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

// ── Export CSV ─────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    Logger::exportCsv([
        'level'     => $_GET['level']     ?? '',
        'category'  => $_GET['category']  ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to'   => $_GET['date_to']   ?? '',
        'search'    => $_GET['search']    ?? '',
    ]);
    exit;
}

// ── Filters ────────────────────────────────────────────────────────────────────
$filterLevel    = $_GET['level']     ?? '';
$filterCategory = $_GET['category']  ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to']   ?? '';
$filterSearch   = $_GET['search']    ?? '';

// ── Pagination ─────────────────────────────────────────────────────────────────
$perPage = 30;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total = Logger::countLogs($filterLevel, $filterCategory, $filterDateFrom, $filterDateTo, $filterSearch);
$logs  = Logger::getLogs($filterLevel, $filterCategory, $filterDateFrom, $filterDateTo, $filterSearch, 0, $perPage, $offset);
$stats = Logger::getStats();
$pages = (int) ceil($total / $perPage);

$categories = Logger::getCategories();
$catList    = array_column($categories, 'category');

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs & Monitoring — Fellow Traveler Admin</title>
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

.main { margin-left: 240px; flex: 1; padding: 32px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
.page-title { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; }
.page-title span { font-size: 1rem; font-weight: 400; color: #888; margin-left: 8px; }
.header-actions { display: flex; gap: 10px; }

/* Alerts */
.alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
.alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.alert-danger  { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card {
    background: #fff; border-radius: 10px; padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    border-top: 3px solid var(--c, #667eea); text-align: center;
}
.stat-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: .5px; color: #888; margin-bottom: 6px; }
.stat-value { font-size: 1.6rem; font-weight: 700; color: var(--c, #333); }

/* Filter card */
.filter-card { background: #fff; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.filter-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-size: 0.75rem; font-weight: 600; color: #666; text-transform: uppercase; }
.filter-group select,
.filter-group input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 7px; font-size: 0.88rem; color: #333; background: #fafafa; }
.filter-group input[type="text"] { width: 200px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .2s; }
.btn:hover { opacity: .85; }
.btn-primary  { background: #667eea; color: #fff; }
.btn-success  { background: #28a745; color: #fff; }
.btn-danger   { background: #dc3545; color: #fff; }
.btn-secondary{ background: #6c757d; color: #fff; }
.btn-sm { padding: 5px 11px; font-size: 0.78rem; border-radius: 6px; }

/* Table */
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 24px; }
.card-header { padding: 16px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 1rem; font-weight: 700; color: #1a1a2e; }

table { width: 100%; border-collapse: collapse; }
th, td { padding: 11px 14px; text-align: left; font-size: 0.86rem; }
th { background: #f8f9fa; font-weight: 700; color: #555; border-bottom: 2px solid #e9ecef; }
tr:not(:last-child) td { border-bottom: 1px solid #f4f4f4; }
tr:hover td { background: #fafbfc; }

.message-cell { max-width: 340px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.url-cell { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: monospace; font-size: 0.78rem; color: #888; }

/* Level badges */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.badge-debug    { background: #f0f0f0; color: #888; }
.badge-info     { background: #dbeafe; color: #1e40af; }
.badge-warning  { background: #fef3c7; color: #92400e; }
.badge-error    { background: #fee2e2; color: #991b1b; }
.badge-critical { background: #7b1c1c; color: #fff; }

/* Category chip */
.cat-chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.74rem; background: #f0f0f0; color: #555; text-transform: capitalize; }

/* Pagination */
.pagination { display: flex; gap: 6px; align-items: center; padding: 16px 24px; justify-content: center; }
.pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 0.84rem; text-decoration: none; background: #fff; color: #555; border: 1px solid #e0e0e0; }
.pagination a:hover { background: #667eea; color: #fff; border-color: #667eea; }
.pagination .active { background: #667eea; color: #fff; border-color: #667eea; font-weight: 700; }

/* Empty state */
.empty { text-align: center; padding: 60px 20px; color: #aaa; }
.empty .icon { font-size: 3rem; margin-bottom: 12px; }

/* Row highlight on level */
tr.row-critical td { background: #fff0f0; }
tr.row-error td    { background: #fff5f5; }
</style>
</head>
<body>
<div class="wrapper">

<!-- Sidebar -->
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

<!-- Main -->
<main class="main">

    <div class="page-header">
        <div>
            <div class="page-title">📋 Logs & Monitoring <span><?= number_format($total) ?> entries</span></div>
        </div>
        <div class="header-actions">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
               class="btn btn-success">⬇ Export CSV</a>
        </div>
    </div>

    <?php if ($success === 'cleared'): ?>
        <div class="alert alert-success">✅ Logs cleared successfully.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="--c:#888">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        <div class="stat-card" style="--c:#dc3545">
            <div class="stat-label">Errors</div>
            <div class="stat-value"><?= number_format($stats['by_level']['error'] ?? 0) ?></div>
        </div>
        <div class="stat-card" style="--c:#7b1c1c">
            <div class="stat-label">Critical</div>
            <div class="stat-value"><?= number_format($stats['by_level']['critical'] ?? 0) ?></div>
        </div>
        <div class="stat-card" style="--c:#f59e0b">
            <div class="stat-label">Warnings</div>
            <div class="stat-value"><?= number_format($stats['by_level']['warning'] ?? 0) ?></div>
        </div>
        <div class="stat-card" style="--c:#0d6efd">
            <div class="stat-label">Info</div>
            <div class="stat-value"><?= number_format($stats['by_level']['info'] ?? 0) ?></div>
        </div>
        <div class="stat-card" style="--c:#28a745">
            <div class="stat-label">Today</div>
            <div class="stat-value"><?= number_format($stats['today']) ?></div>
        </div>
        <div class="stat-card" style="--c:#6f42c1">
            <div class="stat-label">This Week</div>
            <div class="stat-value"><?= number_format($stats['week']) ?></div>
        </div>
    </div>

    <!-- Filter panel -->
    <div class="filter-card">
        <form method="GET" action="logs.php" class="filter-form">
            <div class="filter-group">
                <label>Level</label>
                <select name="level">
                    <option value="">All Levels</option>
                    <?php foreach (['debug','info','warning','error','critical'] as $lv): ?>
                        <option value="<?= $lv ?>" <?= $filterLevel === $lv ? 'selected' : '' ?>><?= ucfirst($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($catList as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($cat)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="filter-group">
                <label>To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search message…" value="<?= htmlspecialchars($filterSearch) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="logs.php" class="btn btn-secondary">✕ Reset</a>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Log Entries</div>
            <div style="font-size:.83rem;color:#888">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div class="empty">
                <div class="icon">📋</div>
                <p>No log entries match your filters.</p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Category</th>
                    <th>Message</th>
                    <th>User</th>
                    <th>IP</th>
                    <th>URL</th>
                    <th>Time</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr class="row-<?= $log['level'] ?>">
                    <td><span class="badge <?= Logger::levelBadgeClass($log['level']) ?>"><?= strtoupper($log['level']) ?></span></td>
                    <td><span class="cat-chip"><?= htmlspecialchars($log['category']) ?></span></td>
                    <td class="message-cell" title="<?= htmlspecialchars($log['message']) ?>">
                        <?= htmlspecialchars($log['message']) ?>
                    </td>
                    <td style="font-size:.83rem">
                        <?= $log['user_name']
                            ? '<a href="user_details.php?id=' . $log['user_id'] . '" style="color:#667eea">' . htmlspecialchars($log['user_name']) . '</a>'
                            : '<span style="color:#bbb">System</span>' ?>
                    </td>
                    <td style="font-family:monospace;font-size:.8rem;color:#888"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    <td class="url-cell" title="<?= htmlspecialchars($log['url'] ?? '') ?>"><?= htmlspecialchars($log['url'] ?? '—') ?></td>
                    <td style="white-space:nowrap;font-size:.82rem;color:#888"><?= Logger::timeAgo($log['created_at']) ?></td>
                    <td>
                        <a href="log_details.php?id=<?= $log['log_id'] ?>" class="btn btn-secondary btn-sm">👁 View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹ Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</main>
</div>
</body>
</html>
