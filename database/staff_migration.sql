-- ============================================================
-- PrimeCare — Staff App Migration
-- Run ONCE against primecare_db (after schema.sql).
-- Adds everything the Android staff app needs WITHOUT changing
-- any column the patient website already depends on.
-- ============================================================
USE primecare_db;

-- 1. STAFF ACCOUNTS (Admins + Doctors) ---------------------------------
-- Admins are not necessarily doctors (e.g. front-desk), so doctor_id is
-- NULL for them and set for DOCTOR accounts.
CREATE TABLE IF NOT EXISTS staff (
    staff_id      INT AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('ADMIN', 'DOCTOR') NOT NULL,
    first_name    VARCHAR(50) NOT NULL,
    last_name     VARCHAR(50) NOT NULL,
    doctor_id     INT NULL,
    status        ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 2. API TOKENS (only the SHA-256 hash of each token is stored) --------
CREATE TABLE IF NOT EXISTS staff_tokens (
    token_hash CHAR(64) PRIMARY KEY,
    staff_id   INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_staff FOREIGN KEY (staff_id) REFERENCES staff(staff_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;
CREATE INDEX idx_staff_tokens_expiry ON staff_tokens(expires_at);

-- 3. QUEUE / WALK-IN / CONSULTATION-START TRACKING ---------------------
-- "In consultation" is NOT a new status value: the website's status enum
-- and pills stay untouched. An appointment is "in consultation" when it is
-- CheckedIn AND consultation_started_at is set.
ALTER TABLE appointments
    ADD COLUMN queue_number            INT      NULL AFTER status,
    ADD COLUMN is_walkin               TINYINT(1) NOT NULL DEFAULT 0 AFTER queue_number,
    ADD COLUMN is_followup             TINYINT(1) NOT NULL DEFAULT 0 AFTER is_walkin,
    ADD COLUMN checked_in_at           DATETIME NULL AFTER is_followup,
    ADD COLUMN consultation_started_at DATETIME NULL AFTER checked_in_at;

-- 4. DRAFT FLAG on medical records -------------------------------------
-- Prescriptions need a record_id, but a doctor may add a prescription before
-- finalising. The record is created as a draft and hidden from the website
-- (see records/list.php + prescriptions/list.php) until finalised.
ALTER TABLE medical_records
    ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER notes;

-- 5. FULL CONSULTATION DETAIL (vitals etc.) ----------------------------
-- medical_records keeps what the patient may see (diagnosis, treatment,
-- follow-up, notes). Everything else clinical lives here.
CREATE TABLE IF NOT EXISTS consultations (
    consultation_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT NOT NULL UNIQUE,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    record_id       INT NULL,
    symptoms        VARCHAR(500) NOT NULL DEFAULT '',
    blood_pressure  VARCHAR(20)  NOT NULL DEFAULT '',
    temperature     VARCHAR(10)  NOT NULL DEFAULT '',
    pulse           VARCHAR(10)  NOT NULL DEFAULT '',
    spo2            VARCHAR(10)  NOT NULL DEFAULT '',
    medical_history TEXT NULL,
    allergies       VARCHAR(255) NOT NULL DEFAULT '',
    diagnosis       VARCHAR(255) NOT NULL DEFAULT '',
    treatment       TEXT NULL,
    investigations  VARCHAR(500) NOT NULL DEFAULT '',
    notes           TEXT NULL,
    sick_leave_days INT NOT NULL DEFAULT 0,
    follow_up_date  DATE NULL,
    status          ENUM('Draft', 'Final') NOT NULL DEFAULT 'Draft',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_consult_appt    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_consult_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_consult_doctor  FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_consult_record  FOREIGN KEY (record_id) REFERENCES medical_records(record_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Allow the 'login' rate limiter to be used for staff too (same enum, no change needed).
