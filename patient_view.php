<?php
/**
 * patient_view.php - the full "patient file": demographics, alerts,
 * visit history, labs, prescriptions, admissions and invoices.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('patients');

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { flash('danger', 'Patient not found.'); header('Location: patients.php'); exit; }
audit('PATIENT_VIEW', $p['mrn']);

// ---- Admit / discharge actions (doctor, nurse, admin) -------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('visits')) {
    csrf_check();
    $action = post('action');
    if ($action === 'admit') {
        $pdo->prepare('INSERT INTO admissions (patient_id, department_id, ward, bed_no, admitted_at, diagnosis, admitted_by)
                       VALUES (?,?,?,?,NOW(),?,?)')
            ->execute([$id, (int)post('department_id'), post('ward'), post('bed_no'), post('diagnosis'), current_user()['id']]);
        audit('ADMIT', $p['mrn']);
        flash('success', 'Patient admitted.');
    } elseif ($action === 'discharge') {
        $pdo->prepare("UPDATE admissions SET status=?, discharged_at=NOW() WHERE id=? AND patient_id=? AND status='Admitted'")
            ->execute([in_array(post('outcome'), ['Discharged','Referred','Deceased'], true) ? post('outcome') : 'Discharged', (int)post('adm_id'), $id]);
        audit('DISCHARGE', $p['mrn']);
        flash('success', 'Patient status updated.');
    }
    header("Location: patient_view.php?id=$id");
    exit;
}

$q = fn(string $sql) => (function () use ($pdo, $sql, $id) {
    $s = $pdo->prepare($sql); $s->execute([$id]); return $s->fetchAll();
})();

$visits = $q("SELECT v.*, d.name AS dept, u.full_name AS doctor FROM visits v
              JOIN departments d ON d.id=v.department_id LEFT JOIN users u ON u.id=v.doctor_id
              WHERE v.patient_id=? ORDER BY v.visit_date DESC");
$labs   = $q("SELECT * FROM lab_requests WHERE patient_id=? ORDER BY requested_at DESC LIMIT 20");
$rx     = $q("SELECT * FROM prescriptions WHERE patient_id=? ORDER BY prescribed_at DESC LIMIT 20");
$adm    = $q("SELECT a.*, d.name AS dept FROM admissions a JOIN departments d ON d.id=a.department_id
              WHERE a.patient_id=? ORDER BY a.admitted_at DESC");
$inv    = $q("SELECT * FROM invoices WHERE patient_id=? ORDER BY created_at DESC LIMIT 20");
$depts  = $pdo->query('SELECT id, name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$current = array_filter($adm, fn($a) => $a['status'] === 'Admitted');

$pageTitle = 'Patient file';
$active = 'patients';
require __DIR__ . '/includes/header.php';
?>
<section class="card profile">
  <div class="avatar"><?= e(strtoupper($p['first_name'][0] . $p['middle_name'][0])) ?></div>
  <div class="profile-main">
    <h2><?= e("{$p['first_name']} {$p['middle_name']} {$p['last_name']}") ?></h2>
    <p class="muted">MRN <strong><?= e($p['mrn']) ?></strong> &middot; <?= e($p['gender']) ?> &middot;
       <?= age_from($p['date_of_birth']) ?> years (<?= e($p['date_of_birth']) ?>) &middot; Phone: <?= e($p['phone'] ?: '-') ?></p>
    <p class="muted">Address: <?= e(implode(', ', array_filter([$p['region'], $p['zone'], $p['woreda'], $p['kebele']]))) ?: '-' ?></p>
    <?php if ($p['allergies']): ?><span class="badge danger">Allergy: <?= e($p['allergies']) ?></span><?php endif; ?>
    <?php if ($p['chronic_illness']): ?><span class="badge warning">Chronic: <?= e($p['chronic_illness']) ?></span><?php endif; ?>
    <span class="badge">Blood: <?= e($p['blood_group']) ?></span>
    <span class="badge"><?= e($p['payment_type']) ?></span>
    <?php if ($current): ?><span class="badge success">Currently admitted</span><?php endif; ?>
  </div>
  <div class="profile-actions">
    <?php if (can('visits')): ?><a class="btn btn-primary" href="visit_form.php?patient_id=<?= $id ?>">+ New consultation</a><?php endif; ?>
    <?php if (can('appointments')): ?><a class="btn btn-light" href="appointments.php?patient_id=<?= $id ?>">Book appointment</a><?php endif; ?>
    <?php if (can('patient_add')): ?><a class="btn btn-light" href="patient_form.php?id=<?= $id ?>">Edit</a><?php endif; ?>
    <button class="btn btn-light" data-print>Print</button>
  </div>
</section>

<div class="tabs" data-tabs>
  <button class="tab active" data-tab="t-visits">Visits (<?= count($visits) ?>)</button>
  <button class="tab" data-tab="t-lab">Lab (<?= count($labs) ?>)</button>
  <button class="tab" data-tab="t-rx">Prescriptions (<?= count($rx) ?>)</button>
  <button class="tab" data-tab="t-adm">Admissions (<?= count($adm) ?>)</button>
  <button class="tab" data-tab="t-bill">Billing (<?= count($inv) ?>)</button>
</div>

<section class="card tab-panel active" id="t-visits">
  <?php if (!$visits) echo '<p class="muted">No visits recorded yet.</p>'; ?>
  <?php foreach ($visits as $v): ?>
    <article class="record">
      <header><strong><?= e(date('d M Y H:i', strtotime($v['visit_date']))) ?></strong> -
        <?= e($v['dept']) ?> <span class="badge"><?= e($v['visit_type']) ?></span>
        <span class="muted">Dr. <?= e($v['doctor'] ?? '-') ?></span></header>
      <p><b>Complaint:</b> <?= e($v['chief_complaint']) ?></p>
      <p class="muted small">
        Temp <?= e($v['temperature_c'] ?? '-') ?>&deg;C &middot;
        BP <?= e(($v['bp_systolic'] ?? '-') . '/' . ($v['bp_diastolic'] ?? '-')) ?> &middot;
        Pulse <?= e($v['pulse_rate'] ?? '-') ?> &middot; SpO<sub>2</sub> <?= e($v['spo2'] ?? '-') ?>% &middot;
        Wt <?= e($v['weight_kg'] ?? '-') ?> kg
      </p>
      <?php if ($v['examination']): ?><p><b>Exam:</b> <?= nl2br(e($v['examination'])) ?></p><?php endif; ?>
      <p><b>Diagnosis:</b> <?= e($v['diagnosis'] ?: '-') ?></p>
      <?php if ($v['treatment_plan']): ?><p><b>Plan:</b> <?= nl2br(e($v['treatment_plan'])) ?></p><?php endif; ?>
      <p><b>Disposition:</b> <?= e($v['disposition']) ?></p>
    </article>
  <?php endforeach; ?>
</section>

<section class="card tab-panel" id="t-lab">
  <table class="table"><thead><tr><th>Date</th><th>Test</th><th>Priority</th><th>Status</th><th>Result</th></tr></thead><tbody>
  <?php foreach ($labs as $l): ?>
    <tr><td><?= e(date('d M Y', strtotime($l['requested_at']))) ?></td><td><?= e($l['test_name']) ?></td>
        <td><?= e($l['priority']) ?></td><td><span class="badge <?= $l['status']==='Completed'?'success':'warning' ?>"><?= e($l['status']) ?></span></td>
        <td><?= nl2br(e($l['result'] ?? '-')) ?></td></tr>
  <?php endforeach; if (!$labs) echo '<tr><td colspan="5" class="muted">No lab requests.</td></tr>'; ?>
  </tbody></table>
</section>

<section class="card tab-panel" id="t-rx">
  <table class="table"><thead><tr><th>Date</th><th>Drug</th><th>Dose</th><th>Frequency</th><th>Days</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rx as $r): ?>
    <tr><td><?= e(date('d M Y', strtotime($r['prescribed_at']))) ?></td><td><?= e($r['drug_name']) ?></td>
        <td><?= e($r['dosage']) ?></td><td><?= e($r['frequency']) ?></td><td><?= (int)$r['duration_days'] ?></td>
        <td><span class="badge <?= $r['status']==='Dispensed'?'success':'warning' ?>"><?= e($r['status']) ?></span></td></tr>
  <?php endforeach; if (!$rx) echo '<tr><td colspan="6" class="muted">No prescriptions.</td></tr>'; ?>
  </tbody></table>
</section>

<section class="card tab-panel" id="t-adm">
  <?php foreach ($adm as $a): ?>
    <article class="record">
      <header><strong><?= e($a['dept']) ?></strong> - Ward <?= e($a['ward'] ?: '-') ?>, Bed <?= e($a['bed_no'] ?: '-') ?>
        <span class="badge <?= $a['status']==='Admitted'?'warning':'' ?>"><?= e($a['status']) ?></span></header>
      <p>Admitted: <?= e($a['admitted_at']) ?> <?= $a['discharged_at'] ? ' | Left: ' . e($a['discharged_at']) : '' ?></p>
      <p>Diagnosis: <?= e($a['diagnosis'] ?: '-') ?></p>
      <?php if ($a['status'] === 'Admitted' && can('visits')): ?>
        <form method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="discharge"><input type="hidden" name="adm_id" value="<?= (int)$a['id'] ?>">
          <select name="outcome"><option>Discharged</option><option>Referred</option><option>Deceased</option></select>
          <button class="btn btn-sm btn-danger" data-confirm="Update the status of this admission?">Close admission</button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; if (!$adm) echo '<p class="muted">No admissions.</p>'; ?>

  <?php if (can('visits') && !$current): ?>
  <h4>Admit this patient</h4>
  <form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="admit">
    <label>Department <select name="department_id" required><?php foreach ($depts as $d) echo '<option value="'.(int)$d['id'].'">'.e($d['name']).'</option>'; ?></select></label>
    <label>Ward <input name="ward"></label>
    <label>Bed no. <input name="bed_no"></label>
    <label>Admitting diagnosis <input name="diagnosis"></label>
    <div class="span-all"><button class="btn btn-primary">Admit patient</button></div>
  </form>
  <?php endif; ?>
</section>

<section class="card tab-panel" id="t-bill">
  <table class="table"><thead><tr><th>Date</th><th>Description</th><th>Amount (ETB)</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($inv as $i): ?>
    <tr><td><?= e(date('d M Y', strtotime($i['created_at']))) ?></td><td><?= e($i['description']) ?></td>
        <td><?= number_format((float)$i['amount'], 2) ?></td>
        <td><span class="badge <?= $i['status']==='Paid'?'success':'warning' ?>"><?= e($i['status']) ?></span></td></tr>
  <?php endforeach; if (!$inv) echo '<tr><td colspan="4" class="muted">No invoices.</td></tr>'; ?>
  </tbody></table>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
