<?php
// admin/users.php
// Task T32 — Admin user list with search, filter, pagination

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/UserManagement.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// Admin guard
$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetchColumn() !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$result     = UserManagement::getUsers($search, $status, $page, $perPage);
$users      = $result['users'];
$total      = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

$successMsg = htmlspecialchars($_GET['success'] ?? '');
$errorMsg   = htmlspecialchars($_GET['error']   ?? '');

function pageUrl(int $p, string $s, string $st): string {
    return 'users.php?' . http_build_query(['page' => $p, 'search' => $s, 'status' => $st]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management — Admin</title>
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

        .page { max-width: 1200px; margin: 28px auto; padding: 0 20px; }
        h1 { font-size: 24px; color: #0f172a; margin-bottom: 22px; }

        .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .filters {
            background: white; padding: 16px 18px; border-radius: 10px;
            margin-bottom: 18px; display: flex; gap: 12px; align-items: center;
            box-shadow: 0 1px 4px rgba(0,0,0,.07); flex-wrap: wrap;
        }
        .filters input[type="text"] {
            flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px;
        }
        .filters select {
            padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;
            background: white; font-size: 14px;
        }
        .btn-search {
            padding: 10px 22px; background: #0f172a; color: white;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer;
        }
        .total-hint { font-size: 14px; color: #64748b; margin-left: auto; }

        .table-wrap { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; }
        th { padding: 13px 16px; text-align: left; font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #e5e7eb; }
        td { padding: 13px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafafa; }

        .user-cell { display: flex; gap: 11px; align-items: center; }
        .user-photo { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background: #e5e7eb; }
        .user-name  { font-weight: 700; color: #0f172a; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 12px; font-weight: 700; }
        .badge-active  { background: #dcfce7; color: #166534; }
        .badge-blocked { background: #fee2e2; color: #991b1b; }
        .badge-admin   { background: #e0e7ff; color: #3730a3; }

        .actions { display: flex; gap: 6px; }
        .btn-sm {
            padding: 6px 13px; font-size: 12px; border-radius: 6px; font-weight: 700;
            text-decoration: none; border: none; cursor: pointer; transition: opacity .15s;
        }
        .btn-sm:hover { opacity: 0.85; }
        .btn-view    { background: #1d4ed8; color: white; }
        .btn-block   { background: #dc2626; color: white; }
        .btn-unblock { background: #16a34a; color: white; }

        .empty-state { padding: 48px; text-align: center; color: #94a3b8; font-size: 15px; }

        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 22px; }
        .pager {
            padding: 8px 13px; border-radius: 7px; font-size: 14px; font-weight: 600;
            text-decoration: none; background: white; color: #374151;
            border: 1px solid #d1d5db;
        }
        .pager:hover  { background: #f1f5f9; }
        .pager.active { background: #0f172a; color: white; border-color: #0f172a; }
        .pager.disabled { opacity: 0.4; pointer-events: none; }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler — Admin</a>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="reports_dashboard.php">Reports</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="page">
    <h1>👥 User Management</h1>

    <?php if ($successMsg): ?><div class="alert alert-success">✅ <?php echo $successMsg; ?></div><?php endif; ?>
    <?php if ($errorMsg):   ?><div class="alert alert-error">❌ <?php echo $errorMsg; ?></div><?php endif; ?>

    <!-- Filters -->
    <form class="filters" method="GET" action="users.php">
        <input type="text" name="search" placeholder="Search by name or email…" value="<?php echo htmlspecialchars($search); ?>">
        <select name="status" onchange="this.form.submit()">
            <option value="all"     <?php echo $status === 'all'     ? 'selected' : ''; ?>>All Users</option>
            <option value="active"  <?php echo $status === 'active'  ? 'selected' : ''; ?>>Active Only</option>
            <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked Only</option>
        </select>
        <button type="submit" class="btn-search">Search</button>
        <span class="total-hint"><?php echo number_format($total); ?> user(s) found</span>
    </form>

    <!-- Table -->
    <div class="table-wrap">
        <?php if (empty($users)): ?>
            <div class="empty-state">No users found matching your filters.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <img
                                src="<?php echo htmlspecialchars($u['profile_photo'] ?? '../images/default-avatar.png'); ?>"
                                class="user-photo" alt="">
                            <span class="user-name"><?php echo htmlspecialchars($u['name']); ?></span>
                        </div>
                    </td>
                    <td style="color:#64748b;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <?php if (($u['role'] ?? '') === 'admin'): ?>
                            <span class="badge badge-admin">Admin</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:13px;">User</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#64748b;white-space:nowrap;"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <?php if ($u['is_blocked']): ?>
                            <span class="badge badge-blocked">🚫 Blocked</span>
                        <?php else: ?>
                            <span class="badge badge-active">✅ Active</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:#64748b;">
                        <?php echo (int)$u['trips_organized']; ?> organized<br>
                        <?php echo (int)$u['trips_joined']; ?> joined
                    </td>
                    <td>
                        <div class="actions">
                            <a href="user_details.php?id=<?php echo (int)$u['user_id']; ?>" class="btn-sm btn-view">View</a>
                            <?php if ($u['is_blocked']): ?>
                                <a href="block_user.php?id=<?php echo (int)$u['user_id']; ?>&action=unblock"
                                   class="btn-sm btn-unblock"
                                   onclick="return confirm('Unblock this user?')">Unblock</a>
                            <?php elseif (($u['role'] ?? '') !== 'admin'): ?>
                                <a href="block_user.php?id=<?php echo (int)$u['user_id']; ?>&action=block"
                                   class="btn-sm btn-block"
                                   onclick="return confirm('Block this user?')">Block</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="<?php echo pageUrl($page - 1, $search, $status); ?>"
           class="pager <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹ Prev</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
        ?>
            <a href="<?php echo pageUrl($p, $search, $status); ?>"
               class="pager <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="<?php echo pageUrl($page + 1, $search, $status); ?>"
           class="pager <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next ›</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
