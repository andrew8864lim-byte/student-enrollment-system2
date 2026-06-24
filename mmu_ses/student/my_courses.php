<?php
/**
 * Student → Add / Drop Subjects (Screen 4 in the report).
 * Shows all the student's enrolment rows split into:
 * * Currently enrolled (can be dropped)
 * * On the waitlist (can be withdrawn, will free their queue position)
 * * Past / dropped (read-only, for history)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

// ----- Handle drop POST -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'drop') {
    AuthController::verifyCsrf();
    $course_id = (int)($_POST['course_id'] ?? 0);
    $res = EnrollmentController::drop($user['id'], $course_id);
    flash_set($res['ok'] ? 'success' : 'error', $res['message']);
    header('Location: ' . base_url('student/my_courses.php'));
    exit;
}

$enrolled   = EnrollmentModel::activeForStudent($user['id']);
$waitlisted = EnrollmentModel::waitlistedForStudent($user['id']);
$history    = EnrollmentModel::forStudent($user['id'], 'dropped');
$completed  = EnrollmentModel::forStudent($user['id'], 'completed');

$credit_now = EnrollmentModel::currentCreditHours($user['id']);
$credit_cap = MAX_CREDIT_HOURS_PER_TRIMESTER;

$page_title  = 'Add / Drop Subjects';
$active_page = 'my_courses';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Add / Drop Subjects</h2>
        <p>Manage your enrolments for <?= e(ACADEMIC_TRIMESTER) ?>, <?= e(ACADEMIC_YEAR_LABEL) ?>.</p>
    </div>
    <a class="btn btn-accent" href="<?= base_url('student/courses.php') ?>">+ Add a Subject</a>
</div>

<div class="stat-strip">
    <div class="stat-pill"><span class="stat-pill-label">Enrolled</span><strong><?= count($enrolled) ?></strong></div>
    <div class="stat-pill"><span class="stat-pill-label">Waitlisted</span><strong><?= count($waitlisted) ?></strong></div>
    <div class="stat-pill"><span class="stat-pill-label">Credit hours</span><strong><?= $credit_now ?> / <?= $credit_cap ?></strong></div>
</div>

<h3 style="margin-top:24px;">Currently Enrolled</h3>
<?php if (empty($enrolled)): ?>
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>No active enrolments. Head to <a href="<?= base_url('student/courses.php') ?>">Course Registration</a> to start.</p>
    </div>
<?php else: ?>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Code</th><th>Course Name</th><th>Lecturer</th>
                <th>Credits</th><th>Schedule</th><th>Status</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enrolled as $r): ?>
                <tr>
                    <td><strong><?= e($r['course_code']) ?></strong></td>
                    <td><?= e($r['course_name']) ?></td>
                    <td><?= e($r['lecturer_name']) ?></td>
                    <td><?= (int)$r['credit_hours'] ?></td>
                    <td><?= e($r['schedule_info']) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Drop <?= e($r['course_code']) ?>? Your seat will go to the next student on the waitlist (if any).');">
                            <?= AuthController::csrfField() ?>
                            <input type="hidden" name="action" value="drop">
                            <input type="hidden" name="course_id" value="<?= (int)$r['course_id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Drop</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($waitlisted)): ?>
    <h3 style="margin-top:32px;">On the Waitlist</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Code</th><th>Course Name</th><th>Lecturer</th><th>Credits</th><th>Position</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($waitlisted as $r): ?>
                    <tr>
                        <td><strong><?= e($r['course_code']) ?></strong></td>
                        <td><?= e($r['course_name']) ?></td>
                        <td><?= e($r['lecturer_name']) ?></td>
                        <td><?= (int)$r['credit_hours'] ?></td>
                        <td><?= status_badge('waitlisted', (int)$r['waitlist_position']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Withdraw from the waitlist for <?= e($r['course_code']) ?>?');">
                                <?= AuthController::csrfField() ?>
                                <input type="hidden" name="action" value="drop">
                                <input type="hidden" name="course_id" value="<?= (int)$r['course_id'] ?>">
                                <button class="btn btn-sm btn-outline" type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (!empty($completed)): ?>
    <h3 style="margin-top:32px;">Completed (Past Trimesters)</h3>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Code</th><th>Course Name</th><th>Credits</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($completed as $r): ?>
                    <tr>
                        <td><strong><?= e($r['course_code']) ?></strong></td>
                        <td><?= e($r['course_name']) ?></td>
                        <td><?= (int)$r['credit_hours'] ?></td>
                        <td><?= status_badge('completed') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (!empty($history)): ?>
    <h3 style="margin-top:32px;">Dropped (History)</h3>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Code</th><th>Course Name</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($history as $r): ?>
                    <tr>
                        <td><strong><?= e($r['course_code']) ?></strong></td>
                        <td><?= e($r['course_name']) ?></td>
                        <td><?= status_badge('dropped') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>