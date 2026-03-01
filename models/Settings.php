<?php
// models/Settings.php
// Task T36 — Platform settings model with in-memory cache

require_once __DIR__ . '/../config/database.php';

class Settings
{
    private static array $cache = [];

    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // ── Get single setting ─────────────────────────────────────────────────────
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $db   = self::db();
        $stmt = $db->prepare("SELECT setting_value, setting_type FROM ft_settings WHERE setting_key=?");
        $stmt->execute([$key]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return $default;

        $val = self::cast($row['setting_value'], $row['setting_type']);
        self::$cache[$key] = $val;
        return $val;
    }

    // ── Set single setting ─────────────────────────────────────────────────────
    public static function set(string $key, string $value, ?int $updatedBy = null): bool
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "UPDATE ft_settings SET setting_value=?, updated_by=?, updated_at=NOW() WHERE setting_key=?"
        );
        $ok = $stmt->execute([$value, $updatedBy, $key]);
        if ($ok) unset(self::$cache[$key]);
        return $ok;
    }

    // ── Get all settings by category ───────────────────────────────────────────
    public static function getByCategory(string $category): array
    {
        $db   = self::db();
        $stmt = $db->prepare("SELECT * FROM ft_settings WHERE category=? ORDER BY setting_key");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── List distinct categories ───────────────────────────────────────────────
    public static function getCategories(): array
    {
        $db   = self::db();
        $stmt = $db->query("SELECT DISTINCT category FROM ft_settings ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['general'];
    }

    // ── Bulk update within a transaction ──────────────────────────────────────
    public static function bulkUpdate(array $updates, ?int $updatedBy = null): bool
    {
        $db = self::db();
        try {
            $db->beginTransaction();
            foreach ($updates as $key => $value) {
                self::set((string)$key, (string)$value, $updatedBy);
            }
            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    public static function clearCache(): void { self::$cache = []; }

    // ── Type casting ───────────────────────────────────────────────────────────
    private static function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool)(int)$value,
            'number'  => is_numeric($value) ? (float)$value : 0,
            default   => $value,
        };
    }
}
