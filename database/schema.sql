-- ============================================================
-- PrimeCare Medical Centre — Database Schema
-- Shared database used by:
--   1. Patient Website (PHP/MySQL)  — read/write for patient-owned data
--   2. Android App (Kotlin)         — read/write for doctor/admin-owned data
-- ============================================================

CREATE DATABASE IF NOT EXISTS primecare_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE primecare_db;

-- ============================================================
-- 1. DEPARTMENTS
-- Groups doctors and appointments by specialty/service area.
-- Managed by administrators via the Android app.
-- ============================================================
CREATE TABLE departments (
    department_id   INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    description      VARCHAR(255) NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed data — matches the departments listed on services.html, so the
-- public Services page and the booking dropdown stay consistent.
INSERT INTO departments (department_name, description) VALUES
    ('General Practice', 'Everyday check-ups, screenings and family medicine.'),
    ('Cardiology', 'Heart health assessments, ECGs and specialist care.'),
    ('Paediatrics', 'Compassionate care for infants, children and teens.'),
    ('Neurology', 'Diagnosis and management of neurological conditions.'),
    ('Preventive Care', 'Vaccinations, wellness plans and lifestyle counselling.'),
    ('Women''s Health', 'Gynaecology, antenatal care and wellness screenings.'),
    ('Pathology & Screening', 'On-site blood pressure, glucose, and cholesterol screening, with lab referrals available.');

-- ============================================================
-- 2. DOCTORS
-- Medical staff. Created/managed by admins via the Android app.
-- The website only ever READS from this table (to display
-- assigned doctor names) — it never inserts or updates rows here.
-- ============================================================
CREATE TABLE doctors (
    doctor_id       INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    specialty       VARCHAR(100) NULL,
    department_id   INT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    phone           VARCHAR(20)  NULL,
    status          ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_doctors_department
        FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_doctors_department ON doctors(department_id);

-- ============================================================
-- 3. PATIENTS
-- Core identity table for website users. Website has full
-- read/write access; the Android app only reads patient details
-- needed for check-in/queue/records display.
-- ============================================================
CREATE TABLE patients (
    patient_id      INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    phone           VARCHAR(20)  NOT NULL,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    date_of_birth   DATE NULL,
    gender          ENUM('Male', 'Female', 'Other', 'Prefer not to say') NULL,
    address         VARCHAR(255) NULL,
    emergency_contact_name  VARCHAR(100) NULL,
    emergency_contact_phone VARCHAR(20)  NULL,
    reset_token_hash    VARCHAR(255) NULL,   -- hashed password-reset token (Forgot Password flow)
    reset_token_expires TIMESTAMP NULL,      -- token expiry (e.g. now + 30 minutes)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3B. LOGIN_ATTEMPTS
-- Supports rate limiting on login and registration. Not tied to
-- a specific patient (the attempt may be against an email that
-- doesn't even exist), so it's keyed by IP address + attempt type.
-- ============================================================
CREATE TABLE login_attempts (
    attempt_id    INT AUTO_INCREMENT PRIMARY KEY,
    identifier    VARCHAR(100) NOT NULL,  -- IP address
    attempt_type  ENUM('login', 'register') NOT NULL,
    attempted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_login_attempts_lookup ON login_attempts(identifier, attempt_type, attempted_at);

-- ============================================================
-- 4. APPOINTMENTS
-- Central coordination point between website (booking) and
-- Android app (doctor assignment, check-in, completion).
-- ============================================================
CREATE TABLE appointments (
    appointment_id   INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    doctor_id        INT NULL,
    department_id    INT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason           VARCHAR(255) NOT NULL,
    status           ENUM('Booked', 'CheckedIn', 'Completed', 'Cancelled')
                          NOT NULL DEFAULT 'Booked',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointments_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_department
        FOREIGN KEY (department_id) REFERENCES departments(department_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    -- Prevents the same patient double-booking the same date+time
    CONSTRAINT uq_patient_slot UNIQUE (patient_id, appointment_date, appointment_time)
) ENGINE=InnoDB;

CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_doctor  ON appointments(doctor_id);
CREATE INDEX idx_appointments_date    ON appointments(appointment_date);

-- ============================================================
-- 5. MEDICAL_RECORDS
-- Permanent clinical history. Created by doctors via the Android
-- app. The website only READS records belonging to the logged-in
-- patient.
-- ============================================================
CREATE TABLE medical_records (
    record_id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT NOT NULL,
    doctor_id         INT NOT NULL,
    appointment_id    INT NULL,
    consultation_date DATE NOT NULL,
    diagnosis         VARCHAR(255) NOT NULL,
    treatment         TEXT NULL,          -- treatment/management plan for this consultation
    follow_up_date    DATE NULL,          -- next recommended visit, if any
    notes             TEXT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_records_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_records_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_records_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_records_patient ON medical_records(patient_id);
CREATE INDEX idx_records_doctor  ON medical_records(doctor_id);

-- ============================================================
-- 6. PRESCRIPTIONS
-- Medication issued during a consultation. Separated from
-- medical_records so patients can view/request prescriptions
-- independently, per the project requirements.
-- ============================================================
CREATE TABLE prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    record_id       INT NOT NULL,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    medication_name VARCHAR(150) NOT NULL,
    dosage          VARCHAR(100) NOT NULL,
    instructions    VARCHAR(255) NULL,
    date_issued     DATE NOT NULL,
    status          ENUM('Active', 'Completed', 'Expired') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_prescriptions_record
        FOREIGN KEY (record_id) REFERENCES medical_records(record_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_prescriptions_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_prescriptions_doctor
        FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_prescriptions_patient ON prescriptions(patient_id);
CREATE INDEX idx_prescriptions_record  ON prescriptions(record_id);

-- ============================================================
-- 7. PRESCRIPTION_REQUESTS
-- A patient's request to repeat an existing prescription.
-- Created by the website, reviewed/actioned by a doctor via
-- the Android app.
-- ============================================================
CREATE TABLE prescription_requests (
    request_id      INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    patient_id      INT NOT NULL,
    request_date    DATE NOT NULL DEFAULT (CURRENT_DATE),
    status          ENUM('Requested', 'Approved', 'Declined', 'Ready')
                        NOT NULL DEFAULT 'Requested',
    reviewed_by     INT NULL,
    reviewed_at     TIMESTAMP NULL,
    notes           VARCHAR(255) NULL,

    CONSTRAINT fk_requests_prescription
        FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_requests_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES doctors(doctor_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_requests_patient ON prescription_requests(patient_id);
CREATE INDEX idx_requests_status  ON prescription_requests(status);

-- ============================================================
-- 8. NOTIFICATIONS
-- In-app messages for patients (appointment reminders,
-- prescription request updates, general announcements).
-- ============================================================
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    title            VARCHAR(150) NOT NULL,
    message          VARCHAR(255) NOT NULL,
    type             ENUM('Appointment', 'Prescription', 'General') NOT NULL DEFAULT 'General',
    is_read          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notifications_patient ON notifications(patient_id);
CREATE INDEX idx_notifications_unread  ON notifications(patient_id, is_read);
