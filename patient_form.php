<?php
/**
 * patient_form.php - one form used for BOTH registering a new patient
 * (no ?id) and editing an existing patient (?id=5).
 */
require_once __DIR__ . '/includes/auth.php';
require_login('patient_add');

$id      = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$p       = [];

if ($editing) {
    $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { flash('danger', 'Patient not found.'); header('Location: patients.php'); exit; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Collect + trim every field
    $f = [];
    foreach (['first_name','middle_name','last_name','gender','date_of_birth','phone','region','zone',
              'woreda','kebele','marital_status','occupation','blood_group','allergies',
              'chronic_illness','emergency_name','emergency_phone','payment_type'] as $k) {
        $f[$k] = post($k);
    }

    // ---- Server-side validation (never trust the browser) ----------
    if ($f['first_name'] === '' || $f['middle_name'] === '' || $f['last_name'] === '')
        $errors[] = 'First, middle and last name are required.';
    if (!in_array($f['gender'], ['Male','Female'], true))
        $errors[] = 'Select a valid gender.';
    $dob = DateTime::createFromFormat('Y-m-d', $f['date_of_birth']);
    if (!$dob || $dob > new DateTime('today'))
        $errors[] = 'Enter a valid date of birth (not in the future).';
    if ($f['phone'] !== '' && !preg_match('/^\+?[0-9 ]{9,15}$/', $f['phone']))
        $errors[] = 'Phone number must contain 9-15 digits (e.g. 0911223344 or +251911223344).';
    if (!in_array($f['payment_type'], ['Cash','Insurance','Credit','Free'], true))
        $errors[] = 'Select a payment type.';
    if (!in_array($f['blood_group'], ['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'], true))
        $f['blood_group'] = 'Unknown';
    if (!in_array($f['marital_status'], ['Single','Married','Divorced','Widowed'], true))
        $f['marital_status'] = null;

    if (!$errors) {
        // Empty optional strings are stored as NULL
        foreach ($f as $k => $v) { if ($v === '') $f[$k] = null; }

        if ($editing) {
            $sql = 'UPDATE patients SET first_name=:first_name, middle_name=:middle_name, last_name=:last_name,
                    gender=:gender, date_of_birth=:date_of_birth, phone=:phone, region=:region, zone=:zone,
                    woreda=:woreda, kebele=:kebele, marital_status=:marital_status, occupation=:occupation,
                    blood_group=:blood_group, allergies=:allergies, chronic_illness=:chronic_illness,
                    emergency_name=:emergency_name, emergency_phone=:emergency_phone, payment_type=:payment_type
                    WHERE id=:id';
            $f['id'] = $id;
            $pdo->prepare($sql)->execute($f);
            audit('PATIENT_UPDATE', "patient #$id");
            flash('success', 'Patient record updated.');
            header("Location: patient_view.php?id=$id");
        } else {
            $sql = 'INSERT INTO patients (mrn, first_name, middle_name, last_name, gender, date_of_birth, phone,
                    region, zone, woreda, kebele, marital_status, occupation, blood_group, allergies,
                    chronic_illness, emergency_name, emergency_phone, payment_type, registered_by)
                    VALUES (:mrn,:first_name,:middle_name,:last_name,:gender,:date_of_birth,:phone,
                    :region,:zone,:woreda,:kebele,:marital_status,:occupation,:blood_group,:allergies,
                    :chronic_illness,:emergency_name,:emergency_phone,:payment_type,:registered_by)';
            $f['mrn'] = next_mrn($pdo);
            $f['registered_by'] = current_user()['id'];
            $pdo->prepare($sql)->execute($f);
            $newId = (int)$pdo->lastInsertId();
            audit('PATIENT_REGISTER', $f['mrn']);
            flash('success', 'Patient registered. MRN: ' . $f['mrn']);
            header("Location: patient_view.php?id=$newId");
        }
        exit;
    }
    $p = array_merge($p, $_POST);   // keep what the user typed
}

/** Small helper: current value of a field (for re-filling the form). */
$v = fn(string $k, string $d = '') => e($p[$k] ?? $d);
$sel = fn(string $k, string $opt) => (($p[$k] ?? '') === $opt) ? 'selected' : '';

$pageTitle = $editing ? 'Edit patient' : 'Register new patient';
$active = 'patients';
require __DIR__ . '/includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul><?php foreach ($errors as $er) echo '<li>' . e($er) . '</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form-grid" data-validate novalidate>
  <?= csrf_field() ?>

  <h3 class="span-all">1. Personal information</h3>
  <label>First name *     <input name="first_name"  value="<?= $v('first_name') ?>"  required></label>
  <label>Father's name *  <input name="middle_name" value="<?= $v('middle_name') ?>" required></label>
  <label>Grandfather's name * <input name="last_name" value="<?= $v('last_name') ?>" required></label>
  <label>Gender *
    <select name="gender" required>
      <option value="">-- select --</option>
      <option <?= $sel('gender','Male') ?>>Male</option>
      <option <?= $sel('gender','Female') ?>>Female</option>
    </select>
  </label>
  <label>Date of birth * <input type="date" name="date_of_birth" id="dob" max="<?= date('Y-m-d') ?>" value="<?= $v('date_of_birth') ?>" required>
    <small class="muted" id="ageOut"></small></label>
  <label>Phone <input name="phone" value="<?= $v('phone') ?>" placeholder="0911223344" pattern="\+?[0-9 ]{9,15}"></label>
  <label>Marital status
    <select name="marital_status">
      <option value="">--</option>
      <?php foreach (['Single','Married','Divorced','Widowed'] as $m): ?>
        <option <?= $sel('marital_status',$m) ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Occupation <input name="occupation" value="<?= $v('occupation') ?>"></label>

  <h3 class="span-all">2. Address</h3>
  <label>Region <input name="region" value="<?= $v('region','Amhara') ?>"></label>
  <label>Zone   <input name="zone"   value="<?= $v('zone') ?>"></label>
  <label>Woreda <input name="woreda" value="<?= $v('woreda') ?>"></label>
  <label>Kebele <input name="kebele" value="<?= $v('kebele') ?>"></label>

  <h3 class="span-all">3. Medical alerts</h3>
  <label>Blood group
    <select name="blood_group">
      <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $b): ?>
        <option <?= $sel('blood_group',$b) ?>><?= $b ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Known allergies <input name="allergies" value="<?= $v('allergies') ?>" placeholder="e.g. Penicillin"></label>
  <label>Chronic illness <input name="chronic_illness" value="<?= $v('chronic_illness') ?>" placeholder="e.g. Diabetes, Hypertension"></label>

  <h3 class="span-all">4. Emergency contact &amp; payment</h3>
  <label>Contact person <input name="emergency_name" value="<?= $v('emergency_name') ?>"></label>
  <label>Contact phone  <input name="emergency_phone" value="<?= $v('emergency_phone') ?>"></label>
  <label>Payment type *
    <select name="payment_type" required>
      <?php foreach (['Cash','Insurance','Credit','Free'] as $t): ?>
        <option <?= $sel('payment_type',$t) ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <div class="span-all actions">
    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Register patient' ?></button>
    <a class="btn btn-light" href="<?= $editing ? 'patient_view.php?id=' . $id : 'patients.php' ?>">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
