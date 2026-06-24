<?php
/**
 * Legacy shim — kept so any older code that does
 *   require_once 'includes/db.php';
 * keeps working. New code should require the bootstrap instead and
 * access PDO through Database::pdo().
 */

require_once __DIR__ . '/bootstrap.php';

/** Global $pdo exposed for any legacy callers. */
$pdo = Database::pdo();
