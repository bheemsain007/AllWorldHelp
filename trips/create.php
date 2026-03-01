<?php
// trips/create.php
// Task T15 — Trip creation form with photo upload and itinerary builder

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$tomorrow       = date('Y-m-d', strtotime('+1 day'));
$dayAfterTomorrow = date('Y-m-d', strtotime('+2 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Trip — Fellow Traveler</title>
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
            background: white; padding: 28px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09); margin-bottom: 22px; text-align: center;
        }
        .page-header h1 { color: #1d4ed8; margin-bottom: 6px; }
        .page-header p  { color: #64748b; font-size: 15px; }

        .form-card {
            background: white; padding: 28px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09); margin-bottom: 22px;
        }
        .form-card h2 { color: #1d4ed8; margin-bottom: 20px; font-size: 20px; }

        .form-group { margin-bottom: 20px; }
        .form-row    { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        label { display: block; margin-bottom: 6px; font-weight: bold; color: #374151; }
        .required { color: #dc2626; }

        input[type="text"], input[type="number"], input[type="date"],
        textarea, select {
            width: 100%; padding: 11px 14px; border: 1px solid #d1d5db;
            border-radius: 6px; font-size: 15px; font-family: Arial;
        }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29,78,216,0.15);
        }
        textarea { resize: vertical; min-height: 120px; }
        .char-counter { text-align: right; color: #6b7280; font-size: 13px; margin-top: 4px; }
        .hint { font-size: 13px; color: #6b7280; margin-top: 4px; }

        /* Photo upload */
        .photo-upload-zone {
            border: 2px dashed #d1d5db; border-radius: 8px;
            padding: 30px; text-align: center; cursor: pointer;
            transition: border-color 0.2s; background: #fafafa;
        }
        .photo-upload-zone:hover { border-color: #1d4ed8; background: #eff6ff; }
        .photo-upload-zone input { display: none; }
        .photo-upload-zone .icon { font-size: 40px; margin-bottom: 10px; }
        #imagePreview {
            max-width: 100%; max-height: 300px; margin-top: 16px;
            border-radius: 8px; display: none; object-fit: cover;
        }

        /* Itinerary */
        .itinerary-day {
            background: #f8fafc; padding: 16px; border-radius: 8px;
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
            border: none; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 600; margin-top: 10px;
        }
        .add-day-btn:hover { background: #15803d; }

        /* Error messages */
        .alert-error {
            background: #fee2e2; color: #b91c1c; padding: 12px 16px;
            border-radius: 6px; margin-bottom: 18px; border: 1px solid #fca5a5;
            font-size: 14px;
        }

        /* Buttons */
        .form-actions { display: flex; gap: 12px; margin-top: 10px; }
        .btn {
            padding: 14px 28px; border-radius: 6px; border: none;
            font-size: 16px; font-weight: 700; cursor: pointer;
            text-decoration: none; display: inline-block; text-align: center;
        }
        .btn-primary   { background: #1d4ed8; color: white; flex: 1; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="search.php">Browse Trips</a>
        <a href="../profile/my_profile.php">Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <div class="page-header">
        <h1>✈️ Create New Trip</h1>
        <p>Plan your adventure and find fellow travelers to join you!</p>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert-error">
            <?php
            $err = $_GET['error'];
            $messages = [
                'csrf'        => '❌ Security token expired. Please try again.',
                'title'       => '❌ Title must be 5–100 characters.',
                'destination' => '❌ Please enter a valid destination.',
                'description' => '❌ Description must be 50–2000 characters.',
                'date'        => '❌ Invalid date format.',
                'past_date'   => '❌ Start date must be in the future.',
                'date_order'  => '❌ End date must be after start date.',
                'budget'      => '❌ Minimum budget is ₹500.',
                'travelers'   => '❌ Max travelers must be between 2 and 50.',
                'type'        => '❌ Please select a valid trip type.',
                'ratelimit'   => '❌ Too many trips created. Please wait before creating another.',
                'failed'      => '❌ Failed to create trip. Please try again.',
            ];
            echo $messages[$err] ?? '❌ Please fix the errors below.';
            ?>
        </div>
    <?php endif; ?>

    <form action="create_process.php" method="POST" enctype="multipart/form-data" id="createForm">
        <?php echo CSRF::getTokenField(); ?>

        <!-- ── Basic Information ── -->
        <div class="form-card">
            <h2>📋 Basic Information</h2>

            <div class="form-group">
                <label>Trip Title <span class="required">*</span></label>
                <input type="text" name="title" required minlength="5" maxlength="100"
                       placeholder="e.g., Goa Beach Adventure – Jan 2026"
                       value="<?php echo htmlspecialchars($_GET['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Destination <span class="required">*</span></label>
                <input type="text" name="destination" required minlength="2" maxlength="100"
                       placeholder="e.g., Goa, India">
            </div>

            <div class="form-group">
                <label>Description <span class="required">*</span>
                    <span style="font-weight:normal; font-size:13px; color:#6b7280;">(minimum 50 characters)</span>
                </label>
                <textarea name="description" id="descriptionInput" required
                          minlength="50" maxlength="2000"
                          placeholder="Describe your trip — highlights, vibe, what travelers can expect..."></textarea>
                <div class="char-counter"><span id="descCount">0</span> / 2000 characters</div>
            </div>
        </div>

        <!-- ── Dates, Budget & Travelers ── -->
        <div class="form-card">
            <h2>📅 Dates, Budget & Group Size</h2>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date <span class="required">*</span></label>
                    <input type="date" name="start_date" required min="<?php echo $tomorrow; ?>">
                </div>
                <div class="form-group">
                    <label>End Date <span class="required">*</span></label>
                    <input type="date" name="end_date" required min="<?php echo $dayAfterTomorrow; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Budget Per Person (₹) <span class="required">*</span></label>
                    <input type="number" name="budget_per_person" required
                           min="500" step="100" placeholder="e.g., 15000">
                    <p class="hint">Minimum ₹500 per person</p>
                </div>
                <div class="form-group">
                    <label>Max Travelers <span class="required">*</span></label>
                    <input type="number" name="max_travelers" required
                           min="2" max="50" placeholder="e.g., 6">
                    <p class="hint">2 – 50 people (including you)</p>
                </div>
            </div>

            <div class="form-group">
                <label>Trip Type <span class="required">*</span></label>
                <select name="trip_type" required>
                    <option value="">— Select type —</option>
                    <option value="solo">🎒 Solo Travel (looking for companions)</option>
                    <option value="group">👥 Group Travel</option>
                    <option value="family">👨‍👩‍👧 Family Trip</option>
                    <option value="couple">💑 Couple</option>
                    <option value="friends">🤝 Friends</option>
                </select>
            </div>
        </div>

        <!-- ── Trip Image ── -->
        <div class="form-card">
            <h2>🖼️ Trip Photo (Optional)</h2>
            <label for="tripImage" class="photo-upload-zone">
                <div class="icon">📷</div>
                <div><strong>Click to upload trip photo</strong></div>
                <div class="hint" style="margin-top:6px;">JPG, PNG or GIF — max 5 MB</div>
                <input type="file" id="tripImage" name="trip_image"
                       accept="image/jpeg,image/png,image/gif">
                <img id="imagePreview" alt="Preview">
            </label>
        </div>

        <!-- ── Day-wise Itinerary ── -->
        <div class="form-card">
            <h2>🗓️ Day-wise Itinerary (Optional)</h2>
            <p class="hint" style="margin-bottom:16px;">Break down your trip day by day to help companions plan.</p>

            <div id="itineraryContainer">
                <div class="itinerary-day">
                    <label>Day 1</label>
                    <textarea name="itinerary[]" rows="3"
                              placeholder="Describe activities for Day 1…"></textarea>
                </div>
            </div>

            <button type="button" class="add-day-btn" onclick="addDay()">+ Add Day</button>
        </div>

        <!-- ── Actions ── -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">🚀 Create Trip</button>
            <a href="search.php" class="btn btn-secondary">Cancel</a>
        </div>

    </form>
</div><!-- /.container -->

<script>
    // ── Description character counter
    const descInput = document.getElementById('descriptionInput');
    const descCount = document.getElementById('descCount');
    descInput.addEventListener('input', function () { descCount.textContent = this.value.length; });

    // ── Image preview
    document.getElementById('tripImage').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const preview = document.getElementById('imagePreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ── Itinerary day builder
    let dayCount = 1;
    function addDay() {
        dayCount++;
        const container = document.getElementById('itineraryContainer');
        const div = document.createElement('div');
        div.className = 'itinerary-day';
        div.innerHTML = `
            <button type="button" class="remove-day" onclick="removeDay(this)">✕ Remove</button>
            <label>Day ${dayCount}</label>
            <textarea name="itinerary[]" rows="3"
                      placeholder="Describe activities for Day ${dayCount}…"></textarea>
        `;
        container.appendChild(div);
    }

    function removeDay(btn) {
        btn.closest('.itinerary-day').remove();
        // Renumber remaining days
        document.querySelectorAll('.itinerary-day label').forEach((lbl, i) => {
            lbl.textContent = `Day ${i + 1}`;
        });
        dayCount = document.querySelectorAll('.itinerary-day').length;
    }

    // ── Date validation: end date must be after start date
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput   = document.querySelector('input[name="end_date"]');
    startDateInput.addEventListener('change', function () {
        const next = new Date(this.value);
        next.setDate(next.getDate() + 1);
        endDateInput.min = next.toISOString().split('T')[0];
        if (endDateInput.value && endDateInput.value <= this.value) {
            endDateInput.value = next.toISOString().split('T')[0];
        }
    });
</script>
</body>
</html>
