<?php
// trips/search.php
// Task T13 — Trip search & browse page with filters, sorting, and pagination

require_once "../includes/SessionManager.php";
SessionManager::start();
require_once "../config/database.php";
require_once "../models/Trip.php";
require_once "../includes/helpers.php";

$loggedIn = SessionManager::isActive();

// ── Collect filters from query string ────────────────────────────────────────
// trip_type submitted as array of checkboxes (trip_type[])
$rawTripTypes = $_GET['trip_type'] ?? [];
if (!is_array($rawTripTypes)) {
    $rawTripTypes = $rawTripTypes !== '' ? [$rawTripTypes] : [];
}

$filters = [
    'keyword'             => trim($_GET['keyword']             ?? ''),
    'start_date'          => trim($_GET['start_date']          ?? ''),
    'end_date'            => trim($_GET['end_date']            ?? ''),
    'min_budget'          => trim($_GET['min_budget']          ?? ''),
    'max_budget'          => trim($_GET['max_budget']          ?? ''),
    'trip_type'           => $rawTripTypes,
    'min_seats'           => trim($_GET['min_seats']           ?? ''),
    'duration'            => trim($_GET['duration']            ?? ''),
    'max_travelers_range' => trim($_GET['max_travelers_range'] ?? ''),
    'sort'                => trim($_GET['sort']                ?? 'date_asc'),
];

// ── Pagination ────────────────────────────────────────────────────────────────
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 20;
$offset     = ($page - 1) * $limit;

// ── Query ─────────────────────────────────────────────────────────────────────
$trips      = Trip::search($filters, $limit, $offset);
$totalTrips = Trip::countSearch($filters);
$totalPages = (int) ceil($totalTrips / $limit);

// Helper: rebuild query string preserving filters except 'page'
function paginationUrl(array $filters, int $page): string {
    $params         = $filters;
    $params['page'] = $page;
    // Filter: remove empty strings and empty arrays
    $params = array_filter($params, fn($v) => is_array($v) ? !empty($v) : $v !== '');
    return '?' . http_build_query($params);
}

// Helper: emit hidden inputs for all filters except $skip key
// Handles array filters (e.g. trip_type[]) correctly
function filterHiddenInputs(array $filters, string $skip): string {
    $html = '';
    foreach ($filters as $k => $v) {
        if ($k === $skip) continue;
        if (is_array($v)) {
            foreach ($v as $item) {
                $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '[]" value="' . htmlspecialchars($item) . '">' . "\n";
            }
        } elseif ($v !== '') {
            $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">' . "\n";
        }
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Trips — Fellow Traveler</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* ── Navigation ── */
        nav {
            background: #1d4ed8; color: white; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .brand { font-size: 20px; font-weight: bold; text-decoration: none; color: white; }
        nav a { color: white; text-decoration: none; margin-left: 14px; font-size: 14px; }
        nav a:hover { text-decoration: underline; }

        /* ── Hero / Search bar ── */
        .hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white; padding: 50px 20px 80px; text-align: center;
        }
        .hero h1 { font-size: 36px; margin-bottom: 10px; }
        .hero p  { font-size: 17px; opacity: 0.85; margin-bottom: 30px; }

        .search-bar-wrap {
            max-width: 720px; margin: -40px auto 20px;
            background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.14);
        }
        .search-row { display: flex; gap: 10px; }
        .search-row input {
            flex: 1; padding: 12px 16px; border: 1px solid #d1d5db;
            border-radius: 6px; font-size: 16px;
        }
        .search-row input:focus { outline: none; border-color: #1d4ed8; }
        .search-row button {
            padding: 12px 28px; background: #1d4ed8; color: white;
            border: none; border-radius: 6px; font-size: 15px;
            cursor: pointer; font-weight: 600; white-space: nowrap;
        }
        .search-row button:hover { background: #1e40af; }

        /* ── Main layout ── */
        .container {
            max-width: 1280px; margin: 0 auto; padding: 20px;
            display: flex; gap: 28px; align-items: flex-start;
        }

        /* ── Sidebar filters ── */
        .sidebar {
            width: 270px; flex-shrink: 0;
            background: white; padding: 22px; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09);
        }
        .sidebar h2 { font-size: 16px; color: #1d4ed8; margin-bottom: 18px; }
        .filter-section { margin-bottom: 20px; }
        .filter-section h3 { font-size: 14px; color: #374151; margin-bottom: 8px; font-weight: 700; }
        .filter-section input,
        .filter-section select {
            width: 100%; padding: 9px 12px; border: 1px solid #d1d5db;
            border-radius: 5px; font-size: 14px; margin-bottom: 8px;
        }
        .filter-section input:focus,
        .filter-section select:focus { outline: none; border-color: #1d4ed8; }
        .filter-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .apply-btn {
            width: 100%; padding: 11px; background: #1d4ed8; color: white;
            border: none; border-radius: 5px; font-size: 15px;
            cursor: pointer; font-weight: 600;
        }
        .apply-btn:hover { background: #1e40af; }
        .clear-link {
            display: block; text-align: center; margin-top: 10px;
            color: #64748b; font-size: 13px; text-decoration: none;
        }
        .clear-link:hover { color: #1d4ed8; }

        /* ── Results area ── */
        .main { flex: 1; min-width: 0; }
        .results-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .results-count { color: #374151; font-size: 15px; }
        .sort-select {
            padding: 8px 12px; border: 1px solid #d1d5db;
            border-radius: 5px; font-size: 14px; cursor: pointer;
        }

        /* ── Trips grid ── */
        .trips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }

        /* ── Empty state ── */
        .no-results {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.09);
        }
        .no-results h3 { font-size: 22px; color: #374151; margin-bottom: 10px; }
        .no-results p  { color: #64748b; }

        /* ── Pagination ── */
        .pagination {
            display: flex; justify-content: center; flex-wrap: wrap;
            gap: 8px; margin-top: 10px;
        }
        .pagination a, .pagination span {
            padding: 8px 14px; background: white; border-radius: 6px;
            text-decoration: none; color: #1d4ed8; font-size: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .pagination a:hover   { background: #eff6ff; }
        .pagination .active   { background: #1d4ed8; color: white; }
        .pagination .disabled { color: #9ca3af; cursor: default; }

        /* ── Responsive ── */
        @media (max-width: 860px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
        }
        @media (max-width: 540px) {
            .hero h1 { font-size: 26px; }
            .trips-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav>
    <a href="../dashboard.php" class="brand">🌍 Fellow Traveler</a>
    <div>
        <?php if ($loggedIn): ?>
            <a href="../profile/my_profile.php">My Profile</a>
            <a href="create.php">+ New Trip</a>
            <a href="../auth/logout.php">Logout</a>
        <?php else: ?>
            <a href="../auth/login.php">Login</a>
            <a href="../auth/register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ── Hero ── -->
<div class="hero">
    <h1>🌏 Discover Your Next Adventure</h1>
    <p>Find fellow travelers going your way</p>
</div>

<!-- ── Search bar (hero overlap) ── -->
<div class="search-bar-wrap">
    <form method="GET" class="search-row">
        <?php echo filterHiddenInputs($filters, 'keyword'); ?>
        <input
            type="text" name="keyword"
            placeholder="Search destination or trip name…"
            value="<?php echo htmlspecialchars($filters['keyword']); ?>"
        >
        <button type="submit">🔍 Search</button>
    </form>
</div>

<!-- ── Main Content ── -->
<div class="container">

    <!-- Sidebar filters -->
    <aside class="sidebar">
        <h2>🎚️ Filters</h2>
        <form method="GET">
            <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($filters['keyword']); ?>">

            <div class="filter-section">
                <h3>📅 Travel Dates</h3>
                <input type="date" name="start_date" placeholder="From"
                       value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                <input type="date" name="end_date" placeholder="To"
                       value="<?php echo htmlspecialchars($filters['end_date']); ?>">
            </div>

            <div class="filter-section">
                <h3>💰 Budget (₹ per person)</h3>
                <div class="filter-row">
                    <input type="number" name="min_budget" placeholder="Min" min="0"
                           value="<?php echo htmlspecialchars($filters['min_budget']); ?>">
                    <input type="number" name="max_budget" placeholder="Max" min="0"
                           value="<?php echo htmlspecialchars($filters['max_budget']); ?>">
                </div>
            </div>

            <div class="filter-section">
                <h3>🧳 Trip Type</h3>
                <?php foreach (['solo' => 'Solo', 'group' => 'Group', 'family' => 'Family', 'couple' => 'Couple', 'friends' => 'Friends'] as $val => $label): ?>
                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:14px;cursor:pointer;">
                        <input type="checkbox" name="trip_type[]" value="<?php echo $val; ?>"
                               <?php echo in_array($val, $filters['trip_type']) ? 'checked' : ''; ?>>
                        <?php echo $label; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="filter-section">
                <h3>📆 Trip Duration</h3>
                <select name="duration">
                    <option value="">Any Duration</option>
                    <option value="short"  <?php echo $filters['duration'] === 'short'  ? 'selected' : ''; ?>>Short (1–3 days)</option>
                    <option value="medium" <?php echo $filters['duration'] === 'medium' ? 'selected' : ''; ?>>Medium (4–7 days)</option>
                    <option value="long"   <?php echo $filters['duration'] === 'long'   ? 'selected' : ''; ?>>Long (8+ days)</option>
                </select>
            </div>

            <div class="filter-section">
                <h3>👥 Group Size</h3>
                <select name="max_travelers_range">
                    <option value="">Any Size</option>
                    <option value="small"  <?php echo $filters['max_travelers_range'] === 'small'  ? 'selected' : ''; ?>>Small (2–5)</option>
                    <option value="medium" <?php echo $filters['max_travelers_range'] === 'medium' ? 'selected' : ''; ?>>Medium (6–10)</option>
                    <option value="large"  <?php echo $filters['max_travelers_range'] === 'large'  ? 'selected' : ''; ?>>Large (11–20)</option>
                    <option value="xlarge" <?php echo $filters['max_travelers_range'] === 'xlarge' ? 'selected' : ''; ?>>XL (21+)</option>
                </select>
            </div>

            <div class="filter-section">
                <h3>👤 Min Seats Available</h3>
                <input type="number" name="min_seats" placeholder="e.g. 2" min="1" max="50"
                       value="<?php echo htmlspecialchars($filters['min_seats']); ?>">
            </div>

            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($filters['sort']); ?>">

            <button type="submit" class="apply-btn">Apply Filters</button>
            <a href="search.php" class="clear-link">✕ Clear all filters</a>
        </form>
    </aside>

    <!-- Results -->
    <main class="main">
        <div class="results-header">
            <div class="results-count">
                <strong><?php echo number_format($totalTrips); ?></strong>
                trip<?php echo $totalTrips !== 1 ? 's' : ''; ?> found
                <?php if ($filters['keyword']): ?>
                    for "<em><?php echo htmlspecialchars($filters['keyword']); ?></em>"
                <?php endif; ?>
            </div>
            <div>
                <form method="GET" style="display:inline;">
                    <?php echo filterHiddenInputs($filters, 'sort'); ?>
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="date_asc"    <?php echo $filters['sort'] === 'date_asc'    ? 'selected' : ''; ?>>📅 Earliest First</option>
                        <option value="date_desc"   <?php echo $filters['sort'] === 'date_desc'   ? 'selected' : ''; ?>>📅 Latest First</option>
                        <option value="budget_asc"  <?php echo $filters['sort'] === 'budget_asc'  ? 'selected' : ''; ?>>💰 Budget: Low → High</option>
                        <option value="budget_desc" <?php echo $filters['sort'] === 'budget_desc' ? 'selected' : ''; ?>>💰 Budget: High → Low</option>
                        <option value="newest"      <?php echo $filters['sort'] === 'newest'      ? 'selected' : ''; ?>>🆕 Newest Listed</option>
                        <option value="most_joined" <?php echo $filters['sort'] === 'most_joined' ? 'selected' : ''; ?>>👥 Most Popular</option>
                    </select>
                </form>
            </div>
        </div>

        <?php if (empty($trips)): ?>
            <div class="no-results">
                <h3>🔍 No trips found</h3>
                <p>Try adjusting your filters or searching a different destination.</p>
                <?php if ($loggedIn): ?>
                    <p style="margin-top:16px;">
                        <a href="create.php" style="color:#1d4ed8; font-weight:600;">
                            + Be the first to create a trip!
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="trips-grid">
                <?php foreach ($trips as $trip): ?>
                    <?php include 'trip_card.php'; ?>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo paginationUrl($filters, $page - 1); ?>">← Prev</a>
                <?php else: ?>
                    <span class="disabled">← Prev</span>
                <?php endif; ?>

                <?php
                $window = 2;
                for ($i = 1; $i <= $totalPages; $i++):
                    if ($i === 1 || $i === $totalPages || abs($i - $page) <= $window):
                ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo paginationUrl($filters, $i); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php
                    elseif (abs($i - $page) === $window + 1):
                        echo '<span class="disabled">…</span>';
                    endif;
                endfor;
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo paginationUrl($filters, $page + 1); ?>">Next →</a>
                <?php else: ?>
                    <span class="disabled">Next →</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

</div><!-- /.container -->
</body>
</html>
