<?php
// admin/revenue.php
// Task T34 — Revenue dashboard

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

$summary       = Revenue::getSummary();
$recent        = Revenue::getRecentTransactions(10);
$monthlyData   = Revenue::getMonthlyRevenue(12);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Dashboard — Admin</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; }
        nav { background: #0f172a; color: white; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        nav a:hover { color: white; }

        .page { max-width: 1300px; margin: 28px auto; padding: 0 20px; }
        h1 { font-size: 24px; color: #0f172a; margin-bottom: 24px; }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 26px; }
        .sum-card {
            background: white; padding: 22px 24px; border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.07); position: relative; overflow: hidden;
        }
        .sum-card::before { content:''; position:absolute; top:0; left:0; width:5px; height:100%; background:var(--c); }
        .sum-label { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .sum-value { font-size: 30px; font-weight: 800; color: var(--c); }
        .sum-sub   { font-size: 12px; color: #94a3b8; margin-top: 8px; }

        .grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        .card h2 { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 18px; }
        .chart-wrap { height: 240px; position: relative; }

        .txn-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .txn-item:last-child { border-bottom: none; }
        .txn-type {
            display: inline-block; padding: 3px 9px; border-radius: 10px;
            font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; text-transform: uppercase;
        }
        .txn-user  { font-size: 14px; color: #0f172a; font-weight: 600; margin-top: 4px; }
        .txn-desc  { font-size: 12px; color: #94a3b8; }
        .txn-amount { font-size: 17px; font-weight: 800; color: #16a34a; }

        .status-completed { background: #dcfce7; color: #166534; }
        .status-pending   { background: #fef9c3; color: #78350f; }
        .status-failed    { background: #fee2e2; color: #991b1b; }
        .status-refunded  { background: #f3f4f6; color: #4b5563; }

        .quick-links { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .qlink { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; }
        .qlink:hover { background: #e2e8f0; }

        @media (max-width: 900px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="revenue.php">Revenue</a>
        <a href="transactions.php">Transactions</a>
        <a href="revenue_settings.php">Settings</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <h1>💰 Revenue Dashboard</h1>

    <div class="summary-grid">
        <div class="sum-card" style="--c:#16a34a;">
            <div class="sum-label">Total Revenue</div>
            <div class="sum-value">₹<?php echo number_format($summary['total_revenue']); ?></div>
            <div class="sum-sub">All completed transactions</div>
        </div>
        <div class="sum-card" style="--c:#1d4ed8;">
            <div class="sum-label">This Month</div>
            <div class="sum-value">₹<?php echo number_format($summary['month_revenue']); ?></div>
            <div class="sum-sub"><?php echo $summary['month_growth'] >= 0 ? '+' : ''; ?><?php echo $summary['month_growth']; ?>% vs last month</div>
        </div>
        <div class="sum-card" style="--c:#ca8a04;">
            <div class="sum-label">Avg. Transaction</div>
            <div class="sum-value">₹<?php echo number_format($summary['avg_transaction']); ?></div>
            <div class="sum-sub"><?php echo number_format($summary['total_transactions']); ?> transactions</div>
        </div>
        <div class="sum-card" style="--c:#7c3aed;">
            <div class="sum-label">Pending Payouts</div>
            <div class="sum-value">₹<?php echo number_format($summary['pending_payouts']); ?></div>
            <div class="sum-sub"><?php echo $summary['payout_count']; ?> organiser(s)</div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Monthly revenue chart -->
        <div class="card">
            <h2>📈 Monthly Revenue (Last 12 Months)</h2>
            <div class="chart-wrap">
                <canvas id="monthlyChart"></canvas>
            </div>
            <div class="quick-links">
                <a href="transactions.php" class="qlink">📋 All Transactions</a>
                <a href="transactions.php?type=payout&status=pending" class="qlink">💸 Pending Payouts</a>
                <a href="revenue_settings.php" class="qlink">⚙️ Rate Settings</a>
                <a href="export_revenue.php" class="qlink">📥 Export CSV</a>
            </div>
        </div>

        <!-- Recent transactions -->
        <div class="card">
            <h2>🕓 Recent Transactions</h2>
            <?php if (empty($recent)): ?>
                <p style="color:#94a3b8;font-size:14px;">No transactions yet.</p>
            <?php else: ?>
                <?php foreach ($recent as $t): ?>
                <div class="txn-item">
                    <div>
                        <span class="txn-type status-<?php echo htmlspecialchars($t['status']); ?>"><?php echo htmlspecialchars($t['type']); ?></span>
                        <div class="txn-user"><?php echo htmlspecialchars($t['user_name'] ?? '—'); ?></div>
                        <div class="txn-desc"><?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></div>
                    </div>
                    <div class="txn-amount">₹<?php echo number_format((float)$t['amount'], 2); ?></div>
                </div>
                <?php endforeach; ?>
                <a href="transactions.php" style="display:block;text-align:center;margin-top:14px;color:#1d4ed8;text-decoration:none;font-size:14px;font-weight:600;">View All →</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const mLabels = <?php echo json_encode(array_column($monthlyData, 'month')); ?>;
const mValues = <?php echo json_encode(array_column($monthlyData, 'total')); ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: mLabels,
        datasets: [{
            label: 'Revenue (₹)',
            data: mValues,
            backgroundColor: '#16a34acc',
            borderRadius: 5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString() } }
        }
    }
});
</script>
</body>
</html>
