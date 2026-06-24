<?php
/**
 * Student → Course Registration (Screen 3 in the report).
 * Thin controller: validates input, delegates to EnrollmentController,
 * displays the result via flash messages. No SQL is written here.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll') {
    AuthController::verifyCsrf();
    $course_id = (int)($_POST['course_id'] ?? 0);

    $res = EnrollmentController::enroll($user['id'], $course_id);

    $flash_type = $res['ok']
        ? ($res['kind'] === 'waitlisted' ? 'warning' : 'success')
        : 'error';
    flash_set($flash_type, $res['message']);
    header('Location: ' . base_url('student/courses.php'));
    exit;
}

$q          = trim($_GET['q'] ?? '');
$trim       = $_GET['trim'] ?? '';
$show_full  = isset($_GET['show_full']);

$courses = CourseModel::availableForStudent($user['id']);

$courses = array_filter($courses, function ($c) use ($q, $trim, $show_full, $user) {
    if ($c['programme'] !== $user['programme']) return false;
    if ($q !== '') {
        $hay = strtolower($c['course_code'] . ' ' . $c['course_name'] . ' ' . $c['lecturer_name']);
        if (strpos($hay, strtolower($q)) === false) return false;
    }
    if ($trim !== '' && is_numeric($trim) && (int)$c['trimester'] !== (int)$trim) return false;
    if (!$show_full && (int)$c['is_full'] === 1) return false;
    return true;
});

// Pre-compute prereq status and the credit-hour summary for the view
$credit_now = EnrollmentModel::currentCreditHours($user['id']);
$credit_cap = MAX_CREDIT_HOURS_PER_TRIMESTER;

// For each course, pre-evaluate prereq + cap so the UI can disable & explain
$row_info = [];
foreach ($courses as $c) {
    $cid = (int)$c['id'];
    $pre = EnrollmentController::checkPrerequisitesMet($user['id'], $cid);
    $row_info[$cid] = [
        'prereqs'         => PrerequisiteModel::forCourse($cid),
        'prereq_ok'       => $pre['ok'],
        'missing_prereqs' => $pre['missing'],
        'fits_credit_cap' => ($credit_now + (int)$c['credit_hours']) <= $credit_cap,
    ];
}

$page_title  = 'Course Registration';
$active_page = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Course Registration</h2>
        <p>Browse and enrol in available subjects for the <strong><?= e($user['programme']) ?></strong> programme.</p>
    </div>
</div>

<div class="stat-strip">
    <div class="stat-pill">
        <span class="stat-pill-label">Credit hours this trimester</span>
        <strong><?= $credit_now ?> / <?= $credit_cap ?></strong>
    </div>
    <?php if ($credit_now >= $credit_cap): ?>
        <span class="badge badge-danger">At the cap — no more enrolments allowed</span>
    <?php elseif ($credit_now >= $credit_cap - 3): ?>
        <span class="badge badge-warning">Close to the cap</span>
    <?php else: ?>
        <span class="badge badge-info"><?= $credit_cap - $credit_now ?> credit hours remaining</span>
    <?php endif; ?>
</div>

<form method="GET" class="filter-bar">
    <input type="text" name="q" value="<?= e($q) ?>"
           placeholder="🔍 Search by course code, name, or lecturer...">
    <select name="trim">
        <option value="">All trimesters</option>
        <?php for ($i = 1; $i <= 9; $i++): ?>
            <option value="<?= $i ?>" <?= ($trim !== '' && (int)$trim === $i) ? 'selected' : '' ?>>
                Trimester <?= $i ?>
            </option>
        <?php endfor; ?>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:var(--text-muted);">
        <input type="checkbox" name="show_full" value="1" <?= $show_full ? 'checked' : '' ?>> Show full classes
    </label>
    <button class="btn" type="submit">Apply</button>
    <a href="<?= base_url('student/courses.php') ?>" class="btn btn-outline">Reset</a>
</form>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Lecturer</th>
                <th>Credits</th>
                <th>Trim</th>
                <th>Seats</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($courses)): ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <p>No courses match your filters.</p>
                </div>
            </td></tr>
        <?php else: foreach ($courses as $c):
            $info = $row_info[(int)$c['id']];
            $remaining = (int)$c['quota'] - (int)$c['enrolled_count'];
            $pct = $c['quota'] > 0 ? min(100, ($c['enrolled_count'] / $c['quota']) * 100) : 100;
            $fillCls = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warning' : '');
            $isFull  = (int)$c['is_full'] === 1;
        ?>
            <tr>
                <td><strong><?= e($c['course_code']) ?></strong></td>
                <td>
                    <?= e($c['course_name']) ?>
                    <?php if ($c['schedule_info']): ?>
                        <div style="font-size:12px;color:var(--text-muted);"><?= e($c['schedule_info']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($info['prereqs'])): ?>
                        <div style="font-size:11px;margin-top:4px;">
                            <span class="badge badge-muted">Prereq:</span>
                            <?php foreach ($info['prereqs'] as $pr): ?>
                                <code style="font-size:11px;"><?= e($pr['course_code']) ?></code>
                            <?php endforeach; ?>
                            <?php if (!$info['prereq_ok']): ?>
                                <span class="badge badge-danger">not met</span>
                            <?php else: ?>
                                <span class="badge badge-success">met</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?= e($c['lecturer_name']) ?></td>
                <td><?= (int)$c['credit_hours'] ?></td>
                <td><?= (int)$c['trimester'] ?></td>
                <td>
                    <div class="quota-bar">
                        <div class="quota-bar-fill <?= $fillCls ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div style="font-size:12px;margin-top:4px;color:var(--text-muted);">
                        <?= (int)$c['enrolled_count'] ?> / <?= (int)$c['quota'] ?>
                        <?php if ($isFull): ?>
                            <span class="badge badge-warning">FULL → waitlist</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <?php
                    $reason = null;
                    if (!$info['prereq_ok']) {
                        $reason = 'Prerequisites not met';
                    } elseif (!$info['fits_credit_cap']) {
                        $reason = 'Over credit cap';
                    }
                    ?>
                    <?php if ($reason): ?>
                        <button class="btn btn-sm" disabled
                                style="background:#9ca3af;cursor:not-allowed;"
                                title="<?= e($reason) ?>">Blocked</button>
                    <?php else: ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('<?= $isFull
                                  ? 'This course is full — you will be added to the waitlist. Continue?'
                                  : 'Confirm enrolment in ' . e($c['course_code']) . '?' ?>');">
                            <?= AuthController::csrfField() ?>
                            <input type="hidden" name="action" value="enroll">
                            <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                            <button class="btn <?= $isFull ? 'btn-warning' : 'btn-accent' ?> btn-sm" type="submit">
                                <?= $isFull ? 'Join Waitlist' : 'Enroll' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>