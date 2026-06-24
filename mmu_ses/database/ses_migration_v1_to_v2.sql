-- =====================================================================
-- Migration: v1 → v2
-- Run this ONLY if you already imported the original ses_setup.sql
-- (v1) and want to keep your data. Otherwise just re-import the new
-- ses_setup.sql and the database will be rebuilt fresh.
-- =====================================================================

USE mmu_ses;

-- 0) Add credit_hours to courses (v1 had none, v2 needs it for the cap)
ALTER TABLE courses
    ADD COLUMN credit_hours INT NOT NULL DEFAULT 3 AFTER lecturer_name;

-- 1) Extend the enrollments status enum and add waitlist position column
ALTER TABLE enrollments
    MODIFY status ENUM('enrolled','waitlisted','dropped','completed')
        NOT NULL DEFAULT 'enrolled';

ALTER TABLE enrollments
    ADD COLUMN waitlist_position INT DEFAULT NULL AFTER status;

CREATE INDEX IF NOT EXISTS idx_enrol_status
    ON enrollments (status);
CREATE INDEX IF NOT EXISTS idx_enrol_waitpos
    ON enrollments (course_id, waitlist_position);

-- 2) Add the prerequisites table
CREATE TABLE IF NOT EXISTS course_prerequisites (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    course_id       INT NOT NULL,
    prereq_id       INT NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (prereq_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_course_prereq (course_id, prereq_id),
    CHECK (course_id <> prereq_id)
) ENGINE=InnoDB;
