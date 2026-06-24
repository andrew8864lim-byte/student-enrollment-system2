<?php
/**
 * Shared layout header / sidebar
 * Pages set $page_title and $active_page before including this file.
 */
require_once __DIR__ . '/bootstrap.php';
require_login();
$user = current_user();
$role = $user['role'];

$page_title  = $page_title  ?? 'MMU SES';
$active_page = $active_page ?? '';

// Build the menu
if ($role === 'student') {
    $menu_top = [
        ['url' => 'student/dashboard.php',  'key' => 'dashboard',  'icon' => '🏠', 'label' => 'Dashboard'],
        ['url' => 'student/courses.php',    'key' => 'courses',    'icon' => '📚', 'label' => 'Course Registration'],
        ['url' => 'student/my_courses.php', 'key' => 'my_courses', 'icon' => '📝', 'label' => 'Add / Drop Subject'],
        ['url' => 'student/waitlist.php',   'key' => 'waitlist',   'icon' => '⏳', 'label' => 'My Waitlist'],
        ['url' => 'student/timetable.php',  'key' => 'timetable',  'icon' => '📅', 'label' => 'My Timetable'],
        ['url' => 'student/slip.php',       'key' => 'slip',       'icon' => '🧾', 'label' => 'Registration Slip'],
    ];
    $menu_bottom = [
        ['url' => 'student/profile.php', 'key' => 'profile', 'icon' => '👤', 'label' => 'Profile'],
        ['url' => 'logout.php',          'key' => 'logout',  'icon' => '🚪', 'label' => 'Logout'],
    ];
} else {
    $menu_top = [
        ['url' => 'admin/dashboard.php',     'key' => 'dashboard',    'icon' => '🏠', 'label' => 'Dashboard'],
        ['url' => 'admin/students.php',      'key' => 'students',     'icon' => '👥', 'label' => 'Manage Students'],
        ['url' => 'admin/courses.php',       'key' => 'courses',      'icon' => '📚', 'label' => 'Manage Courses'],
        ['url' => 'admin/prerequisites.php', 'key' => 'prerequisites','icon' => '🔗', 'label' => 'Prerequisites'],
        ['url' => 'admin/enrollments.php',   'key' => 'enrollments',  'icon' => '🎯', 'label' => 'All Enrollments'],
        ['url' => 'admin/reports.php',       'key' => 'reports',      'icon' => '📊', 'label' => 'Reports'],
    ];
    $menu_bottom = [
        ['url' => 'logout.php', 'key' => 'logout', 'icon' => '🚪', 'label' => 'Logout'],
    ];
}

$initials = '';
foreach (explode(' ', $user['full_name']) as $part) {
    if ($part !== '') $initials .= mb_substr($part, 0, 1);
}
$initials = strtoupper(mb_substr($initials, 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> – MMU SES</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-mark">SES</div>
            <div>
                <h2>MMU SES</h2>
                <small><?= $role === 'admin' ? 'Administrator' : 'Student Portal' ?></small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <?php foreach ($menu_top as $m): ?>
                <a href="<?= base_url($m['url']) ?>"
                   class="<?= $active_page === $m['key'] ? 'active' : '' ?>">
                    <span class="icon"><?= $m['icon'] ?></span> <?= e($m['label']) ?>
                </a>
            <?php endforeach; ?>
            <div class="sidebar-section">Account</div>
            <?php foreach ($menu_bottom as $m): ?>
                <a href="<?= base_url($m['url']) ?>"
                   class="<?= $active_page === $m['key'] ? 'active' : '' ?>">
                    <span class="icon"><?= $m['icon'] ?></span> <?= e($m['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <h1><?= e($page_title) ?></h1>
            <div class="user-chip">
                <div class="avatar"><?= e($initials) ?></div>
                <div>
                    <div style="font-weight:600"><?= e($user['full_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted)"><?= e($user['login_id']) ?></div>
                </div>
            </div>
        </div>
        <div class="content">
            <?= flash_render() ?>
