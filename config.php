<?php
/**
 * config/config.php
 * ---------------------------------------------------------------
 * Central configuration + database connection (PDO).
 * Every page includes this file first (through includes/auth.php).
 */

// ---- 1. Application constants ------------------------------------
define('APP_NAME',  'UoGSH Patient Information Management System');
define('APP_SHORT', 'UoGSH-PIMS');
define('HOSPITAL',  'University of Gondar Specialized Hospital');

// ---- 2. Database settings (change to match your server) ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'uogsh_pims');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP default is an empty password

// ---- 3. Time zone (Ethiopia = UTC+3) -----------------------------
date_default_timezone_set('Africa/Addis_Ababa');

// ---- 4. PDO connection -------------------------------------------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays by column name
            // true lets one named parameter (e.g. :q) be reused several times in a query.
            // Values are still bound separately from the SQL text, so SQL injection is still prevented.
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]
    );
} catch (PDOException $e) {
    // Never show raw DB errors to users (could leak passwords/paths)
    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please contact the system administrator.');
}
