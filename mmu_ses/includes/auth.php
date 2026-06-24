<?php
/**
 * Authentication & general-purpose helper functions
 * --------------------------------------------------------------------
 * Session start happens in includes/bootstrap.php. This file only
 * holds the small global helpers that views and controllers call.
 */

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function is_student(): bool {
    return is_logged_in() && $_SESSION['role'] === 'student';
}

function is_admin(): bool {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        flash_set('warning', 'Please log in to continue.');
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function require_student(): void {
    require_login();
    if (!is_student()) {
        flash_set('error', 'Students only — admins cannot access this area.');
        header('Location: ' . base_url('admin/dashboard.php'));
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        flash_set('error', 'Administrators only — please log in as admin.');
        header('Location: ' . base_url('student/dashboard.php'));
        exit;
    }
}

function current_user(): array {
    return [
        'id'        => $_SESSION['user_id']    ?? null,
        'role'      => $_SESSION['role']       ?? null,
        'full_name' => $_SESSION['full_name']  ?? '',
        'login_id'  => $_SESSION['login_id']   ?? '',
        'programme' => $_SESSION['programme']  ?? '',
        'trimester' => $_SESSION['trimester']  ?? 0,
    ];
}

/**
 * Build a URL relative to the application root, regardless of whether
 * we are currently in /, /student, or /admin. Works whether the app
 * is at /mmu_ses/ or at /.
 */
function base_url(string $path = ''): string {
    $script = $_SERVER['SCRIPT_NAME'];
    $dir = str_replace('\\', '/', dirname($script));
    if (basename($dir) === 'student' || basename($dir) === 'admin') {
        $dir = dirname($dir);
    }
    $dir = rtrim($dir, '/');
    return $dir . '/' . ltrim($path, '/');
}

/** HTML-escape shorthand. Always use this for any database value
 *  before sending it to the browser. */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/* ---------------- Flash messages ---------------- */

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function flash_render(): string {
    $f = flash_get();
    if (!$f) return '';
    $cls = 'alert-info';
    $icon = 'i';
    switch ($f['type']) {
        case 'success': $cls = 'alert-success'; $icon = '✓'; break;
        case 'error':   $cls = 'alert-error';   $icon = '✗'; break;
        case 'warning': $cls = 'alert-warning'; $icon = '!'; break;
    }
    return '<div class="alert ' . $cls . '">'
         . '<span class="alert-icon">' . $icon . '</span>'
         . '<span>' . e($f['message']) . '</span>'
         . '</div>';
}

/** Pretty status badge (Enrolled / Waitlisted / Dropped / Completed). */
function status_badge(string $status, ?int $position = null): string {
    $map = [
        'enrolled'   => ['Enrolled',   'badge-success'],
        'waitlisted' => ['Waitlisted', 'badge-warning'],
        'dropped'    => ['Dropped',    'badge-muted'],
        'completed'  => ['Completed',  'badge-info'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'badge-muted'];
    if ($status === 'waitlisted' && $position !== null) {
        $label .= ' #' . $position;
    }
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}
