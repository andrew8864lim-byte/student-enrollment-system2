<?php
/**
 * Admin → Reports.
 * Aggregate statistics and CSV export endpoints.
 * Export type comes via ?export=students|courses|enrollments and streams
 * the file straight back instead of rendering HTML.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

// ----- CSV Export endpoints -----
if (!empty($_GET['export'])) {
    $type = $_GET['export'];
    
    ob_start();

    if ($type === 'students') {
        $rows = StudentModel::findAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students_' . date('Ymd_His') . '.csv"');
        
        $out = fopen('php://output', 'w');

        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['id', 'student_id', 'full_name', 'email', 'password', 'programme', 'trimester', 'created_at']);
        foreach ($rows as $r) {
            $excel_student_id = '="' . $r['student_id'] . '"';
            $excel_created_at = '="' . $r['created_at'] . '"';
            
            fputcsv($out, [
                $r['id'],
                $excel_student_id,
                $r['full_name'],
                $r['email'],
                $r['password'],
                $r['programme'],
                $r['trimester'],
                $excel_created_at
            ]);
        }
        fclose($out);
        exit;
    }
    
    if ($type === 'courses') {
        $rows = CourseModel::findAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="courses_' . date('Ymd_His') . '.csv"');
        
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($out, ['Code', 'Name', 'Lecturer', 'Programme', 'Trimester', 'Credit Hours', 'Quota', 'Enrolled', 'Schedule']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['course_code'], $r['course_name'], $r['lecturer_name'], $r['programme'], $r['trimester'], $r['credit_hours'], $r['quota'], $r['enrolled_count'], $r['schedule_info']]);
        }
        fclose($out);
        exit;
    }
    
    if ($type === 'enrollments') {
        $rows = EnrollmentModel::findAll();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="enrollments_' . date('Ymd_His') . '.csv"');
        
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['Student ID', 'Student Name', 'Course Code', 'Course Name', 'Credits', 'Status']);
        foreach ($rows as $r) {
            $excel_student_id = '="' . $r['student_id'] . '"';
            fputcsv($out, [
                $excel_student_id, 
                $r['student_name'], 
                $r['course_code'], 
                $r['course_name'], 
                $r['credit_hours'], 
                $r['status']
            ]);
        }
        fclose($out);
        exit;
    }
}

// ----- Stats for the dashboard -----
$num_students    = StudentModel::count();
$num_courses     = CourseModel::count();
$enrolled_count  = EnrollmentModel::countByStatus('enrolled');
$waitlist_count  = EnrollmentModel::countByStatus('waitlisted');
$dropped_count   = EnrollmentModel::countByStatus('dropped');
$completed_count = EnrollmentModel::countByStatus('completed');
$top_courses     = CourseModel::topFilled(10);

$page_title  = 'Reports';
$active_page = 'reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h2>Reports &amp; Exports</h2><p>Snapshot of the system; download CSV for offline analysis.</p></div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#dbeafe;">👥</div>
        <div><div class="stat-label">Students</div><div class="stat-value"><?= $num_students ?></div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#fef3c7;">📚</div>
        <div><div class="stat-label">Courses</div><div class="stat-value"><?= $num_courses ?></div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#d1fae5;">🎯</div>
        <div><div class="stat-label">Enrolled</div><div class="stat-value"><?= $enrolled_count ?></div></div></div>
    <div class="stat-card"><div class="stat-icon" style="background:#ede9fe;">⏳</div>
        <div><div class="stat-label">Waitlisted</div><div class="stat-value"><?= $waitlist_count ?></div></div></div>
</div>

<div class="card">
    <div class="card-header"><h3>Downloads (CSV)</h3></div>
    <div class="card-body">
        <a class="btn" href="?export=students">⬇ Students</a>
        <a class="btn" href="?export=courses">⬇ Courses</a>
        <a class="btn" href="?export=enrollments">⬇ All Enrollments</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Status Breakdown</h3></div>
    <div class="card-body" style="padding:0;">
        <table style="margin:0;">
            <thead><tr><th>Status</th><th style="text-align:right;">Count</th></tr></thead>
            <tbody>
                <tr><td><?= status_badge('enrolled') ?></td><td style="text-align:right;"><strong><?= $enrolled_count ?></strong></td></tr>
                <tr><td><?= status_badge('waitlisted') ?></td><td style="text-align:right;"><strong><?= $waitlist_count ?></strong></td></tr>
                <tr><td><?= status_badge('completed') ?></td><td style="text-align:right;"><strong><?= $completed_count ?></strong></td></tr>
                <tr><td><?= status_badge('dropped') ?></td><td style="text-align:right;"><strong><?= $dropped_count ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Top Filled Courses</h3></div>
    <div class="card-body" style="padding:0;">
        <table style="margin:0;">
            <thead><tr><th>Code</th><th>Name</th><th>Enrolled / Quota</th><th style="text-align:right;">Fill %</th></tr></thead>
            <tbody>
                <?php foreach ($top_courses as $c):
                    $pct = $c['quota'] > 0 ? min(100, ($c['enrolled_count'] / $c['quota']) * 100) : 0;
                ?>
                    <tr>
                        <td><strong><?= e($c['course_code']) ?></strong></td>
                        <td><?= e($c['course_name']) ?></td>
                        <td><?= (int)$c['enrolled_count'] ?> / <?= (int)$c['quota'] ?></td>
                        <td style="text-align:right;"><?= number_format($pct, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>