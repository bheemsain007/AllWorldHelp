<?php
// models/Report.php
// Task T30 — Report/Moderation model for ft_reports table

require_once __DIR__ . '/../config/database.php';

class Report
{
    // ── Allowed enum values ────────────────────────────────────────────────────
    private static array $validTypes      = ['user', 'trip', 'message', 'photo', 'comment'];
    private static array $validCategories = ['spam', 'harassment', 'inappropriate', 'fraud', 'safety', 'other'];
    private static array $validStatuses   = ['pending', 'reviewed', 'resolved', 'dismissed'];

    // ── Validation helpers ─────────────────────────────────────────────────────
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::$validTypes, true);
    }

    public static function isValidCategory(string $category): bool
    {
        return in_array($category, self::$validCategories, true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::$validStatuses, true);
    }

    public static function getTypes(): array      { return self::$validTypes; }
    public static function getCategories(): array { return self::$validCategories; }
    public static function getStatuses(): array   { return self::$validStatuses; }

    // ── Category labels ────────────────────────────────────────────────────────
    public static function getCategoryLabel(string $cat): string
    {
        return match ($cat) {
            'spam'          => '🚫 Spam',
            'harassment'    => '😡 Harassment',
            'inappropriate' => '🔞 Inappropriate Content',
            'fraud'         => '💰 Fraud / Scam',
            'safety'        => '⚠️ Safety Concern',
            'other'         => '❓ Other',
            default         => ucfirst($cat),
        };
    }

    // ── Create ─────────────────────────────────────────────────────────────────
    public static function create(array $data): bool
    {
        $db   = Database::getInstance()->getConnection();
        $sql  = "INSERT INTO ft_reports
                    (reporter_id, reported_type, reported_id, category, description, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            (int)   $data['reporter_id'],
                    $data['reported_type'],
            (int)   $data['reported_id'],
                    $data['category'],
                    $data['description'] ?? '',
        ]);
    }

    // ── Find by ID ─────────────────────────────────────────────────────────────
    public static function findById(int $reportId): ?array
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM ft_reports WHERE report_id = ?");
        $stmt->execute([$reportId]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Check duplicate (same reporter + type + id within 24 h) ───────────────
    public static function findDuplicate(int $reporterId, string $type, int $reportedId): ?array
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM ft_reports
              WHERE reporter_id   = ?
                AND reported_type = ?
                AND reported_id   = ?
                AND created_at   >= NOW() - INTERVAL 24 HOUR
              LIMIT 1"
        );
        $stmt->execute([$reporterId, $type, $reportedId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Get all reports (with optional status filter + pagination) ─────────────
    public static function getAll(string $status = '', int $limit = 30, int $offset = 0): array
    {
        $db     = Database::getInstance()->getConnection();
        $where  = '';
        $params = [];

        if ($status !== '' && self::isValidStatus($status)) {
            $where    = 'WHERE r.status = ?';
            $params[] = $status;
        }

        $sql = "SELECT
                    r.*,
                    reporter.name  AS reporter_name,
                    reporter.email AS reporter_email
                FROM   ft_reports r
                JOIN   ft_users   reporter ON reporter.user_id = r.reporter_id
                {$where}
                ORDER BY
                    FIELD(r.status, 'pending', 'reviewed', 'resolved', 'dismissed'),
                    r.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Count (for pagination) ─────────────────────────────────────────────────
    public static function countAll(string $status = ''): int
    {
        $db     = Database::getInstance()->getConnection();
        $where  = '';
        $params = [];

        if ($status !== '' && self::isValidStatus($status)) {
            $where    = 'WHERE status = ?';
            $params[] = $status;
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM ft_reports {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ── Update status + admin notes ────────────────────────────────────────────
    public static function updateStatus(int $reportId, string $status, string $adminNotes = ''): bool
    {
        if (!self::isValidStatus($status)) {
            return false;
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "UPDATE ft_reports
                SET status      = ?,
                    admin_notes = ?
              WHERE report_id   = ?"
        );
        return $stmt->execute([$status, $adminNotes, $reportId]);
    }

    // ── Stats summary ──────────────────────────────────────────────────────────
    public static function getStats(): array
    {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->query(
            "SELECT
                COUNT(*)                                       AS total,
                SUM(status = 'pending')                        AS pending,
                SUM(status = 'reviewed')                       AS reviewed,
                SUM(status = 'resolved')                       AS resolved,
                SUM(status = 'dismissed')                      AS dismissed,
                SUM(reported_type = 'user')                    AS type_user,
                SUM(reported_type = 'trip')                    AS type_trip,
                SUM(reported_type = 'message')                 AS type_message,
                SUM(reported_type = 'photo')                   AS type_photo,
                SUM(reported_type = 'comment')                 AS type_comment
             FROM ft_reports"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
