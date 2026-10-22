<?php
/** logout.php - destroys the session and returns to the login page. */
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    audit('LOGOUT', current_user()['username']);
}
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
