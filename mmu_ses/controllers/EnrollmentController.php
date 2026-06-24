<?php
/**
 * EnrollmentController
 * --------------------------------------------------------------------
 * Sits between the page (view) and the model. Owns the business
 * rules that span multiple tables:
 *   * Prerequisite enforcement
 *   * Credit-hour cap (max 22 per trimester)
 *   * Friendly error messages
 *
 * Public methods return ['ok' => bool, 'message' => string, ...].
 * Pages render the result; they never compute it themselves.
 */

class EnrollmentController {

    /**
     * Check whether a student has met all prerequisites for a course.
     *
     * Returns ['ok' => true] if all prereqs are completed or there
     * are none. Otherwise ['ok' => false, 'missing' => [course rows]].
     */
    public static function checkPrerequisitesMet(int $studentId, int $courseId): array {
        $prereqs = PrerequisiteModel::forCourse($courseId);
        if (!$prereqs) {
            return ['ok' => true, 'missing' => []];
        }

        $completed = EnrollmentModel::completedCourseIds($studentId);
        $missing = [];
        foreach ($prereqs as $p) {
            if (!in_array((int)$p['prereq_id'], $completed, true)) {
                $missing[] = $p;
            }
        }
        return [
            'ok'      => $missing === [],
            'missing' => $missing,
        ];
    }

    /**
     * Check whether enrolling in this course would push the student
     * over the credit-hour cap. Counts current enrolments only (not
     * waitlisted, not dropped).
     */
    public static function checkCreditCap(int $studentId, int $courseId): array {
        $current = EnrollmentModel::currentCreditHours($studentId);
        $course  = CourseModel::findById($courseId);
        if (!$course) {
            return ['ok' => false,
                    'message' => 'Course not found.',
                    'current' => $current,
                    'after'   => $current,
                    'cap'     => MAX_CREDIT_HOURS_PER_TRIMESTER];
        }
        $after = $current + (int)$course['credit_hours'];
        return [
            'ok'      => $after <= MAX_CREDIT_HOURS_PER_TRIMESTER,
            'current' => $current,
            'after'   => $after,
            'cap'     => MAX_CREDIT_HOURS_PER_TRIMESTER,
            'course'  => $course,
        ];
    }

    /**
     * Full enrol flow:
     *   1) Verify prerequisites are met
     *   2) Verify credit cap not breached
     *   3) Delegate to EnrollmentModel::enroll()
     *      which itself handles quota → waitlist atomically
     *
     * Returns ['ok'=>bool, 'message'=>string, 'kind'=>string, ...].
     * `kind` is one of: prereq, credit_cap, enrolled, waitlisted, error.
     */
    public static function enroll(int $studentId, int $courseId): array {
        // 1) Prereqs
        $pre = self::checkPrerequisitesMet($studentId, $courseId);
        if (!$pre['ok']) {
            $codes = array_map(fn($p) => $p['course_code'], $pre['missing']);
            return [
                'ok'      => false,
                'kind'    => 'prereq',
                'message' => 'Cannot enrol: missing prerequisite(s): '
                            . implode(', ', $codes),
                'missing' => $pre['missing'],
            ];
        }

        // 2) Credit cap
        $cap = self::checkCreditCap($studentId, $courseId);
        if (!$cap['ok']) {
            return [
                'ok'      => false,
                'kind'    => 'credit_cap',
                'message' => "Cannot enrol: this would put you at " .
                              "{$cap['after']} credit hours, over the " .
                              "trimester cap of {$cap['cap']}.",
                'current' => $cap['current'],
                'after'   => $cap['after'],
                'cap'     => $cap['cap'],
            ];
        }

        // 3) Enrol or waitlist
        try {
            $res = EnrollmentModel::enroll($studentId, $courseId);
            return [
                'ok'      => true,
                'kind'    => $res['status'],  // 'enrolled' or 'waitlisted'
                'message' => $res['message'],
                'position'=> $res['position'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'ok'      => false,
                'kind'    => 'error',
                'message' => 'Could not enrol: ' . $e->getMessage(),
            ];
        }
    }

    /** Drop wrapper that returns the standard ['ok'..., 'message'...] shape. */
    public static function drop(int $studentId, int $courseId): array {
        try {
            $res = EnrollmentModel::drop($studentId, $courseId);
            $msg = $res['message'];
            if (!empty($res['promoted'])) {
                $p = $res['promoted'];
                $msg .= " Promoted {$p['sid']} ({$p['full_name']}) "
                       . "from the waitlist.";
            }
            return ['ok' => true, 'kind' => 'dropped', 'message' => $msg];
        } catch (Throwable $e) {
            return [
                'ok'      => false,
                'kind'    => 'error',
                'message' => 'Could not drop: ' . $e->getMessage(),
            ];
        }
    }
}
