<?php
// trips/expenses.php
// Task T24 — Expense dashboard: total summary, balance breakdown, expense list

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripExpense.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$tripId = (int) ($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip ────────────────────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

// ── Viewer Context ────────────────────────────────────────────────────────────
$viewerId      = (int) SessionManager::get('user_id');
$isOrganizer   = $viewerId && ((int) $trip['user_id'] === $viewerId);
$isParticipant = $viewerId && Trip::isParticipant($tripId, $viewerId);

if (!$isParticipant) {
    redirect("view.php?id={$tripId}&error=not_participant");
}

// ── Data ──────────────────────────────────────────────────────────────────────
$expenses      = TripExpense::getExpensesForTrip($tripId);
$balances      = TripExpense::calculateBalances($tripId);
$totalAmount   = array_sum(array_column($expenses, 'amount'));
$expenseCount  = count($expenses);
$categoryMeta  = TripExpense::getCategoryMeta();

// My balance (positive = I'm owed money, negative = I owe)
$myNet = 0.0;
foreach ($balances as $b) {
    if ($b['user_id'] === $viewerId) { $myNet = $b['net']; break; }
}

// ── Flash ─────────────────────────────────────────────────────────────────────
$flash     = null;
$flashType = null;
$flashMap  = [
    'added'   => ['✅ Expense added successfully!',  'success'],
    'deleted' => ['✅ Expense deleted.',             'success'],
    'failed'  => ['❌ Something went wrong.',        'error'],
    'permission' => ['❌ You cannot delete this expense.', 'error'],
];
$sKey = $_GET['success'] ?? '';
$eKey = $_GET['error']   ?? '';
if ($sKey && isset($flashMap[$sKey])) { [$flash, $flashType] = $flashMap[$sKey]; }
elseif ($eKey && isset($flashMap[$eKey])) { [$flash, $flashType] = $flashMap[$eKey]; }

$csrfField = CSRF::getTokenField();
$tripTitle = htmlspecialchars($trip['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses — <?php echo $tripTitle; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* ── Nav ── */
        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        /* ── Layout ── */
        .container { max-width: 820px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 22px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        }
        .header-card h1 { color: #1d4ed8; font-size: 21px; margin-bottom: 4px; }
        .header-card p  { color: #64748b; font-size: 14px; }
        .btn-add {
            background: #1d4ed8; color: white; text-decoration: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 14px;
            white-space: nowrap; transition: background 0.2s;
        }
        .btn-add:hover { background: #1e40af; }

        /* ── Summary Cards ── */
        .summary-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 24px;
        }
        @media (max-width: 600px) { .summary-grid { grid-template-columns: 1fr; } }
        .summary-card {
            background: white; padding: 22px 20px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07); text-align: center;
        }
        .summary-card .label { color: #64748b; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .summary-card .value { font-size: 28px; font-weight: 800; }
        .value.blue   { color: #1d4ed8; }
        .value.green  { color: #16a34a; }
        .value.red    { color: #dc2626; }
        .value.slate  { color: #475569; }

        /* ── Balance Section ── */
        .section-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07); margin-bottom: 24px; overflow: hidden;
        }
        .section-header {
            padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; justify-content: space-between;
        }
        .section-header h2 { color: #1e293b; font-size: 17px; }
        .balance-row {
            padding: 14px 22px; border-bottom: 1px solid #f8fafc;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .balance-row:last-child { border-bottom: none; }
        .balance-name { font-size: 14px; color: #374151; font-weight: 600; }
        .balance-you  { font-size: 12px; color: #64748b; }
        .badge-owed    { color: #16a34a; font-weight: 700; font-size: 15px; }
        .badge-owes    { color: #dc2626; font-weight: 700; font-size: 15px; }
        .badge-settled { color: #94a3b8; font-size: 13px; }

        /* ── Expense List ── */
        .expense-item {
            padding: 18px 22px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: flex-start; gap: 14px;
        }
        .expense-item:last-child { border-bottom: none; }
        .expense-body { flex: 1; min-width: 0; }
        .expense-top  { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        .expense-desc { font-weight: 700; color: #1e293b; font-size: 15px; word-break: break-word; }
        .expense-amount { font-weight: 800; color: #1d4ed8; font-size: 16px; white-space: nowrap; }
        .expense-meta { font-size: 13px; color: #64748b; margin-bottom: 8px; }
        .cat-badge {
            display: inline-block; padding: 3px 10px; border-radius: 10px;
            font-size: 11px; font-weight: 700; margin-right: 8px;
        }
        .cat-food          { background: #fef3c7; color: #92400e; }
        .cat-transport     { background: #dbeafe; color: #1e40af; }
        .cat-accommodation { background: #f3e8ff; color: #6b21a8; }
        .cat-other         { background: #e5e7eb; color: #374151; }
        .split-info        { font-size: 12px; color: #94a3b8; }
        .btn-del {
            font-size: 12px; color: #94a3b8; background: none; border: none;
            cursor: pointer; text-decoration: underline; padding: 0; white-space: nowrap;
        }
        .btn-del:hover { color: #dc2626; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 56px 20px; color: #64748b;
        }
        .empty-state .emoji { font-size: 46px; margin-bottom: 14px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="trip_feed.php?trip_id=<?php echo $tripId; ?>">Updates</a>
        <a href="trip_discussion.php?trip_id=<?php echo $tripId; ?>">Discussion</a>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>💰 Trip Expenses</h1>
            <p><strong><?php echo $tripTitle; ?></strong> · <?php echo $expenseCount; ?> expense<?php echo $expenseCount !== 1 ? 's' : ''; ?></p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="view.php?id=<?php echo $tripId; ?>" style="color:#1d4ed8;text-decoration:none;font-size:14px;">← Back to Trip</a>
            <a href="add_expense.php?trip_id=<?php echo $tripId; ?>" class="btn-add">➕ Add Expense</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Spent</div>
            <div class="value blue">₹<?php echo number_format($totalAmount, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Your Balance</div>
            <?php if ($myNet > 0.005): ?>
                <div class="value green">+₹<?php echo number_format($myNet, 2); ?></div>
            <?php elseif ($myNet < -0.005): ?>
                <div class="value red">-₹<?php echo number_format(abs($myNet), 2); ?></div>
            <?php else: ?>
                <div class="value slate">₹0.00</div>
            <?php endif; ?>
        </div>
        <div class="summary-card">
            <div class="label">Transactions</div>
            <div class="value slate"><?php echo $expenseCount; ?></div>
        </div>
    </div>

    <!-- Balance Summary -->
    <?php if (!empty($balances)): ?>
    <div class="section-card">
        <div class="section-header">
            <h2>⚖️ Balance Summary</h2>
        </div>
        <?php foreach ($balances as $b): ?>
            <?php if (abs($b['net']) < 0.005) continue; ?>
            <div class="balance-row">
                <div>
                    <div class="balance-name">
                        <?php echo htmlspecialchars($b['name']); ?>
                        <?php if ($b['user_id'] === $viewerId): ?><span class="balance-you">(you)</span><?php endif; ?>
                    </div>
                    <?php if ($b['net'] > 0): ?>
                        <div style="font-size:13px;color:#16a34a;">should receive</div>
                    <?php else: ?>
                        <div style="font-size:13px;color:#dc2626;">should pay</div>
                    <?php endif; ?>
                </div>
                <?php if ($b['net'] > 0): ?>
                    <span class="badge-owed">+₹<?php echo number_format($b['net'], 2); ?></span>
                <?php else: ?>
                    <span class="badge-owes">-₹<?php echo number_format(abs($b['net']), 2); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Expense List -->
    <div class="section-card">
        <div class="section-header">
            <h2>📋 All Expenses</h2>
        </div>
        <?php if (empty($expenses)): ?>
            <div class="empty-state">
                <div class="emoji">💸</div>
                <h2>No expenses yet</h2>
                <p>Add the first expense to start tracking your trip spending.</p>
            </div>
        <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
                <?php
                    $cat      = $exp['category'];
                    $catInfo  = $categoryMeta[$cat] ?? $categoryMeta['other'];
                    $split    = json_decode($exp['split_among'], true) ?? [];
                    $share    = count($split) > 0 ? (float)$exp['amount'] / count($split) : 0;
                    $isPayer  = ((int) $exp['paid_by'] === $viewerId);
                    $canDel   = $isPayer || $isOrganizer;
                    $ts       = strtotime($exp['created_at']);
                    $dateStr  = date('M j, Y g:i A', $ts);
                ?>
                <div class="expense-item">
                    <div class="expense-body">
                        <div class="expense-top">
                            <div>
                                <span class="cat-badge <?php echo $catInfo['class']; ?>"><?php echo $catInfo['label']; ?></span>
                                <span class="expense-desc"><?php echo htmlspecialchars($exp['description']); ?></span>
                            </div>
                            <span class="expense-amount">₹<?php echo number_format((float)$exp['amount'], 2); ?></span>
                        </div>
                        <div class="expense-meta">
                            Paid by <strong><?php echo htmlspecialchars($exp['payer_name']); ?></strong>
                            · <?php echo $dateStr; ?>
                        </div>
                        <div class="split-info">
                            Split among <?php echo count($split); ?> people
                            · ₹<?php echo number_format($share, 2); ?>/person
                        </div>
                        <?php if ($canDel): ?>
                            <div style="margin-top:8px;">
                                <form method="POST" action="delete_expense.php" style="display:inline"
                                      onsubmit="return confirm('Delete this expense?')">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="expense_id" value="<?php echo (int) $exp['expense_id']; ?>">
                                    <input type="hidden" name="trip_id"    value="<?php echo $tripId; ?>">
                                    <button type="submit" class="btn-del">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /.container -->
</body>
</html>
