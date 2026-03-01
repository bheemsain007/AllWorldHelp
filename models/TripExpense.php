<?php
// models/TripExpense.php
// Task T24 — TripExpense model: add expenses, fetch list, calculate balances, delete

class TripExpense {

    private const TABLE       = 'ft_trip_expenses';
    private const CATEGORIES  = ['food', 'transport', 'accommodation', 'other'];

    // ── Create Expense ────────────────────────────────────────────────────────
    /**
     * Insert a new expense row.
     * $data: trip_id, paid_by, amount, description, category, split_among (JSON string)
     * Returns true on success.
     */
    public static function create(array $data): bool {
        global $pdo;

        $sql = "INSERT INTO ft_trip_expenses
                    (trip_id, paid_by, amount, description, category, split_among, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                (int)   $data['trip_id'],
                (int)   $data['paid_by'],
                (float) $data['amount'],
                        $data['description'],
                        $data['category'],
                        $data['split_among'],   // already JSON-encoded by caller
            ]);
        } catch (PDOException $e) {
            error_log("TripExpense::create error: " . $e->getMessage());
            return false;
        }
    }

    // ── Get Expenses For Trip ─────────────────────────────────────────────────
    /**
     * Return all expenses for a trip, newest first, with payer name.
     */
    public static function getExpensesForTrip(int $tripId, int $limit = 200): array {
        global $pdo;

        $sql = "SELECT e.expense_id, e.trip_id, e.paid_by, e.amount,
                       e.description, e.category, e.split_among, e.created_at,
                       u.name AS payer_name, u.profile_photo AS payer_photo
                FROM ft_trip_expenses e
                JOIN ft_users u ON u.user_id = e.paid_by
                WHERE e.trip_id = :tid
                ORDER BY e.created_at DESC
                LIMIT :lim";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':tid', $tripId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("TripExpense::getExpensesForTrip error: " . $e->getMessage());
            return [];
        }
    }

    // ── Find By ID ────────────────────────────────────────────────────────────
    public static function findById(int $expenseId): ?array {
        global $pdo;

        $sql = "SELECT * FROM ft_trip_expenses WHERE expense_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$expenseId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────
    /**
     * Hard-delete an expense row. Caller must verify permission first.
     */
    public static function delete(int $expenseId): bool {
        global $pdo;

        $sql = "DELETE FROM ft_trip_expenses WHERE expense_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$expenseId]);
        } catch (PDOException $e) {
            error_log("TripExpense::delete error: " . $e->getMessage());
            return false;
        }
    }

    // ── Calculate Balances ────────────────────────────────────────────────────
    /**
     * Compute net balance per user across all trip expenses.
     * Positive value = this user is owed money (paid more than share).
     * Negative value = this user owes money (paid less than share).
     *
     * Returns: array of ['user_id' => int, 'name' => string, 'net' => float]
     * sorted by net descending (creditors first).
     */
    public static function calculateBalances(int $tripId): array {
        global $pdo;

        $expenses = self::getExpensesForTrip($tripId, 1000);

        // Accumulate net per user_id
        $nets = [];   // user_id => float

        foreach ($expenses as $expense) {
            $paidBy     = (int) $expense['paid_by'];
            $amount     = (float) $expense['amount'];
            $splitAmong = json_decode($expense['split_among'], true);

            if (empty($splitAmong)) continue;

            $share = $amount / count($splitAmong);

            // Payer is credited the full amount
            $nets[$paidBy] = ($nets[$paidBy] ?? 0.0) + $amount;

            // Each participant in split is debited their share
            foreach ($splitAmong as $uid) {
                $uid = (int) $uid;
                $nets[$uid] = ($nets[$uid] ?? 0.0) - $share;
            }
        }

        if (empty($nets)) return [];

        // Fetch names for all involved users
        $ids  = array_keys($nets);
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $sql  = "SELECT user_id, name FROM ft_users WHERE user_id IN ({$in})";

        $names = [];
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $names[(int) $row['user_id']] = $row['name'];
            }
        } catch (PDOException $e) {
            error_log("TripExpense::calculateBalances names error: " . $e->getMessage());
        }

        $result = [];
        foreach ($nets as $uid => $net) {
            $result[] = [
                'user_id' => $uid,
                'name'    => $names[$uid] ?? 'Unknown',
                'net'     => round($net, 2),
            ];
        }

        // Sort creditors first (highest net first)
        usort($result, fn($a, $b) => $b['net'] <=> $a['net']);

        return $result;
    }

    // ── Count ─────────────────────────────────────────────────────────────────
    public static function countForTrip(int $tripId): int {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ft_trip_expenses WHERE trip_id = ?");
            $stmt->execute([$tripId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ── Valid Category ────────────────────────────────────────────────────────
    public static function isValidCategory(string $cat): bool {
        return in_array($cat, self::CATEGORIES, true);
    }

    // ── Category Meta ─────────────────────────────────────────────────────────
    public static function getCategoryMeta(): array {
        return [
            'food'          => ['label' => '🍕 Food & Drinks',  'class' => 'cat-food'],
            'transport'     => ['label' => '🚗 Transport',      'class' => 'cat-transport'],
            'accommodation' => ['label' => '🏨 Accommodation',  'class' => 'cat-accommodation'],
            'other'         => ['label' => '📌 Other',          'class' => 'cat-other'],
        ];
    }
}
?>
