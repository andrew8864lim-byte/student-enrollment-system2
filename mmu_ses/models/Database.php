<?php
/**
 * Database
 * --------------------------------------------------------------------
 * Singleton wrapper around a single PDO connection.
 * Every model in the system uses Database::pdo() so we never open
 * more than one connection per request.
 *
 * Credentials are loaded from includes/db_credentials.php which
 * remains the only place to edit when moving from XAMPP to a live
 * host (e.g. InfinityFree).
 */

class Database {

    private static ?PDO $instance = null;

    /** Returns the shared PDO instance, creating it on first use. */
    public static function pdo(): PDO {
        if (self::$instance === null) {
            // Pull constants defined in includes/db_credentials.php
            $dsn = 'mysql:host=' . DB_HOST
                 . ';dbname=' . DB_NAME
                 . ';charset=utf8mb4';
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Friendly error page rather than a raw stack trace
                http_response_code(500);
                die(self::renderConnectionError($e->getMessage()));
            }
        }
        return self::$instance;
    }

    private static function renderConnectionError(string $msg): string {
        $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Database error</title>
<style>
 body{font-family:system-ui,sans-serif;background:#f4f6fb;padding:60px 20px;color:#1f2937}
 .card{max-width:640px;margin:auto;background:#fff;border-radius:14px;
       padding:36px;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06)}
 h1{color:#991b1b;margin:0 0 8px}
 .err{background:#fef2f2;border-left:4px solid #b91c1c;
      padding:14px;border-radius:6px;font-family:monospace;font-size:13px;
      color:#7f1d1d;margin:18px 0}
 .hint{color:#6b7280;font-size:14px;line-height:1.6}
 code{background:#eef2ff;padding:2px 6px;border-radius:4px;font-size:13px}
</style></head>
<body><div class="card">
 <h1>Database connection failed</h1>
 <p>The application could not reach the MySQL database.</p>
 <div class="err">{$safe}</div>
 <div class="hint">
  <strong>Checklist:</strong><br>
  • Have you started MySQL in the XAMPP Control Panel?<br>
  • Did you import <code>database/ses_setup.sql</code> in phpMyAdmin?<br>
  • Are the credentials in <code>includes/db_credentials.php</code> correct
    for your environment?
 </div>
</div></body></html>
HTML;
    }
}
