<?php
/**
 * departments.php - admin adds / renames / activates / deactivates departments.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('departments');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'add') {
            $code = strtoupper(post('code')); $name = post('name');
            if ($code === '' || $name === '') throw new RuntimeException('Code and name are required.');
            $pdo->prepare('INSERT INTO departments (code, name, description) VALUES (?,?,?)')
                ->execute([$code, $name, post('description') ?: null]);
            audit('DEPT_ADD', $name);
            flash('success', 'Department added.');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE departments SET is_active = 1 - is_active WHERE id=?')->execute([(int)post('id')]);
            audit('DEPT_TOGGLE', '#' . (int)post('id'));
            flash('success', 'Department status changed.');
        } elseif ($action === 'edit') {
            $pdo->prepare('UPDATE departments SET name=?, description=? WHERE id=?')
                ->execute([post('name'), post('description') ?: null, (int)post('id')]);
            audit('DEPT_EDIT', '#' . (int)post('id'));
            flash('success', 'Department updated.');
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That department code already exists.' : 'Database error.');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    header('Location: departments.php');
    exit;
}

$rows = $pdo->query("SELECT d.*,
        (SELECT COUNT(*) FROM users u WHERE u.department_id=d.id) AS staff,
        (SELECT COUNT(*) FROM visits v WHERE v.department_id=d.id) AS visits
        FROM departments d ORDER BY d.name")->fetchAll();

$pageTitle = 'Departments';
$active = 'departments';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2">
  <section class="card">
    <h3><?= count($rows) ?> departments</h3>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Code</th><th>Name / description</th><th>Staff</th><th>Visits</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $d): ?>
        <tr class="<?= $d['is_active'] ? '' : 'inactive' ?>">
          <td><strong><?= e($d['code']) ?></strong></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input name="name" value="<?= e($d['name']) ?>" required>
              <input name="description" value="<?= e($d['description']) ?>" placeholder="Description">
              <button class="btn btn-sm btn-light">Save</button>
            </form>
          </td>
          <td><?= (int)$d['staff'] ?></td><td><?= (int)$d['visits'] ?></td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn btn-sm <?= $d['is_active'] ? 'btn-danger' : 'btn-success' ?>"><?= $d['is_active'] ? 'Disable' : 'Enable' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card">
    <h3>Add department</h3>
    <form method="post" class="form-stack" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Code * <input name="code" maxlength="10" required placeholder="e.g. CAR"></label>
      <label>Name * <input name="name" required></label>
      <label>Description <input name="description"></label>
      <button class="btn btn-primary">Add</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
