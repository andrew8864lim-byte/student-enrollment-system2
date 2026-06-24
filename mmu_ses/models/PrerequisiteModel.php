<?php
/**
 * PrerequisiteModel
 * --------------------------------------------------------------------
 * Read and write the course_prerequisites table.
 *
 * A "prereq met" check, however, also depends on the student's
 * completed-enrolments history. That logic lives in
 * EnrollmentController::checkPrerequisitesMet() because it spans
 * two tables, but the raw lookups stay here.
 */

class PrerequisiteModel {

    /** Return all prereq rows expanded with the prereq course's details. */
    public static function forCourse(int $courseId): array {
        $sql = 'SELECT p.id, p.prereq_id,
                       c.course_code, c.course_name
                  FROM course_prerequisites p
                  JOIN courses c ON c.id = p.prereq_id
                 WHERE p.course_id = :cid
                 ORDER BY c.course_code';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    /** All prereqs across the system, expanded for an admin table. */
    public static function findAll(): array {
        $sql = 'SELECT p.id,
                       c.course_code  AS course_code,
                       c.course_name  AS course_name,
                       pc.course_code AS prereq_code,
                       pc.course_name AS prereq_name,
                       p.course_id,
                       p.prereq_id
                  FROM course_prerequisites p
                  JOIN courses c  ON c.id  = p.course_id
                  JOIN courses pc ON pc.id = p.prereq_id
                 ORDER BY c.course_code, pc.course_code';
        return Database::pdo()->query($sql)->fetchAll();
    }

    /** Returns just the prereq course IDs for a given course. */
    public static function idsForCourse(int $courseId): array {
        $stmt = Database::pdo()->prepare(
            'SELECT prereq_id FROM course_prerequisites WHERE course_id = :c');
        $stmt->execute([':c' => $courseId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Add a prereq link. Caller has already validated both IDs exist. */
    public static function add(int $courseId, int $prereqId): void {
        if ($courseId === $prereqId) {
            throw new InvalidArgumentException(
                'A course cannot be a prerequisite of itself.');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO course_prerequisites (course_id, prereq_id)
             VALUES (:c, :p)');
        $stmt->execute([':c' => $courseId, ':p' => $prereqId]);
    }

    public static function delete(int $id): void {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM course_prerequisites WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
