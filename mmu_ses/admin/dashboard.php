<?php
/**
 * Admin → Dashboard.
 * System-wide stats and a glimpse at the most-filled courses.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$user = current_user();

$num_students = StudentModel::count();
$num_courses  = CourseModel::count();
$num_admins   = AdminModel::count();

$enrolled_count   = EnrollmentModel::countByStatus('enrolled');
$waitlist_count   = EnrollmentModel::countByStatus('waitlisted');
$dropped_count    = EnrollmentModel::countByStatus('dropped');
$completed_count  = EnrollmentModel::countByStatus('completed');

$top_courses = CourseModel::topFilled(5);

$page_title  = 'Dashboard';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Welcome, <?= e($user['full_name']) ?></h2>
        <p>Administrator view — <?= e(ACADEMIC_YEAR_LABEL) ?>, <?= e(ACADEMIC_TRIMESTER) ?>.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">👥</div>
        <div><div class="stat-label">Students</div><div class="stat-value"><?= $num_students ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;">📚</div>
        <div><div class="stat-label">Courses</div><div class="stat-value"><?= $num_courses ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;">🎯</div>
        <div><div class="stat-label">Active Enrollments</div><div class="stat-value"><?= $enrolled_count ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe;">⏳</div>
        <div><div class="stat-label">Waitlisted</div><div class="stat-value"><?= $waitlist_count ?></div></div>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h3>Top filled courses</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($top_courses)): ?>
                <div class="empty-state"><div class="icon">📭</div><p>No courses yet.</p></div>
            <?php else: ?>
                <table style="margin:0;">
                    <thead><tr><th>Code</th><th>Name</th><th>Filled</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_courses as $c):
                            $pct = $c['quota'] > 0 ? min(100, ($c['enrolled_count'] / $c['quota']) * 100) : 0;
                            $cls = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warning' : '');
                        ?>
                            <tr>
                                <td><strong><?= e($c['course_code']) ?></strong></td>
                                <td><?= e($c['course_name']) ?></td>
                                <td>
                                    <div class="quota-bar"><div class="quota-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
                                    <div style="font-size:12px;margin-top:4px;color:var(--text-muted);">
                                        <?= (int)$c['enrolled_count'] ?> / <?= (int)$c['quota'] ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Enrollment status breakdown</h3></div>
        <div class="card-body">
            <table style="margin:0;">
                <tr><td><?= status_badge('enrolled') ?></td><td style="text-align:right;"><strong><?= $enrolled_count ?></strong></td></tr>
                <tr><td><?= status_badge('waitlisted') ?></td><td style="text-align:right;"><strong><?= $waitlist_count ?></strong></td></tr>
                <tr><td><?= status_badge('completed') ?></td><td style="text-align:right;"><strong><?= $completed_count ?></strong></td></tr>
                <tr><td><?= status_badge('dropped') ?></td><td style="text-align:right;"><strong><?= $dropped_count ?></strong></td></tr>
            </table>
            <p style="font-size:12px;color:var(--text-muted);margin-top:10px;">
                <?= $num_admins ?> administrator accounts active.
            </p>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header"><h3>Quick actions</h3></div>
    <div class="card-body">
        <div class="quick-actions">
            <a class="quick-action" href="<?= base_url('admin/students.php') ?>"><div class="icon">👥</div><strong>Manage students</strong></a>
            <a class="quick-action" href="<?= base_url('admin/courses.php') ?>"><div class="icon">📚</div><strong>Manage courses</strong></a>
            <a class="quick-action" href="<?= base_url('admin/prerequisites.php') ?>"><div class="icon">🔗</div><strong>Prerequisites</strong></a>
            <a class="quick-action" href="<?= base_url('admin/reports.php') ?>"><div class="icon">📊</div><strong>Reports / CSV</strong></a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
