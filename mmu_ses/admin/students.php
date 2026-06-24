<?php
/**
 * Admin → Manage Students (Screen 5 in the report).
 * Full CRUD plus password reset correctly. All persistence goes through StudentModel.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $data = [
                'student_id' => trim($_POST['student_id'] ?? ''),
                'full_name'  => trim($_POST['full_name'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'password'   => $_POST['password'] ?? '',
                'programme'  => trim($_POST['programme'] ?? 'AI'),
                'trimester'  => (int)($_POST['trimester'] ?? 1),
            ];
            if ($data['student_id'] === '' || $data['full_name'] === ''
                || $data['email'] === '' || strlen($data['password']) < 6) {
                throw new RuntimeException('All fields are required, and password must be 6+ characters.');
            }
            StudentModel::create($data);
            flash_set('success', 'Student ' . $data['student_id'] . ' created.');
        }
        elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'student_id' => trim($_POST['student_id'] ?? ''),
                'full_name'  => trim($_POST['full_name'] ?? ''),
                'email'      => trim($_POST['email'] ?? ''),
                'programme'  => trim($_POST['programme'] ?? 'AI'),
                'trimester'  => (int)($_POST['trimester'] ?? 1),
            ];
            StudentModel::update($id, $data);
            flash_set('success', 'Student updated.');
        }
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            StudentModel::delete($id);
            flash_set('success', 'Student deleted.');
        }
        elseif ($action === 'reset_password') {
            $id    = (int)($_POST['id'] ?? 0);
            $newpw = $_POST['new_password'] ?? '';
            if (strlen($newpw) < 6) {
                throw new RuntimeException('Password must be at least 6 characters.');
            }
            StudentModel::setPassword($id, $newpw);
            flash_set('success', 'Password reset successfully.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
            if (strpos($e->getMessage(), 'student_id') !== false) {
                flash_set('error', 'Error: The Student ID already exists in the system.');
            } elseif (strpos($e->getMessage(), 'email') !== false) {
                flash_set('error', 'Error: The Email address already exists in the system.');
            } else {
                flash_set('error', 'Error: Duplicate data detected (Student ID or Email already exists).');
            }
        } else {
            flash_set('error', 'Database error: ' . $e->getMessage());
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    header('Location: ' . base_url('admin/students.php'));
    exit;
}

$students   = StudentModel::findAll();
$edit       = isset($_GET['edit']) ? StudentModel::findById((int)$_GET['edit']) : null;
$reset      = isset($_GET['reset_pw']) ? StudentModel::findById((int)$_GET['reset_pw']) : null;

$page_title  = 'Manage Students';
$active_page = 'students';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Manage Students</h2>
        <p><?= count($students) ?> student account<?= count($students) === 1 ? '' : 's' ?> in the system.</p>
    </div>
    <?php if (!$edit && !isset($_GET['new'])): ?>
        <a class="btn btn-accent" href="?new=1">+ Add Student</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['new']) || $edit): ?>
<div class="card">
    <div class="card-header"><h3><?= $edit ? 'Edit Student' : 'Add New Student' ?></h3></div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= AuthController::csrfField() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Student ID *</label>
                <input type="text" name="student_id" value="<?= e($edit['student_id'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" value="<?= e($edit['full_name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required>
            </div>
            <?php if (!$edit): ?>
                <div class="form-group">
                    <label>Initial Password *</label>
                    <input type="password" name="password" minlength="6" required>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Programme</label>
                <input type="text" name="programme" value="<?= e($edit['programme'] ?? 'AI') ?>" required>
            </div>
            <div class="form-group">
                <label>Current Trimester</label>
                <input type="number" name="trimester" min="1" max="9" value="<?= (int)($edit['trimester'] ?? 1) ?>" required>
            </div>
            <div style="grid-column: 1 / -1;">
                <button class="btn" type="submit"><?= $edit ? 'Save Changes' : 'Create Student' ?></button>
                <a class="btn btn-outline" href="<?= base_url('admin/students.php') ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($reset): ?>
<div class="card">
    <div class="card-header"><h3>Reset Password — <?= e($reset['student_id']) ?></h3></div>
    <div class="card-body">
        <form method="POST">
            <?= AuthController::csrfField() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= (int)$reset['id'] ?>">
            <div class="form-group">
                <label>New Password (6+ chars)</label>
                <input type="password" name="new_password" minlength="6" required>
            </div>
            <button class="btn" type="submit">Set Password</button>
            <a class="btn btn-outline" href="<?= base_url('admin/students.php') ?>">Cancel</a>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Student ID</th><th>Name</th><th>Email</th><th>Programme</th><th>Trim</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><strong><?= e($s['student_id']) ?></strong></td>
                    <td><?= e($s['full_name']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['programme']) ?></td>
                    <td><?= (int)$s['trimester'] ?></td>
                    <td style="white-space:nowrap;">
                        <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-outline" href="?reset_pw=<?= (int)$s['id'] ?>">Reset PW</a>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Permanently delete <?= e($s['student_id']) ?>? All their enrolments will be removed.');">
                            <?= AuthController::csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>