USE sas_ojt_database;

ALTER TABLE users
    MODIFY role ENUM('admin', 'sas_staff', 'coordinator', 'supervisor', 'company', 'student') NOT NULL;

ALTER TABLE evaluations
    MODIFY evaluator_role ENUM('supervisor', 'coordinator', 'student') NOT NULL;

CREATE TABLE IF NOT EXISTS journals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    placement_id INT UNSIGNED NOT NULL,
    journal_date DATE NOT NULL,
    title VARCHAR(150) NOT NULL,
    activities TEXT NOT NULL,
    learnings TEXT DEFAULT NULL,
    hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    supervisor_feedback TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_student_journal_date (student_id, journal_date),
    KEY idx_journals_placement_status (placement_id, status),
    CONSTRAINT fk_journals_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_journals_placement FOREIGN KEY (placement_id) REFERENCES ojt_placements (id) ON DELETE CASCADE,
    CONSTRAINT fk_journals_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_descriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    supervisor_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    qualifications TEXT DEFAULT NULL,
    location VARCHAR(150) DEFAULT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jobs_company_status (company_id, status),
    CONSTRAINT fk_jobs_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_jobs_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
