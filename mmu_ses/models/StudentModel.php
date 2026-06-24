<?php
/**
 * StudentModel
 * --------------------------------------------------------------------
 * All SQL touching the `students` table lives here. Pages and
 * controllers ask this class for data; they never write SQL directly.
 */

class StudentModel {

    /** Find one student by primary key (the auto-increment id). */
    public static function findById(int $id): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM students WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Find by the public-facing student_id, e.g. "253UT256KY". */
    public static function findByStudentId(string $sid): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM students WHERE student_id = :sid LIMIT 1');
        $stmt->execute([':sid' => $sid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Find by email (used during registration uniqueness check). */
    public static function findByEmail(string $email): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM students WHERE email = :em LIMIT 1');
        $stmt->execute([':em' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** List all students (admin view). Newest first. */
    public static function findAll(): array {
        $stmt = Database::pdo()->query(
            'SELECT * FROM students ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    /**
     * Create a new student. Returns the new PK on success.
     * Throws PDOException on duplicate student_id / email.
     */
    public static function create(array $data): int {
        $sql = 'INSERT INTO students
                (student_id, full_name, email, password, programme, trimester)
                VALUES (:sid, :name, :em, :pw, :pg, :tr)';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            ':sid'  => $data['student_id'],
            ':name' => $data['full_name'],
            ':em'   => $data['email'],
            ':pw'   => password_hash($data['password'], PASSWORD_BCRYPT),
            ':pg'   => $data['programme'],
            ':tr'   => (int)$data['trimester'],
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    /** Update an existing student. */
    public static function update(int $id, array $data): void {
        $sql = 'UPDATE students SET
                  student_id = :sid,
                  full_name  = :name,
                  email      = :em,
                  programme  = :pg,
                  trimester  = :tr
                WHERE id = :id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            ':id'   => $id,
            ':sid'  => $data['student_id'],
            ':name' => $data['full_name'],
            ':em'   => $data['email'],
            ':pg'   => $data['programme'],
            ':tr'   => (int)$data['trimester'],
        ]);
    }

    /** Reset / change a student's password (caller already validated). */
    public static function setPassword(int $id, string $newPlain): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE students SET password = :pw WHERE id = :id');
        $stmt->execute([
            ':pw' => password_hash($newPlain, PASSWORD_BCRYPT),
            ':id' => $id,
        ]);
    }

    /**
     * Authenticate by student_id + plain password.
     * Returns the student row on success, null on failure.
     */
    public static function authenticate(string $sid, string $plain): ?array {
        $row = self::findByStudentId($sid);
        if ($row && password_verify($plain, $row['password'])) {
            return $row;
        }
        return null;
    }

    /** Delete a student (cascades into enrollments via FK). */
    public static function delete(int $id): void {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM students WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** Count of students (for dashboard cards). */
    public static function count(): int {
        return (int)Database::pdo()
            ->query('SELECT COUNT(*) FROM students')->fetchColumn();
    }
}
