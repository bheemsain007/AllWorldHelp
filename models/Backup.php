<?php
// models/Backup.php
// Task T37 — Database backup & restore operations

require_once __DIR__ . '/../config/database.php';

// Backup directory: one level above project root (outside web root is ideal;
// adjust to /var/www/html/backups in production)
if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', __DIR__ . '/../backups/');
}

class Backup
{
    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // ── Create backup ──────────────────────────────────────────────────────────
    /**
     * Creates a gzipped mysqldump of the entire database.
     *
     * @param string   $type      'manual'|'auto'|'weekly'|'pre_restore'|'pre_update'
     * @param int|null $createdBy Admin user_id (null for cron/system)
     * @return int|false  New backup_id on success, false on failure
     */
    public static function create(string $type = 'manual', ?int $createdBy = null)
    {
        // Ensure backup directory exists
        if (!is_dir(BACKUP_DIR)) {
            if (!mkdir(BACKUP_DIR, 0755, true)) {
                error_log('Backup: cannot create directory ' . BACKUP_DIR);
                return false;
            }
        }

        $timestamp = date('Ymd_His');
        $filename  = "{$type}_backup_{$timestamp}.sql.gz";
        $filepath  = BACKUP_DIR . $filename;

        // Build mysqldump command (credentials escaped via escapeshellarg)
        $cmd = sprintf(
            'mysqldump -h%s -u%s -p%s %s 2>/dev/null | gzip > %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
            error_log("Backup: mysqldump failed (exit {$exitCode}) for file {$filename}");
            if (file_exists($filepath)) unlink($filepath);   // remove empty file
            return false;
        }

        $size = filesize($filepath);

        // Record in database
        $db   = self::db();
        $stmt = $db->prepare(
            "INSERT INTO ft_backups (filename, type, size, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        if (!$stmt->execute([$filename, $type, $size, $createdBy])) {
            return false;
        }

        return (int) $db->lastInsertId();
    }

    // ── Restore backup ─────────────────────────────────────────────────────────
    /**
     * Restores the database from an existing backup file.
     * NOTE: overwrites ALL current data — caller must create a pre-restore backup first.
     *
     * @param int $backupId
     * @return bool
     */
    public static function restore(int $backupId): bool
    {
        $backup = self::findById($backupId);
        if (!$backup) return false;

        $filepath = BACKUP_DIR . basename($backup['filename']);   // prevent path traversal
        if (!file_exists($filepath)) {
            error_log("Backup: file not found for restore: {$filepath}");
            return false;
        }

        $cmd = sprintf(
            'gunzip < %s | mysql -h%s -u%s -p%s %s 2>/dev/null',
            escapeshellarg($filepath),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            error_log("Backup: restore failed (exit {$exitCode}) from {$filepath}");
            return false;
        }

        return true;
    }

    // ── Download helper ────────────────────────────────────────────────────────
    /**
     * Streams a backup file to the browser for download.
     * Call before any output is sent.
     *
     * @param int $backupId
     * @return bool false if file not found
     */
    public static function download(int $backupId): bool
    {
        $backup = self::findById($backupId);
        if (!$backup) return false;

        $filepath = BACKUP_DIR . basename($backup['filename']);
        if (!file_exists($filepath)) return false;

        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache');
        readfile($filepath);
        return true;
    }

    // ── Delete ─────────────────────────────────────────────────────────────────
    public static function delete(int $backupId): bool
    {
        $backup = self::findById($backupId);
        if (!$backup) return false;

        $filepath = BACKUP_DIR . basename($backup['filename']);
        if (file_exists($filepath)) unlink($filepath);

        $db   = self::db();
        $stmt = $db->prepare("DELETE FROM ft_backups WHERE backup_id = ?");
        return $stmt->execute([$backupId]);
    }

    // ── Delete old backups ─────────────────────────────────────────────────────
    /**
     * Deletes backups (file + DB record) older than $days days.
     * Skips 'pre_restore' and 'pre_update' types to preserve safety snapshots.
     *
     * @return int Number of backups deleted
     */
    public static function deleteOldBackups(int $days = 30): int
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT * FROM ft_backups
              WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND type NOT IN ('pre_restore', 'pre_update')"
        );
        $stmt->execute([$days]);
        $old = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deleted = 0;
        foreach ($old as $b) {
            $fp = BACKUP_DIR . basename($b['filename']);
            if (file_exists($fp)) unlink($fp);

            $db->prepare("DELETE FROM ft_backups WHERE backup_id = ?")
               ->execute([$b['backup_id']]);
            $deleted++;
        }

        return $deleted;
    }

    // ── Find by ID ─────────────────────────────────────────────────────────────
    public static function findById(int $backupId): ?array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT b.*, u.name AS created_by_name
               FROM ft_backups b
               LEFT JOIN ft_users u ON u.user_id = b.created_by
              WHERE b.backup_id = ?"
        );
        $stmt->execute([$backupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Get all ────────────────────────────────────────────────────────────────
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        $db   = self::db();
        $stmt = $db->prepare(
            "SELECT b.*, u.name AS created_by_name
               FROM ft_backups b
               LEFT JOIN ft_users u ON u.user_id = b.created_by
              ORDER BY b.created_at DESC
              LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Count ─────────────────────────────────────────────────────────────────
    public static function countAll(): int
    {
        return (int) self::db()->query("SELECT COUNT(*) FROM ft_backups")->fetchColumn();
    }

    // ── Statistics ─────────────────────────────────────────────────────────────
    public static function getStatistics(): array
    {
        $db  = self::db();
        $row = $db->query(
            "SELECT COUNT(*) AS total_backups,
                    COALESCE(SUM(size), 0) AS total_size,
                    MAX(created_at) AS last_backup_date
               FROM ft_backups"
        )->fetch(PDO::FETCH_ASSOC);

        // Count by type
        $types = [];
        $typeRows = $db->query(
            "SELECT type, COUNT(*) AS cnt FROM ft_backups GROUP BY type"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($typeRows as $t) {
            $types[$t['type']] = (int) $t['cnt'];
        }

        $row['by_type']      = $types;
        $row['total_backups'] = (int) $row['total_backups'];
        $row['total_size']    = (int) $row['total_size'];

        // Oldest backup
        $oldest = $db->query("SELECT MIN(created_at) FROM ft_backups")->fetchColumn();
        $row['oldest_backup_date'] = $oldest;

        return $row;
    }

    // ── Format file size ───────────────────────────────────────────────────────
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // ── Type label & badge class ───────────────────────────────────────────────
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'manual'      => 'Manual',
            'auto'        => 'Auto Daily',
            'weekly'      => 'Weekly',
            'pre_restore' => 'Pre-Restore',
            'pre_update'  => 'Pre-Update',
            default       => ucfirst($type),
        };
    }

    public static function typeBadgeClass(string $type): string
    {
        return match ($type) {
            'manual'      => 'badge-manual',
            'auto'        => 'badge-auto',
            'weekly'      => 'badge-weekly',
            'pre_restore' => 'badge-prerestore',
            'pre_update'  => 'badge-preupdate',
            default       => 'badge-manual',
        };
    }
}
