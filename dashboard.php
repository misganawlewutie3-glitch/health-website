<?php
/**
 * dashboard.php - summary cards, today's appointments, patients per department.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('dashboard');

// ---- Counters (each is one small SQL query) -----------------------
$stats = [
    'Total patients'      => $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
    'Registered today'    => $pdo->query('SELECT COUNT(*) FROM patients WHERE DATE(created_at)=CURDATE()')->fetchColumn(),
    'Appointments today'  => $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='Scheduled'")->fetchColumn(),
    'Currently admitted'  => $pdo->query("SELECT COUNT(*) FROM admissions WHERE status='Admitted'")->fetchColumn(),
    'Pending lab tests'   => $pdo->query("SELECT COUNT(*) FROM lab_requests WHERE status='Requested'")->fetchColumn(),
    'Pending prescriptions'=> $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE status='Pending'")->fetchColumn(),
];

// ---- Today's appointments ----------------------------------------
$appts = $pdo->query(
    "SELECT a.appointment_time, a.reason, p.mrn, CONCAT(p.first_name,' ',p.last_name) AS pname, d.name AS dept
       FROM appointments a
       JOIN patients p    ON p.id = a.patient_id
       JOIN departments d ON d.id = a.department_id
      WHERE a.appointment_date = CURDATE() AND a.status='Scheduled'
      ORDER BY a.appointment_time LIMIT 8"
)->fetchAll();

// ---- Visits per department (last 30 days) for the bar chart -------
$perDept = $pdo->query(
    "SELECT d.name, COUNT(v.id) AS total
       FROM departments d
       LEFT JOIN visits v ON v.department_id = d.id AND v.visit_date >= (NOW() - INTERVAL 30 DAY)
      WHERE d.is_active = 1
      GROUP BY d.id, d.name
     HAVING total > 0
      ORDER BY total DESC LIMIT 10"
)->fetchAll();
$max = max(array_column($perDept, 'total') ?: [1]);

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="cards">
  <?php foreach ($stats as $label => $value): ?>
    <div class="card stat">
      <div class="stat-num" data-count="<?= (int)$value ?>"><?= (int)$value ?></div>
      <div class="stat-label"><?= e($label) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <section class="card">
    <h3>Today's appointments</h3>
    <?php if (!$appts): ?>
      <p class="muted">No scheduled appointments for today.</p>
    <?php else: ?>
    <table class="table">
      <thead><tr><th>Time</th><th>Patient</th><th>Department</th></tr></thead>
      <tbody>
      <?php foreach ($appts as $a): ?>
        <tr>
          <td><?= e(substr($a['appointment_time'], 0, 5)) ?></td>
          <td><?= e($a['pname']) ?><br><small class="muted"><?= e($a['mrn']) ?></small></td>
          <td><?= e($a['dept']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3>Busiest departments (last 30 days)</h3>
    <?php if (!$perDept): ?>
      <p class="muted">No visit data yet.</p>
    <?php endif; ?>
    <?php foreach ($perDept as $d): ?>
      <div class="bar-row">
        <span class="bar-label"><?= e($d['name']) ?></span>
        <span class="bar"><span style="width:<?= round($d['total'] / $max * 100) ?>%"></span></span>
        <span class="bar-val"><?= (int)$d['total'] ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
