<?php
/**
 * Student → My Waitlist.
 * Lists every course the student is queued for, with their queue position
 * and an option to withdraw. When somebody drops, EnrollmentModel
 * automatically promotes the head of the queue — see
 * EnrollmentModel::drop() for the logic.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw') {
    AuthController::verifyCsrf();
    $course_id = (int)($_POST['course_id'] ?? 0);
    $res = EnrollmentController::drop($user['id'], $course_id);
    flash_set($res['ok'] ? 'success' : 'error', $res['message']);
    header('Location: ' . base_url('student/waitlist.php'));
    exit;
}

$waitlisted = EnrollmentModel::waitlistedForStudent($user['id']);

$page_title  = 'My Waitlist';
$active_page = 'waitlist';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>My Waitlist</h2>
        <p>Courses you're queued for. You'll be promoted automatically when a seat opens up.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:14px;margin:0;">
            <strong>How the waitlist works:</strong> When a course is full at the time you click <em>Enrol</em>,
            you're added to the bottom of its queue. If another enrolled student drops the course,
            the student at position #1 is automatically moved into the open seat, and everyone behind them shifts up by one.
            Waitlisted courses do <strong>not</strong> count toward your credit-hour cap.
        </p>
    </div>
</div>

<?php if (empty($waitlisted)): ?>
    <div class="empty-state">
        <div class="icon">✨</div>
        <p>You're not waitlisted for anything right now.</p>
    </div>
<?php else: ?>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Position</th>
                <th>Course</th>
                <th>Lecturer</th>
                <th>Credits</th>
                <th>Queued On</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($waitlisted as $r): ?>
            <tr>
                <td><?= status_badge('waitlisted', (int)$r['waitlist_position']) ?></td>
                <td>
                    <strong><?= e($r['course_code']) ?></strong>
                    <div style="font-size:12px;color:var(--text-muted);"><?= e($r['course_name']) ?></div>
                </td>
                <td><?= e($r['lecturer_name']) ?></td>
                <td><?= (int)$r['credit_hours'] ?></td>
                <td style="font-size:13px;"><?= e(date('d M Y, H:i', strtotime($r['enrollment_date']))) ?></td>
                <td>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Withdraw from the waitlist for <?= e($r['course_code']) ?>?');">
                        <?= AuthController::csrfField() ?>
                        <input type="hidden" name="action" value="withdraw">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
