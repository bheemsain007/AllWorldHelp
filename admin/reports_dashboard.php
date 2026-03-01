<?php
// admin/reports_dashboard.php
// Task T30 — Admin moderation dashboard (list + filter reports)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Report.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// ── Admin guard ────────────────────────────────────────────────────────────────
// Expects ft_users.role = 'admin'
$db       = Database::getInstance()->getConnection();
$stmt     = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$userId]);
$userRole = $stmt->fetchColumn();

if ($userRole !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

// ── Filters ────────────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== '' && !Report::isValidStatus($filterStatus)) {
    $filterStatus = '';
}

// ── Pagination ─────────────────────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$total   = Report::countAll($filterStatus);
$pages   = max(1, (int) ceil($total / $perPage));

// ── Fetch ──────────────────────────────────────────────────────────────────────
$reports = Report::getAll($filterStatus, $perPage, $offset);
$stats   = Report::getStats();

// ── Success / error banner ─────────────────────────────────────────────────────
$successMsg = match ($_GET['success'] ?? '') {
    'reviewed'  => '✅ Report marked as Reviewed.',
    'resolved'  => '✅ Report marked as Resolved.',
    'dismissed' => '✅ Report dismissed.',
    default     => '',
};
$errorMsg = match ($_GET['error'] ?? '') {
    'failed' => '❌ Action failed. Please try again.',
    default  => '',
};

// ── Helpers ────────────────────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'pending'   => ['#fef3c7', '#92400e', '⏳ Pending'],
        'reviewed'  => ['#dbeafe', '#1e40af', '🔍 Reviewed'],
        'resolved'  => ['#dcfce7', '#166534', '✅ Resolved'],
        'dismissed' => ['#f3f4f6', '#4b5563', '🚫 Dismissed'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#f3f4f6', '#4b5563', ucfirst($status)];
    return "<span style=\"background:{$bg};color:{$color};padding:3px 9px;border-radius:12px;font-size:12px;font-weight:700;\">{$label}</span>";
}

function paginationUrl(int $p, string $status): string {
    $params = ['page' => $p];
    if ($status !== '') $params['status'] = $status;
    return 'reports_dashboard.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard — Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; }

        nav {
            background: #0f172a; color: white; padding: 12px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        nav a:hover { color: white; }

        .container { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
        h1 { font-size: 26px; color: #0f172a; margin-bottom: 24px; }

        /* Stats row */
        .stats-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 28px; }
        .stat-card {
            flex: 1; min-width: 140px; background: white; border-radius: 10px;
            padding: 16px 20px; box-shadow: 0 1px 4px rgba(0,0,0,.08);
            text-align: center;
        }
        .stat-card .num { font-size: 28px; font-weight: 800; color: #0f172a; }
        .stat-card .lbl { font-size: 13px; color: #64748b; margin-top: 4px; }
        .stat-card.pending  .num { color: #d97706; }
        .stat-card.reviewed .num { color: #2563eb; }
        .stat-card.resolved .num { color: #16a34a; }
        .stat-card.dismissed .num { color: #6b7280; }

        /* Filter bar */
        .filter-bar {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px;
            background: white; padding: 14px 18px; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .filter-bar span { font-size: 14px; color: #64748b; align-self: center; margin-right: 4px; }
        .filter-btn {
            padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
            text-decoration: none; border: 2px solid transparent;
            transition: all 0.15s;
        }
        .filter-btn.all     { background: #f1f5f9; color: #475569; }
        .filter-btn.active  { background: #0f172a; color: white; }
        .filter-btn:hover:not(.active) { border-color: #0f172a; color: #0f172a; }

        /* Alert banners */
        .alert-success {
            background: #dcfce7; color: #166534; border: 1px solid #86efac;
            padding: 13px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;
        }
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 13px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;
        }

        /* Reports table */
        .table-wrap {
            background: white; border-radius: 10px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #0f172a; color: white; }
        th { padding: 13px 16px; text-align: left; font-size: 13px; font-weight: 600; white-space: nowrap; }
        td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        .report-id   { color: #94a3b8; font-size: 13px; }
        .reporter    { font-weight: 600; color: #0f172a; }
        .reporter-email { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .type-badge  {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 12px; font-weight: 600; background: #e0e7ff; color: #3730a3;
        }
        .desc-preview { color: #374151; max-width: 260px; word-break: break-word; }
        .desc-none    { color: #94a3b8; font-style: italic; }
        .date         { color: #64748b; font-size: 13px; white-space: nowrap; }
        .action-link  {
            display: inline-block; padding: 6px 12px; background: #0f172a; color: white;
            border-radius: 6px; font-size: 13px; text-decoration: none; white-space: nowrap;
        }
        .action-link:hover { background: #1e293b; }

        .empty-state { padding: 48px; text-align: center; color: #94a3b8; }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 16px; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 24px; }
        .page-btn {
            padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 600;
            text-decoration: none; background: white; color: #374151;
            border: 1px solid #d1d5db; transition: all 0.15s;
        }
        .page-btn:hover { background: #f1f5f9; }
        .page-btn.active { background: #0f172a; color: white; border-color: #0f172a; }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="reports_dashboard.php">Reports</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <h1>🛡️ Reports Dashboard</h1>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="num"><?php echo (int)($stats['total'] ?? 0); ?></div>
            <div class="lbl">Total Reports</div>
        </div>
        <div class="stat-card pending">
            <div class="num"><?php echo (int)($stats['pending'] ?? 0); ?></div>
            <div class="lbl">Pending</div>
        </div>
        <div class="stat-card reviewed">
            <div class="num"><?php echo (int)($stats['reviewed'] ?? 0); ?></div>
            <div class="lbl">Reviewed</div>
        </div>
        <div class="stat-card resolved">
            <div class="num"><?php echo (int)($stats['resolved'] ?? 0); ?></div>
            <div class="lbl">Resolved</div>
        </div>
        <div class="stat-card dismissed">
            <div class="num"><?php echo (int)($stats['dismissed'] ?? 0); ?></div>
            <div class="lbl">Dismissed</div>
        </div>
    </div>

    <?php if ($successMsg): ?><div class="alert-success"><?php echo $successMsg; ?></div><?php endif; ?>
    <?php if ($errorMsg):   ?><div class="alert-error"><?php echo $errorMsg; ?></div><?php endif; ?>

    <!-- Filter bar -->
    <div class="filter-bar">
        <span>Filter:</span>
        <?php
        $filters = ['' => 'All', 'pending' => '⏳ Pending', 'reviewed' => '🔍 Reviewed', 'resolved' => '✅ Resolved', 'dismissed' => '🚫 Dismissed'];
        foreach ($filters as $val => $label):
            $cls = ($filterStatus === $val) ? 'filter-btn active' : 'filter-btn all';
            $url = $val === '' ? 'reports_dashboard.php' : 'reports_dashboard.php?status=' . $val;
        ?>
            <a href="<?php echo $url; ?>" class="<?php echo $cls; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Reports table -->
    <div class="table-wrap">
        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>No reports found<?php echo $filterStatus ? ' with status "' . htmlspecialchars($filterStatus) . '"' : ''; ?>.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reporter</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td><span class="report-id">#<?php echo (int)$r['report_id']; ?></span></td>
                    <td>
                        <div class="reporter"><?php echo htmlspecialchars($r['reporter_name']); ?></div>
                        <div class="reporter-email"><?php echo htmlspecialchars($r['reporter_email']); ?></div>
                    </td>
                    <td>
                        <span class="type-badge"><?php echo htmlspecialchars($r['reported_type']); ?> #<?php echo (int)$r['reported_id']; ?></span>
                    </td>
                    <td><?php echo Report::getCategoryLabel($r['category']); ?></td>
                    <td>
                        <?php if (!empty($r['description'])): ?>
                            <div class="desc-preview"><?php echo htmlspecialchars(mb_strimwidth($r['description'], 0, 120, '…')); ?></div>
                        <?php else: ?>
                            <span class="desc-none">No description</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo statusBadge($r['status']); ?></td>
                    <td><span class="date"><?php echo date('d M Y', strtotime($r['created_at'])); ?></span></td>
                    <td>
                        <a href="handle_report.php?report_id=<?php echo (int)$r['report_id']; ?>" class="action-link">
                            Review →
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
        <a href="<?php echo paginationUrl($page - 1, $filterStatus); ?>"
           class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹ Prev</a>
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="<?php echo paginationUrl($p, $filterStatus); ?>"
               class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="<?php echo paginationUrl($page + 1, $filterStatus); ?>"
           class="page-btn <?php echo $page >= $pages ? 'disabled' : ''; ?>">Next ›</a>
    </div>
    <?php endif; ?>

</div><!-- /.container -->
</body>
</html>
