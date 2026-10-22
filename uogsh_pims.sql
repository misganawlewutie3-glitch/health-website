-- =====================================================================
-- University of Gondar Specialized Hospital (UoGSH)
-- Patient Information Management System (PIMS) - Database Schema
-- Engine: MySQL / MariaDB (InnoDB, utf8mb4)
-- Import:  mysql -u root -p < uogsh_pims.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS uogsh_pims
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uogsh_pims;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log, payments, invoices, prescriptions, lab_requests,
                     visits, admissions, appointments, patients, users, departments;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. DEPARTMENTS  (24 departments - editable by the administrator)
-- ---------------------------------------------------------------------
CREATE TABLE departments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(10)  NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USERS  (staff accounts, role based access)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(120) NOT NULL,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','doctor','nurse','receptionist','lab','pharmacist','cashier') NOT NULL,
  department_id INT UNSIGNED NULL,
  phone         VARCHAR(30)  NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. PATIENTS  (the central record, one row per person)
-- ---------------------------------------------------------------------
CREATE TABLE patients (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mrn              VARCHAR(20)  NOT NULL UNIQUE,          -- Medical Record Number (card number)
  first_name       VARCHAR(60)  NOT NULL,
  middle_name      VARCHAR(60)  NOT NULL,
  last_name        VARCHAR(60)  NOT NULL,
  gender           ENUM('Male','Female') NOT NULL,
  date_of_birth    DATE         NOT NULL,
  phone            VARCHAR(30)  NULL,
  region           VARCHAR(60)  NULL DEFAULT 'Amhara',
  zone             VARCHAR(60)  NULL,
  woreda           VARCHAR(60)  NULL,
  kebele           VARCHAR(60)  NULL,
  marital_status   ENUM('Single','Married','Divorced','Widowed') NULL,
  occupation       VARCHAR(80)  NULL,
  blood_group      ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL DEFAULT 'Unknown',
  allergies        VARCHAR(255) NULL,
  chronic_illness  VARCHAR(255) NULL,
  emergency_name   VARCHAR(120) NULL,
  emergency_phone  VARCHAR(30)  NULL,
  payment_type     ENUM('Cash','Insurance','Credit','Free') NOT NULL DEFAULT 'Cash',
  registered_by    INT UNSIGNED NULL,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_patient_name (first_name, last_name),
  INDEX idx_patient_phone (phone),
  CONSTRAINT fk_patient_user FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. APPOINTMENTS
-- ---------------------------------------------------------------------
CREATE TABLE appointments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id       INT UNSIGNED NOT NULL,
  department_id    INT UNSIGNED NOT NULL,
  doctor_id        INT UNSIGNED NULL,
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  reason           VARCHAR(255) NULL,
  status           ENUM('Scheduled','Completed','Cancelled','No-Show') NOT NULL DEFAULT 'Scheduled',
  created_by       INT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_appt_date (appointment_date),
  CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id)    REFERENCES patients(id)    ON DELETE CASCADE,
  CONSTRAINT fk_appt_dept    FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctor_id)     REFERENCES users(id)       ON DELETE SET NULL,
  CONSTRAINT fk_appt_creator FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. ADMISSIONS (inpatient stay)
-- ---------------------------------------------------------------------
CREATE TABLE admissions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id     INT UNSIGNED NOT NULL,
  department_id  INT UNSIGNED NOT NULL,
  ward           VARCHAR(60) NULL,
  bed_no         VARCHAR(20) NULL,
  admitted_at    DATETIME NOT NULL,
  discharged_at  DATETIME NULL,
  diagnosis      VARCHAR(255) NULL,
  status         ENUM('Admitted','Discharged','Referred','Deceased') NOT NULL DEFAULT 'Admitted',
  admitted_by    INT UNSIGNED NULL,
  CONSTRAINT fk_adm_patient FOREIGN KEY (patient_id)    REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_adm_dept    FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_adm_user    FOREIGN KEY (admitted_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. VISITS (clinical encounter / consultation note)
-- ---------------------------------------------------------------------
CREATE TABLE visits (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id       INT UNSIGNED NOT NULL,
  department_id    INT UNSIGNED NOT NULL,
  doctor_id        INT UNSIGNED NULL,
  visit_date       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  visit_type       ENUM('OPD','Emergency','Inpatient','Follow-up') NOT NULL DEFAULT 'OPD',
  chief_complaint  VARCHAR(255) NOT NULL,
  history          TEXT NULL,
  temperature_c    DECIMAL(4,1) NULL,
  bp_systolic      SMALLINT NULL,
  bp_diastolic     SMALLINT NULL,
  pulse_rate       SMALLINT NULL,
  resp_rate        SMALLINT NULL,
  weight_kg        DECIMAL(5,1) NULL,
  height_cm        DECIMAL(5,1) NULL,
  spo2             SMALLINT NULL,
  examination      TEXT NULL,
  diagnosis        VARCHAR(255) NULL,
  treatment_plan   TEXT NULL,
  disposition      ENUM('Home','Admit','Refer','Follow-up') NOT NULL DEFAULT 'Home',
  INDEX idx_visit_patient (patient_id, visit_date),
  CONSTRAINT fk_visit_patient FOREIGN KEY (patient_id)    REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_dept    FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_visit_doctor  FOREIGN KEY (doctor_id)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. LAB REQUESTS
-- ---------------------------------------------------------------------
CREATE TABLE lab_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id      INT UNSIGNED NOT NULL,
  patient_id    INT UNSIGNED NOT NULL,
  test_name     VARCHAR(120) NOT NULL,
  priority      ENUM('Routine','Urgent') NOT NULL DEFAULT 'Routine',
  status        ENUM('Requested','Completed') NOT NULL DEFAULT 'Requested',
  result        TEXT NULL,
  requested_by  INT UNSIGNED NULL,
  processed_by  INT UNSIGNED NULL,
  requested_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at  DATETIME NULL,
  CONSTRAINT fk_lab_visit   FOREIGN KEY (visit_id)   REFERENCES visits(id)   ON DELETE CASCADE,
  CONSTRAINT fk_lab_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. PRESCRIPTIONS
-- ---------------------------------------------------------------------
CREATE TABLE prescriptions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id       INT UNSIGNED NOT NULL,
  patient_id     INT UNSIGNED NOT NULL,
  drug_name      VARCHAR(120) NOT NULL,
  dosage         VARCHAR(60)  NOT NULL,
  frequency      VARCHAR(60)  NOT NULL,
  duration_days  SMALLINT NOT NULL DEFAULT 5,
  instructions   VARCHAR(255) NULL,
  status         ENUM('Pending','Dispensed') NOT NULL DEFAULT 'Pending',
  prescribed_by  INT UNSIGNED NULL,
  dispensed_by   INT UNSIGNED NULL,
  prescribed_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dispensed_at   DATETIME NULL,
  CONSTRAINT fk_rx_visit   FOREIGN KEY (visit_id)   REFERENCES visits(id)   ON DELETE CASCADE,
  CONSTRAINT fk_rx_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. INVOICES & PAYMENTS (billing)
-- ---------------------------------------------------------------------
CREATE TABLE invoices (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id   INT UNSIGNED NOT NULL,
  visit_id     INT UNSIGNED NULL,
  description  VARCHAR(255) NOT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  status       ENUM('Unpaid','Paid','Waived') NOT NULL DEFAULT 'Unpaid',
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id  INT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('Cash','Mobile Money','Bank','Insurance') NOT NULL DEFAULT 'Cash',
  received_by INT UNSIGNED NULL,
  paid_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. AUDIT LOG (who did what - required for patient data privacy)
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  details    VARCHAR(255) NULL,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================
INSERT INTO departments (code, name, description) VALUES
('OPD',  'Outpatient Department',        'General outpatient consultation'),
('EMR',  'Emergency Department',         '24-hour emergency and trauma care'),
('IMD',  'Internal Medicine',            'Adult medical diseases'),
('SUR',  'General Surgery',              'Surgical consultation and operations'),
('PED',  'Pediatrics',                   'Care of children and neonates'),
('GYN',  'Gynecology & Obstetrics',      'Maternal health, labour and gynecology'),
('ORT',  'Orthopedics',                  'Bone, joint and trauma surgery'),
('OPH',  'Ophthalmology',                'Eye clinic'),
('ENT',  'Ear, Nose & Throat',           'ENT clinic'),
('DER',  'Dermatology',                  'Skin diseases'),
('PSY',  'Psychiatry',                   'Mental health services'),
('DEN',  'Dental Clinic',                'Oral and dental care'),
('RAD',  'Radiology',                    'X-ray, ultrasound, CT scan'),
('LAB',  'Laboratory',                   'Clinical laboratory services'),
('PHA',  'Pharmacy',                     'Drug dispensing'),
('NEU',  'Neurology',                    'Brain and nervous system'),
('CAR',  'Cardiology',                   'Heart diseases'),
('ONC',  'Oncology',                     'Cancer treatment centre'),
('URO',  'Urology',                      'Urinary tract and kidney surgery'),
('NEP',  'Nephrology & Dialysis',        'Kidney diseases and dialysis'),
('PHY',  'Physiotherapy',                'Rehabilitation services'),
('ICU',  'Intensive Care Unit',          'Critical care'),
('ANE',  'Anesthesia',                   'Anesthesia and pain management'),
('NUT',  'Nutrition Clinic',             'Dietary and nutrition counselling');

-- Default administrator.  Username: admin   Password: Admin@1234  (CHANGE AFTER FIRST LOGIN)
INSERT INTO users (full_name, username, password_hash, role, department_id) VALUES
('System Administrator', 'admin',
 '$2y$10$aOMMcq2HtTosC17H1Xwa0ufUQ.BRT6P0PJCVOPZhm.G29kvUaZGR.', 'admin', 1);
