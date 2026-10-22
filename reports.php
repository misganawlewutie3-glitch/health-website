<?php
/**
 * reports.php - statistics for a date range + CSV export.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('reports');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$p = [':f' => $from, ':t' => $to];

$run = function (string $sql) use ($pdo, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); };

$byDept = $run("SELECT d.name, COUNT(v.id) AS visits, COUNT(DISTINCT v.patient_id) AS patients
                  FROM visits v JOIN departments d ON d.id=v.department_id
                 WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY d.id, d.name ORDER BY visits DESC");
$byDx   = $run("SELECT diagnosis, COUNT(*) AS n FROM visits
                 WHERE diagnosis IS NOT NULL AND DATE(visit_date) BETWEEN :f AND :t
                 GROUP BY diagnosis ORDER BY n DESC LIMIT 10");
$byGender = $run("SELECT p.gender, COUNT(*) AS n FROM visits v JOIN patients p ON p.id=v.patient_id
                   WHERE DATE(v.visit_date) BETWEEN :f AND :t GROUP BY p.gender");
$revenue = $run("SELECT method, SUM(amount) AS total FROM payments
                  WHERE DATE(paid_at) BETWEEN :f AND :t GROUP BY method");

// ---- CSV export -----------------------------------------------------
if (isset($_GET['export'])) {
    audit('REPORT_EXPORT', "$from to $to");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="uogsh_report_' . $from . '_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                          // BOM so Excel opens UTF-8 correctly
    fputcsv($out, ['Department', 'Visits', 'Unique patients']);
    foreach ($byDept as $r) fputcsv($out, [$r['name'], $r['visits'], $r['patients']]);
    exit;
}

$pageTitle = 'Reports';
$active = 'reports';
require __DIR__ . '/includes/header.php';
?>
<form method="get" class="toolbar filter-form">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn btn-primary">Generate</button>
  <a class="btn btn-light" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">Download CSV</a>
  <button type="button" class="btn btn-light" data-print>Print</button>
</form>

<div class="grid-2">
  <section class="card">
    <h3>Visits by department</h3>
    <table class="table"><thead><tr><th>Department</th><th>Visits</th><th>Patients</th></tr></thead><tbody>
    <?php foreach ($byDept as $r) echo '<tr><td>'.e($r['name']).'</td><td>'.(int)$r['visits'].'</td><td>'.(int)$r['patients'].'</td></tr>';
          if (!$byDept) echo '<tr><td colspan="3" class="muted">No data in this period.</td></tr>'; ?>
    </tbody></table>
  </section>
  <section class="card">
    <h3>Top 10 diagnoses</h3>
    <table class="table"><thead><tr><th>Diagnosis</th><th>Cases</th></tr></thead><tbody>
    <?php foreach ($byDx as $r) echo '<tr><td>'.e($r['diagnosis']).'</td><td>'.(int)$r['n'].'</td></tr>';
          if (!$byDx) echo '<tr><td colspan="2" class="muted">No data.</td></tr>'; ?>
    </tbody></table>
    <h3>Visits by gender</h3>
    <?php foreach ($byGender as $r) echo '<p>'.e($r['gender']).': <strong>'.(int)$r['n'].'</strong></p>'; ?>
    <h3>Revenue by payment method (ETB)</h3>
    <?php foreach ($revenue as $r) echo '<p>'.e($r['method']).': <strong>'.number_format((float)$r['total'],2).'</strong></p>';
          if (!$revenue) echo '<p class="muted">No payments.</p>'; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
