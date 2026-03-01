<?php
// trips/add_expense.php
// Task T24 — Add expense form (GET shows form, POST processes and inserts)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripExpense.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";
require_once "../includes/create_notification.php";

$userId = (int) SessionManager::get('user_id');
$tripId = (int) ($_GET['trip_id'] ?? $_POST['trip_id'] ?? 0);

if ($tripId <= 0) {
    redirect("search.php");
}

// ── Fetch Trip & Permission ───────────────────────────────────────────────────
$trip = Trip::findById($tripId);
if (!$trip) {
    redirect("search.php?error=notfound");
}

if (!Trip::isParticipant($tripId, $userId)) {
    redirect("expenses.php?trip_id={$tripId}&error=not_participant");
}

// ── Participants List (for split checkbox) ────────────────────────────────────
$participants = Trip::getParticipants($tripId);

// ── Handle POST ───────────────────────────────────────────────────────────────
$error       = null;
$oldAmount   = '';
$oldDesc     = '';
$oldCategory = 'other';
$oldSplit    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = '❌ Security token invalid. Please try again.';
    } else {

        $amount      = (float)  ($_POST['amount']      ?? 0);
        $description = InputValidator::sanitizeString($_POST['description'] ?? '');
        $category    = $_POST['category'] ?? 'other';
        $splitAmong  = $_POST['split_among'] ?? [];

        // Preserve values on error
        $oldAmount   = $_POST['amount'] ?? '';
        $oldDesc     = htmlspecialchars($description);
        $oldCategory = $category;
        $oldSplit    = array_map('intval', $splitAmong);

        if ($amount <= 0) {
            $error = '❌ Amount must be greater than 0.';
        } elseif (mb_strlen($description) < 2 || mb_strlen($description) > 200) {
            $error = '❌ Description must be between 2 and 200 characters.';
        } elseif (!TripExpense::isValidCategory($category)) {
            $error = '❌ Invalid category selected.';
        } elseif (empty($splitAmong)) {
            $error = '❌ Please select at least one person to split with.';
        } else {
            $success = TripExpense::create([
                'trip_id'     => $tripId,
                'paid_by'     => $userId,
                'amount'      => $amount,
                'description' => $description,
                'category'    => $category,
                'split_among' => json_encode(array_map('intval', $splitAmong)),
            ]);

            if ($success) {
                // Notify split participants (excluding the payer)
                $splitIds = array_map('intval', $splitAmong);
                foreach ($splitIds as $pid) {
                    if ($pid !== $userId) {
                        createNotification(
                            $pid,
                            'expense_added',
                            'New Expense',
                            "New expense on \"{$trip['title']}\": {$description}",
                            "../trips/expenses.php?trip_id={$tripId}"
                        );
                    }
                }
                redirect("expenses.php?trip_id={$tripId}&success=added");
            } else {
                $error = '❌ Failed to add expense. Please try again.';
            }
        }
    }
}

$csrfField   = CSRF::getTokenField();
$tripTitle   = htmlspecialchars($trip['title']);
$categoryMeta = TripExpense::getCategoryMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 640px; margin: 36px auto; padding: 0 20px; }

        /* ── Error ── */
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }

        /* ── Trip Banner ── */
        .trip-banner {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 24px;
            font-size: 14px; color: #1e3a8a;
        }
        .trip-banner strong { color: #1d4ed8; }

        /* ── Form Card ── */
        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 24px; }
        .form-group  { margin-bottom: 22px; }
        label.field-label {
            display: block; font-weight: 700; margin-bottom: 8px; color: #374151;
        }
        .required { color: #dc2626; }

        /* ── Inputs ── */
        input[type="number"],
        input[type="text"],
        select {
            width: 100%; padding: 11px 13px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
        }
        input:focus, select:focus { outline: none; border-color: #1d4ed8; }

        /* ── Participant Checkboxes ── */
        .checkbox-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 10px;
        }
        .btn-select-all {
            font-size: 12px; color: #1d4ed8; background: none; border: none;
            cursor: pointer; text-decoration: underline; padding: 0;
        }
        .participant-list {
            border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;
            max-height: 220px; overflow-y: auto;
        }
        .participant-row {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer;
        }
        .participant-row:last-child { border-bottom: none; }
        .participant-row:hover { background: #f8fafc; }
        .participant-row input { margin: 0; width: auto; cursor: pointer; }
        .participant-row label { cursor: pointer; font-size: 14px; color: #374151; }

        /* ── Submit ── */
        .btn-submit {
            width: 100%; padding: 14px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 17px;
            font-weight: 700; cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #1e40af; }

        /* ── Back link ── */
        .back-link {
            display: inline-block; margin-top: 14px; color: #64748b;
            text-decoration: none; font-size: 14px;
        }
        .back-link:hover { color: #1d4ed8; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">Trip Details</a>
        <a href="expenses.php?trip_id=<?php echo $tripId; ?>">All Expenses</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="trip-banner">
        💰 Adding expense for: <strong><?php echo $tripTitle; ?></strong>
    </div>

    <div class="form-card">
        <h1>➕ Add Expense</h1>

        <form method="POST">
            <?php echo $csrfField; ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

            <!-- Amount -->
            <div class="form-group">
                <label class="field-label" for="amount">
                    Amount (₹) <span class="required">*</span>
                </label>
                <input
                    type="number"
                    id="amount"
                    name="amount"
                    step="0.01"
                    min="0.01"
                    max="9999999"
                    placeholder="0.00"
                    value="<?php echo htmlspecialchars($oldAmount); ?>"
                    required
                >
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="field-label" for="description">
                    Description <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="description"
                    name="description"
                    maxlength="200"
                    placeholder="e.g., Group dinner at restaurant"
                    value="<?php echo htmlspecialchars($oldDesc); ?>"
                    required
                >
            </div>

            <!-- Category -->
            <div class="form-group">
                <label class="field-label" for="category">
                    Category <span class="required">*</span>
                </label>
                <select id="category" name="category" required>
                    <?php foreach ($categoryMeta as $val => $meta): ?>
                        <option value="<?php echo $val; ?>"
                            <?php echo ($oldCategory === $val) ? 'selected' : ''; ?>>
                            <?php echo $meta['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Split Among -->
            <div class="form-group">
                <div class="checkbox-header">
                    <label class="field-label" style="margin:0;">
                        Split Among <span class="required">*</span>
                    </label>
                    <button type="button" class="btn-select-all" onclick="selectAll()">Select All</button>
                </div>
                <div class="participant-list">
                    <?php foreach ($participants as $p): ?>
                        <?php $checked = in_array((int)$p['user_id'], $oldSplit, true) || empty($oldSplit); ?>
                        <div class="participant-row">
                            <input
                                type="checkbox"
                                name="split_among[]"
                                value="<?php echo (int) $p['user_id']; ?>"
                                id="part_<?php echo (int) $p['user_id']; ?>"
                                <?php echo $checked ? 'checked' : ''; ?>
                            >
                            <label for="part_<?php echo (int) $p['user_id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?>
                                <?php if ((int)$p['user_id'] === $userId): ?>
                                    <span style="color:#94a3b8;font-size:12px;">(you)</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-submit">💰 Add Expense</button>
        </form>

        <a href="expenses.php?trip_id=<?php echo $tripId; ?>" class="back-link">
            ← Back to expenses
        </a>
    </div>

</div><!-- /.container -->

<script>
    function selectAll() {
        document.querySelectorAll('input[name="split_among[]"]').forEach(cb => cb.checked = true);
    }
    // Make entire row clickable
    document.querySelectorAll('.participant-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'LABEL') {
                const cb = this.querySelector('input[type="checkbox"]');
                cb.checked = !cb.checked;
            }
        });
    });
</script>
</body>
</html>
