<?php
// admin/backup.php
// Task T37 — Backup management dashboard

require_once '../includes/SessionManager.php';
SessionManager::start();
require_once '../auth/auth_check.php';
require_once '../config/database.php';
require_once '../models/Backup.php';
require_once '../includes/CSRF.php';
require_once '../includes/helpers.php';

// Admin guard
$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare('SELECT role FROM ft_users WHERE user_id = ?');
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect('../dashboard.php?error=unauthorized');

// Pagination
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total   = Backup::countAll();
$backups = Backup::getAll($perPage, $offset);
$stats   = Backup::getStatistics();
$pages   = (int) ceil($total / $perPage);

// Flash messages
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$successMsg = match ($success) {
    'backup_created'   => 'Backup created successfully.',
    'restore_complete' => 'Database restored successfully.',
    'deleted'          => 'Backup deleted.',
    default            => '',
};
$errorMsg = match ($error) {
    'backup_failed'  => 'Backup creation failed. Check server logs.',
    'restore_failed' => 'Database restore failed. Check server logs.',
    'not_found'      => 'Backup not found.',
    'delete_failed'  => 'Could not delete backup.',
    'csrf'           => 'Security token mismatch. Please try again.',
    default          => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backup Management — Fellow Traveler Admin</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; color: #333; }

/* ── Layout ── */
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
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-title { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; }
.page-title span { font-size: 1rem; font-weight: 400; color: #888; margin-left: 8px; }

/* ── Alerts ── */
.alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
.alert-success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
.alert-danger  { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }

/* ── Stat cards ── */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 28px; }
.stat-card {
    background: #fff; border-radius: 12px; padding: 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    border-left: 4px solid var(--c, #667eea);
}
.stat-label { font-size: 0.8rem; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.stat-value { font-size: 1.8rem; font-weight: 700; color: #1a1a2e; }
.stat-sub   { font-size: 0.78rem; color: #aaa; margin-top: 4px; }

/* ── Buttons ── */
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: opacity .2s; }
.btn:hover { opacity: .85; }
.btn-primary  { background: #667eea; color: #fff; }
.btn-success  { background: #28a745; color: #fff; }
.btn-warning  { background: #f59e0b; color: #fff; }
.btn-danger   { background: #dc3545; color: #fff; }
.btn-secondary{ background: #6c757d; color: #fff; }
.btn-sm { padding: 5px 12px; font-size: 0.8rem; border-radius: 6px; }

/* ── Card / table ── */
.card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 28px; }
.card-header { padding: 18px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
.card-title  { font-size: 1rem; font-weight: 700; color: #1a1a2e; }
.card-body   { padding: 24px; }

table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px 16px; text-align: left; font-size: 0.88rem; }
th { background: #f8f9fa; font-weight: 700; color: #555; border-bottom: 2px solid #e9ecef; }
tr:not(:last-child) td { border-bottom: 1px solid #f0f0f0; }
tr:hover td { background: #fafbfc; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.badge-manual     { background: #e8f4fd; color: #0d6efd; }
.badge-auto       { background: #d4edda; color: #155724; }
.badge-weekly     { background: #e0d8ff; color: #6f42c1; }
.badge-prerestore { background: #fff3cd; color: #856404; }
.badge-preupdate  { background: #f8d7da; color: #721c24; }

.actions { display: flex; gap: 6px; }
.filename { font-family: monospace; font-size: 0.83rem; color: #555; }

/* ── Pagination ── */
.pagination { display: flex; gap: 6px; align-items: center; margin-top: 20px; justify-content: center; }
.pagination a, .pagination span {
    padding: 7px 13px; border-radius: 7px; font-size: 0.85rem; text-decoration: none;
    background: #fff; color: #555; border: 1px solid #e0e0e0;
}
.pagination a:hover { background: #667eea; color: #fff; border-color: #667eea; }
.pagination .active { background: #667eea; color: #fff; border-color: #667eea; font-weight: 700; }

/* ── Empty state ── */
.empty { text-align: center; padding: 60px 20px; color: #aaa; }
.empty .icon { font-size: 3rem; margin-bottom: 12px; }

/* ── Modal overlay ── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 14px; padding: 32px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.3); text-align: center; }
.modal-icon { font-size: 3rem; margin-bottom: 12px; }
.modal-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 8px; }
.modal-text  { color: #666; font-size: 0.9rem; margin-bottom: 22px; }
.modal-actions { display: flex; gap: 10px; justify-content: center; }
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
    <a href="backup.php" class="active">💾 Backup</a>
    <a href="../dashboard.php">← Back to Site</a>
</nav>

<!-- Main Content -->
<main class="main">

    <!-- Header -->
    <div class="page-header">
        <div>
            <div class="page-title">💾 Backup Management <span><?= $total ?> backups</span></div>
        </div>
        <form method="POST" action="create_backup.php" id="createBackupForm">
            <?= CSRF::getTokenField() ?>
            <button type="submit" class="btn btn-primary" onclick="return confirmCreate()">
                ➕ Create Backup
            </button>
        </form>
    </div>

    <!-- Flash messages -->
    <?php if ($successMsg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card" style="--c:#667eea">
            <div class="stat-label">Total Backups</div>
            <div class="stat-value"><?= $stats['total_backups'] ?></div>
            <div class="stat-sub">In storage</div>
        </div>
        <div class="stat-card" style="--c:#28a745">
            <div class="stat-label">Total Size</div>
            <div class="stat-value"><?= Backup::formatSize($stats['total_size']) ?></div>
            <div class="stat-sub">Disk used</div>
        </div>
        <div class="stat-card" style="--c:#f59e0b">
            <div class="stat-label">Last Backup</div>
            <div class="stat-value" style="font-size:1.1rem">
                <?= $stats['last_backup_date']
                    ? date('M j, Y', strtotime($stats['last_backup_date']))
                    : 'Never' ?>
            </div>
            <div class="stat-sub">
                <?= $stats['last_backup_date']
                    ? date('g:i A', strtotime($stats['last_backup_date']))
                    : '—' ?>
            </div>
        </div>
        <div class="stat-card" style="--c:#6f42c1">
            <div class="stat-label">Manual / Auto</div>
            <div class="stat-value">
                <?= ($stats['by_type']['manual'] ?? 0) ?> /
                <?= ($stats['by_type']['auto'] ?? 0) + ($stats['by_type']['weekly'] ?? 0) ?>
            </div>
            <div class="stat-sub">Manual vs automated</div>
        </div>
    </div>

    <!-- Info notice -->
    <div class="alert" style="background:#e8f4fd;color:#0a4f7f;border-left:4px solid #0d6efd;margin-bottom:24px;font-size:.87rem;">
        ℹ️ Backups are stored in the <code>backups/</code> directory. Automated backups run daily at 2 AM and weekly on Sunday at 3 AM via cron.
        Old backups (> 30 days) are purged automatically during automated runs.
    </div>

    <!-- Backups Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Backup History</div>
        </div>

        <?php if (empty($backups)): ?>
            <div class="empty">
                <div class="icon">💾</div>
                <p>No backups yet. Create your first backup using the button above.</p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
                <tr>
                    <td style="color:#aaa;font-size:.8rem"><?= $b['backup_id'] ?></td>
                    <td><span class="filename"><?= htmlspecialchars($b['filename']) ?></span></td>
                    <td>
                        <span class="badge <?= Backup::typeBadgeClass($b['type']) ?>">
                            <?= Backup::typeLabel($b['type']) ?>
                        </span>
                    </td>
                    <td><?= Backup::formatSize((int)$b['size']) ?></td>
                    <td><?= $b['created_by_name'] ? htmlspecialchars($b['created_by_name']) : '<span style="color:#aaa">System</span>' ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($b['created_at'])) ?></td>
                    <td>
                        <div class="actions">
                            <a href="download_backup.php?id=<?= $b['backup_id'] ?>&csrf=<?= CSRF::getToken() ?>"
                               class="btn btn-secondary btn-sm" title="Download">⬇ Download</a>

                            <a href="restore_backup.php?id=<?= $b['backup_id'] ?>"
                               class="btn btn-warning btn-sm" title="Restore"
                               onclick="return confirm('⚠️ Are you sure you want to restore from this backup? This will overwrite ALL current data!')">
                                ♻ Restore
                            </a>

                            <button class="btn btn-danger btn-sm"
                                    onclick="confirmDelete(<?= $b['backup_id'] ?>, '<?= htmlspecialchars(addslashes($b['filename'])) ?>')"
                                    title="Delete">🗑 Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="pagination" style="padding:16px 24px">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">‹ Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div><!-- /card -->

</main>
</div><!-- /wrapper -->

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">🗑️</div>
        <div class="modal-title">Delete Backup?</div>
        <div class="modal-text" id="deleteModalText">This backup file will be permanently removed from the server. This action cannot be undone.</div>
        <div class="modal-actions">
            <form method="POST" action="delete_backup.php" id="deleteForm">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="backup_id" id="deleteBackupId">
                <button type="submit" class="btn btn-danger">Yes, Delete</button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
function confirmCreate() {
    return confirm('Create a new manual backup now? This may take a moment depending on database size.');
}

function confirmDelete(id, filename) {
    document.getElementById('deleteBackupId').value = id;
    document.getElementById('deleteModalText').textContent =
        'You are about to permanently delete: ' + filename + '. This cannot be undone.';
    document.getElementById('deleteModal').classList.add('open');
    return false;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
</body>
</html>
