<?php
// admin/handle_report.php
// Task T30 — Admin: view a single report and take action

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Report.php";
require_once "../includes/CSRF.php";
require_once "../includes/InputValidator.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// ── Admin guard ────────────────────────────────────────────────────────────────
$db       = Database::getInstance()->getConnection();
$stmt     = $db->prepare("SELECT role FROM ft_users WHERE user_id = ?");
$stmt->execute([$userId]);
$userRole = $stmt->fetchColumn();

if ($userRole !== 'admin') {
    redirect("../dashboard.php?error=unauthorized");
}

$reportId = (int) ($_GET['report_id'] ?? 0);
if ($reportId <= 0) {
    redirect("reports_dashboard.php");
}

// ── Handle POST (admin action) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        redirect("handle_report.php?report_id={$reportId}&error=csrf");
    }

    $newStatus  = trim($_POST['new_status'] ?? '');
    $adminNotes = InputValidator::sanitizeString($_POST['admin_notes'] ?? '');

    if (!Report::isValidStatus($newStatus) || $newStatus === 'pending') {
        redirect("handle_report.php?report_id={$reportId}&error=invalid_status");
    }

    $success = Report::updateStatus($reportId, $newStatus, $adminNotes);
    if ($success) {
        redirect("reports_dashboard.php?success={$newStatus}");
    } else {
        redirect("handle_report.php?report_id={$reportId}&error=failed");
    }
}

// ── Load report ────────────────────────────────────────────────────────────────
$report = Report::findById($reportId);
if (!$report) {
    redirect("reports_dashboard.php?error=not_found");
}

// ── Load reporter info ─────────────────────────────────────────────────────────
$stmtR = $db->prepare("SELECT name, email, profile_photo FROM ft_users WHERE user_id = ?");
$stmtR->execute([$report['reporter_id']]);
$reporter = $stmtR->fetch(PDO::FETCH_ASSOC);

// ── Load reported entity details ───────────────────────────────────────────────
$reportedDetail = null;
$reportedLink   = '#';

switch ($report['reported_type']) {
    case 'user':
        $s = $db->prepare("SELECT name, email FROM ft_users WHERE user_id = ?");
        $s->execute([$report['reported_id']]);
        $reportedDetail = $s->fetch(PDO::FETCH_ASSOC);
        $reportedLink   = "../profile/public_profile.php?user_id={$report['reported_id']}";
        break;

    case 'trip':
        $s = $db->prepare("SELECT title, status FROM ft_trips WHERE trip_id = ?");
        $s->execute([$report['reported_id']]);
        $reportedDetail = $s->fetch(PDO::FETCH_ASSOC);
        $reportedLink   = "../trips/view.php?id={$report['reported_id']}";
        break;

    case 'message':
        $s = $db->prepare("SELECT message_text, sender_id, receiver_id FROM ft_messages WHERE message_id = ?");
        $s->execute([$report['reported_id']]);
        $reportedDetail = $s->fetch(PDO::FETCH_ASSOC);
        break;

    case 'photo':
        $s = $db->prepare("SELECT photo_url, user_id FROM ft_photos WHERE photo_id = ?");
        $s->execute([$report['reported_id']]);
        $reportedDetail = $s->fetch(PDO::FETCH_ASSOC);
        break;

    case 'comment':
        $s = $db->prepare("SELECT comment_text, user_id FROM ft_comments WHERE comment_id = ?");
        $s->execute([$report['reported_id']]);
        $reportedDetail = $s->fetch(PDO::FETCH_ASSOC);
        break;
}

$csrfField = CSRF::getTokenField();

// ── Helpers ────────────────────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'pending'   => ['#fef3c7', '#92400e', '⏳ Pending'],
        'reviewed'  => ['#dbeafe', '#1e40af', '🔍 Reviewed'],
        'resolved'  => ['#dcfce7', '#166534', '✅ Resolved'],
        'dismissed' => ['#f3f4f6', '#4b5563', '🚫 Dismissed'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#f3f4f6', '#4b5563', ucfirst($status)];
    return "<span style=\"background:{$bg};color:{$color};padding:4px 12px;border-radius:14px;font-size:13px;font-weight:700;\">{$label}</span>";
}

$errorMsg = match ($_GET['error'] ?? '') {
    'csrf'           => '❌ Security token invalid.',
    'invalid_status' => '❌ Invalid status selected.',
    'failed'         => '❌ Action failed. Please try again.',
    default          => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handle Report #<?php echo $reportId; ?> — Admin</title>
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

        .container { max-width: 820px; margin: 32px auto; padding: 0 20px; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px; color: #64748b;
            text-decoration: none; font-size: 14px; margin-bottom: 20px;
        }
        .back-link:hover { color: #0f172a; }

        h1 { font-size: 24px; color: #0f172a; margin-bottom: 24px; }

        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 13px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;
        }

        .card {
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 20px;
        }
        .card h2 { font-size: 16px; color: #64748b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .05em; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .detail-row { display: flex; flex-direction: column; gap: 4px; }
        .detail-label { font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
        .detail-value { font-size: 15px; color: #0f172a; word-break: break-word; }

        .description-box {
            background: #f8fafc; border-radius: 8px; padding: 14px;
            color: #374151; font-size: 14px; line-height: 1.6; margin-top: 12px;
            white-space: pre-wrap; word-break: break-word;
        }

        .reported-box {
            background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px;
            padding: 14px 18px; color: #78350f; font-size: 14px; line-height: 1.6;
        }
        .reported-link {
            display: inline-block; margin-top: 8px; color: #2563eb; text-decoration: none; font-size: 13px;
        }
        .reported-link:hover { text-decoration: underline; }

        /* Action form */
        .action-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .action-card h2 { font-size: 18px; color: #0f172a; margin-bottom: 20px; }

        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
        .action-btn {
            flex: 1; min-width: 130px; padding: 12px 14px;
            border: 2px solid transparent; border-radius: 8px; font-size: 14px; font-weight: 700;
            cursor: pointer; transition: all 0.15s; text-align: center;
        }
        .action-btn.reviewed  { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .action-btn.resolved  { background: #dcfce7; color: #166534; border-color: #86efac; }
        .action-btn.dismissed { background: #f3f4f6; color: #4b5563; border-color: #d1d5db; }
        .action-btn.selected  { outline: 3px solid #0f172a; outline-offset: 2px; }
        .action-btn:hover     { filter: brightness(0.95); }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; font-size: 14px; }
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; font-family: inherit;
            resize: vertical; min-height: 90px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #0f172a; }

        .btn-submit {
            padding: 13px 28px; background: #0f172a; color: white;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #1e293b; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        .already-handled {
            background: #f1f5f9; border-radius: 8px; padding: 16px 20px;
            color: #64748b; font-size: 14px; border: 1px solid #e2e8f0;
        }
        .already-handled .notes { margin-top: 8px; color: #374151; font-style: italic; }

        input[type="hidden"]#new_status { display: none; }
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

    <a href="reports_dashboard.php" class="back-link">← Back to Reports Dashboard</a>

    <h1>🔍 Report #<?php echo $reportId; ?> &nbsp;<?php echo statusBadge($report['status']); ?></h1>

    <?php if ($errorMsg): ?>
        <div class="alert-error"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Report Details -->
    <div class="card">
        <h2>Report Details</h2>
        <div class="detail-grid">
            <div class="detail-row">
                <span class="detail-label">Reporter</span>
                <span class="detail-value">
                    <?php echo htmlspecialchars($reporter['name'] ?? 'Unknown'); ?>
                    <span style="color:#94a3b8;font-size:13px;"> &lt;<?php echo htmlspecialchars($reporter['email'] ?? ''); ?>&gt;</span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Submitted</span>
                <span class="detail-value"><?php echo date('d M Y, H:i', strtotime($report['created_at'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Reported Type</span>
                <span class="detail-value" style="text-transform:capitalize;"><?php echo htmlspecialchars($report['reported_type']); ?> (ID: <?php echo (int)$report['reported_id']; ?>)</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Category</span>
                <span class="detail-value"><?php echo Report::getCategoryLabel($report['category']); ?></span>
            </div>
        </div>

        <div style="margin-top:18px;">
            <div class="detail-label" style="margin-bottom:6px;">Description</div>
            <?php if (!empty($report['description'])): ?>
                <div class="description-box"><?php echo htmlspecialchars($report['description']); ?></div>
            <?php else: ?>
                <p style="color:#94a3b8;font-style:italic;font-size:14px;">No description provided.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reported Entity -->
    <div class="card">
        <h2>Reported <?php echo ucfirst(htmlspecialchars($report['reported_type'])); ?></h2>
        <div class="reported-box">
            <?php if ($reportedDetail): ?>
                <?php foreach ($reportedDetail as $key => $val): ?>
                    <div>
                        <strong><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</strong>
                        <?php echo htmlspecialchars((string) $val); ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($reportedLink !== '#'): ?>
                    <a href="<?php echo htmlspecialchars($reportedLink); ?>" target="_blank" class="reported-link">
                        → View <?php echo ucfirst($report['reported_type']); ?> ↗
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <em>The reported <?php echo htmlspecialchars($report['reported_type']); ?> could not be found (may have been deleted).</em>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Notes (if already handled) -->
    <?php if (!empty($report['admin_notes'])): ?>
    <div class="card">
        <h2>Previous Admin Notes</h2>
        <div class="description-box"><?php echo htmlspecialchars($report['admin_notes']); ?></div>
    </div>
    <?php endif; ?>

    <!-- Action -->
    <div class="action-card">
        <h2>⚙️ Take Action</h2>

        <?php if (in_array($report['status'], ['resolved', 'dismissed'], true)): ?>
            <div class="already-handled">
                This report is already marked as <strong><?php echo ucfirst($report['status']); ?></strong> and requires no further action.
                <?php if (!empty($report['admin_notes'])): ?>
                    <div class="notes">Notes: <?php echo htmlspecialchars($report['admin_notes']); ?></div>
                <?php endif; ?>
                <div style="margin-top:12px;">
                    <a href="reports_dashboard.php" style="color:#2563eb;text-decoration:none;font-size:14px;">← Return to Dashboard</a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" action="handle_report.php?report_id=<?php echo $reportId; ?>" id="actionForm">
                <?php echo $csrfField; ?>
                <input type="hidden" name="new_status" id="new_status_input" value="">

                <div class="action-buttons">
                    <button type="button" class="action-btn reviewed" onclick="selectAction('reviewed', this)">
                        🔍 Mark Reviewed
                    </button>
                    <button type="button" class="action-btn resolved" onclick="selectAction('resolved', this)">
                        ✅ Mark Resolved
                    </button>
                    <button type="button" class="action-btn dismissed" onclick="selectAction('dismissed', this)">
                        🚫 Dismiss Report
                    </button>
                </div>

                <div class="form-group">
                    <label for="admin_notes">Admin Notes <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
                    <textarea
                        id="admin_notes"
                        name="admin_notes"
                        maxlength="2000"
                        placeholder="Add any notes about the action taken or outcome (visible to admin team only)…"
                    ><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    Apply Action
                </button>
            </form>
        <?php endif; ?>
    </div>

</div><!-- /.container -->

<script>
    let selectedStatus = '';

    function selectAction(status, btn) {
        // Deselect all
        document.querySelectorAll('.action-btn').forEach(b => b.classList.remove('selected'));
        // Select clicked
        btn.classList.add('selected');
        selectedStatus = status;
        document.getElementById('new_status_input').value = status;
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Apply: ' + btn.textContent.trim();
    }

    // Confirm before submit
    document.getElementById('actionForm')?.addEventListener('submit', function (e) {
        if (!selectedStatus) {
            e.preventDefault();
            alert('Please select an action first.');
            return;
        }
        const confirmed = confirm('Apply action "' + selectedStatus + '" to this report?');
        if (!confirmed) e.preventDefault();
    });
</script>
</body>
</html>
