<?php
/**
 * pharmacy.php - pending prescriptions, grouped per patient; pharmacist dispenses.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('pharmacy');

$canDispense = in_array(current_user()['role'], ['pharmacist','admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canDispense) {
    csrf_check();
    $pdo->prepare("UPDATE prescriptions SET status='Dispensed', dispensed_by=?, dispensed_at=NOW() WHERE id=? AND status='Pending'")
        ->execute([current_user()['id'], (int)post('id')]);
    audit('DISPENSE', 'prescription #' . (int)post('id'));
    flash('success', 'Drug marked as dispensed.');
    header('Location: pharmacy.php');
    exit;
}

$status = in_array($_GET['status'] ?? '', ['Pending','Dispensed'], true) ? $_GET['status'] : 'Pending';
$st = $pdo->prepare("SELECT r.*, p.mrn, p.allergies, CONCAT(p.first_name,' ',p.last_name) AS pname
                       FROM prescriptions r JOIN patients p ON p.id=r.patient_id
                      WHERE r.status=? ORDER BY r.prescribed_at DESC LIMIT 100");
$st->execute([$status]);
$rows = $st->fetchAll();

$pageTitle = 'Pharmacy';
$active = 'pharmacy';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs">
  <a class="tab <?= $status==='Pending'?'active':'' ?>" href="?status=Pending">Pending</a>
  <a class="tab <?= $status==='Dispensed'?'active':'' ?>" href="?status=Dispensed">Dispensed</a>
</div>
<section class="card">
  <table class="table">
    <thead><tr><th>Date</th><th>Patient</th><th>Drug</th><th>Dose</th><th>Freq.</th><th>Days</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(date('d M H:i', strtotime($r['prescribed_at']))) ?></td>
        <td><?= e($r['pname']) ?><br><small class="muted"><?= e($r['mrn']) ?></small>
          <?php if ($r['allergies']): ?><br><span class="badge danger">Allergy: <?= e($r['allergies']) ?></span><?php endif; ?></td>
        <td><strong><?= e($r['drug_name']) ?></strong></td>
        <td><?= e($r['dosage']) ?></td><td><?= e($r['frequency']) ?></td><td><?= (int)$r['duration_days'] ?></td>
        <td>
          <?php if ($status === 'Pending' && $canDispense): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-success">Dispense</button></form>
          <?php elseif ($r['dispensed_at']): echo e(date('d M H:i', strtotime($r['dispensed_at']))); endif; ?>
        </td>
      </tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="center muted">Nothing here.</td></tr>'; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
