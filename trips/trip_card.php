<?php
/**
 * trips/trip_card.php
 * Task T13 — Reusable trip card component.
 * Expects: $trip (array from Trip::search / Trip::findById etc.)
 */
?>
<div class="trip-card">
    <div class="trip-image">
        <img
            src="<?php echo !empty($trip['image'])
                ? '../uploads/trips/' . htmlspecialchars($trip['image'])
                : '../assets/default-trip.jpg'; ?>"
            alt="<?php echo htmlspecialchars($trip['title']); ?>"
            loading="lazy"
        >
        <span class="trip-type-badge"><?php echo htmlspecialchars(ucfirst($trip['trip_type'])); ?></span>
    </div>

    <div class="trip-content">
        <h3 class="trip-title">
            <a href="view.php?id=<?php echo (int) $trip['trip_id']; ?>">
                <?php echo htmlspecialchars($trip['title']); ?>
            </a>
        </h3>

        <div class="trip-meta">
            <span class="meta-item">
                📍 <?php echo htmlspecialchars($trip['destination']); ?>
            </span>
            <span class="meta-item">
                📅 <?php echo date('M j', strtotime($trip['start_date'])); ?> –
                   <?php echo date('M j, Y', strtotime($trip['end_date'])); ?>
            </span>
        </div>

        <div class="trip-budget">
            <strong>₹<?php echo number_format((float) $trip['budget_per_person']); ?></strong>
            <span>per person</span>
        </div>

        <?php
        $available = (int) $trip['max_travelers'] - (int) $trip['joined_travelers'];
        $seatClass = $available <= 2 ? 'seats-low' : 'seats-ok';
        ?>
        <div class="trip-seats <?php echo $seatClass; ?>">
            👤 <?php echo $available; ?> / <?php echo (int) $trip['max_travelers']; ?> seats available
        </div>

        <div class="trip-organizer">
            <img
                src="<?php echo !empty($trip['organizer_photo'])
                    ? '../uploads/profiles/' . htmlspecialchars($trip['organizer_photo'])
                    : '../assets/default-avatar.png'; ?>"
                alt="Organizer"
                class="organizer-thumb"
            >
            <span>by <strong><?php echo htmlspecialchars($trip['organizer_name']); ?></strong></span>
        </div>

        <a href="view.php?id=<?php echo (int) $trip['trip_id']; ?>" class="view-btn">
            View Details →
        </a>
    </div>
</div>

<style>
/* Trip card styles — included once per page by the first card rendered.
   Duplicate <style> tags are harmless but can be extracted to a shared CSS file later. */
.trip-card {
    background: white; border-radius: 10px;
    overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex; flex-direction: column;
}
.trip-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.14);
}
.trip-image { position: relative; height: 190px; overflow: hidden; }
.trip-image img { width: 100%; height: 100%; object-fit: cover; }
.trip-type-badge {
    position: absolute; top: 10px; right: 10px;
    background: #1d4ed8; color: white; padding: 4px 10px;
    border-radius: 12px; font-size: 12px; font-weight: 600;
}
.trip-content { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.trip-title { font-size: 16px; font-weight: 700; }
.trip-title a { color: #1e293b; text-decoration: none; }
.trip-title a:hover { color: #1d4ed8; }
.trip-meta { display: flex; flex-direction: column; gap: 4px; }
.meta-item { color: #64748b; font-size: 13px; }
.trip-budget { font-size: 15px; color: #1d4ed8; }
.trip-budget span { color: #64748b; font-size: 13px; font-weight: normal; }
.trip-seats { font-size: 13px; }
.seats-low  { color: #dc2626; font-weight: 600; }
.seats-ok   { color: #16a34a; }
.trip-organizer {
    display: flex; align-items: center; gap: 8px;
    padding-top: 10px; border-top: 1px solid #e5e7eb;
    font-size: 13px; color: #64748b; margin-top: auto;
}
.organizer-thumb { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
.view-btn {
    display: block; text-align: center; padding: 10px;
    background: #1d4ed8; color: white; text-decoration: none;
    border-radius: 6px; font-size: 14px; font-weight: 600;
    margin-top: 10px; transition: background 0.2s;
}
.view-btn:hover { background: #1e40af; }
</style>
