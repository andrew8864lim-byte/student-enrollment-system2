<?php
/**
 * EnrollmentModel
 * --------------------------------------------------------------------
 * All data access for the enrollments table. Heavy lifters:
 *   * enroll()              — atomic enrol-or-waitlist using a row lock
 *   * drop()                — drop + auto-promote head of waitlist
 *   * currentCreditHours()  — sum of credit_hours of student's active
 *                             enrolments, for the credit-cap check
 *   * completedCourseIds()  — used by the prerequisite check
 */

class EnrollmentModel {

    /* --------------------------------------------------------------- */
    /*  Lookups                                                         */
    /* --------------------------------------------------------------- */

    /** Active (enrolled or waitlisted) and dropped/completed rows for a student. */
    public static function forStudent(int $studentId, ?string $status = null): array {
        $sql = 'SELECT e.id, e.status, e.enrollment_date, e.waitlist_position,
                       c.id   AS course_id,
                       c.course_code, c.course_name, c.lecturer_name,
                       c.credit_hours, c.schedule_info, c.quota, c.enrolled_count
                  FROM enrollments e
                  JOIN courses c ON c.id = e.course_id
                 WHERE e.student_id = :sid';
        $params = [':sid' => $studentId];
        if ($status !== null) {
            $sql .= ' AND e.status = :st';
            $params[':st'] = $status;
        }
        $sql .= ' ORDER BY e.status, c.course_code';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Just the currently active (enrolled) rows for a student. */
    public static function activeForStudent(int $studentId): array {
        return self::forStudent($studentId, 'enrolled');
    }

    /** Waitlisted rows for a student. */
    public static function waitlistedForStudent(int $studentId): array {
        return self::forStudent($studentId, 'waitlisted');
    }

    /** Sum of credit hours the student currently carries (enrolled only). */
    public static function currentCreditHours(int $studentId): int {
        $sql = 'SELECT COALESCE(SUM(c.credit_hours), 0)
                  FROM enrollments e
                  JOIN courses c ON c.id = e.course_id
                 WHERE e.student_id = :sid AND e.status = "enrolled"';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':sid' => $studentId]);
        return (int)$stmt->fetchColumn();
    }

    /** IDs of courses the student has completed (passed). */
    public static function completedCourseIds(int $studentId): array {
        $stmt = Database::pdo()->prepare(
            'SELECT course_id FROM enrollments
              WHERE student_id = :sid AND status = "completed"');
        $stmt->execute([':sid' => $studentId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Existing enrolment row for (student, course) regardless of status. */
    public static function existing(int $studentId, int $courseId): ?array {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM enrollments
              WHERE student_id = :s AND course_id = :c LIMIT 1');
        $stmt->execute([':s' => $studentId, ':c' => $courseId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    /** All enrolments (admin filter view). */
    public static function findAll(?string $status = null): array {
        $sql = 'SELECT e.id, e.status, e.enrollment_date, e.waitlist_position,
                       s.student_id, s.full_name AS student_name,
                       c.course_code, c.course_name, c.credit_hours
                  FROM enrollments e
                  JOIN students s ON s.id = e.student_id
                  JOIN courses  c ON c.id = e.course_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE e.status = :st';
            $params[':st'] = $status;
        }
        $sql .= ' ORDER BY e.enrollment_date DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** System-wide count by status (admin dashboard). */
    public static function countByStatus(string $status): int {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM enrollments WHERE status = :s');
        $stmt->execute([':s' => $status]);
        return (int)$stmt->fetchColumn();
    }

    /* --------------------------------------------------------------- */
    /*  Mutations                                                       */
    /* --------------------------------------------------------------- */

    /**
     * Enrol a student in a course. Returns one of:
     *   ['status' => 'enrolled',   'message' => ..., 'course' => row]
     *   ['status' => 'waitlisted', 'position' => N, 'message' => ..., 'course' => row]
     *
     * This is a transaction that locks the courses row to prevent
     * two students grabbing the last seat at once.
     *
     * Caller (controller) is responsible for prerequisite + credit cap
     * checks BEFORE invoking this method.
     */
    public static function enroll(int $studentId, int $courseId): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Lock the course row to prevent races on the last seat
            $stmt = $pdo->prepare(
                'SELECT * FROM courses WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $courseId]);
            $course = $stmt->fetch();
            if (!$course) {
                throw new RuntimeException('Course does not exist.');
            }

            // Fetch the FULL existing row (need id, not just status) so a
            // previously-dropped row can be reused instead of silently
            // failing to update.
            $stmt = $pdo->prepare(
                'SELECT * FROM enrollments
                  WHERE student_id = :s AND course_id = :c LIMIT 1
                  FOR UPDATE');
            $stmt->execute([':s' => $studentId, ':c' => $courseId]);
            $existing = $stmt->fetch();

            // Disallow re-enrolling if there's an active or completed row
            if ($existing &&
                in_array($existing['status'],
                         ['enrolled','waitlisted','completed'], true)) {
                throw new RuntimeException(
                    'You already have an active record for this course.');
            }

            if ($course['enrolled_count'] < $course['quota']) {
                // Seat available — enrol directly
                if ($existing) {
                    // Re-use the previously dropped row
                    $stmt = $pdo->prepare(
                        'UPDATE enrollments
                            SET status = "enrolled",
                                waitlist_position = NULL,
                                enrollment_date = CURRENT_TIMESTAMP
                          WHERE id = :id');
                    $stmt->execute([':id' => $existing['id']]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO enrollments
                          (student_id, course_id, status)
                          VALUES (:s, :c, "enrolled")');
                    $stmt->execute([':s' => $studentId, ':c' => $courseId]);
                }
                $pdo->prepare(
                    'UPDATE courses SET enrolled_count = enrolled_count + 1
                      WHERE id = :id')
                    ->execute([':id' => $courseId]);
                $pdo->commit();
                return [
                    'status'  => 'enrolled',
                    'message' => "Enrolled in {$course['course_code']} successfully.",
                    'course'  => $course,
                ];
            }

            // Course is full — put on waitlist
            $stmt = $pdo->prepare(
                'SELECT COALESCE(MAX(waitlist_position),0) + 1
                   FROM enrollments
                  WHERE course_id = :c AND status = "waitlisted"');
            $stmt->execute([':c' => $courseId]);
            $pos = (int)$stmt->fetchColumn();

            if ($existing) {
                $stmt = $pdo->prepare(
                    'UPDATE enrollments
                        SET status = "waitlisted",
                            waitlist_position = :pos,
                            enrollment_date = CURRENT_TIMESTAMP
                      WHERE id = :id');
                $stmt->execute([':pos' => $pos, ':id' => $existing['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO enrollments
                        (student_id, course_id, status, waitlist_position)
                        VALUES (:s, :c, "waitlisted", :pos)');
                $stmt->execute([
                    ':s' => $studentId,
                    ':c' => $courseId,
                    ':pos' => $pos,
                ]);
            }
            $pdo->commit();
            return [
                'status'   => 'waitlisted',
                'position' => $pos,
                'message'  => "Course {$course['course_code']} is full. " .
                              "You have been added to the waitlist at position {$pos}.",
                'course'   => $course,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Drop an enrolment. If the dropped record was 'enrolled', the
     * head of the waitlist (if any) is automatically promoted, which
     * keeps `enrolled_count` accurate without changing the seat count.
     */
    public static function drop(int $studentId, int $courseId): array {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Lock the course row
            $stmt = $pdo->prepare(
                'SELECT * FROM courses WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $courseId]);
            $course = $stmt->fetch();
            if (!$course) {
                throw new RuntimeException('Course does not exist.');
            }

            $stmt = $pdo->prepare(
                'SELECT * FROM enrollments
                  WHERE student_id = :s AND course_id = :c LIMIT 1');
            $stmt->execute([':s' => $studentId, ':c' => $courseId]);
            $row = $stmt->fetch();
            if (!$row || !in_array($row['status'],
                                   ['enrolled','waitlisted'], true)) {
                throw new RuntimeException(
                    'You do not have an active record for this course.');
            }

            $wasEnrolled = ($row['status'] === 'enrolled');

            // Mark dropped
            $pdo->prepare(
                'UPDATE enrollments
                    SET status = "dropped", waitlist_position = NULL
                  WHERE id = :id')
                ->execute([':id' => $row['id']]);

            $promoted = null;

            if ($wasEnrolled) {
                // Try to promote the head of the waitlist into the seat
                $stmt = $pdo->prepare(
                    'SELECT e.id, e.student_id, s.full_name, s.student_id AS sid
                       FROM enrollments e
                       JOIN students s ON s.id = e.student_id
                      WHERE e.course_id = :c AND e.status = "waitlisted"
                      ORDER BY e.waitlist_position ASC
                      LIMIT 1');
                $stmt->execute([':c' => $courseId]);
                $head = $stmt->fetch();

                if ($head) {
                    // Promote them — seat stays counted, no double-increment
                    $pdo->prepare(
                        'UPDATE enrollments
                            SET status = "enrolled",
                                waitlist_position = NULL
                          WHERE id = :id')
                        ->execute([':id' => $head['id']]);

                    // Shift every other waitlister up by one position
                    $pdo->prepare(
                        'UPDATE enrollments
                            SET waitlist_position = waitlist_position - 1
                          WHERE course_id = :c AND status = "waitlisted"')
                        ->execute([':c' => $courseId]);

                    $promoted = $head;
                    // enrolled_count stays the same; one out, one in
                } else {
                    // No waitlist — free the seat
                    $pdo->prepare(
                        'UPDATE courses
                            SET enrolled_count = GREATEST(enrolled_count - 1, 0)
                          WHERE id = :id')
                        ->execute([':id' => $courseId]);
                }
            } else {
                // Was waitlisted — close the gap they left in the queue
                $pdo->prepare(
                    'UPDATE enrollments
                        SET waitlist_position = waitlist_position - 1
                      WHERE course_id = :c
                        AND status = "waitlisted"
                        AND waitlist_position > :pos')
                    ->execute([
                        ':c'   => $courseId,
                        ':pos' => (int)$row['waitlist_position'],
                    ]);
            }

            $pdo->commit();
            return [
                'status'   => 'dropped',
                'message'  => "Dropped {$course['course_code']} successfully.",
                'course'   => $course,
                'promoted' => $promoted,  // null or row with sid/full_name
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}