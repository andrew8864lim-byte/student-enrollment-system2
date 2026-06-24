<?php
/**
 * CourseModel
 * --------------------------------------------------------------------
 * Data access for the `courses` table and helpers for working with
 * the join into `course_prerequisites`.
 */

class CourseModel {

    public static function findById(int $id): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM courses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByCode(string $code): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM courses WHERE course_code = :c LIMIT 1');
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** All courses ordered by course code. */
    public static function findAll(): array {
        return Database::pdo()
            ->query('SELECT * FROM courses ORDER BY course_code')
            ->fetchAll();
    }

    /**
     * Available courses for a student to enrol in, i.e. those they
     * don't already have an active row in `enrollments` for.
     * Returns rows enriched with `available_seats` and `is_full`.
     */
    public static function availableForStudent(int $studentId): array {
        $sql = "SELECT c.*,
                       (c.quota - c.enrolled_count) AS available_seats,
                       CASE WHEN c.enrolled_count >= c.quota THEN 1 ELSE 0 END AS is_full
                  FROM courses c
                  WHERE c.id NOT IN (
                      SELECT course_id FROM enrollments
                       WHERE student_id = :sid
                         AND status IN ('enrolled','waitlisted','completed')
                  )
                  ORDER BY c.course_code";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll();
    }

    /** Insert a new course. Returns the new PK. */
    public static function create(array $data): int {
        $sql = 'INSERT INTO courses
                (course_code, course_name, lecturer_name, credit_hours,
                 quota, enrolled_count, programme, trimester, schedule_info)
                VALUES (:code, :name, :lec, :ch, :q, 0, :pg, :tr, :sch)';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            ':code' => $data['course_code'],
            ':name' => $data['course_name'],
            ':lec'  => $data['lecturer_name'],
            ':ch'   => (int)$data['credit_hours'],
            ':q'    => (int)$data['quota'],
            ':pg'   => $data['programme'],
            ':tr'   => (int)$data['trimester'],
            ':sch'  => $data['schedule_info'] ?? '',
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    /**
     * Update a course. Caller must guarantee that the new quota is
     * >= current enrolled_count (see admin/courses.php).
     */
    public static function update(int $id, array $data): void {
        $sql = 'UPDATE courses SET
                  course_code   = :code,
                  course_name   = :name,
                  lecturer_name = :lec,
                  credit_hours  = :ch,
                  quota         = :q,
                  programme     = :pg,
                  trimester     = :tr,
                  schedule_info = :sch
                WHERE id = :id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            ':id'   => $id,
            ':code' => $data['course_code'],
            ':name' => $data['course_name'],
            ':lec'  => $data['lecturer_name'],
            ':ch'   => (int)$data['credit_hours'],
            ':q'    => (int)$data['quota'],
            ':pg'   => $data['programme'],
            ':tr'   => (int)$data['trimester'],
            ':sch'  => $data['schedule_info'] ?? '',
        ]);
    }

    public static function delete(int $id): void {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM courses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function count(): int {
        return (int)Database::pdo()
            ->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    }

    /** Top N most-filled courses (admin dashboard). */
    public static function topFilled(int $limit = 5): array {
        $stmt = Database::pdo()->prepare(
            'SELECT course_code, course_name, enrolled_count, quota
               FROM courses
              ORDER BY enrolled_count DESC, course_code
              LIMIT ' . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
