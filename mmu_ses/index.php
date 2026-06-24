<?php
require_once __DIR__ . '/includes/auth.php';

if (is_admin()) {
    header('Location: ' . base_url('admin/dashboard.php'));
} elseif (is_student()) {
    header('Location: ' . base_url('student/dashboard.php'));
} else {
    header('Location: ' . base_url('login.php'));
}
exit;
