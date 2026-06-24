<?php
/**
 * Login page (Screen 1 in the project report).
 * --------------------------------------------------------------------
 * Thin controller-style page:
 *   * Delegates authentication to AuthController::login()
 *   * Includes a CSRF token in the form
 *   * Auto-detects student vs admin (no role dropdown needed)
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in → straight to the right dashboard
if (is_admin())   { header('Location: ' . base_url('admin/dashboard.php')); exit; }
if (is_student()) { header('Location: ' . base_url('student/dashboard.php')); exit; }

$error    = '';
$login_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $res = AuthController::login($login_id, $password);
    if ($res['ok']) {
        flash_set('success', 'Welcome back, ' . $_SESSION['full_name'] . '!');
        $dest = ($res['role'] === 'admin')
            ? 'admin/dashboard.php' : 'student/dashboard.php';
        header('Location: ' . base_url($dest));
        exit;
    }
    $error = $res['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – MMU Student Enrollment System</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-mark">SES</div>
            <h1>MMU SES — Welcome Back</h1>
            <p>Multimedia University Student Enrollment System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">✗</span><span><?= e($error) ?></span>
            </div>
        <?php endif; ?>
        <?= flash_render() ?>

        <form method="POST" action="">
            <?= AuthController::csrfField() ?>

            <div class="form-group">
                <label for="login_id">Student ID or Admin ID</label>
                <input type="text" id="login_id" name="login_id"
                       value="<?= e($login_id) ?>"
                       placeholder="e.g. 253UT256KY or ADM001"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password" required>
            </div>

            <div class="form-options">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="#" onclick="alert('Please contact admin@mmu.edu.my to reset your password.'); return false;">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-block">Login</button>
        </form>

        <div class="auth-divider">
            Don't have a student account?
            <a href="<?= base_url('register.php') ?>"><strong>Register here</strong></a>
        </div>

        <div style="margin-top:18px;padding:14px;background:#f8fafc;border-radius:8px;font-size:12px;color:#6b7280;">
            <strong>Demo accounts (password: <code>password123</code>):</strong><br>
            Student: <code>253UT256KY</code> &nbsp;|&nbsp; Admin: <code>ADM001</code>
        </div>
    </div>
</div>
</body>
</html>
