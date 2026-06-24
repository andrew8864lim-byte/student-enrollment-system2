<?php
/**
 * Self-registration page for students.
 * Routes the create logic through StudentModel.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$error = '';
$form  = [
    'student_id' => '',
    'full_name'  => '',
    'email'      => '',
    'programme'  => 'AI',
    'trimester'  => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AuthController::verifyCsrf();
    $form['student_id'] = trim($_POST['student_id'] ?? '');
    $form['full_name']  = trim($_POST['full_name']  ?? '');
    $form['email']      = trim($_POST['email']      ?? '');
    $form['programme']  = trim($_POST['programme']  ?? 'AI');
    $form['trimester']  = (int)($_POST['trimester'] ?? 1);
    $password           = $_POST['password']         ?? '';
    $password_confirm   = $_POST['password_confirm'] ?? '';

    if ($form['student_id'] === '' || $form['full_name'] === ''
        || $form['email'] === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (StudentModel::findByStudentId($form['student_id'])) {
        $error = 'A student with this ID already exists.';
    } elseif (StudentModel::findByEmail($form['email'])) {
        $error = 'A student with this email already exists.';
    } else {
        try {
            StudentModel::create([
                'student_id' => $form['student_id'],
                'full_name'  => $form['full_name'],
                'email'      => $form['email'],
                'password'   => $password,
                'programme'  => $form['programme'],
                'trimester'  => $form['trimester'],
            ]);
            flash_set('success', 'Registration successful. You can now log in.');
            header('Location: ' . base_url('login.php'));
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create account: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – MMU SES</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card wide">
        <div class="auth-logo">
            <div class="logo-mark">SES</div>
            <h1>Create Student Account</h1>
            <p>Register to access the MMU Student Enrollment System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <span class="alert-icon">✗</span><span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= AuthController::csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="student_id">Student ID</label>
                    <input type="text" id="student_id" name="student_id"
                           value="<?= e($form['student_id']) ?>"
                           placeholder="e.g. 253UT256KY" required>
                </div>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e($form['full_name']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">MMU Email</label>
                <input type="email" id="email" name="email"
                       value="<?= e($form['email']) ?>"
                       placeholder="firstname.lastname@student.mmu.edu.my" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="programme">Programme</label>
                    <select name="programme" id="programme">
                        <?php foreach (['AI','CS','IT','SE','DS','BIT'] as $p): ?>
                            <option value="<?= $p ?>"
                                <?= $form['programme'] === $p ? 'selected' : '' ?>>
                                <?= $p ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="trimester">Current Trimester</label>
                    <select name="trimester" id="trimester">
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>"
                                <?= $form['trimester'] === $i ? 'selected' : '' ?>>
                                Trimester <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm"
                           name="password_confirm" required>
                </div>
            </div>

            <button type="submit" class="btn btn-block">Create Account</button>
        </form>

        <div class="auth-divider">
            Already have an account?
            <a href="<?= base_url('login.php') ?>"><strong>Login here</strong></a>
        </div>
    </div>
</div>
</body>
</html>
