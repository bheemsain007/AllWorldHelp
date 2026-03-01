<?php
// trips/gallery.php
// Task T23 — Trip photo gallery: 3-column grid, lightbox, upload modal, delete

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../models/TripPhoto.php";
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
$canUpload     = $isParticipant;   // organizer is already included via isParticipant

// ── Fetch Photos ──────────────────────────────────────────────────────────────
$photos     = TripPhoto::getPhotosForTrip($tripId);
$photoCount = count($photos);

// ── Flash Messages ────────────────────────────────────────────────────────────
$flash     = null;
$flashType = null;
$flashMap  = [
    'uploaded'        => ['✅ Photo uploaded successfully!',      'success'],
    'deleted'         => ['✅ Photo deleted.',                    'success'],
    'not_participant' => ['❌ Only trip participants can upload.', 'error'],
    'invalid_file'    => ['❌ Invalid file type or file too large (max 5 MB).', 'error'],
    'upload_failed'   => ['❌ Upload failed. Please try again.',  'error'],
    'delete_failed'   => ['❌ Could not delete photo.',           'error'],
    'permission'      => ['❌ You do not have permission.',       'error'],
    'csrf'            => ['❌ Security token invalid.',           'error'],
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
    <title>Gallery — <?php echo $tripTitle; ?></title>
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
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        /* ── Flash ── */
        .flash { padding: 14px 18px; border-radius: 6px; margin-bottom: 20px; font-size: 15px; }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.error   { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* ── Header ── */
        .header-card {
            background: white; padding: 22px 26px; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            flex-wrap: wrap;
        }
        .header-card h1 { color: #1d4ed8; font-size: 22px; margin-bottom: 4px; }
        .header-card p  { color: #64748b; font-size: 14px; }
        .btn-upload {
            background: #1d4ed8; color: white; border: none;
            padding: 11px 22px; border-radius: 8px; font-size: 14px;
            font-weight: 700; cursor: pointer; transition: background 0.2s; white-space: nowrap;
        }
        .btn-upload:hover { background: #1e40af; }

        /* ── Photo Grid ── */
        .photo-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 860px) { .photo-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .photo-grid { grid-template-columns: 1fr; } }

        /* ── Photo Card ── */
        .photo-card {
            background: white; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,.09); overflow: hidden;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .photo-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.13); }
        .photo-thumb {
            width: 100%; height: 220px; object-fit: cover;
            cursor: zoom-in; display: block; transition: opacity 0.2s;
        }
        .photo-thumb:hover { opacity: 0.92; }
        .photo-info { padding: 14px 16px; }
        .photo-caption {
            color: #374151; font-size: 13px; line-height: 1.5;
            margin-bottom: 10px; word-break: break-word;
        }
        .photo-meta {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 12px; color: #94a3b8; gap: 8px; flex-wrap: wrap;
        }
        .uploader { display: flex; align-items: center; gap: 6px; }
        .uploader-avatar {
            width: 22px; height: 22px; border-radius: 50%;
            object-fit: cover; border: 1px solid #e5e7eb;
        }
        .btn-delete-photo {
            background: none; border: 1px solid #fca5a5; color: #dc2626;
            padding: 3px 10px; border-radius: 5px; font-size: 11px;
            cursor: pointer; transition: background 0.15s; white-space: nowrap;
        }
        .btn-delete-photo:hover { background: #fee2e2; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 70px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,.08);
        }
        .empty-state .emoji { font-size: 52px; margin-bottom: 16px; }
        .empty-state h2 { color: #1e293b; margin-bottom: 8px; }
        .empty-state p  { color: #64748b; }

        /* ── Upload Modal ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 900;
            justify-content: center; align-items: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white; border-radius: 14px; padding: 32px;
            max-width: 480px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,.2);
        }
        .modal-box h2 { color: #1d4ed8; font-size: 20px; margin-bottom: 22px; }
        .upload-field {
            display: block; width: 100%; padding: 14px;
            border: 2px dashed #d1d5db; border-radius: 8px;
            text-align: center; cursor: pointer; font-size: 14px;
            color: #64748b; margin-bottom: 14px; transition: border-color 0.2s;
        }
        .upload-field:hover { border-color: #1d4ed8; }
        .upload-field input[type="file"] { display: none; }
        .caption-input {
            width: 100%; padding: 11px 13px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; font-family: inherit;
            margin-bottom: 18px;
        }
        .caption-input:focus { outline: none; border-color: #1d4ed8; }
        .modal-actions { display: flex; gap: 10px; }
        .btn-modal-submit {
            flex: 1; padding: 12px; background: #1d4ed8; color: white;
            border: none; border-radius: 8px; font-size: 14px;
            font-weight: 700; cursor: pointer; transition: background 0.2s;
        }
        .btn-modal-submit:hover { background: #1e40af; }
        .btn-modal-cancel {
            flex: 1; padding: 12px; background: #f1f5f9; color: #475569;
            border: none; border-radius: 8px; font-size: 14px;
            font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-modal-cancel:hover { background: #e2e8f0; }
        .file-chosen { font-size: 13px; color: #1d4ed8; margin-top: -10px; margin-bottom: 12px; }

        /* ── Lightbox ── */
        .lightbox {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.92); z-index: 1000;
            justify-content: center; align-items: center; padding: 20px;
        }
        .lightbox.open { display: flex; }
        .lightbox-img {
            max-width: 90vw; max-height: 88vh; border-radius: 8px;
            object-fit: contain; box-shadow: 0 4px 40px rgba(0,0,0,.5);
        }
        .lightbox-close {
            position: absolute; top: 18px; right: 28px;
            color: white; font-size: 42px; cursor: pointer;
            line-height: 1; user-select: none; transition: opacity 0.15s;
        }
        .lightbox-close:hover { opacity: 0.7; }
        .lightbox-caption {
            position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
            color: rgba(255,255,255,.85); font-size: 14px; text-align: center;
            max-width: 70%; background: rgba(0,0,0,.4);
            padding: 6px 16px; border-radius: 20px;
        }

        /* ── Join Notice ── */
        .join-notice {
            background: #fefce8; border: 1px solid #fef08a;
            color: #713f12; padding: 12px 18px; border-radius: 8px;
            font-size: 14px; margin-bottom: 20px;
        }
        .join-notice a { color: #1d4ed8; }
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
        <?php if ($viewerId): ?>
            <a href="../auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="../auth/login.php">Login</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?php echo htmlspecialchars($flashType); ?>"><?php echo $flash; ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="header-card">
        <div>
            <h1>📸 Trip Gallery</h1>
            <p>
                <strong><?php echo $tripTitle; ?></strong> ·
                <?php echo $photoCount; ?> photo<?php echo $photoCount !== 1 ? 's' : ''; ?>
            </p>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <a href="view.php?id=<?php echo $tripId; ?>"
               style="color:#1d4ed8;text-decoration:none;font-size:14px;">← Back to Trip</a>
            <?php if ($canUpload): ?>
                <button class="btn-upload" onclick="openUploadModal()">📤 Upload Photo</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($viewerId && !$canUpload): ?>
        <div class="join-notice">
            ℹ️ <a href="view.php?id=<?php echo $tripId; ?>">Join this trip</a> to upload photos.
        </div>
    <?php elseif (!$viewerId): ?>
        <div class="join-notice">
            ℹ️ <a href="../auth/login.php">Log in</a> and join the trip to upload photos.
        </div>
    <?php endif; ?>

    <!-- Gallery Grid -->
    <?php if (empty($photos)): ?>
        <div class="empty-state">
            <div class="emoji">📷</div>
            <h2>No photos yet</h2>
            <p>
                <?php if ($canUpload): ?>
                    Be the first to share a trip memory!
                <?php else: ?>
                    Participants will share trip photos here.
                <?php endif; ?>
            </p>
        </div>

    <?php else: ?>
        <div class="photo-grid">
            <?php foreach ($photos as $photo): ?>
                <?php
                    $photoFile   = htmlspecialchars($photo['photo_filename']);
                    $photoSrc    = '../uploads/trip_photos/' . $photoFile;
                    $caption     = htmlspecialchars($photo['caption'] ?? '');
                    $uploaderName = htmlspecialchars($photo['uploader_name']);
                    $uploaderImg = !empty($photo['uploader_photo'])
                        ? '../uploads/profiles/' . htmlspecialchars($photo['uploader_photo'])
                        : '../assets/default-avatar.png';
                    $ts = strtotime($photo['created_at']);
                    $dateStr = date('M j, Y', $ts) === date('M j, Y')
                        ? 'Today ' . date('g:i A', $ts)
                        : date('M j, Y', $ts);
                    $isOwn       = $viewerId && ((int) $photo['user_id'] === $viewerId);
                    $canDelete   = $isOwn || $isOrganizer;
                ?>
                <div class="photo-card">
                    <img
                        class="photo-thumb"
                        src="<?php echo $photoSrc; ?>"
                        alt="<?php echo $caption ?: 'Trip photo'; ?>"
                        onclick="openLightbox('<?php echo $photoSrc; ?>', '<?php echo addslashes($caption); ?>')"
                    >
                    <div class="photo-info">
                        <?php if ($caption): ?>
                            <div class="photo-caption"><?php echo $caption; ?></div>
                        <?php endif; ?>
                        <div class="photo-meta">
                            <span class="uploader">
                                <img class="uploader-avatar" src="<?php echo $uploaderImg; ?>" alt="">
                                <?php echo $uploaderName; ?>
                            </span>
                            <span><?php echo $dateStr; ?></span>
                            <?php if ($canDelete): ?>
                                <form method="POST" action="delete_photo.php" style="margin:0"
                                      onsubmit="return confirm('Delete this photo?')">
                                    <?php echo $csrfField; ?>
                                    <input type="hidden" name="photo_id" value="<?php echo (int) $photo['photo_id']; ?>">
                                    <input type="hidden" name="trip_id"  value="<?php echo $tripId; ?>">
                                    <button type="submit" class="btn-delete-photo">🗑 Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div><!-- /.container -->

<!-- Upload Modal -->
<?php if ($canUpload): ?>
<div id="uploadModal" class="modal-overlay" onclick="if(event.target===this) closeUploadModal()">
    <div class="modal-box">
        <h2>📤 Upload Photo</h2>
        <form id="uploadForm" action="upload_photo.php" method="POST" enctype="multipart/form-data">
            <?php echo $csrfField; ?>
            <input type="hidden" name="trip_id" value="<?php echo $tripId; ?>">

            <label class="upload-field" id="dropLabel" for="photoInput">
                <input type="file" id="photoInput" name="photo" accept=".jpg,.jpeg,.png,.gif" required>
                📁 Click to choose a photo (JPG, PNG, GIF — max 5 MB)
            </label>
            <div class="file-chosen" id="fileChosen" style="display:none"></div>

            <input
                type="text"
                name="caption"
                class="caption-input"
                placeholder="Caption (optional)"
                maxlength="200"
            >

            <div class="modal-actions">
                <button type="submit" class="btn-modal-submit">Upload</button>
                <button type="button" class="btn-modal-cancel" onclick="closeUploadModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImg" class="lightbox-img" src="" alt="">
    <div id="lightboxCaption" class="lightbox-caption" style="display:none"></div>
</div>

<script>
    // ── Upload Modal ──────────────────────────────────────────────────────────
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('open');
    }
    function closeUploadModal() {
        document.getElementById('uploadModal').classList.remove('open');
    }

    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            const label  = document.getElementById('fileChosen');
            if (this.files.length > 0) {
                label.textContent = '✅ ' + this.files[0].name;
                label.style.display = 'block';
                document.getElementById('dropLabel').style.borderColor = '#1d4ed8';
            }
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeUploadModal();
            closeLightbox();
        }
    });

    // ── Lightbox ──────────────────────────────────────────────────────────────
    function openLightbox(src, caption) {
        document.getElementById('lightboxImg').src = src;
        const cap = document.getElementById('lightboxCaption');
        if (caption) {
            cap.textContent = caption;
            cap.style.display = 'block';
        } else {
            cap.style.display = 'none';
        }
        document.getElementById('lightbox').classList.add('open');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.getElementById('lightboxImg').src = '';
    }
</script>
</body>
</html>
