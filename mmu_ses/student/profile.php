<?php
/**
 * Student → Profile.
 * Allows the student to update their contact details and change password.
 * All persistence goes through StudentModel.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_student();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $data = [
            'student_id' => $user['login_id'], // not editable from this screen
            'full_name'  => trim($_POST['full_name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'programme'  => trim($_POST['programme'] ?? $user['programme']),
            'trimester'  => (int)($_POST['trimester'] ?? $user['trimester']),
        ];
        $err = null;
        if ($data['full_name'] === '' || $data['email'] === '') {
            $err = 'Name and email cannot be empty.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $err = 'That email address is not valid.';
        }
        if ($err) {
            flash_set('error', $err);
        } else {
            try {
                StudentModel::update($user['id'], $data);
                // Refresh session-stored display name
                $_SESSION['full_name'] = $data['full_name'];
                $_SESSION['programme'] = $data['programme'];
                $_SESSION['trimester'] = $data['trimester'];
                flash_set('success', 'Profile updated successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Could not update profile (email may already be in use).');
            }
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stu = StudentModel::findById($user['id']);
        if (!$stu || !password_verify($current, $stu['password'])) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash_set('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'New password and confirmation do not match.');
        } else {
            StudentModel::setPassword($user['id'], $new);
            flash_set('success', 'Password changed successfully.');
        }
    }
    header('Location: ' . base_url('student/profile.php'));
    exit;
}

$stu = StudentModel::findById($user['id']);

$page_title  = 'My Profile';
$active_page = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div><h2>My Profile</h2><p>Update your details and change your password.</p></div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h3>Personal Details</h3></div>
        <div class="card-body">
            <form method="POST">
                <?= AuthController::csrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" value="<?= e($stu['student_id']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= e($stu['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= e($stu['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Programme</label>
                    <input type="text" name="programme" value="<?= e($stu['programme']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Current Trimester</label>
                    <input type="number" name="trimester" min="1" max="9"
                           value="<?= (int)$stu['trimester'] ?>" required>
                </div>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Change Password</h3></div>
        <div class="card-body">
            <form method="POST">
                <?= AuthController::csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" minlength="6" required>
                    <small style="color:var(--text-muted);">Minimum 6 characters.</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" minlength="6" required>
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
