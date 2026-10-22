<?php
/**
 * visits.php - list of recent consultations, filterable by department and date.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('visits');

$dept = (int)($_GET['department_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

$sql = "SELECT v.id, v.visit_date, v.visit_type, v.chief_complaint, v.diagnosis, v.patient_id,
               p.mrn, CONCAT(p.first_name,' ',p.last_name) AS pname, d.name AS dept, u.full_name AS doctor
          FROM visits v
          JOIN patients p ON p.id=v.patient_id
          JOIN departments d ON d.id=v.department_id
          LEFT JOIN users u ON u.id=v.doctor_id
         WHERE DATE(v.visit_date) BETWEEN :f AND :t";
$params = [':f' => $from, ':t' => $to];
if ($dept) { $sql .= ' AND v.department_id = :d'; $params[':d'] = $dept; }
$sql .= ' ORDER BY v.visit_date DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$depts = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$pageTitle = 'Consultations';
$active = 'visits';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="toolbar filter-form">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Department
    <select name="department_id"><option value="0">All</option>
      <?php foreach ($depts as $d) echo '<option value="'.(int)$d['id'].'"'.($dept===(int)$d['id']?' selected':'').'>'.e($d['name']).'</option>'; ?>
    </select>
  </label>
  <button class="btn btn-primary">Filter</button>
  <a class="btn btn-light" href="patients.php">Find a patient to start a consultation</a>
</form>

<section class="card">
  <div class="table-wrap"><table class="table table-hover">
    <thead><tr><th>Date</th><th>Patient</th><th>Department</th><th>Type</th><th>Complaint</th><th>Diagnosis</th><th>Doctor</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(date('d M H:i', strtotime($r['visit_date']))) ?></td>
        <td><a href="patient_view.php?id=<?= (int)$r['patient_id'] ?>"><?= e($r['pname']) ?></a><br><small class="muted"><?= e($r['mrn']) ?></small></td>
        <td><?= e($r['dept']) ?></td><td><?= e($r['visit_type']) ?></td>
        <td><?= e($r['chief_complaint']) ?></td><td><?= e($r['diagnosis'] ?? '-') ?></td><td><?= e($r['doctor'] ?? '-') ?></td>
      </tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="center muted">No consultations in this period.</td></tr>'; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
