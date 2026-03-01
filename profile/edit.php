<?php
// profile/edit.php
// Task T12 — Profile edit form (pre-filled with current user data)

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../auth/auth_check.php";
require_once "../config/database.php";
require_once "../models/User.php";
require_once "../includes/CSRF.php";
require_once "../includes/helpers.php";

$userId = (int) SessionManager::get('user_id');
$user   = User::findById($userId);

if (!$user) {
    redirect("../auth/logout.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — Fellow Traveler</title>
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

        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }

        .form-card {
            background: white; padding: 30px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        h2 { color: #1d4ed8; margin-bottom: 20px; font-size: 22px; }

        /* Photo section */
        .photo-section {
            display: flex; align-items: center; gap: 30px;
            margin-bottom: 30px; padding-bottom: 30px;
            border-bottom: 2px solid #e5e7eb; flex-wrap: wrap;
        }
        .current-photo {
            width: 130px; height: 130px; border-radius: 50%;
            object-fit: cover; border: 4px solid #1d4ed8; flex-shrink: 0;
        }
        .photo-upload-label {
            background: #1d4ed8; color: white; padding: 10px 20px;
            border-radius: 5px; cursor: pointer; display: inline-block;
            font-size: 14px; font-weight: 600;
        }
        .photo-upload-label:hover { background: #1e40af; }
        .photo-input { display: none; }

        /* Form elements */
        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #374151; }
        input[type="text"], input[type="tel"], input[type="date"],
        input[type="email"], textarea {
            width: 100%; padding: 11px 14px; border: 1px solid #d1d5db;
            border-radius: 5px; font-size: 15px; font-family: inherit;
        }
        input:focus, textarea:focus { outline: none; border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29,78,216,0.15); }
        textarea { resize: vertical; min-height: 100px; }
        .readonly { background: #f3f4f6; cursor: not-allowed; color: #6b7280; }
        .char-counter { text-align: right; color: #6b7280; font-size: 13px; margin-top: 4px; }

        /* Buttons */
        .buttons { display: flex; gap: 10px; margin-top: 25px; }
        .btn {
            padding: 12px 28px; border-radius: 5px; border: none;
            font-size: 15px; cursor: pointer; text-decoration: none;
            display: inline-block; text-align: center; font-weight: 600;
        }
        .btn-primary   { background: #1d4ed8; color: white; }
        .btn-primary:hover { background: #1e40af; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }

        /* Messages */
        .success {
            background: #d1fae5; color: #065f46; padding: 14px 18px;
            border-radius: 5px; margin-bottom: 20px; border: 1px solid #6ee7b7;
        }
        .error {
            background: #fee2e2; color: #b91c1c; padding: 14px 18px;
            border-radius: 5px; margin-bottom: 20px; border: 1px solid #fca5a5;
        }

        .hint { font-size: 13px; color: #6b7280; margin-top: 4px; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .photo-section { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <a href="my_profile.php">← My Profile</a>
        <a href="../dashboard.php">Dashboard</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <?php if (isset($_GET['success'])): ?>
        <div class="success">✅ Profile updated successfully!</div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php
            $err = $_GET['error'];
            if ($err === 'validation') echo "❌ Please check all fields — some values are invalid.";
            elseif ($err === 'photo')  echo "❌ Photo upload failed. Please try a JPG, PNG or GIF under 5 MB.";
            elseif ($err === 'csrf')   echo "❌ Security token expired. Please try again.";
            else                       echo "❌ Update failed. Please try again.";
            ?>
        </div>
    <?php endif; ?>

    <!-- ── Profile Info Form ── -->
    <div class="form-card">
        <h2>✏️ Edit Profile</h2>

        <!-- Photo upload -->
        <div class="photo-section">
            <img
                src="<?php echo !empty($user['profile_photo'])
                    ? '../uploads/profiles/' . htmlspecialchars($user['profile_photo'])
                    : '../assets/default-avatar.png'; ?>"
                alt="Profile Photo"
                class="current-photo"
                id="photoPreview"
            >
            <div>
                <p style="font-weight:bold; margin-bottom:6px;">Profile Photo</p>
                <p class="hint">JPG, PNG or GIF — max 5 MB.</p>
                <form action="photo_upload.php" method="POST" enctype="multipart/form-data" id="photoForm" style="margin-top:12px;">
                    <?php echo CSRF::getTokenField(); ?>
                    <label class="photo-upload-label" for="photoInput">
                        📷 Choose Photo
                    </label>
                    <input type="file" id="photoInput" name="profile_photo"
                           accept="image/jpeg,image/png,image/gif" class="photo-input">
                </label>
                </form>
            </div>
        </div>

        <!-- Main edit form -->
        <form action="update.php" method="POST">
            <?php echo CSRF::getTokenField(); ?>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required minlength="2" maxlength="50"
                       value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username (cannot be changed)</label>
                    <input type="text" class="readonly" readonly
                           value="<?php echo htmlspecialchars($user['username']); ?>">
                </div>
                <div class="form-group">
                    <label>Email (cannot be changed)</label>
                    <input type="email" class="readonly" readonly
                           value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" id="bioInput" maxlength="500"
                          placeholder="Tell fellow travelers about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                <div class="char-counter"><span id="bioCount">0</span> / 500 characters</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone"
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                           placeholder="e.g. 9876543210">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob"
                           value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>"
                           max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" maxlength="100"
                           value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                           placeholder="e.g. Mumbai">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" maxlength="100"
                           value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>"
                           placeholder="e.g. India">
                </div>
            </div>

            <div class="buttons">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <a href="my_profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- ── Password Change Link ── -->
    <div class="form-card">
        <h2>🔒 Security</h2>
        <p style="color:#6b7280; margin-bottom:12px;">
            Want to update your password?
        </p>
        <a href="password_change.php" class="btn btn-primary">Change Password</a>
    </div>

</div><!-- /.container -->

<script>
    // Bio character counter
    const bioInput = document.getElementById('bioInput');
    const bioCount = document.getElementById('bioCount');
    function updateCount() { bioCount.textContent = bioInput.value.length; }
    bioInput.addEventListener('input', updateCount);
    updateCount(); // initialise

    // Photo preview + auto-submit on file select
    document.getElementById('photoInput').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => { document.getElementById('photoPreview').src = e.target.result; };
            reader.readAsDataURL(this.files[0]);
            document.getElementById('photoForm').submit();
        }
    });
</script>
</body>
</html>
