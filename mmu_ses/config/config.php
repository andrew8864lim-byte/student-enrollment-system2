<?php
/**
 * Application configuration
 * --------------------------------------------------------------------
 * Central place for tuning the business rules that the rest of the
 * system relies on. Lecturer should be able to read this file alone
 * and understand the policy.
 */

// Max credit hours a student may carry per trimester (FR-02 / NFR-04)
const MAX_CREDIT_HOURS_PER_TRIMESTER = 22;

// Min credit hours (warning only, not enforced)
const MIN_CREDIT_HOURS_PER_TRIMESTER = 9;

// CSRF
const CSRF_TOKEN_NAME = '_csrf';

// Institution branding (used on the registration slip)
const INSTITUTION_NAME      = 'Multimedia University';
const INSTITUTION_FACULTY   = 'Faculty of Information Science and Technology';
const ACADEMIC_YEAR_LABEL   = '2026 / 2027';
const ACADEMIC_TRIMESTER    = 'Trimester 2';

// Date / time
date_default_timezone_set('Asia/Kuala_Lumpur');
