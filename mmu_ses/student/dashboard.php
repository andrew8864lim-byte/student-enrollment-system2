<?php
/**
 * Student → Dashboard (Screen 2 in the report).
 * Uses models only — no SQL is written in this file.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

$enrolled   = EnrollmentModel::activeForStudent($user['id']);
$waitlisted = EnrollmentModel::waitlistedForStudent($user['id']);
$completed  = EnrollmentModel::forStudent($user['id'], 'completed');

$credit_now = EnrollmentModel::currentCreditHours($user['id']);
$credit_cap = MAX_CREDIT_HOURS_PER_TRIMESTER;
$credit_pct = $credit_cap > 0 ? min(100, ($credit_now / $credit_cap) * 100) : 0;

// Recent enrolment activity (last 5)
$recent = array_slice(
    EnrollmentModel::forStudent($user['id']),
    0, 5
);

$page_title  = 'Dashboard';
$active_page = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Welcome, <?= e($user['full_name']) ?> 👋</h2>
        <p>
            <?= e($user['programme']) ?> • Trimester <?= (int)$user['trimester'] ?> •
            <?= e(ACADEMIC_YEAR_LABEL) ?>
        </p>
    </div>
    <a class="btn btn-accent" href="<?= base_url('student/slip.php') ?>">🧾 View Registration Slip</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">📚</div>
        <div>
            <div class="stat-label">Active Enrolments</div>
            <div class="stat-value"><?= count($enrolled) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;">⏳</div>
        <div>
            <div class="stat-label">Waitlisted</div>
            <div class="stat-value"><?= count($waitlisted) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;">✅</div>
        <div>
            <div class="stat-label">Completed Subjects</div>
            <div class="stat-value"><?= count($completed) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe;">🎯</div>
        <div>
            <div class="stat-label">Credit Hours</div>
            <div class="stat-value"><?= $credit_now ?><span style="font-size:14px;color:var(--text-muted);"> / <?= $credit_cap ?></span></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Credit hour usage</h3></div>
    <div class="card-body">
        <div class="quota-bar" style="height:16px;">
            <div class="quota-bar-fill <?= $credit_pct >= 100 ? 'full' : ($credit_pct >= 80 ? 'warning' : '') ?>"
                 style="width:<?= $credit_pct ?>%"></div>
        </div>
        <p style="margin-top:10px;color:var(--text-muted);font-size:14px;">
            You are carrying <strong><?= $credit_now ?> credit hours</strong> out of the maximum
            <strong><?= $credit_cap ?></strong> permitted per trimester.
            <?php if ($credit_now < MIN_CREDIT_HOURS_PER_TRIMESTER): ?>
                <span class="badge badge-warning">Below the recommended minimum of <?= MIN_CREDIT_HOURS_PER_TRIMESTER ?></span>
            <?php elseif ($credit_now >= $credit_cap): ?>
                <span class="badge badge-danger">At the cap</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h3>Quick actions</h3></div>
        <div class="card-body">
            <div class="quick-actions">
                <a class="quick-action" href="<?= base_url('student/courses.php') ?>">
                    <div class="icon">📚</div>
                    <strong>Register for a Course</strong>
                    <small>Browse available subjects</small>
                </a>
                <a class="quick-action" href="<?= base_url('student/my_courses.php') ?>">
                    <div class="icon">📝</div>
                    <strong>Add / Drop</strong>
                    <small>Adjust your enrolments</small>
                </a>
                <a class="quick-action" href="<?= base_url('student/timetable.php') ?>">
                    <div class="icon">📅</div>
                    <strong>Timetable</strong>
                    <small>View your weekly schedule</small>
                </a>
                <a class="quick-action" href="<?= base_url('student/slip.php') ?>">
                    <div class="icon">🧾</div>
                    <strong>Registration Slip</strong>
                    <small>Print / save as PDF</small>
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Recent Activity</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recent)): ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>No enrolment activity yet.</p>
                </div>
            <?php else: ?>
                <table style="margin:0;">
                    <thead><tr><th>Course</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['course_code']) ?></strong>
                                    <div style="font-size:12px;color:var(--text-muted);"><?= e($r['course_name']) ?></div>
                                </td>
                                <td><?= status_badge($r['status'], $r['waitlist_position'] ?? null) ?></td>
                                <td style="font-size:12px;"><?= e(date('d M Y', strtotime($r['enrollment_date']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
