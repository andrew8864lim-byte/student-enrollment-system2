<?php
/**
 * Student → Registration Confirmation Slip.
 * Self-contained HTML page that uses @media print styles so the
 * browser's "Save as PDF" produces a clean, printable artefact.
 *
 * Bypasses the standard sidebar layout because the slip is meant to
 * stand on its own when printed.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

$stu        = StudentModel::findById($user['id']);
$enrolled   = EnrollmentModel::activeForStudent($user['id']);
$waitlisted = EnrollmentModel::waitlistedForStudent($user['id']);
$credit_now = EnrollmentModel::currentCreditHours($user['id']);

$slip_no    = 'SES-' . str_pad((string)$user['id'], 4, '0', STR_PAD_LEFT)
            . '-' . date('YmdHis');
$generated  = date('d M Y, H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Slip – <?= e($stu['student_id']) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <style>
        body { background: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; }
        .slip-page {
            max-width: 820px; margin: 24px auto; background: #fff;
            border: 1px solid #e2e8f0; box-shadow: 0 4px 18px rgba(15,23,42,.08);
            padding: 40px 48px;
        }
        .slip-actions { max-width: 820px; margin: 18px auto; display: flex; gap: 10px; }
        .slip-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 22px; }
        .slip-header h1 { font-size: 22px; margin: 0; color: #0f172a; }
        .slip-header .meta { font-size: 12px; color: #475569; text-align: right; }
        .slip-title { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: #1e3a8a; margin: 18px 0 8px; }
        .slip-sub   { text-align: center; font-size: 13px; color: #475569; margin-bottom: 24px; }
        .slip-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; font-size: 14px; margin-bottom: 24px; }
        .slip-meta dt { color: #64748b; }
        .slip-meta dd { margin: 0; color: #0f172a; font-weight: 600; }
        table.slip { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 6px; }
        table.slip th, table.slip td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        table.slip th { background: #f1f5f9; font-weight: 600; }
        table.slip tfoot td { font-weight: 700; background: #f8fafc; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 48px; font-size: 12px; color: #475569; }
        .signatures div { border-top: 1px solid #0f172a; padding-top: 6px; }
        .footnote { font-size: 11px; color: #64748b; margin-top: 28px; border-top: 1px dashed #cbd5e1; padding-top: 10px; }

        @media print {
            body { background: #fff; }
            .slip-actions, .no-print { display: none !important; }
            .slip-page { box-shadow: none; border: none; margin: 0; padding: 18px 24px; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>

<div class="slip-actions no-print">
    <a class="btn btn-outline" href="<?= base_url('student/dashboard.php') ?>">← Back to dashboard</a>
    <button class="btn btn-accent" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>

<div class="slip-page">

    <div class="slip-header">
        <div>
            <h1><?= e(INSTITUTION_NAME) ?></h1>
            <div style="font-size:12px;color:#475569;"><?= e(INSTITUTION_FACULTY) ?></div>
        </div>
        <div class="meta">
            Slip No.: <strong><?= e($slip_no) ?></strong><br>
            Generated: <?= e($generated) ?>
        </div>
    </div>

    <div class="slip-title">Subject Registration Confirmation</div>
    <div class="slip-sub"><?= e(ACADEMIC_YEAR_LABEL) ?> &middot; <?= e(ACADEMIC_TRIMESTER) ?></div>

    <dl class="slip-meta">
        <dt>Student ID</dt>           <dd><?= e($stu['student_id']) ?></dd>
        <dt>Programme</dt>            <dd><?= e($stu['programme']) ?></dd>
        <dt>Full Name</dt>            <dd><?= e($stu['full_name']) ?></dd>
        <dt>Trimester (Current)</dt>  <dd><?= (int)$stu['trimester'] ?></dd>
        <dt>Email</dt>                <dd><?= e($stu['email']) ?></dd>
        <dt>Total Credit Hours</dt>   <dd><?= $credit_now ?> / <?= MAX_CREDIT_HOURS_PER_TRIMESTER ?></dd>
    </dl>

    <h3 style="margin-top:12px;font-size:14px;color:#0f172a;">Registered Subjects</h3>
    <table class="slip">
        <thead>
            <tr>
                <th style="width:32px;">#</th>
                <th>Code</th>
                <th>Course Name</th>
                <th>Lecturer</th>
                <th>Schedule</th>
                <th style="width:60px;text-align:right;">Credits</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($enrolled)): ?>
            <tr><td colspan="6" style="text-align:center;color:#64748b;">No enrolled subjects.</td></tr>
        <?php else: $n = 1; foreach ($enrolled as $r): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td><strong><?= e($r['course_code']) ?></strong></td>
                <td><?= e($r['course_name']) ?></td>
                <td><?= e($r['lecturer_name']) ?></td>
                <td><?= e($r['schedule_info']) ?></td>
                <td style="text-align:right;"><?= (int)$r['credit_hours'] ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;">Total Credit Hours</td>
                <td style="text-align:right;"><?= $credit_now ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($waitlisted)): ?>
        <h3 style="margin-top:24px;font-size:14px;color:#0f172a;">Waitlisted Subjects (provisional)</h3>
        <table class="slip">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th style="width:80px;text-align:right;">Position</th>
                    <th style="width:60px;text-align:right;">Credits</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 1; foreach ($waitlisted as $r): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><strong><?= e($r['course_code']) ?></strong></td>
                        <td><?= e($r['course_name']) ?></td>
                        <td style="text-align:right;">#<?= (int)$r['waitlist_position'] ?></td>
                        <td style="text-align:right;"><?= (int)$r['credit_hours'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="font-size:11px;color:#64748b;margin-top:8px;">
            Waitlisted subjects are not yet confirmed. They will become enrolled automatically
            when a seat opens up, in queue order.
        </p>
    <?php endif; ?>

    <div class="signatures">
        <div>Student Signature &amp; Date</div>
        <div>Faculty Advisor / Registrar</div>
    </div>

    <div class="footnote">
        This is a system-generated registration slip from the MMU Student Enrollment System
        (SES). It is intended for the student's reference and is to be presented to the
        faculty office on request. Verify all subjects above against the offered timetable
        before the add/drop deadline.
    </div>
</div>

</body>
</html>
