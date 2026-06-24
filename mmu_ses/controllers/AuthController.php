<?php
/**
 * AuthController
 * --------------------------------------------------------------------
 * Login / logout flow and CSRF token helpers. Keeps the login pages
 * thin.
 */

class AuthController {

    /**
     * Try to authenticate the given credentials, against both the
     * student and admin tables. On success, populates $_SESSION and
     * regenerates the session ID (anti-fixation).
     *
     * Returns ['ok'=>true, 'role'=>'student'|'admin'] on success, or
     * ['ok'=>false, 'message'=>'Invalid credentials.'] on failure.
     */
    public static function login(string $loginId, string $password): array {
        $loginId  = trim($loginId);
        $password = (string)$password;

        if ($loginId === '' || $password === '') {
            return ['ok' => false, 'message' => 'Please enter both ID and password.'];
        }

        // Try student first
        $student = StudentModel::authenticate($loginId, $password);
        if ($student) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$student['id'];
            $_SESSION['role']      = 'student';
            $_SESSION['full_name'] = $student['full_name'];
            $_SESSION['login_id']  = $student['student_id'];
            $_SESSION['programme'] = $student['programme'];
            $_SESSION['trimester'] = (int)$student['trimester'];
            return ['ok' => true, 'role' => 'student'];
        }

        // Then admin
        $admin = AdminModel::authenticate($loginId, $password);
        if ($admin) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$admin['id'];
            $_SESSION['role']      = 'admin';
            $_SESSION['full_name'] = $admin['full_name'];
            $_SESSION['login_id']  = $admin['admin_id'];
            return ['ok' => true, 'role' => 'admin'];
        }

        return ['ok' => false, 'message' => 'Invalid ID or password.'];
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /* ---------------- CSRF helpers ---------------- */

    /** Get-or-create the per-session CSRF token. */
    public static function csrfToken(): string {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /** Render a hidden CSRF input for inclusion in any form. */
    public static function csrfField(): string {
        $t = self::csrfToken();
        $name = CSRF_TOKEN_NAME;
        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$t}\">";
    }

    /**
     * Verify the token submitted in a POST request. Aborts with HTTP
     * 419 if the token is missing or wrong. Pages call this at the
     * top of any POST branch.
     */
    public static function verifyCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        $got      = $_POST[CSRF_TOKEN_NAME] ?? '';
        if ($expected === '' || !hash_equals($expected, (string)$got)) {
            http_response_code(419);
            die('<h2 style="font-family:sans-serif;padding:30px;'
              . 'color:#991b1b;">Session expired (CSRF token invalid).</h2>'
              . '<p style="font-family:sans-serif;padding:0 30px;">'
              . 'Please <a href="' . htmlspecialchars(base_url('login.php'),
                 ENT_QUOTES, 'UTF-8') . '">log in again</a>.</p>');
        }
    }
}
