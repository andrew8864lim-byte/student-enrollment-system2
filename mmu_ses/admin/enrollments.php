<?php
/**
 * Admin → All Enrollments.
 * Read-only view with a status filter. Shows position for waitlisted rows.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$filter = $_GET['status'] ?? '';
$valid  = ['enrolled', 'waitlisted', 'dropped', 'completed'];
$enrollments = EnrollmentModel::findAll(in_array($filter, $valid, true) ? $filter : null);

$page_title  = 'All Enrollments';
$active_page = 'enrollments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>All Enrollments</h2>
        <p><?= count($enrollments) ?> record<?= count($enrollments) === 1 ? '' : 's' ?><?= $filter ? ' (filtered)' : '' ?>.</p>
    </div>
</div>

<form method="GET" class="filter-bar">
    <label style="display:flex;align-items:center;gap:8px;">Status:
        <select name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($valid as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <a href="<?= base_url('admin/enrollments.php') ?>" class="btn btn-outline btn-sm">Reset</a>
</form>

<?php if (empty($enrollments)): ?>
    <div class="empty-state"><div class="icon">📭</div><p>No enrollments match the filter.</p></div>
<?php else: ?>
<div class="table-wrapper">
    <table>
        <thead>
            <tr><th>Student ID</th><th>Student Name</th><th>Course</th><th>Credits</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
            <?php foreach ($enrollments as $r): ?>
                <tr>
                    <td><strong><?= e($r['student_id']) ?></strong></td>
                    <td><?= e($r['student_name']) ?></td>
                    <td>
                        <strong><?= e($r['course_code']) ?></strong>
                        <div style="font-size:12px;color:var(--text-muted);"><?= e($r['course_name']) ?></div>
                    </td>
                    <td><?= (int)$r['credit_hours'] ?></td>
                    <td><?= status_badge($r['status'], $r['waitlist_position'] ?? null) ?></td>
                    <td style="font-size:13px;"><?= e(date('d M Y, H:i', strtotime($r['enrollment_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
