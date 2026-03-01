<?php
// admin/analytics.php
// Task T33 — Analytics dashboard with Chart.js

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Analytics.php";
require_once "../includes/helpers.php";

$adminId = (int) SessionManager::get('user_id');
$db      = Database::getInstance()->getConnection();
$stmt    = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$adminId]);
if ($stmt->fetchColumn() !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

$range = in_array($_GET['range'] ?? '', ['7', '30', '90', '365', 'all']) ? $_GET['range'] : '30';

$userGrowth     = Analytics::getUserGrowth($range);
$tripStats      = Analytics::getTripStats($range);
$revenueData    = Analytics::getRevenueData($range);
$topDest        = Analytics::getTopDestinations(8);
$tripTypes      = Analytics::getTripTypeDistribution();
$msgActivity    = Analytics::getMessageActivity($range);
$insights       = Analytics::getKeyInsights($range);

// JSON for charts
$j = fn($arr, string $col) => json_encode(array_column($arr, $col));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — Admin</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; }
        nav { background: #0f172a; color: white; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        nav a:hover { color: white; }

        .page { max-width: 1400px; margin: 28px auto; padding: 0 20px; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 24px; color: #0f172a; }

        .range-btns { display: flex; gap: 6px; }
        .range-btn {
            padding: 8px 16px; border: 1px solid #d1d5db; background: white;
            border-radius: 7px; cursor: pointer; text-decoration: none;
            color: #374151; font-size: 13px; font-weight: 600; transition: all .15s;
        }
        .range-btn.active { background: #0f172a; color: white; border-color: #0f172a; }
        .range-btn:hover:not(.active) { background: #f1f5f9; border-color: #0f172a; }

        /* Insights */
        .insights { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .insight-card { background: white; padding: 20px 22px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        .ins-label { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .ins-value { font-size: 30px; font-weight: 800; color: #1d4ed8; line-height: 1; }
        .ins-change {
            margin-top: 8px; font-size: 12px; font-weight: 600; padding: 3px 9px;
            border-radius: 12px; display: inline-block;
        }
        .pos { background: #dcfce7; color: #166534; }
        .neg { background: #fee2e2; color: #991b1b; }
        .neu { background: #f1f5f9; color: #64748b; }

        /* Charts */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-bottom: 24px; }
        .chart-card { background: white; border-radius: 12px; padding: 22px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        .chart-card h2 { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 18px; }
        .chart-wrap { height: 250px; position: relative; }

        @media (max-width: 900px) {
            .insights    { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="analytics.php">Analytics</a>
        <a href="reports_dashboard.php">Reports</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <div class="page-header">
        <h1>📊 Analytics Dashboard</h1>
        <div class="range-btns">
            <?php
            $ranges = ['7' => '7 Days', '30' => '30 Days', '90' => '90 Days', '365' => '1 Year', 'all' => 'All Time'];
            foreach ($ranges as $val => $label):
            ?>
                <a href="analytics.php?range=<?php echo $val; ?>"
                   class="range-btn <?php echo $range === $val ? 'active' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Insights -->
    <div class="insights">
        <?php
        $pct = function(float $v): string {
            if ($v == 0) return '<span class="ins-change neu">No change</span>';
            $cls = $v > 0 ? 'pos' : 'neg';
            $sign = $v > 0 ? '+' : '';
            return "<span class=\"ins-change {$cls}\">{$sign}{$v}% vs prev</span>";
        };
        ?>
        <div class="insight-card">
            <div class="ins-label">New Users</div>
            <div class="ins-value"><?php echo number_format($insights['new_users']); ?></div>
            <?php echo $pct($insights['user_growth_percent']); ?>
        </div>
        <div class="insight-card">
            <div class="ins-label">Trips Created</div>
            <div class="ins-value"><?php echo number_format($insights['trips_created']); ?></div>
            <?php echo $pct($insights['trip_growth_percent']); ?>
        </div>
        <div class="insight-card">
            <div class="ins-label">Total Expenses</div>
            <div class="ins-value" style="font-size:24px;">₹<?php echo number_format($insights['total_revenue']); ?></div>
            <?php echo $pct($insights['revenue_growth_percent']); ?>
        </div>
        <div class="insight-card">
            <div class="ins-label">Avg. Trip Rating</div>
            <div class="ins-value"><?php echo number_format($insights['avg_rating'], 1); ?>⭐</div>
            <span class="ins-change neu"><?php echo number_format($insights['total_ratings']); ?> ratings</span>
        </div>
    </div>

    <!-- Charts row 1 -->
    <div class="charts-grid">
        <div class="chart-card">
            <h2>📈 User Growth</h2>
            <div class="chart-wrap"><canvas id="userChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h2>🗺️ Trip Creations</h2>
            <div class="chart-wrap"><canvas id="tripChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h2>💰 Expense Activity</h2>
            <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h2>💬 Message Activity</h2>
            <div class="chart-wrap"><canvas id="msgChart"></canvas></div>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="charts-grid">
        <div class="chart-card">
            <h2>🌏 Top Destinations</h2>
            <div class="chart-wrap"><canvas id="destChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h2>🏷️ Trip Types</h2>
            <div class="chart-wrap"><canvas id="typeChart"></canvas></div>
        </div>
    </div>
</div>

<script>
const COLORS = ['#1d4ed8','#16a34a','#ca8a04','#dc2626','#7c3aed','#0f766e','#be123c','#6366f1','#ea580c','#4f46e5'];

function lineChart(id, labels, data, color, label) {
    new Chart(document.getElementById(id), {
        type: 'line',
        data: {
            labels,
            datasets: [{ label, data, borderColor: color, backgroundColor: color + '1a', tension: 0.4, fill: true, pointRadius: 3 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }, y: { beginAtZero: true } } }
    });
}

function barChart(id, labels, data, color, label) {
    new Chart(document.getElementById(id), {
        type: 'bar',
        data: { labels, datasets: [{ label, data, backgroundColor: color + 'cc', borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } }, y: { beginAtZero: true } } }
    });
}

function doughnutChart(id, labels, data) {
    new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: COLORS, hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 12 } } } } }
    });
}

const userLabels   = <?php echo $j($userGrowth, 'date'); ?>;
const userValues   = <?php echo $j($userGrowth, 'count'); ?>;
const tripLabels   = <?php echo $j($tripStats, 'date'); ?>;
const tripValues   = <?php echo $j($tripStats, 'count'); ?>;
const revLabels    = <?php echo $j($revenueData, 'date'); ?>;
const revValues    = <?php echo $j($revenueData, 'amount'); ?>;
const msgLabels    = <?php echo $j($msgActivity, 'date'); ?>;
const msgValues    = <?php echo $j($msgActivity, 'count'); ?>;
const destLabels   = <?php echo $j($topDest, 'destination'); ?>;
const destValues   = <?php echo $j($topDest, 'count'); ?>;
const typeLabels   = <?php echo $j($tripTypes, 'trip_type'); ?>;
const typeValues   = <?php echo $j($tripTypes, 'count'); ?>;

lineChart('userChart',    userLabels, userValues, '#1d4ed8', 'New Users');
barChart ('tripChart',    tripLabels, tripValues, '#16a34a', 'Trips Created');
lineChart('revenueChart', revLabels,  revValues,  '#ca8a04', 'Expenses (₹)');
barChart ('msgChart',     msgLabels,  msgValues,  '#7c3aed', 'Messages');
doughnutChart('destChart', destLabels, destValues);
doughnutChart('typeChart', typeLabels, typeValues);
</script>
</body>
</html>
