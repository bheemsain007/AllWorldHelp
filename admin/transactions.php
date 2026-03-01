<?php
// admin/transactions.php
// Task T34 — Transaction management page

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Revenue.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$s       = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$s->execute([$adminId]);
if ($s->fetchColumn() !== 'admin') redirect("../dashboard.php?error=unauthorized");

$type   = $_GET['type']      ?? 'all';
$status = $_GET['status']    ?? 'all';
$from   = $_GET['date_from'] ?? '';
$to     = $_GET['date_to']   ?? '';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$result       = Revenue::getTransactions($type, $status, $from, $to, $page, $perPage);
$transactions = $result['transactions'];
$total        = $result['total'];
$pages        = max(1, (int) ceil($total / $perPage));

$types    = ['all','booking','commission','platform_fee','subscription','feature','payout'];
$statuses = ['all','pending','completed','failed','refunded'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — Admin</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; background:#f1f5f9; }
        nav { background:#0f172a; color:white; padding:12px 24px; display:flex; align-items:center; justify-content:space-between; }
        nav .brand { font-size:20px; font-weight:bold; text-decoration:none; color:white; }
        nav a { color:#94a3b8; text-decoration:none; margin-left:18px; font-size:14px; }
        nav a:hover { color:white; }

        .page { max-width:1200px; margin:28px auto; padding:0 20px; }
        h1 { font-size:24px; color:#0f172a; margin-bottom:22px; }

        .filters { background:white; padding:16px 18px; border-radius:10px; margin-bottom:18px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; box-shadow:0 1px 4px rgba(0,0,0,.07); }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label { font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; }
        .filters select, .filters input[type="date"] { padding:9px 12px; border:1px solid #d1d5db; border-radius:7px; font-size:14px; background:white; }
        .btn-filter { padding:9px 20px; background:#0f172a; color:white; border:none; border-radius:7px; font-size:14px; font-weight:700; cursor:pointer; }
        .btn-filter:hover { background:#1e293b; }
        .total-hint { font-size:13px; color:#64748b; align-self:center; margin-left:auto; }

        .table-wrap { background:white; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.07); }
        table { width:100%; border-collapse:collapse; }
        thead { background:#0f172a; }
        th { padding:12px 16px; text-align:left; font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; }
        td { padding:13px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }

        .type-badge { display:inline-block; padding:3px 9px; border-radius:10px; font-size:11px; font-weight:700; background:#e0e7ff; color:#3730a3; text-transform:uppercase; }
        .status-completed { background:#dcfce7; color:#166534; }
        .status-pending   { background:#fef9c3; color:#78350f; }
        .status-failed    { background:#fee2e2; color:#991b1b; }
        .status-refunded  { background:#f3f4f6; color:#4b5563; }

        .amount-positive { font-weight:800; color:#16a34a; }
        .amount-payout   { font-weight:800; color:#dc2626; }

        .empty-state { padding:48px; text-align:center; color:#94a3b8; font-size:15px; }

        .pagination { display:flex; justify-content:center; gap:6px; margin-top:22px; }
        .pager { padding:8px 13px; border-radius:7px; font-size:14px; font-weight:600; text-decoration:none; background:white; color:#374151; border:1px solid #d1d5db; }
        .pager:hover { background:#f1f5f9; }
        .pager.active { background:#0f172a; color:white; border-color:#0f172a; }
        .pager.disabled { opacity:.4; pointer-events:none; }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="revenue.php">Revenue</a>
        <a href="transactions.php">Transactions</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <h1>📋 Transaction Management</h1>

    <form class="filters" method="GET" action="transactions.php">
        <div class="filter-group">
            <label>Type</label>
            <select name="type">
                <?php foreach ($types as $t): ?>
                    <option value="<?php echo $t; ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ',$t)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <?php foreach ($statuses as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>From</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="filter-group">
            <label>To</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <button type="submit" class="btn-filter">Filter</button>
        <span class="total-hint"><?php echo number_format($total); ?> record(s)</span>
    </form>

    <div class="table-wrap">
        <?php if (empty($transactions)): ?>
            <div class="empty-state">No transactions found for the selected filters.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th><th>User</th><th>Type</th><th>Amount</th><th>Status</th><th>Description</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td style="color:#94a3b8;">#<?php echo (int)$t['transaction_id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($t['user_name'] ?? '—'); ?></strong></td>
                    <td><span class="type-badge"><?php echo htmlspecialchars(str_replace('_',' ',$t['type'])); ?></span></td>
                    <td class="<?php echo $t['type'] === 'payout' ? 'amount-payout' : 'amount-positive'; ?>">
                        <?php echo $t['type'] === 'payout' ? '-' : '+'; ?>₹<?php echo number_format((float)$t['amount'],2); ?>
                    </td>
                    <td><span class="type-badge status-<?php echo htmlspecialchars($t['status']); ?>"><?php echo htmlspecialchars($t['status']); ?></span></td>
                    <td style="color:#64748b;max-width:260px;"><?php echo htmlspecialchars(mb_strimwidth($t['description'] ?? '—', 0, 80, '…')); ?></td>
                    <td style="color:#64748b;white-space:nowrap;"><?php echo date('d M Y', strtotime($t['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <a href="?page=<?php echo $page-1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>" class="pager <?php echo $page<=1?'disabled':''; ?>">‹</a>
        <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
            <a href="?page=<?php echo $p; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>" class="pager <?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo $page+1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>" class="pager <?php echo $page>=$pages?'disabled':''; ?>">›</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
