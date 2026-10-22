<?php
/**
 * index.php  -  LOGIN PAGE
 * Verifies username + password (password_verify against bcrypt hash).
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $password = $_POST['password'] ?? '';

    // Simple brute-force protection: max 5 failed tries per session per 5 minutes
    $_SESSION['fails'] = $_SESSION['fails'] ?? ['n' => 0, 't' => time()];
    if (time() - $_SESSION['fails']['t'] > 300) {
        $_SESSION['fails'] = ['n' => 0, 't' => time()];
    }

    if ($_SESSION['fails']['n'] >= 5) {
        $error = 'Too many failed attempts. Please wait 5 minutes.';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $st->execute([$username]);
        $u = $st->fetch();

        if ($u && password_verify($password, $u['password_hash'])) {
            session_regenerate_id(true);               // prevents session fixation
            $_SESSION['user'] = [
                'id'            => (int)$u['id'],
                'full_name'     => $u['full_name'],
                'username'      => $u['username'],
                'role'          => $u['role'],
                'department_id' => $u['department_id'],
            ];
            $_SESSION['last_activity'] = time();
            $_SESSION['fails'] = ['n' => 0, 't' => time()];
            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
            audit('LOGIN', $u['username']);
            header('Location: dashboard.php');
            exit;
        }
        $_SESSION['fails']['n']++;
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | <?= e(APP_SHORT) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="brand-logo big">✚</div>
    <h2><?= e(HOSPITAL) ?></h2>
    <p class="muted">Patient Information Management System</p>

    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert alert-warning">Your session expired. Please log in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" data-validate>
      <?= csrf_field() ?>
      <label>Username
        <input type="text" name="username" required autofocus>
      </label>
      <label>Password
        <div class="pw-wrap">
          <input type="password" name="password" id="pw" required>
          <button type="button" class="pw-toggle" data-toggle-pw="pw">Show</button>
        </div>
      </label>
      <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>
    <p class="muted small center">Authorised hospital staff only. All activity is logged.</p>
  </div>
  <script src="assets/js/app.js"></script>
</body>
</html>
