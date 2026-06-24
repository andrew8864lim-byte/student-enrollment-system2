<?php
/**
 * Admin → Prerequisites.
 * Manage the (course, prereq) links in course_prerequisites. Adds, deletes.
 * Cycle prevention is intentionally lightweight — we only block A→A on insert. A full topological cycle check is overkill for a course catalogue.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $course_id = (int)($_POST['course_id'] ?? 0);
            $prereq_id = (int)($_POST['prereq_id'] ?? 0);
            if ($course_id <= 0 || $prereq_id <= 0) {
                throw new RuntimeException('Pick a course and a prerequisite.');
            }
            $cA = CourseModel::findById($course_id);
            $cB = CourseModel::findById($prereq_id);
            if (!$cA || !$cB) throw new RuntimeException('Course not found.');
            PrerequisiteModel::add($course_id, $prereq_id);
            flash_set('success', "Added: {$cA['course_code']} now requires {$cB['course_code']}.");
        }
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            PrerequisiteModel::delete($id);
            flash_set('success', 'Prerequisite link removed.');
        }
    } catch (PDOException $e) {
        flash_set('error', 'Database error (duplicate link?): ' . $e->getMessage());
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    header('Location: ' . base_url('admin/prerequisites.php'));
    exit;
}

$links   = PrerequisiteModel::findAll();
$courses = CourseModel::findAll();

$page_title  = 'Manage Prerequisites';
$active_page = 'prerequisites';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Course Prerequisites</h2>
        <p>Define which courses must be passed before others can be taken.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Add a Prerequisite</h3></div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= AuthController::csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Course (the advanced one)</label>
                <select name="course_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['course_code'] . ' — ' . $c['course_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Requires (the prerequisite)</label>
                <select name="prereq_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['course_code'] . ' — ' . $c['course_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <button class="btn" type="submit">Add Link</button>
            </div>
        </form>
        <p style="margin-top:10px;font-size:12px;color:var(--text-muted);">
            A student can only enrol in the advanced course once their <code>enrollments</code>
            record for the prerequisite has status <strong>completed</strong>.
        </p>
    </div>
</div>

<h3 style="margin-top:24px;">Existing Prerequisites (<?= count($links) ?>)</h3>
<?php if (empty($links)): ?>
    <div class="empty-state"><div class="icon">🔗</div><p>No prerequisite links yet.</p></div>
<?php else: ?>
<div class="table-wrapper">
    <table>
        <thead>
            <tr><th>Course</th><th></th><th>Requires</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php foreach ($links as $l): ?>
                <tr>
                    <td>
                        <strong><?= e($l['course_code']) ?></strong>
                        <div style="font-size:12px;color:var(--text-muted);"><?= e($l['course_name']) ?></div>
                    </td>
                    <td style="text-align:center;color:var(--text-muted);">⟵ requires ⟵</td>
                    <td>
                        <strong><?= e($l['prereq_code']) ?></strong>
                        <div style="font-size:12px;color:var(--text-muted);"><?= e($l['prereq_name']) ?></div>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Remove the requirement that <?= e($l['course_code']) ?> needs <?= e($l['prereq_code']) ?>?');">
                            <?= AuthController::csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
