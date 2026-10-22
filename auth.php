<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------
 * Session start, login protection, role permissions, CSRF tokens
 * and small helper functions used on every page.
 */
require_once __DIR__ . '/../config/config.php';

// ---- Secure session settings --------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,          // JavaScript cannot read the cookie
        'samesite' => 'Lax',         // basic CSRF protection
    ]);
    session_start();
}

// ---- Role permission map -----------------------------------------
// Which roles may open which module.
const PERMISSIONS = [
    'dashboard'    => ['admin','doctor','nurse','receptionist','lab','pharmacist','cashier'],
    'patients'     => ['admin','doctor','nurse','receptionist'],
    'patient_add'  => ['admin','receptionist','nurse'],
    'appointments' => ['admin','doctor','nurse','receptionist'],
    'visits'       => ['admin','doctor','nurse'],
    'lab'          => ['admin','doctor','lab'],
    'pharmacy'     => ['admin','doctor','pharmacist'],
    'billing'      => ['admin','cashier','receptionist'],
    'users'        => ['admin'],
    'departments'  => ['admin'],
    'reports'      => ['admin','doctor'],
];

/** Escape output (prevents XSS). Use on EVERY value printed into HTML. */
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Is someone logged in? */
function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

/** Current user array (id, full_name, role ...) */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/** Can the current user use a module? */
function can(string $module): bool {
    $u = current_user();
    return $u && in_array($u['role'], PERMISSIONS[$module] ?? [], true);
}

/** Force login, and (optionally) check the module permission. */
function require_login(?string $module = null): void {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    // Auto logout after 30 minutes of inactivity
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();

    if ($module !== null && !can($module)) {
        http_response_code(403);
        exit('<h2 style="font-family:sans-serif;padding:40px">403 - You do not have permission to open this page.</h2>');
    }
}

// ---- CSRF protection ---------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Security token expired. Go back, refresh the page and try again.');
    }
}

// ---- Flash messages (one-time notices after redirect) -------------
function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---- Audit log -----------------------------------------------------
function audit(string $action, string $details = ''): void {
    global $pdo;
    $uid = current_user()['id'] ?? null;
    $st  = $pdo->prepare('INSERT INTO audit_log (user_id, action, details, ip_address) VALUES (?,?,?,?)');
    $st->execute([$uid, $action, mb_substr($details, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
}

// ---- Misc helpers --------------------------------------------------
/** Generate next Medical Record Number, e.g. UOG-2026-000123 */
function next_mrn(PDO $pdo): string {
    $year = date('Y');
    $n = (int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM patients')->fetchColumn();
    return sprintf('UOG-%s-%06d', $year, $n);
}

/** Age in years from a date of birth. */
function age_from(string $dob): int {
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

/** Cleaned POST value (trimmed string). */
function post(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}
