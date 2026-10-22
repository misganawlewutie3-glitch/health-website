<?php
/**
 * appointments.php - book, list and change status of appointments.
 */
require_once __DIR__ . '/includes/auth.php';
require_login('appointments');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'book') {
        $pid  = (int)post('patient_id');
        $dept = (int)post('department_id');
        $date = post('appointment_date');
        $time = post('appointment_time');
        $doc  = (int)post('doctor_id') ?: null;

        if (!$pid)  $errors[] = 'Choose a patient.';
        if (!$dept) $errors[] = 'Choose a department.';
        if (!DateTime::createFromFormat('Y-m-d', $date) || $date < date('Y-m-d')) $errors[] = 'Date cannot be in the past.';
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) $errors[] = 'Enter a valid time.';

        // Prevent double booking of the same doctor at the same time
        if (!$errors && $doc) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status='Scheduled'");
            $c->execute([$doc, $date, $time . ':00']);
            if ($c->fetchColumn() > 0) $errors[] = 'This doctor already has an appointment at that time.';
        }
        if (!$errors) {
            $pdo->prepare('INSERT INTO appointments (patient_id, department_id, doctor_id, appointment_date, appointment_time, reason, created_by)
                           VALUES (?,?,?,?,?,?,?)')
                ->execute([$pid, $dept, $doc, $date, $time, post('reason') ?: null, current_user()['id']]);
            audit('APPT_BOOK', "patient #$pid on $date $time");
            flash('success', 'Appointment booked.');
            header('Location: appointments.php');
            exit;
        }
    } elseif (post('action') === 'status') {
        $s = post('status');
        if (in_array($s, ['Scheduled','Completed','Cancelled','No-Show'], true)) {
            $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$s, (int)post('id')]);
            audit('APPT_STATUS', "#" . (int)post('id') . " -> $s");
            flash('success', 'Status updated.');
        }
        header('Location: appointments.php');
        exit;
    }
}

$date = $_GET['date'] ?? date('Y-m-d');
$st = $pdo->prepare("SELECT a.*, p.mrn, CONCAT(p.first_name,' ',p.last_name) AS pname, d.name AS dept, u.full_name AS doctor
                       FROM appointments a
                       JOIN patients p ON p.id=a.patient_id
                       JOIN departments d ON d.id=a.department_id
                       LEFT JOIN users u ON u.id=a.doctor_id
                      WHERE a.appointment_date = ? ORDER BY a.appointment_time");
$st->execute([$date]);
$rows = $st->fetchAll();

$depts   = $pdo->query('SELECT id, name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$doctors = $pdo->query("SELECT id, full_name, department_id FROM users WHERE role='doctor' AND is_active=1 ORDER BY full_name")->fetchAll();

// If we came from a patient file (?patient_id=) pre-load that patient
$preId = (int)($_GET['patient_id'] ?? 0);
$pre = null;
if ($preId) { $s = $pdo->prepare('SELECT id, mrn, first_name, last_name FROM patients WHERE id=?'); $s->execute([$preId]); $pre = $s->fetch(); }

$pageTitle = 'Appointments';
$active = 'appointments';
require __DIR__ . '/includes/header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

<div class="grid-2">
  <section class="card">
    <h3>Book appointment</h3>
    <form method="post" class="form-stack" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="book">

      <label>Patient (search by name / MRN) *
        <input type="hidden" name="patient_id" id="patient_id" value="<?= $pre ? (int)$pre['id'] : '' ?>">
        <input type="text" id="patientSearch" autocomplete="off" placeholder="Start typing..."
               value="<?= $pre ? e($pre['first_name'].' '.$pre['last_name'].' ('.$pre['mrn'].')') : '' ?>">
        <div class="suggest" id="suggest"></div>
      </label>

      <label>Department *
        <select name="department_id" id="apptDept" required>
          <option value="">-- select --</option>
          <?php foreach ($depts as $d) echo '<option value="'.(int)$d['id'].'">'.e($d['name']).'</option>'; ?>
        </select>
      </label>
      <label>Doctor (optional)
        <select name="doctor_id" id="apptDoctor">
          <option value="">-- any available --</option>
          <?php foreach ($doctors as $d) echo '<option value="'.(int)$d['id'].'" data-dept="'.(int)$d['department_id'].'">'.e($d['full_name']).'</option>'; ?>
        </select>
      </label>
      <label>Date * <input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></label>
      <label>Time * <input type="time" name="appointment_time" value="09:00" required></label>
      <label>Reason <input name="reason"></label>
      <button class="btn btn-primary">Book</button>
    </form>
  </section>

  <section class="card">
    <h3>Schedule for
      <form method="get" class="inline-form"><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></form>
    </h3>
    <table class="table">
      <thead><tr><th>Time</th><th>Patient</th><th>Dept / Doctor</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr>
          <td><?= e(substr($a['appointment_time'], 0, 5)) ?></td>
          <td><a href="patient_view.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a><br><small class="muted"><?= e($a['mrn']) ?></small></td>
          <td><?= e($a['dept']) ?><br><small class="muted"><?= e($a['doctor'] ?? 'Unassigned') ?></small></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <select name="status" onchange="this.form.submit()">
                <?php foreach (['Scheduled','Completed','Cancelled','No-Show'] as $s) echo '<option'.($a['status']===$s?' selected':'').'>'.$s.'</option>'; ?>
              </select>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$rows) echo '<tr><td colspan="4" class="muted center">No appointments on this date.</td></tr>'; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
