<?php
// trips/edit.php
// Task T16 — Trip edit form: pre-filled, owner-only permission, itinerary builder

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

// ── Resolve trip ──────────────────────────────────────────────────────────────
if (!isset($_GET['id'])) {
    redirect("search.php");
}

$tripId = (int) $_GET['id'];
$trip   = Trip::findById($tripId);

if (!$trip) {
    http_response_code(404);
    die("Trip not found.");
}

// ── Permission check ──────────────────────────────────────────────────────────
$userId = (int) SessionManager::get('user_id');
if ((int) $trip['user_id'] !== $userId) {
    http_response_code(403);
    die("You don't have permission to edit this trip.");
}

$hasStarted     = strtotime($trip['start_date']) <= time();
$hasParticipants = (int) $trip['joined_travelers'] > 0;

// Decode existing itinerary for pre-filling
$itinerary = [];
if (!empty($trip['itinerary'])) {
    $decoded = json_decode($trip['itinerary'], true);
    if (is_array($decoded)) {
        $itinerary = $decoded;
    }
}
$dayCount = count($itinerary) ?: 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Trip — <?php echo htmlspecialchars($trip['title']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 14px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        .container { max-width: 820px; margin: 30px auto; padding: 0 20px; }
        .page-header {
            background: white; padding: 24px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09); margin-bottom: 20px; text-align: center;
        }
        .page-header h1 { color: #1d4ed8; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .form-card {
            background: white; padding: 26px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09); margin-bottom: 20px;
        }
        .form-card h2 { color: #1d4ed8; margin-bottom: 18px; font-size: 19px; }

        .form-group { margin-bottom: 18px; }
        .form-row    { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        label { display: block; margin-bottom: 5px; font-weight: bold; color: #374151; }
        .required { color: #dc2626; }

        input[type="text"], input[type="number"], input[type="date"],
        textarea, select {
            width: 100%; padding: 11px 14px; border: 1px solid #d1d5db;
            border-radius: 6px; font-size: 15px; font-family: Arial;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #1d4ed8;
        }
        input[readonly], input[readonly]:focus {
            background: #f3f4f6; color: #6b7280; cursor: not-allowed;
            border-color: #d1d5db; box-shadow: none;
        }
        textarea { resize: vertical; min-height: 120px; }
        .char-counter { text-align: right; color: #6b7280; font-size: 13px; margin-top: 4px; }
        .hint { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .locked-note { font-size: 13px; color: #dc2626; margin-top: 4px; }

        /* Warning boxes */
        .warning-box {
            background: #fef3c7; border-left: 4px solid #f59e0b;
            padding: 14px 18px; margin-bottom: 18px; border-radius: 0 6px 6px 0;
            font-size: 14px;
        }

        /* Photo upload */
        .photo-upload-zone {
            border: 2px dashed #d1d5db; border-radius: 8px;
            padding: 22px; text-align: center; cursor: pointer;
            transition: border-color 0.2s; background: #fafafa;
        }
        .photo-upload-zone:hover { border-color: #1d4ed8; background: #eff6ff; }
        .photo-upload-zone input { display: none; }
        #imagePreview { max-width: 100%; max-height: 280px; border-radius: 8px; display: none; margin-top:12px; }
        .current-image { max-width: 100%; max-height: 280px; border-radius: 8px; }

        /* Itinerary */
        .itinerary-day {
            background: #f8fafc; padding: 14px; border-radius: 8px;
            margin-bottom: 10px; position: relative;
            border-left: 4px solid #1d4ed8;
        }
        .itinerary-day label { color: #1d4ed8; margin-bottom: 8px; font-size: 14px; }
        .remove-day {
            position: absolute; top: 10px; right: 10px;
            background: #dc2626; color: white; border: none;
            padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px;
        }
        .remove-day:hover { background: #b91c1c; }
        .add-day-btn {
            background: #16a34a; color: white; padding: 10px 20px;
            border: none; border-radius: 6px; cursor: pointer; font-size: 14px;
            font-weight: 600; margin-top: 10px;
        }
        .add-day-btn:hover { background: #15803d; }

        /* Danger Zone */
        .delete-section {
            background: #fff5f5; border: 2px solid #dc2626;
            padding: 24px; border-radius: 10px; margin-top: 10px;
        }
        .delete-section h2 { color: #dc2626; margin-bottom: 10px; }
        .delete-section p  { color: #6b7280; margin-bottom: 16px; font-size: 14px; }

        /* Alert */
        .alert-error {
            background: #fee2e2; color: #b91c1c; padding: 12px 16px;
            border-radius: 6px; margin-bottom: 16px; border: 1px solid #fca5a5; font-size: 14px;
        }

        /* Buttons */
        .form-actions { display: flex; gap: 12px; }
        .btn {
            padding: 13px 28px; border-radius: 6px; border: none;
            font-size: 16px; font-weight: 700; cursor: pointer;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-primary   { background: #1d4ed8; color: white; flex: 1; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-danger    { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }

        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="view.php?id=<?php echo $tripId; ?>">← Trip Details</a>
        <a href="search.php">Browse</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <h1>✏️ Edit Trip</h1>
        <p><?php echo htmlspecialchars($trip['title']); ?></p>
    </div>

    <?php if ($hasStarted): ?>
        <div class="warning-box">
            ⚠️ <strong>This trip has already started.</strong> You can only edit the description and itinerary.
        </div>
    <?php elseif ($hasParticipants): ?>
        <div class="warning-box">
            ⚠️ <strong><?php echo (int) $trip['joined_travelers']; ?> participant(s) have joined.</strong>
            Budget cannot be changed.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-error">
            <?php
            $e = $_GET['error'];
            $msgs = [
                'title'          => '❌ Title must be 5–100 characters.',
                'description'    => '❌ Description must be 50–2000 characters.',
                'date'           => '❌ Invalid date format.',
                'date_order'     => '❌ End date must be after start date.',
                'budget_locked'  => '❌ Budget cannot be changed after participants join.',
                'max_travelers'  => '❌ Max travelers cannot be less than current participant count.',
                'failed'         => '❌ Update failed. Please try again.',
            ];
            echo $msgs[$e] ?? '❌ Please fix the errors below.';
            ?>
        </div>
    <?php endif; ?>

    <form action="update.php" method="POST" enctype="multipart/form-data">
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

        <!-- ── Basic Information ── -->
        <div class="form-card">
            <h2>📋 Basic Information</h2>
            <div class="form-group">
                <label>Trip Title <span class="required">*</span></label>
                <input type="text" name="title" required minlength="5" maxlength="100"
                       value="<?php echo htmlspecialchars($trip['title']); ?>"
                       <?php echo $hasStarted ? 'readonly' : ''; ?>>
            </div>
            <div class="form-group">
                <label>Destination <span class="required">*</span></label>
                <input type="text" name="destination" required minlength="2" maxlength="100"
                       value="<?php echo htmlspecialchars($trip['destination']); ?>"
                       <?php echo $hasStarted ? 'readonly' : ''; ?>>
            </div>
            <div class="form-group">
                <label>Description <span class="required">*</span></label>
                <textarea name="description" id="descriptionInput" required
                          minlength="50" maxlength="2000"><?php echo htmlspecialchars($trip['description']); ?></textarea>
                <div class="char-counter">
                    <span id="descCount"><?php echo mb_strlen($trip['description']); ?></span> / 2000 characters
                </div>
            </div>
        </div>

        <!-- ── Dates, Budget & Size ── -->
        <div class="form-card">
            <h2>📅 Dates, Budget & Group Size</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required
                           value="<?php echo htmlspecialchars($trip['start_date']); ?>"
                           <?php echo $hasStarted ? 'readonly' : ''; ?>>
                </div>
                <div class="form-group">
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required
                           value="<?php echo htmlspecialchars($trip['end_date']); ?>"
                           <?php echo $hasStarted ? 'readonly' : ''; ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Budget Per Person (₹) <span class="required">*</span></label>
                    <input type="number" name="budget_per_person" required min="500"
                           value="<?php echo (int) $trip['budget_per_person']; ?>"
                           <?php echo $hasParticipants ? 'readonly' : ''; ?>>
                    <?php if ($hasParticipants): ?>
                        <p class="locked-note">🔒 Locked — participants have joined</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Max Travelers <span class="required">*</span></label>
                    <input type="number" name="max_travelers" required
                           min="<?php echo max(2, (int) $trip['joined_travelers']); ?>" max="50"
                           value="<?php echo (int) $trip['max_travelers']; ?>"
                           <?php echo $hasStarted ? 'readonly' : ''; ?>>
                    <?php if ($hasParticipants): ?>
                        <p class="hint">Minimum <?php echo (int) $trip['joined_travelers']; ?> (current participants)</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Trip Type <span class="required">*</span></label>
                <select name="trip_type" required <?php echo $hasStarted ? 'disabled' : ''; ?>>
                    <?php foreach (['solo', 'group', 'family', 'couple', 'friends'] as $type): ?>
                        <option value="<?php echo $type; ?>"
                            <?php echo $trip['trip_type'] === $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($hasStarted): ?>
                    <!-- Send the value as hidden because disabled inputs aren't submitted -->
                    <input type="hidden" name="trip_type" value="<?php echo htmlspecialchars($trip['trip_type']); ?>">
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Current / New Image ── -->
        <div class="form-card">
            <h2>🖼️ Trip Photo</h2>
            <?php if (!empty($trip['image'])): ?>
                <p class="hint" style="margin-bottom:12px;">Current photo:</p>
                <img src="../uploads/trips/<?php echo htmlspecialchars($trip['image']); ?>"
                     alt="Current trip photo" class="current-image">
                <p class="hint" style="margin-top:12px;">Upload a new photo below to replace it:</p>
            <?php endif; ?>
            <label for="tripImage" class="photo-upload-zone" style="margin-top:12px; display:block;">
                <div>📷 <?php echo $trip['image'] ? 'Upload replacement photo' : 'Upload trip photo'; ?> (optional)</div>
                <div class="hint" style="margin-top:6px;">JPG, PNG or GIF — max 5 MB</div>
                <input type="file" id="tripImage" name="trip_image"
                       accept="image/jpeg,image/png,image/gif">
                <img id="imagePreview" alt="New photo preview">
            </label>
        </div>

        <!-- ── Itinerary ── -->
        <div class="form-card">
            <h2>🗓️ Day-wise Itinerary</h2>
            <div id="itineraryContainer">
                <?php if (empty($itinerary)): ?>
                    <div class="itinerary-day">
                        <label>Day 1</label>
                        <textarea name="itinerary[]" rows="3" placeholder="Describe Day 1…"></textarea>
                    </div>
                <?php else: ?>
                    <?php foreach ($itinerary as $day => $plan): ?>
                        <div class="itinerary-day">
                            <?php if ($day > 1): ?>
                                <button type="button" class="remove-day"
                                        onclick="removeDay(this)">✕ Remove</button>
                            <?php endif; ?>
                            <label>Day <?php echo (int) $day; ?></label>
                            <textarea name="itinerary[]" rows="3"><?php echo htmlspecialchars($plan); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="add-day-btn" onclick="addDay()">+ Add Day</button>
        </div>

        <!-- ── Actions ── -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="view.php?id=<?php echo $tripId; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <!-- ── Danger Zone ── -->
    <div class="delete-section">
        <h2>⚠️ Danger Zone</h2>
        <p>Deleting a trip is permanent. Participants will lose access. This action cannot be undone.</p>
        <?php if ($hasStarted): ?>
            <p style="color:#dc2626; font-weight:600;">Cannot delete a trip that has already started.</p>
        <?php else: ?>
            <form action="delete.php" method="POST"
                  onsubmit="return confirm('Delete this trip permanently? This cannot be undone.')">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">
                <button type="submit" class="btn btn-danger">🗑️ Delete This Trip</button>
            </form>
        <?php endif; ?>
    </div>
</div><!-- /.container -->

<script>
    // Description character counter
    const descInput = document.getElementById('descriptionInput');
    const descCount = document.getElementById('descCount');
    descInput.addEventListener('input', function () { descCount.textContent = this.value.length; });

    // Image preview
    document.getElementById('tripImage').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const preview = document.getElementById('imagePreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Itinerary builder
    let dayCount = <?php echo $dayCount; ?>;

    function addDay() {
        dayCount++;
        const container = document.getElementById('itineraryContainer');
        const div = document.createElement('div');
        div.className = 'itinerary-day';
        div.innerHTML = `
            <button type="button" class="remove-day" onclick="removeDay(this)">✕ Remove</button>
            <label>Day ${dayCount}</label>
            <textarea name="itinerary[]" rows="3" placeholder="Describe Day ${dayCount}…"></textarea>
        `;
        container.appendChild(div);
    }

    function removeDay(btn) {
        btn.closest('.itinerary-day').remove();
        // Renumber
        document.querySelectorAll('.itinerary-day label').forEach((lbl, i) => {
            lbl.textContent = `Day ${i + 1}`;
        });
        dayCount = document.querySelectorAll('.itinerary-day').length;
    }
</script>
</body>
</html>
