<?php
// moderation/report_form.php
// Task T30 — Report submission form (GET shows form, links from anywhere in app)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Report.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');

// ── Read & validate query params ───────────────────────────────────────────────
$reportedType = trim($_GET['type'] ?? '');
$reportedId   = (int) ($_GET['id'] ?? 0);

if (!Report::isValidType($reportedType) || $reportedId <= 0) {
    redirect("../dashboard.php?error=invalid_report");
}

// ── Prevent self-report for user type ─────────────────────────────────────────
if ($reportedType === 'user' && $reportedId === $userId) {
    redirect("../dashboard.php?error=cannot_report_self");
}

// ── Check for recent duplicate ─────────────────────────────────────────────────
$duplicate = Report::findDuplicate($userId, $reportedType, $reportedId);
if ($duplicate) {
    redirect("../dashboard.php?error=already_reported");
}

// ── Build context label ────────────────────────────────────────────────────────
$contextLabel = match ($reportedType) {
    'user'    => 'a user',
    'trip'    => 'a trip',
    'message' => 'a message',
    'photo'   => 'a photo',
    'comment' => 'a comment',
    default   => 'this content',
};

$csrfField  = CSRF::getTokenField();
$categories = Report::getCategories();
$error      = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report <?php echo htmlspecialchars(ucfirst($reportedType)); ?> — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 15px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        .container { max-width: 560px; margin: 36px auto; padding: 0 20px; }

        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 15px;
        }
        .info-box {
            background: #eff6ff; border: 1px solid #bfdbfe;
            padding: 14px 18px; border-radius: 8px; margin-bottom: 24px;
            color: #1e3a8a; font-size: 14px; line-height: 1.6;
        }

        .form-card {
            background: white; padding: 32px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .form-card h1 { color: #dc2626; font-size: 22px; margin-bottom: 6px; }
        .form-card .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }

        .form-group { margin-bottom: 22px; }
        .field-label { display: block; font-weight: 700; margin-bottom: 10px; color: #374151; }
        .required { color: #dc2626; }

        /* Category radio list */
        .category-list { display: flex; flex-direction: column; gap: 8px; }
        .category-row {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 14px; border: 2px solid #e5e7eb; border-radius: 8px;
            cursor: pointer; transition: border-color 0.15s, background 0.15s;
        }
        .category-row:hover { border-color: #dc2626; background: #fff5f5; }
        .category-row input[type="radio"] { margin-top: 2px; cursor: pointer; accent-color: #dc2626; }
        .category-row.selected { border-color: #dc2626; background: #fff1f2; }
        .cat-label { font-size: 15px; font-weight: 600; color: #374151; }
        .cat-desc  { font-size: 12px; color: #64748b; margin-top: 2px; }

        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 15px; font-family: inherit;
            resize: vertical; min-height: 100px; line-height: 1.5;
        }
        textarea:focus { outline: none; border-color: #dc2626; }
        .char-hint { font-size: 12px; color: #94a3b8; margin-top: 4px; }

        .btn-row { display: flex; gap: 12px; margin-top: 4px; }
        .btn-submit {
            flex: 1; padding: 14px; background: #dc2626; color: white;
            border: none; border-radius: 8px; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #b91c1c; }
        .btn-back {
            padding: 14px 22px; background: #e5e7eb; color: #374151;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 600;
            cursor: pointer; text-decoration: none; text-align: center;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #d1d5db; }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if ($error): ?>
        <div class="alert-error">
            <?php
            echo match ($error) {
                'csrf'        => '❌ Security token invalid. Please try again.',
                'category'    => '❌ Please select a report category.',
                'desc_length' => '❌ Description must be under 1000 characters.',
                'failed'      => '❌ Failed to submit report. Please try again.',
                default       => '❌ Something went wrong.',
            };
            ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        🛡️ You are reporting <strong><?php echo htmlspecialchars($contextLabel); ?></strong>.
        Reports are reviewed by our moderation team within 24–48 hours.
        Repeated false reports may result in account restrictions.
    </div>

    <div class="form-card">
        <h1>🚩 Submit a Report</h1>
        <div class="subtitle">Help keep Fellow Traveler safe for everyone.</div>

        <form method="POST" action="submit_report.php" id="reportForm">
            <?php echo $csrfField; ?>
            <input type="hidden" name="reported_type" value="<?php echo htmlspecialchars($reportedType); ?>">
            <input type="hidden" name="reported_id"   value="<?php echo $reportedId; ?>">

            <!-- Category -->
            <div class="form-group">
                <label class="field-label">
                    Reason for Report <span class="required">*</span>
                </label>
                <div class="category-list" id="categoryList">
                    <?php
                    $catDescriptions = [
                        'spam'          => 'Unsolicited or repetitive content, irrelevant links.',
                        'harassment'    => 'Threatening, bullying, or offensive behaviour.',
                        'inappropriate' => 'Adult, violent, or otherwise unsuitable content.',
                        'fraud'         => 'Deceptive trips, fake profiles, or scam attempts.',
                        'safety'        => 'Activity that poses a risk to physical safety.',
                        'other'         => 'Does not fit any of the above categories.',
                    ];
                    foreach ($categories as $cat):
                    ?>
                        <label class="category-row" id="row_<?php echo $cat; ?>">
                            <input
                                type="radio"
                                name="category"
                                value="<?php echo $cat; ?>"
                                onchange="highlightRow('<?php echo $cat; ?>')"
                                required
                            >
                            <div>
                                <div class="cat-label"><?php echo Report::getCategoryLabel($cat); ?></div>
                                <div class="cat-desc"><?php echo htmlspecialchars($catDescriptions[$cat]); ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="field-label" for="description">
                    Additional Details <span style="color:#94a3b8;font-weight:400;">(optional)</span>
                </label>
                <textarea
                    id="description"
                    name="description"
                    maxlength="1000"
                    placeholder="Provide any context that might help the moderation team (e.g., links, dates, specific messages)…"
                    oninput="updateCharCount(this)"
                ></textarea>
                <div class="char-hint" id="charCount">0 / 1000 characters</div>
            </div>

            <div class="btn-row">
                <a href="javascript:history.back()" class="btn-back">← Go Back</a>
                <button type="submit" class="btn-submit">🚩 Submit Report</button>
            </div>
        </form>
    </div>

</div><!-- /.container -->

<script>
    function highlightRow(selected) {
        document.querySelectorAll('.category-row').forEach(row => row.classList.remove('selected'));
        const label = document.getElementById('row_' + selected);
        if (label) label.classList.add('selected');
    }

    function updateCharCount(el) {
        document.getElementById('charCount').textContent = el.value.length + ' / 1000 characters';
    }

    // Restore any pre-selected row on page reload
    document.querySelectorAll('input[name="category"]').forEach(radio => {
        if (radio.checked) highlightRow(radio.value);
    });
</script>
</body>
</html>
