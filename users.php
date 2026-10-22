<?php
/**
 * users.php - admin creates staff accounts, resets passwords, enables/disables.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('users');

const ROLES = ['admin','doctor','nurse','receptionist','lab','pharmacist','cashier'];

/** Password rule: at least 8 chars, one letter and one number. */
function strong_password(string $pw): bool {
    return strlen($pw) >= 8 && preg_match('/[A-Za-z]/', $pw) && preg_match('/\d/', $pw);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'add') {
            if (post('full_name') === '' || post('username') === '') throw new RuntimeException('Name and username are required.');
            if (!in_array(post('role'), ROLES, true)) throw new RuntimeException('Invalid role.');
            if (!strong_password($_POST['password'] ?? '')) throw new RuntimeException('Password needs 8+ characters with letters and numbers.');
            $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role, department_id, phone) VALUES (?,?,?,?,?,?)')
                ->execute([post('full_name'), post('username'), password_hash($_POST['password'], PASSWORD_DEFAULT),
                           post('role'), (int)post('department_id') ?: null, post('phone') ?: null]);
            audit('USER_ADD', post('username'));
            flash('success', 'Staff account created.');
        } elseif ($action === 'toggle') {
            $uid = (int)post('id');
            if ($uid === current_user()['id']) throw new RuntimeException('You cannot disable your own account.');
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([$uid]);
            audit('USER_TOGGLE', "#$uid");
            flash('success', 'Account status changed.');
        } elseif ($action === 'reset') {
            if (!strong_password($_POST['password'] ?? '')) throw new RuntimeException('Password needs 8+ characters with letters and numbers.');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), (int)post('id')]);
            audit('USER_RESET_PW', '#' . (int)post('id'));
            flash('success', 'Password reset.');
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That username already exists.' : 'Database error.');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    header('Location: users.php');
    exit;
}

$rows = $pdo->query('SELECT u.*, d.name AS dept FROM users u LEFT JOIN departments d ON d.id=u.department_id ORDER BY u.role, u.full_name')->fetchAll();
$depts = $pdo->query('SELECT id, name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();

$pageTitle = 'Staff users';
$active = 'users';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2">
  <section class="card">
    <h3>Accounts</h3>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Name</th><th>Role</th><th>Department</th><th>Last login</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr class="<?= $u['is_active'] ? '' : 'inactive' ?>">
          <td><strong><?= e($u['full_name']) ?></strong><br><small class="muted"><?= e($u['username']) ?></small></td>
          <td><span class="badge role"><?= e($u['role']) ?></span></td>
          <td><?= e($u['dept'] ?? '-') ?></td>
          <td><?= e($u['last_login'] ?? 'never') ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input type="password" name="password" placeholder="New password" autocomplete="new-password">
              <button class="btn btn-sm btn-light" name="action" value="reset">Reset</button>
              <?php if ((int)$u['id'] !== current_user()['id']): ?>
                <button class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>" name="action" value="toggle"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card">
    <h3>Create staff account</h3>
    <form method="post" class="form-stack" data-validate novalidate autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Full name * <input name="full_name" required></label>
      <label>Username * <input name="username" required pattern="[A-Za-z0-9._-]{3,50}"></label>
      <label>Password * <input type="password" name="password" id="newpw" required minlength="8" autocomplete="new-password">
        <div class="meter"><span id="meterBar"></span></div><small class="muted" id="meterTxt"></small></label>
      <label>Role * <select name="role" required><?php foreach (ROLES as $r) echo '<option>'.e($r).'</option>'; ?></select></label>
      <label>Department <select name="department_id"><option value="">--</option>
        <?php foreach ($depts as $d) echo '<option value="'.(int)$d['id'].'">'.e($d['name']).'</option>'; ?></select></label>
      <label>Phone <input name="phone"></label>
      <button class="btn btn-primary">Create account</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
