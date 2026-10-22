<?php
/**
 * lab.php - laboratory worklist: technicians enter results,
 * doctors see them. Pending tests first, urgent on top.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('lab');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(current_user()['role'], ['lab','admin'], true)) {
    csrf_check();
    $result = post('result');
    if ($result !== '') {
        $pdo->prepare("UPDATE lab_requests SET result=?, status='Completed', processed_by=?, completed_at=NOW()
                        WHERE id=? AND status='Requested'")
            ->execute([$result, current_user()['id'], (int)post('id')]);
        audit('LAB_RESULT', 'request #' . (int)post('id'));
        flash('success', 'Result saved.');
    } else {
        flash('danger', 'Result cannot be empty.');
    }
    header('Location: lab.php?status=' . urlencode($_GET['status'] ?? 'Requested'));
    exit;
}

$status = in_array($_GET['status'] ?? '', ['Requested','Completed'], true) ? $_GET['status'] : 'Requested';
$st = $pdo->prepare("SELECT l.*, p.mrn, p.gender, p.date_of_birth, CONCAT(p.first_name,' ',p.last_name) AS pname
                       FROM lab_requests l JOIN patients p ON p.id=l.patient_id
                      WHERE l.status=? ORDER BY l.priority='Urgent' DESC, l.requested_at DESC LIMIT 100");
$st->execute([$status]);
$rows = $st->fetchAll();
$canEnter = in_array(current_user()['role'], ['lab','admin'], true);

$pageTitle = 'Laboratory';
$active = 'lab';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs">
  <a class="tab <?= $status==='Requested'?'active':'' ?>" href="?status=Requested">Pending</a>
  <a class="tab <?= $status==='Completed'?'active':'' ?>" href="?status=Completed">Completed</a>
</div>
<section class="card">
  <table class="table">
    <thead><tr><th>Requested</th><th>Patient</th><th>Test</th><th>Priority</th><th><?= $status==='Requested' ? 'Enter result' : 'Result' ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(date('d M H:i', strtotime($r['requested_at']))) ?></td>
        <td><?= e($r['pname']) ?><br><small class="muted"><?= e($r['mrn']) ?> &middot; <?= e($r['gender']) ?>, <?= age_from($r['date_of_birth']) ?>y</small></td>
        <td><?= e($r['test_name']) ?></td>
        <td><span class="badge <?= $r['priority']==='Urgent'?'danger':'' ?>"><?= e($r['priority']) ?></span></td>
        <td>
          <?php if ($status === 'Requested' && $canEnter): ?>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input name="result" placeholder="Result / value" required>
              <button class="btn btn-sm btn-success">Save</button>
            </form>
          <?php else: echo nl2br(e($r['result'] ?? 'Awaiting result')); endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="center muted">Nothing here.</td></tr>'; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
