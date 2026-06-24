-- =====================================================================
-- MMU Student Enrollment System (SES) - Database Schema v2
-- TSE6223 Software Engineering Fundamentals
-- =====================================================================
-- Run this file in phpMyAdmin or MySQL CLI to set up the database.
-- All sample passwords are: "password123" (hashed with bcrypt)
--
-- New in v2:
--   * course_prerequisites table (FR: Prerequisite enforcement)
--   * enrollments.status now includes 'waitlisted'
--   * enrollments.waitlist_position for queue ordering
--   * Credit-hour cap is enforced in PHP (see config/config.php)
-- =====================================================================

DROP DATABASE IF EXISTS mmu_ses;
CREATE DATABASE mmu_ses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mmu_ses;

-- ---------------------------------------------------------------------
-- Table: students
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20)  NOT NULL UNIQUE,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    programme       VARCHAR(50)  NOT NULL,
    trimester       INT          NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table: administrators
-- ---------------------------------------------------------------------
CREATE TABLE administrators (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        VARCHAR(20)  NOT NULL UNIQUE,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table: courses
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    course_code     VARCHAR(20)  NOT NULL UNIQUE,
    course_name     VARCHAR(150) NOT NULL,
    lecturer_name   VARCHAR(100) NOT NULL,
    credit_hours    INT          NOT NULL DEFAULT 3,
    quota           INT          NOT NULL DEFAULT 30,
    enrolled_count  INT          NOT NULL DEFAULT 0,
    programme       VARCHAR(50)  NOT NULL,
    trimester       INT          NOT NULL DEFAULT 1,
    schedule_info   VARCHAR(200) DEFAULT '',
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table: course_prerequisites
--   Each row means: to enrol in course_id, you must have passed prereq_id
-- ---------------------------------------------------------------------
CREATE TABLE course_prerequisites (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    course_id       INT NOT NULL,
    prereq_id       INT NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (prereq_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_course_prereq (course_id, prereq_id),
    CHECK (course_id <> prereq_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Table: enrollments
--   status:
--     'enrolled'   = currently registered, counts toward credit cap
--     'waitlisted' = queued; will be promoted on a drop
--     'dropped'    = student dropped (history kept for audit)
--     'completed'  = trimester finished and passed (used for prereq checks)
-- ---------------------------------------------------------------------
CREATE TABLE enrollments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    student_id        INT NOT NULL,
    course_id         INT NOT NULL,
    enrollment_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status            ENUM('enrolled','waitlisted','dropped','completed')
                          NOT NULL DEFAULT 'enrolled',
    waitlist_position INT DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)  ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_student_course (student_id, course_id)
) ENGINE=InnoDB;

CREATE INDEX idx_enrol_status   ON enrollments (status);
CREATE INDEX idx_enrol_waitpos  ON enrollments (course_id, waitlist_position);

-- =====================================================================
-- SAMPLE DATA
-- All sample passwords are "password123" (bcrypt)
-- =====================================================================

INSERT INTO administrators (admin_id, full_name, email, password) VALUES
('ADM001', 'Dr. Tan Wei Ming', 'admin@mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW'),
('ADM002', 'Ms. Lim Su Yin',   'suyin@mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW');

INSERT INTO students (student_id, full_name, email, password, programme, trimester) VALUES
('253UT256KY', 'Andrew Lim Zi Fei',          'andrew.lim@student.mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW', 'AI', 2),
('253UT256JW', 'Desmond Choi Lip Sheng',     'desmond.choi@student.mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW', 'AI', 2),
('243UT245X0', 'Siti Saimah Binti Abd Hamid','siti.saimah@student.mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW', 'AI', 2),
('261UT240PM', 'Lim Yee Chen',               'yeechen.lim@student.mmu.edu.my',
 '$2b$12$Yii7kx6IxPR9DiH6eGvaKuEpj5Q9/jnxCSFjYPj.wORLCteBZkUqW', 'AI', 2);

INSERT INTO courses (course_code, course_name, lecturer_name, credit_hours, quota, enrolled_count, programme, trimester, schedule_info) VALUES
('TSE6223', 'Software Engineering Fundamentals', 'Dr. Tan Wei Ming',  3, 40, 0, 'AI', 2, 'Mon 10:00-12:00, Wed 14:00-15:00'),
('TAI2222', 'Machine Learning',                  'Dr. Wong Kar Wai',  3, 35, 0, 'AI', 2, 'Tue 09:00-11:00, Thu 14:00-15:00'),
('TAI2233', 'Deep Learning',                     'Dr. Rashid Ahmad',  3, 30, 0, 'AI', 2, 'Mon 14:00-16:00, Fri 10:00-11:00'),
('TMA1101', 'Calculus and Analytical Geometry',  'Ms. Chen Hui Min',  4, 50, 0, 'AI', 1, 'Mon 08:00-10:00, Wed 08:00-10:00'),
('TCS1011', 'Programming Principles',            'Mr. Krishnan Raj',  4, 45, 0, 'AI', 1, 'Tue 10:00-12:00, Fri 14:00-16:00'),
('TDS3851', 'Data Structures and Algorithms',    'Dr. Lee Mei Ling',  3, 40, 0, 'AI', 2, 'Wed 10:00-12:00, Fri 13:00-14:00'),
('TEN2231', 'Technical Communication',           'Ms. Sarah Johnson', 3, 50, 0, 'AI', 2, 'Thu 09:00-12:00'),
('TIB2003', 'Database Management Systems',       'Dr. Ahmad Faizal',  3, 35, 0, 'AI', 2, 'Mon 13:00-15:00, Thu 15:00-16:00'),
-- A couple of small-quota courses so waitlisting is easy to demonstrate
('TAI3001', 'Advanced Neural Networks',          'Dr. Wong Kar Wai',  3,  2, 0, 'AI', 3, 'Tue 14:00-16:00, Thu 10:00-11:00'),
('TAI3002', 'Reinforcement Learning',            'Dr. Rashid Ahmad',  3,  2, 0, 'AI', 3, 'Wed 14:00-16:00, Fri 14:00-15:00');

-- ---------------------------------------------------------------------
-- Sample prerequisites
--   * Deep Learning requires Machine Learning
--   * Machine Learning requires Programming Principles
--   * Data Structures requires Programming Principles
--   * Advanced Neural Networks requires Deep Learning
--   * Reinforcement Learning requires Machine Learning
-- ---------------------------------------------------------------------
INSERT INTO course_prerequisites (course_id, prereq_id)
SELECT c.id, p.id FROM courses c JOIN courses p ON p.course_code = 'TAI2222'
WHERE c.course_code = 'TAI2233';
INSERT INTO course_prerequisites (course_id, prereq_id)
SELECT c.id, p.id FROM courses c JOIN courses p ON p.course_code = 'TCS1011'
WHERE c.course_code = 'TAI2222';
INSERT INTO course_prerequisites (course_id, prereq_id)
SELECT c.id, p.id FROM courses c JOIN courses p ON p.course_code = 'TCS1011'
WHERE c.course_code = 'TDS3851';
INSERT INTO course_prerequisites (course_id, prereq_id)
SELECT c.id, p.id FROM courses c JOIN courses p ON p.course_code = 'TAI2233'
WHERE c.course_code = 'TAI3001';
INSERT INTO course_prerequisites (course_id, prereq_id)
SELECT c.id, p.id FROM courses c JOIN courses p ON p.course_code = 'TAI2222'
WHERE c.course_code = 'TAI3002';

-- ---------------------------------------------------------------------
-- Sample completed enrollments
--   * All four team students have already "completed" TCS1011 and TMA1101
--     so they pass the prereq for Machine Learning.
-- ---------------------------------------------------------------------
INSERT INTO enrollments (student_id, course_id, status)
SELECT s.id, c.id, 'completed' FROM students s, courses c
WHERE c.course_code IN ('TCS1011', 'TMA1101');
