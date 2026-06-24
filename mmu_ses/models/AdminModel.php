<?php
/**
 * AdminModel
 * --------------------------------------------------------------------
 * Data access for the administrators table.
 */

class AdminModel {

    public static function findById(int $id): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM administrators WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByAdminId(string $aid): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM administrators WHERE admin_id = :a LIMIT 1');
        $stmt->execute([':a' => $aid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Authenticate by admin_id + plain password. */
    public static function authenticate(string $aid, string $plain): ?array {
        $row = self::findByAdminId($aid);
        if ($row && password_verify($plain, $row['password'])) {
            return $row;
        }
        return null;
    }

    public static function count(): int {
        return (int)Database::pdo()
            ->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
    }
}
