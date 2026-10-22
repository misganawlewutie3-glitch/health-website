<?php
/**
 * api_patient_search.php - tiny JSON endpoint used by the live
 * "type to search patient" box (called with fetch() from app.js).
 */
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !can('patients')) {
    http_response_code(403);
    echo '[]';
    exit;
}
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo '[]'; exit; }

$st = $pdo->prepare("SELECT id, mrn, first_name, middle_name, last_name FROM patients
                      WHERE mrn LIKE :q OR first_name LIKE :q OR last_name LIKE :q
                         OR CONCAT(first_name,' ',middle_name) LIKE :q OR phone LIKE :q
                      ORDER BY first_name LIMIT 8");
$st->execute([':q' => "%$q%"]);
echo json_encode($st->fetchAll(), JSON_UNESCAPED_UNICODE);
