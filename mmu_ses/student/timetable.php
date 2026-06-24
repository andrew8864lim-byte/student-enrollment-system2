<?php
/**
 * Student → Weekly Timetable.
 * Builds a Mon–Fri × 8AM–7PM grid from the schedule_info string on each
 * enrolled course. Format: "Mon 10:00-12:00, Wed 14:00-15:00".
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

$enrolled = EnrollmentModel::activeForStudent($user['id']);

$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$startHour = 8;
$endHour   = 20; 
$colors = ['#1e40af', '#0f766e', '#b45309', '#7e22ce', '#be185d', '#0369a1', '#15803d', '#a16207'];
$palette = [];

$grid = []; // grid[day][hour] => array of class blocks
foreach ($enrolled as $idx => $course) {
    $palette[$course['course_code']] = $colors[$idx % count($colors)];
    foreach (explode(',', $course['schedule_info']) as $slot) {
        $slot = trim($slot);
        if (!preg_match('/^(Mon|Tue|Wed|Thu|Fri)\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/i', $slot, $m)) {
            continue;
        }
        $day = ucfirst(strtolower($m[1]));
        $sH = (int)$m[2]; $sM = (int)$m[3];
        $eH = (int)$m[4]; $eM = (int)$m[5];
        $grid[$day][] = [
            'code'  => $course['course_code'],
            'name'  => $course['course_name'],
            'lec'   => $course['lecturer_name'],
            'start' => $sH + $sM / 60,
            'end'   => $eH + $eM / 60,
            'color' => $palette[$course['course_code']],
        ];
    }
}

$page_title  = 'My Timetable';
$active_page = 'timetable';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Weekly Timetable</h2>
        <p>Schedule for <strong><?= count($enrolled) ?></strong> enrolled subject<?= count($enrolled) === 1 ? '' : 's' ?>.</p>
    </div>
    <button class="btn btn-outline" onclick="window.print()">🖨 Print</button>
</div>

<?php if (empty($enrolled)): ?>
    <div class="empty-state">
        <div class="icon">📅</div>
        <p>No active enrolments. Add a subject from <a href="<?= base_url('student/courses.php') ?>">Course Registration</a>.</p>
    </div>
<?php else: ?>

<div class="timetable">
    <div class="timetable-grid">
        <div class="tt-header tt-corner"></div>
        <?php foreach ($days as $d): ?>
            <div class="tt-header"><?= e($d) ?></div>
        <?php endforeach; ?>

        <?php for ($h = $startHour; $h < $endHour; $h++): ?>
            <div class="tt-time"><?= sprintf('%02d:00', $h) ?></div>
            <?php foreach ($days as $d): ?>
                <div class="tt-cell">
                    <?php if (!empty($grid[$d])):
                        foreach ($grid[$d] as $block):
                            if ($block['start'] <= $h && $h < $block['end']):
                                // Only render the block in its first hour to avoid duplicates
                                if ((int)floor($block['start']) === $h):
                                    $blocks = max(1, $block['end'] - $block['start']);
                            ?>
                            <div class="tt-class" style="background: <?= e($block['color']) ?>;
                                                         height: calc(<?= $blocks * 100 ?>% - 4px);">
                                <strong><?= e($block['code']) ?></strong>
                                <div><?= e($block['name']) ?></div>
                                <small><?= e($block['lec']) ?></small>
                            </div>
                            <?php endif; endif; endforeach; endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Legend</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <?php foreach ($palette as $code => $color): ?>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:14px;height:14px;border-radius:4px;background:<?= e($color) ?>;display:inline-block;"></span>
                    <strong><?= e($code) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>