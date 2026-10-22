<?php
/**
 * billing.php - invoices and payments. Cashier records a payment,
 * invoice status becomes Paid (all inside one transaction).
 */
require_once __DIR__ . '/includes/auth.php';
require_login('billing');

$isCashier = in_array(current_user()['role'], ['cashier','admin'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'invoice') {                       // create manual invoice
        $pid = (int)post('patient_id'); $amt = (float)post('amount');
        if ($pid && $amt > 0 && post('description') !== '') {
            $pdo->prepare('INSERT INTO invoices (patient_id, description, amount, created_by) VALUES (?,?,?,?)')
                ->execute([$pid, post('description'), $amt, current_user()['id']]);
            audit('INVOICE_CREATE', "patient #$pid amount $amt");
            flash('success', 'Invoice created.');
        } else {
            flash('danger', 'Choose a patient, enter a description and a positive amount.');
        }
    } elseif ($action === 'pay' && $isCashier) {
        $method = in_array(post('method'), ['Cash','Mobile Money','Bank','Insurance'], true) ? post('method') : 'Cash';
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("SELECT * FROM invoices WHERE id=? AND status='Unpaid' FOR UPDATE");
            $s->execute([(int)post('id')]);
            if ($inv = $s->fetch()) {
                $pdo->prepare('INSERT INTO payments (invoice_id, amount, method, received_by) VALUES (?,?,?,?)')
                    ->execute([$inv['id'], $inv['amount'], $method, current_user()['id']]);
                $pdo->prepare("UPDATE invoices SET status='Paid' WHERE id=?")->execute([$inv['id']]);
                audit('PAYMENT', "invoice #{$inv['id']} {$inv['amount']} $method");
                flash('success', 'Payment received.');
            }
            $pdo->commit();
        } catch (Throwable $ex) { $pdo->rollBack(); error_log($ex->getMessage()); flash('danger', 'Payment failed.'); }
    } elseif ($action === 'waive' && $isCashier) {
        $pdo->prepare("UPDATE invoices SET status='Waived' WHERE id=? AND status='Unpaid'")->execute([(int)post('id')]);
        audit('INVOICE_WAIVE', '#' . (int)post('id'));
        flash('success', 'Invoice waived.');
    }
    header('Location: billing.php');
    exit;
}

$status = in_array($_GET['status'] ?? '', ['Unpaid','Paid','Waived'], true) ? $_GET['status'] : 'Unpaid';
$st = $pdo->prepare("SELECT i.*, p.mrn, CONCAT(p.first_name,' ',p.last_name) AS pname
                       FROM invoices i JOIN patients p ON p.id=i.patient_id
                      WHERE i.status=? ORDER BY i.created_at DESC LIMIT 100");
$st->execute([$status]);
$rows = $st->fetchAll();
$todayTotal = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at)=CURDATE()')->fetchColumn();
$unpaidTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status='Unpaid'")->fetchColumn();

$pageTitle = 'Billing';
$active = 'billing';
require __DIR__ . '/includes/header.php';
?>
<div class="cards">
  <div class="card stat"><div class="stat-num"><?= number_format($todayTotal, 2) ?></div><div class="stat-label">Collected today (ETB)</div></div>
  <div class="card stat"><div class="stat-num"><?= number_format($unpaidTotal, 2) ?></div><div class="stat-label">Outstanding (ETB)</div></div>
</div>

<div class="grid-2">
  <section class="card">
    <div class="tabs">
      <?php foreach (['Unpaid','Paid','Waived'] as $s): ?>
        <a class="tab <?= $status===$s?'active':'' ?>" href="?status=<?= $s ?>"><?= $s ?></a>
      <?php endforeach; ?>
    </div>
    <table class="table">
      <thead><tr><th>Date</th><th>Patient</th><th>Description</th><th>ETB</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e(date('d M', strtotime($r['created_at']))) ?></td>
          <td><?= e($r['pname']) ?><br><small class="muted"><?= e($r['mrn']) ?></small></td>
          <td><?= e($r['description']) ?></td>
          <td><?= number_format((float)$r['amount'], 2) ?></td>
          <td>
            <?php if ($status === 'Unpaid' && $isCashier): ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <select name="method"><option>Cash</option><option>Mobile Money</option><option>Bank</option><option>Insurance</option></select>
                <button class="btn btn-sm btn-success" name="action" value="pay">Pay</button>
                <button class="btn btn-sm btn-light" name="action" value="waive" data-confirm="Waive this invoice?">Waive</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="center muted">No invoices.</td></tr>'; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h3>New invoice</h3>
    <form method="post" class="form-stack" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="invoice">
      <label>Patient *
        <input type="hidden" name="patient_id" id="patient_id">
        <input type="text" id="patientSearch" autocomplete="off" placeholder="Type name or MRN...">
        <div class="suggest" id="suggest"></div>
      </label>
      <label>Description * <input name="description" placeholder="e.g. Laboratory - CBC" required></label>
      <label>Amount (ETB) * <input type="number" step="0.01" min="0.01" name="amount" required></label>
      <button class="btn btn-primary">Create invoice</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
