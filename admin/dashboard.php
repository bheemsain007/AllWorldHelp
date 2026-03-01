<?php
// admin/dashboard.php
// Task T31 — Main admin control panel

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/AdminStats.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// ── Admin guard ────────────────────────────────────────────────────────────────
$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetchColumn() !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

// ── Fetch all data ─────────────────────────────────────────────────────────────
$stats    = AdminStats::getOverviewStats();
$activity = AdminStats::getRecentActivity(10);
$growth   = AdminStats::getGrowthData(30);
$topTrips = AdminStats::getPopularTrips(5);
$topUsers = AdminStats::getActiveUsers(5);
$alerts   = AdminStats::getAlerts();

// ── Chart data (JSON for JS) ───────────────────────────────────────────────────
$chartLabels = json_encode(array_column($growth, 'date'));
$chartValues = json_encode(array_column($growth, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Fellow Traveler</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; }

        /* ── Nav ── */
        nav {
            background: #0f172a; color: white; padding: 12px 24px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav .nav-links a { color: #94a3b8; text-decoration: none; margin-left: 18px; font-size: 14px; }
        nav .nav-links a:hover { color: white; }
        nav .nav-links a.active { color: white; font-weight: 700; }

        /* ── Layout ── */
        .page { max-width: 1400px; margin: 0 auto; padding: 28px 24px; }

        /* ── Header banner ── */
        .hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #7c3aed 100%);
            color: white; padding: 28px 32px; border-radius: 14px; margin-bottom: 28px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .hero h1 { font-size: 28px; margin-bottom: 6px; }
        .hero p  { opacity: 0.85; font-size: 15px; }
        .hero .meta { text-align: right; font-size: 14px; opacity: 0.8; }

        /* ── Alerts ── */
        .alert-row { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
        .alert {
            padding: 13px 18px; border-radius: 8px; font-size: 14px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .alert.danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert.warning { background: #fef9c3; color: #78350f; border: 1px solid #fde68a; }
        .alert a { font-weight: 700; text-decoration: none; color: inherit; border-bottom: 1px solid currentColor; }

        /* ── Stats grid ── */
        .stats-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 18px; margin-bottom: 28px;
        }
        .stat-card {
            background: white; padding: 22px 24px; border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,.07); position: relative; overflow: hidden;
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 5px; height: 100%; background: var(--c);
        }
        .stat-label { color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .stat-value { font-size: 34px; font-weight: 800; color: var(--c); line-height: 1; }
        .stat-sub   {
            margin-top: 8px; font-size: 12px; font-weight: 600;
            padding: 3px 9px; border-radius: 12px; display: inline-block;
            background: color-mix(in srgb, var(--c) 12%, white);
            color: var(--c);
        }

        /* ── Content grid ── */
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 22px; margin-bottom: 28px; }

        .card {
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
        }
        .card h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 18px; }

        /* ── Activity feed ── */
        .activity-item {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 12px 0; border-bottom: 1px solid #f1f5f9;
        }
        .activity-item:last-child { border-bottom: none; }
        .act-icon {
            width: 38px; height: 38px; border-radius: 50%;
            background: #eff6ff; display: flex; align-items: center;
            justify-content: center; font-size: 17px; flex-shrink: 0;
        }
        .act-text  { font-size: 14px; color: #1e293b; line-height: 1.4; }
        .act-time  { font-size: 12px; color: #94a3b8; margin-top: 3px; }

        /* ── Quick actions ── */
        .qaction {
            display: flex; align-items: center; gap: 14px; padding: 13px;
            margin-bottom: 8px; background: #f8fafc; border: 1px solid #e5e7eb;
            border-radius: 8px; text-decoration: none; color: #1e293b;
            transition: all 0.15s;
        }
        .qaction:hover { background: #f0f4ff; border-color: #1d4ed8; }
        .qaction .qa-icon { font-size: 22px; flex-shrink: 0; }
        .qaction strong   { display: block; font-size: 14px; font-weight: 700; }
        .qaction span     { font-size: 12px; color: #64748b; }

        /* ── Chart ── */
        .chart-card { margin-bottom: 22px; }
        .chart-wrap { height: 220px; position: relative; }

        /* ── Tables (popular trips / active users) ── */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
        .mini-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .mini-table th {
            text-align: left; padding: 8px 12px; font-size: 12px; color: #64748b;
            border-bottom: 2px solid #f1f5f9; font-weight: 600; text-transform: uppercase;
        }
        .mini-table td { padding: 10px 12px; border-bottom: 1px solid #f8fafc; }
        .mini-table tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 10px;
            font-size: 12px; font-weight: 600;
        }
        .badge-open    { background: #dcfce7; color: #166534; }
        .badge-ongoing { background: #dbeafe; color: #1e40af; }

        @media (max-width: 900px) {
            .stats-grid    { grid-template-columns: repeat(2, 1fr); }
            .content-grid  { grid-template-columns: 1fr; }
            .bottom-grid   { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div class="nav-links">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="reports_dashboard.php">Reports <?php if ($stats['pending_reports'] > 0): ?><span style="background:#dc2626;color:white;border-radius:10px;padding:1px 6px;font-size:11px;"><?php echo $stats['pending_reports']; ?></span><?php endif; ?></a>
        <a href="users.php">Users</a>
        <a href="analytics.php">Analytics</a>
        <a href="../dashboard.php">← Site</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">

    <!-- Hero -->
    <div class="hero">
        <div>
            <h1>🛡️ Admin Dashboard</h1>
            <p>Platform overview and control centre — Fellow Traveler</p>
        </div>
        <div class="meta">
            <?php echo date('l, d M Y'); ?><br>
            <?php echo date('H:i'); ?> server time
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($alerts)): ?>
    <div class="alert-row">
        <?php foreach ($alerts as $alert): ?>
        <div class="alert <?php echo $alert['level']; ?>">
            <span><?php echo $alert['icon']; ?> <?php echo htmlspecialchars($alert['message']); ?></span>
            <a href="<?php echo htmlspecialchars($alert['link']); ?>"><?php echo htmlspecialchars($alert['cta']); ?> →</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Stats cards -->
    <div class="stats-grid">
        <div class="stat-card" style="--c:#1d4ed8;">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
            <span class="stat-sub">+<?php echo $stats['new_users_24h']; ?> today</span>
        </div>
        <div class="stat-card" style="--c:#16a34a;">
            <div class="stat-label">Active Trips</div>
            <div class="stat-value"><?php echo number_format($stats['active_trips']); ?></div>
            <span class="stat-sub"><?php echo $stats['trips_this_month']; ?> this month</span>
        </div>
        <div class="stat-card" style="--c:#dc2626;">
            <div class="stat-label">Pending Reports</div>
            <div class="stat-value"><?php echo $stats['pending_reports']; ?></div>
            <span class="stat-sub" style="<?php echo $stats['pending_reports'] > 0 ? 'background:#fee2e2;color:#991b1b;' : ''; ?>">
                <?php echo $stats['pending_reports'] > 0 ? 'Needs attention' : 'All clear ✓'; ?>
            </span>
        </div>
        <div class="stat-card" style="--c:#ca8a04;">
            <div class="stat-label">Avg. Trip Rating</div>
            <div class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?></div>
            <span class="stat-sub">⭐ <?php echo number_format($stats['total_ratings']); ?> ratings</span>
        </div>
        <div class="stat-card" style="--c:#7c3aed;">
            <div class="stat-label">Total Trips</div>
            <div class="stat-value"><?php echo number_format($stats['total_trips']); ?></div>
            <span class="stat-sub">All time</span>
        </div>
        <div class="stat-card" style="--c:#0891b2;">
            <div class="stat-label">Connections</div>
            <div class="stat-value"><?php echo number_format($stats['total_connections']); ?></div>
            <span class="stat-sub">Accepted</span>
        </div>
        <div class="stat-card" style="--c:#059669;">
            <div class="stat-label">Messages Sent</div>
            <div class="stat-value"><?php echo number_format($stats['total_messages']); ?></div>
            <span class="stat-sub">All time</span>
        </div>
        <div class="stat-card" style="--c:#d97706;">
            <div class="stat-label">New Users Today</div>
            <div class="stat-value"><?php echo $stats['new_users_24h']; ?></div>
            <span class="stat-sub">Last 24 hours</span>
        </div>
    </div>

    <!-- Growth chart -->
    <div class="card chart-card">
        <h2>📈 User Growth — Last 30 Days</h2>
        <div class="chart-wrap">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Activity + Quick Actions -->
    <div class="content-grid">
        <!-- Activity feed -->
        <div class="card">
            <h2>🔔 Recent Activity</h2>
            <?php if (empty($activity)): ?>
                <p style="color:#94a3b8;font-size:14px;">No recent activity.</p>
            <?php else: ?>
                <?php foreach ($activity as $item): ?>
                <div class="activity-item">
                    <div class="act-icon"><?php echo $item['icon']; ?></div>
                    <div>
                        <div class="act-text"><?php echo $item['text']; ?></div>
                        <div class="act-time"><?php echo timeAgo($item['created_at']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick actions -->
        <div class="card">
            <h2>⚡ Quick Actions</h2>

            <a href="reports_dashboard.php" class="qaction">
                <span class="qa-icon">🚨</span>
                <div>
                    <strong>View Reports</strong>
                    <span><?php echo $stats['pending_reports']; ?> pending</span>
                </div>
            </a>
            <a href="users.php" class="qaction">
                <span class="qa-icon">👥</span>
                <div>
                    <strong>Manage Users</strong>
                    <span><?php echo number_format($stats['total_users']); ?> total</span>
                </div>
            </a>
            <a href="../trips/search.php" class="qaction">
                <span class="qa-icon">🗺️</span>
                <div>
                    <strong>Browse Trips</strong>
                    <span><?php echo number_format($stats['total_trips']); ?> total trips</span>
                </div>
            </a>
            <a href="analytics.php" class="qaction">
                <span class="qa-icon">📊</span>
                <div>
                    <strong>Analytics</strong>
                    <span>Detailed insights</span>
                </div>
            </a>
            <a href="settings.php" class="qaction">
                <span class="qa-icon">⚙️</span>
                <div>
                    <strong>Platform Settings</strong>
                    <span>Configuration</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Popular trips + Active users -->
    <div class="bottom-grid">
        <!-- Popular trips -->
        <div class="card">
            <h2>🔥 Popular Trips</h2>
            <?php if (empty($topTrips)): ?>
                <p style="color:#94a3b8;font-size:14px;">No active trips found.</p>
            <?php else: ?>
            <table class="mini-table">
                <thead>
                    <tr><th>Trip</th><th>Destination</th><th>Participants</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topTrips as $t): ?>
                    <tr>
                        <td>
                            <a href="../trips/view.php?id=<?php echo (int)$t['trip_id']; ?>"
                               style="color:#1d4ed8;text-decoration:none;font-weight:600;">
                                <?php echo htmlspecialchars(mb_strimwidth($t['title'], 0, 30, '…')); ?>
                            </a>
                        </td>
                        <td style="color:#64748b;"><?php echo htmlspecialchars(mb_strimwidth($t['destination'], 0, 20, '…')); ?></td>
                        <td style="font-weight:700;color:#0f172a;"><?php echo (int)$t['participant_count']; ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($t['status']); ?>"><?php echo ucfirst($t['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Most active users -->
        <div class="card">
            <h2>🏆 Most Active Organisers</h2>
            <?php if (empty($topUsers)): ?>
                <p style="color:#94a3b8;font-size:14px;">No users found.</p>
            <?php else: ?>
            <table class="mini-table">
                <thead>
                    <tr><th>User</th><th>Trips</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($topUsers as $u): ?>
                    <tr>
                        <td>
                            <a href="../profile/public_profile.php?user_id=<?php echo (int)$u['user_id']; ?>"
                               style="color:#1d4ed8;text-decoration:none;font-weight:600;">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </a>
                            <div style="font-size:12px;color:#94a3b8;"><?php echo htmlspecialchars($u['email']); ?></div>
                        </td>
                        <td style="font-weight:700;color:#0f172a;"><?php echo (int)$u['trips_organized']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.page -->

<script>
    // Growth chart
    const labels = <?php echo $chartLabels; ?>;
    const values = <?php echo $chartValues; ?>;

    if (labels.length > 0) {
        const ctx = document.getElementById('growthChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'New Users',
                    data: values,
                    borderColor: '#1d4ed8',
                    backgroundColor: 'rgba(29,78,216,0.08)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: true,
                    tension: 0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    } else {
        document.getElementById('growthChart').parentElement.innerHTML =
            '<p style="text-align:center;color:#94a3b8;padding:40px;">Not enough data yet.</p>';
    }
</script>
</body>
</html>
