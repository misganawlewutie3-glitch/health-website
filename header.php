<?php
/**
 * includes/header.php
 * Top of every inner page: <head>, sidebar menu, top bar.
 * Set $pageTitle and $active (menu key) before including.
 */
$user  = current_user();
$flash = get_flash();
$pageTitle = $pageTitle ?? 'Dashboard';
$active    = $active ?? '';

// Menu definition: key => [label, file, icon]
$menu = [
    'dashboard'    => ['Dashboard',    'dashboard.php',    '🏠'],
    'patients'     => ['Patients',     'patients.php',     '🧑‍⚕️'],
    'appointments' => ['Appointments', 'appointments.php', '📅'],
    'visits'       => ['Consultations','visits.php',       '🩺'],
    'lab'          => ['Laboratory',   'lab.php',          '🧪'],
    'pharmacy'     => ['Pharmacy',     'pharmacy.php',     '💊'],
    'billing'      => ['Billing',      'billing.php',      '💳'],
    'reports'      => ['Reports',      'reports.php',      '📊'],
    'departments'  => ['Departments',  'departments.php',  '🏥'],
    'users'        => ['Staff Users',  'users.php',        '👥'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(APP_SHORT) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-logo">✚</div>
      <div>
        <strong>UoGSH</strong><br>
        <small>Patient Information System</small>
      </div>
    </div>
    <nav>
      <?php foreach ($menu as $key => [$label, $file, $icon]): ?>
        <?php if (can($key)): ?>
          <a href="<?= e($file) ?>" class="<?= $active === $key ? 'active' : '' ?>">
            <span class="ico"><?= $icon ?></span> <?= e($label) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- ===== MAIN AREA ===== -->
  <div class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Toggle menu">☰</button>
      <h1><?= e($pageTitle) ?></h1>
      <div class="userbox">
        <span class="user-name"><?= e($user['full_name']) ?></span>
        <span class="badge role"><?= e(ucfirst($user['role'])) ?></span>
        <a class="btn btn-sm btn-light" href="logout.php">Logout</a>
      </div>
    </header>

    <main class="content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" data-autohide><?= e($flash['msg']) ?></div>
      <?php endif; ?>
