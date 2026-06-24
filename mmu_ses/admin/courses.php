<?php
/**
 * Admin → Manage Courses.
 * Full CRUD. Quota cannot be lowered below the current enrolled_count
 * (safety check enforced here in the controller before delegating to
 * CourseModel::update).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $data = [
                'course_code'   => trim($_POST['course_code'] ?? ''),
                'course_name'   => trim($_POST['course_name'] ?? ''),
                'lecturer_name' => trim($_POST['lecturer_name'] ?? ''),
                'credit_hours'  => (int)($_POST['credit_hours'] ?? 3),
                'quota'         => (int)($_POST['quota'] ?? 30),
                'programme'     => trim($_POST['programme'] ?? 'AI'),
                'trimester'     => (int)($_POST['trimester'] ?? 1),
                'schedule_info' => trim($_POST['schedule_info'] ?? ''),
            ];
            if ($data['course_code'] === '' || $data['course_name'] === ''
                || $data['credit_hours'] < 1 || $data['quota'] < 1) {
                throw new RuntimeException('Course code, name, valid credits and quota are required.');
            }
            CourseModel::create($data);
            flash_set('success', 'Course ' . $data['course_code'] . ' created.');
        }
        elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $existing = CourseModel::findById($id);
            if (!$existing) throw new RuntimeException('Course not found.');

            $new_quota = (int)($_POST['quota'] ?? 0);
            if ($new_quota < (int)$existing['enrolled_count']) {
                throw new RuntimeException(
                    "Cannot lower quota below current enrolled count ({$existing['enrolled_count']})."
                );
            }
            $data = [
                'course_code'   => trim($_POST['course_code'] ?? ''),
                'course_name'   => trim($_POST['course_name'] ?? ''),
                'lecturer_name' => trim($_POST['lecturer_name'] ?? ''),
                'credit_hours'  => (int)($_POST['credit_hours'] ?? 3),
                'quota'         => $new_quota,
                'programme'     => trim($_POST['programme'] ?? 'AI'),
                'trimester'     => (int)($_POST['trimester'] ?? 1),
                'schedule_info' => trim($_POST['schedule_info'] ?? ''),
            ];
            CourseModel::update($id, $data);
            flash_set('success', 'Course updated.');
        }
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            CourseModel::delete($id);
            flash_set('success', 'Course deleted (all related enrolments cascaded).');
        }
    } catch (PDOException $e) {
        flash_set('error', 'Database error: ' . $e->getMessage());
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    header('Location: ' . base_url('admin/courses.php'));
    exit;
}

$courses = CourseModel::findAll();
$edit    = isset($_GET['edit']) ? CourseModel::findById((int)$_GET['edit']) : null;

// 在编辑时，尝试解析已有的 schedule_info 填回表单
$edit_lecture_day = ''; $edit_lecture_start = ''; $edit_lecture_end = '';
$edit_lab_day = ''; $edit_lab_start = ''; $edit_lab_end = '';

if ($edit && !empty($edit['schedule_info'])) {
    // 假设存储格式如: "Mon 10:00-12:00, Wed 14:00-15:00"
    $slots = explode(',', $edit['schedule_info']);
    if (isset($slots[0])) {
        preg_match('/^\s*([A-Za-z]+)\s+(\d{2}:\d{2})-(\d{2}:\d{2})/', $slots[0], $matches);
        if ($matches) {
            $edit_lecture_day = $matches[1];
            $edit_lecture_start = $matches[2];
            $edit_lecture_end = $matches[3];
        }
    }
    if (isset($slots[1])) {
        preg_match('/^\s*([A-Za-z]+)\s+(\d{2}:\d{2})-(\d{2}:\d{2})/', $slots[1], $matches);
        if ($matches) {
            $edit_lab_day = $matches[1];
            $edit_lab_start = $matches[2];
            $edit_lab_end = $matches[3];
        }
    }
}

$page_title  = 'Manage Courses';
$active_page = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .schedule-container {
        display: flex;
        gap: 20px;
        background: #fdfdfd;
        border: 1px solid #e2e8f0;
        padding: 15px;
        border-radius: 8px;
        margin-top: 5px;
    }
    .schedule-block {
        flex: 1;
        background: #ffffff;
        padding: 12px;
        border-radius: 6px;
        border-left: 4px solid #3b82f6;
    }
    .schedule-block.lab-block {
        border-left-color: #10b981;
    }
    .schedule-block h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .schedule-row {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .schedule-row select {
        flex: 1;
        padding: 8px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background-color: #fff;
    }
    .schedule-row span {
        color: #94a3b8;
        font-size: 12px;
    }
    .error-msg {
        color: #ef4444;
        font-size: 13px;
        margin-top: 8px;
        display: none;
        font-weight: bold;
    }
</style>

<div class="page-header">
    <div><h2>Manage Courses</h2><p><?= count($courses) ?> course<?= count($courses) === 1 ? '' : 's' ?> in the system.</p></div>
    <div>
        <a class="btn btn-outline" href="<?= base_url('admin/prerequisites.php') ?>">🔗 Prerequisites</a>
        <a class="btn btn-accent" href="?new=1">+ Add Course</a>
    </div>
</div>

<?php if (isset($_GET['new']) || $edit): ?>
<div class="card">
    <div class="card-header"><h3><?= $edit ? 'Edit Course' : 'Add Course' ?></h3></div>
    <div class="card-body">
        <form method="POST" class="form-grid" id="courseForm">
            <?= AuthController::csrfField() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php endif; ?>

            <input type="hidden" name="schedule_info" id="final_schedule_info" value="<?= e($edit['schedule_info'] ?? '') ?>">

            <div class="form-group">
                <label>Course Code *</label>
                <input type="text" name="course_code" value="<?= e($edit['course_code'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Course Name *</label>
                <input type="text" name="course_name" value="<?= e($edit['course_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Lecturer *</label>
                <input type="text" name="lecturer_name" value="<?= e($edit['lecturer_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Programme *</label>
                <input type="text" name="programme" value="<?= e($edit['programme'] ?? 'AI') ?>" required>
            </div>
            <div class="form-group">
                <label>Trimester</label>
                <input type="number" name="trimester" min="1" max="9" value="<?= (int)($edit['trimester'] ?? 1) ?>" required>
            </div>
            <div class="form-group">
                <label>Credit Hours</label>
                <input type="number" name="credit_hours" min="1" max="6" value="<?= (int)($edit['credit_hours'] ?? 3) ?>" required>
            </div>
            <div class="form-group">
                <label>Quota *</label>
                <input type="number" name="quota" min="1" value="<?= (int)($edit['quota'] ?? 30) ?>" required>
                <?php if ($edit): ?>
                    <small style="color:var(--text-muted);">Currently enrolled: <?= (int)$edit['enrolled_count'] ?></small>
                <?php endif; ?>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Course Schedule *</label>
                <div class="schedule-container">
                    
                    <div class="schedule-block">
                        <h4>📘 Lecture Session</h4>
                        <div class="schedule-row">
                            <select id="lec_day">
                                <option value="">-- Select Day --</option>
                                <option value="Mon" <?= $edit_lecture_day === 'Mon' ? 'selected' : '' ?>>Monday</option>
                                <option value="Tue" <?= $edit_lecture_day === 'Tue' ? 'selected' : '' ?>>Tuesday</option>
                                <option value="Wed" <?= $edit_lecture_day === 'Wed' ? 'selected' : '' ?>>Wednesday</option>
                                <option value="Thu" <?= $edit_lecture_day === 'Thu' ? 'selected' : '' ?>>Thursday</option>
                                <option value="Fri" <?= $edit_lecture_day === 'Fri' ? 'selected' : '' ?>>Friday</option>
                            </select>
                            <select id="lec_start"></select>
                            <span>to</span>
                            <select id="lec_end"></select>
                        </div>
                    </div>

                    <div class="schedule-block lab-block">
                        <h4>🔬 Lab Session (Optional)</h4>
                        <div class="schedule-row">
                            <select id="lab_day">
                                <option value="">-- No Lab --</option>
                                <option value="Mon" <?= $edit_lab_day === 'Mon' ? 'selected' : '' ?>>Monday</option>
                                <option value="Tue" <?= $edit_lab_day === 'Tue' ? 'selected' : '' ?>>Tuesday</option>
                                <option value="Wed" <?= $edit_lab_day === 'Wed' ? 'selected' : '' ?>>Wednesday</option>
                                <option value="Thu" <?= $edit_lab_day === 'Thu' ? 'selected' : '' ?>>Thursday</option>
                                <option value="Fri" <?= $edit_lab_day === 'Fri' ? 'selected' : '' ?>>Friday</option>
                            </select>
                            <select id="lab_start"></select>
                            <span>to</span>
                            <select id="lab_end"></select>
                        </div>
                    </div>

                </div>
                <div class="error-msg" id="schedule_error"></div>
            </div>

            <div style="grid-column: 1 / -1;">
                <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Create Course' ?></button>
                <a class="btn btn-outline" href="<?= base_url('admin/courses.php') ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const timeSlots = [
        "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", 
        "14:00", "15:00", "16:00", "17:00", "18:00", "19:00"
    ];

    const lecStart = document.getElementById('lec_start');
    const lecEnd = document.getElementById('lec_end');
    const labStart = document.getElementById('lab_start');
    const labEnd = document.getElementById('lab_end');
    const errorMsg = document.getElementById('schedule_error');
    const form = document.getElementById('courseForm');

    function populateSelect(selectEl, selectedVal) {
        selectEl.innerHTML = '<option value="">-- Time --</option>';
        timeSlots.forEach(time => {
            let selected = (time === selectedVal) ? 'selected' : '';
            selectEl.innerHTML += `<option value="${time}" ${selected}>${time}</option>`;
        });
    }

    populateSelect(lecStart, "<?= $edit_lecture_start ?>");
    populateSelect(lecEnd, "<?= $edit_lecture_end ?>");
    populateSelect(labStart, "<?= $edit_lab_start ?>");
    populateSelect(labEnd, "<?= $edit_lab_end ?>");

    form.addEventListener('submit', function(e) {
        errorMsg.style.display = 'none';
        errorMsg.innerText = '';

        const lecDayVal = document.getElementById('lec_day').value;
        const lecStartVal = lecStart.value;
        const lecEndVal = lecEnd.value;

        const labDayVal = document.getElementById('lab_day').value;
        const labStartVal = labStart.value;
        const labEndVal = labEnd.value;

        if (!lecDayVal || !lecStartVal || !lecEndVal) {
            e.preventDefault();
            errorMsg.innerText = "❌ Please complete all fields for the Lecture session.";
            errorMsg.style.display = 'block';
            return;
        }

        if (timeSlots.indexOf(lecStartVal) >= timeSlots.indexOf(lecEndVal)) {
            e.preventDefault();
            errorMsg.innerText = "❌ Lecture end time must be later than the start time.";
            errorMsg.style.display = 'block';
            return;
        }

        let segments = [];
        segments.push(`${lecDayVal} ${lecStartVal}-${lecEndVal}`);

        if (labDayVal || labStartVal || labEndVal) {
            if (!labDayVal || !labStartVal || !labEndVal) {
                e.preventDefault();
                errorMsg.innerText = "❌ Please complete all fields for the Lab session or unselect it completely.";
                errorMsg.style.display = 'block';
                return;
            }
            if (timeSlots.indexOf(labStartVal) >= timeSlots.indexOf(labEndVal)) {
                e.preventDefault();
                errorMsg.innerText = "❌ Lab end time must be later than the start time.";
                errorMsg.style.display = 'block';
                return;
            }

            if (lecDayVal === labDayVal) {
                let l_s = timeSlots.indexOf(lecStartVal);
                let l_e = timeSlots.indexOf(lecEndVal);
                let b_s = timeSlots.indexOf(labStartVal);
                let b_e = timeSlots.indexOf(labEndVal);
                
                if (!(b_e <= l_s || b_s >= l_e)) {
                    e.preventDefault();
                    errorMsg.innerText = "❌ Lecture and Lab sessions overlap on the same day.";
                    errorMsg.style.display = 'block';
                    return;
                }
            }
            segments.push(`${labDayVal} ${labStartVal}-${labEndVal}`);
        }

        document.getElementById('final_schedule_info').value = segments.join(', ');
    });
});
</script>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Code</th><th>Name</th><th>Lecturer</th>
                <th>Pg</th><th>Trim</th><th>Credits</th><th>Quota</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c):
                $pct = $c['quota'] > 0 ? min(100, ($c['enrolled_count'] / $c['quota']) * 100) : 0;
                $cls = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warning' : '');
            ?>
                <tr>
                    <td><strong><?= e($c['course_code']) ?></strong></td>
                    <td>
                        <?= e($c['course_name']) ?>
                        <?php if ($c['schedule_info']): ?>
                            <div style="font-size:12px;color:var(--text-muted);"><span style="color:var(--text-accent);">📅</span> <?= e($c['schedule_info']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($c['lecturer_name']) ?></td>
                    <td><?= e($c['programme']) ?></td>
                    <td><?= (int)$c['trimester'] ?></td>
                    <td><?= (int)$c['credit_hours'] ?></td>
                    <td>
                        <div class="quota-bar"><div class="quota-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= (int)$c['enrolled_count'] ?> / <?= (int)$c['quota'] ?></div>
                    </td>
                    <td style="white-space:nowrap;">
                        <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$c['id'] ?>">Edit</a>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete <?= e($c['course_code']) ?>? This will also delete all enrolments for it.');">
                            <?= AuthController::csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>