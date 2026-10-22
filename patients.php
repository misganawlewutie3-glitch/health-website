<?php
/**
 * patients.php - searchable, paginated patient list.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('patients');

$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

// Build the WHERE clause only when a search term exists
$where  = '';
$params = [];
if ($q !== '') {
    $where  = "WHERE mrn LIKE :q OR first_name LIKE :q OR middle_name LIKE :q
                  OR last_name LIKE :q OR phone LIKE :q
                  OR CONCAT(first_name,' ',middle_name,' ',last_name) LIKE :q";
    $params = [':q' => "%$q%"];
}

$st = $pdo->prepare("SELECT COUNT(*) FROM patients $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// LIMIT/OFFSET are integers cast above, so they are safe to put in the SQL text
$st = $pdo->prepare("SELECT * FROM patients $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Patients';
$active = 'patients';
require __DIR__ . '/includes/header.php';
?>
<div class="toolbar">
  <form method="get" class="search-form">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, MRN or phone..." autofocus>
    <button class="btn btn-primary">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-light" href="patients.php">Clear</a><?php endif; ?>
  </form>
  <?php if (can('patient_add')): ?>
    <a class="btn btn-success" href="patient_form.php">+ Register new patient</a>
  <?php endif; ?>
</div>

<section class="card">
  <p class="muted small"><?= $total ?> patient(s) found</p>
  <div class="table-wrap">
  <table class="table table-hover">
    <thead>
      <tr><th>MRN</th><th>Full name</th><th>Sex</th><th>Age</th><th>Phone</th><th>Payment</th><th></th></tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="center muted">No patients found.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><strong><?= e($p['mrn']) ?></strong></td>
        <td><?= e("{$p['first_name']} {$p['middle_name']} {$p['last_name']}") ?></td>
        <td><?= e($p['gender']) ?></td>
        <td><?= age_from($p['date_of_birth']) ?></td>
        <td><?= e($p['phone']) ?></td>
        <td><span class="badge"><?= e($p['payment_type']) ?></span></td>
        <td class="right">
          <a class="btn btn-sm btn-primary" href="patient_view.php?id=<?= (int)$p['id'] ?>">Open file</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a class="<?= $i === $page ? 'current' : '' ?>"
         href="?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
