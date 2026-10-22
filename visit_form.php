<?php
/**
 * visit_form.php - new consultation: vital signs, diagnosis, plus
 * dynamic rows for lab requests and prescriptions. Saved together
 * in ONE database transaction (all or nothing).
 */
require_once __DIR__ . '/includes/auth.php';
require_login('visits');

$pid = (int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$st->execute([$pid]);
$p = $st->fetch();
if (!$p) { flash('danger', 'Select a patient first.'); header('Location: patients.php'); exit; }

$depts = $pdo->query('SELECT id, name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$errors = [];

/** Convert '' to NULL for optional numeric inputs. */
$num = fn($k) => ($_POST[$k] ?? '') === '' ? null : (float)$_POST[$k];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $complaint = post('chief_complaint');
    $deptId    = (int)post('department_id');
    if ($complaint === '') $errors[] = 'Chief complaint is required.';
    if ($deptId <= 0)      $errors[] = 'Select a department.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('INSERT INTO visits (patient_id, department_id, doctor_id, visit_type, chief_complaint, history,
                    temperature_c, bp_systolic, bp_diastolic, pulse_rate, resp_rate, weight_kg, height_cm, spo2,
                    examination, diagnosis, treatment_plan, disposition)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $pid, $deptId, current_user()['id'],
                    in_array(post('visit_type'), ['OPD','Emergency','Inpatient','Follow-up'], true) ? post('visit_type') : 'OPD',
                    $complaint, post('history') ?: null,
                    $num('temperature_c'), $num('bp_systolic'), $num('bp_diastolic'), $num('pulse_rate'),
                    $num('resp_rate'), $num('weight_kg'), $num('height_cm'), $num('spo2'),
                    post('examination') ?: null, post('diagnosis') ?: null, post('treatment_plan') ?: null,
                    in_array(post('disposition'), ['Home','Admit','Refer','Follow-up'], true) ? post('disposition') : 'Home',
                ]);
            $visitId = (int)$pdo->lastInsertId();

            // ---- Lab tests (arrays: lab_test[], lab_priority[]) -----
            $tests = $_POST['lab_test'] ?? [];
            $prios = $_POST['lab_priority'] ?? [];
            foreach ($tests as $i => $t) {
                $t = trim($t);
                if ($t === '') continue;
                $pdo->prepare('INSERT INTO lab_requests (visit_id, patient_id, test_name, priority, requested_by) VALUES (?,?,?,?,?)')
                    ->execute([$visitId, $pid, $t, ($prios[$i] ?? '') === 'Urgent' ? 'Urgent' : 'Routine', current_user()['id']]);
            }

            // ---- Prescriptions (drug[], dosage[], freq[], days[]) ---
            $drugs = $_POST['drug'] ?? []; $dose = $_POST['dosage'] ?? [];
            $freq  = $_POST['freq'] ?? []; $days = $_POST['days'] ?? [];
            foreach ($drugs as $i => $d) {
                $d = trim($d);
                if ($d === '') continue;
                $pdo->prepare('INSERT INTO prescriptions (visit_id, patient_id, drug_name, dosage, frequency, duration_days, prescribed_by)
                               VALUES (?,?,?,?,?,?,?)')
                    ->execute([$visitId, $pid, $d, trim($dose[$i] ?? '') ?: '-', trim($freq[$i] ?? '') ?: '-',
                               max(1, (int)($days[$i] ?? 5)), current_user()['id']]);
            }

            // ---- Automatic consultation invoice (if a fee was entered) ----
            $fee = (float)post('fee');
            if ($fee > 0) {
                $pdo->prepare('INSERT INTO invoices (patient_id, visit_id, description, amount, created_by) VALUES (?,?,?,?,?)')
                    ->execute([$pid, $visitId, 'Consultation fee', $fee, current_user()['id']]);
            }

            $pdo->commit();
            audit('VISIT_CREATE', "visit #$visitId for {$p['mrn']}");
            flash('success', 'Consultation saved.');
            header("Location: patient_view.php?id=$pid");
            exit;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not save the consultation. Nothing was stored; please try again.';
        }
    }
}

$pageTitle = 'New consultation';
$active = 'visits';
require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <strong><?= e("{$p['first_name']} {$p['middle_name']} {$p['last_name']}") ?></strong>
  <span class="muted"> - MRN <?= e($p['mrn']) ?> - <?= age_from($p['date_of_birth']) ?> yrs - <?= e($p['gender']) ?></span>
  <?php if ($p['allergies']): ?><span class="badge danger">Allergy: <?= e($p['allergies']) ?></span><?php endif; ?>
</section>

<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $er) echo e($er) . '<br>'; ?></div><?php endif; ?>

<form method="post" class="card form-grid" data-validate novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="patient_id" value="<?= $pid ?>">

  <h3 class="span-all">1. Visit details</h3>
  <label>Department *
    <select name="department_id" required>
      <option value="">-- select --</option>
      <?php foreach ($depts as $d): ?>
        <option value="<?= (int)$d['id'] ?>" <?= (int)($_POST['department_id'] ?? current_user()['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Visit type
    <select name="visit_type"><option>OPD</option><option>Emergency</option><option>Inpatient</option><option>Follow-up</option></select>
  </label>
  <label class="span-all">Chief complaint * <input name="chief_complaint" required value="<?= e($_POST['chief_complaint'] ?? '') ?>"></label>
  <label class="span-all">History of present illness <textarea name="history" rows="3"><?= e($_POST['history'] ?? '') ?></textarea></label>

  <h3 class="span-all">2. Vital signs</h3>
  <label>Temperature (&deg;C) <input type="number" step="0.1" min="30" max="45" name="temperature_c" id="temp"></label>
  <label>BP systolic <input type="number" min="40" max="300" name="bp_systolic"></label>
  <label>BP diastolic <input type="number" min="20" max="200" name="bp_diastolic"></label>
  <label>Pulse (bpm) <input type="number" min="20" max="250" name="pulse_rate"></label>
  <label>Resp. rate <input type="number" min="5" max="80" name="resp_rate"></label>
  <label>SpO&#8322; (%) <input type="number" min="50" max="100" name="spo2"></label>
  <label>Weight (kg) <input type="number" step="0.1" name="weight_kg" id="wt"></label>
  <label>Height (cm) <input type="number" step="0.1" name="height_cm" id="ht"></label>
  <label>BMI (auto) <input id="bmi" readonly></label>

  <h3 class="span-all">3. Examination &amp; diagnosis</h3>
  <label class="span-all">Physical examination <textarea name="examination" rows="3"></textarea></label>
  <label>Diagnosis <input name="diagnosis"></label>
  <label>Disposition
    <select name="disposition"><option>Home</option><option>Admit</option><option>Refer</option><option>Follow-up</option></select>
  </label>
  <label class="span-all">Treatment plan <textarea name="treatment_plan" rows="2"></textarea></label>

  <h3 class="span-all">4. Laboratory requests</h3>
  <div class="span-all dyn" id="labRows">
    <div class="dyn-row">
      <input name="lab_test[]" placeholder="Test name e.g. CBC, Malaria film, Urinalysis">
      <select name="lab_priority[]"><option>Routine</option><option>Urgent</option></select>
    </div>
  </div>
  <div class="span-all"><button type="button" class="btn btn-sm btn-light" data-add-row="labRows">+ Add test</button></div>

  <h3 class="span-all">5. Prescription</h3>
  <div class="span-all dyn" id="rxRows">
    <div class="dyn-row">
      <input name="drug[]" placeholder="Drug name">
      <input name="dosage[]" placeholder="Dose e.g. 500mg">
      <input name="freq[]" placeholder="e.g. TID">
      <input name="days[]" type="number" min="1" value="5" title="Days">
    </div>
  </div>
  <div class="span-all"><button type="button" class="btn btn-sm btn-light" data-add-row="rxRows">+ Add drug</button></div>

  <h3 class="span-all">6. Billing</h3>
  <label>Consultation fee (ETB) <input type="number" step="0.01" min="0" name="fee" value="0"></label>

  <div class="span-all actions">
    <button class="btn btn-primary">Save consultation</button>
    <a class="btn btn-light" href="patient_view.php?id=<?= $pid ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
