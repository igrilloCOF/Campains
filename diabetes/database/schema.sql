SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS pharmacies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  soe VARCHAR(32) NOT NULL UNIQUE,
  locality VARCHAR(150) NOT NULL,
  name VARCHAR(200) NOT NULL,
  tax_id VARCHAR(32) NOT NULL,
  email VARCHAR(254) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin','pharmacy') NOT NULL,
  pharmacy_id BIGINT UNSIGNED NULL,
  username VARCHAR(120) NOT NULL UNIQUE,
  email VARCHAR(254) NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  state ENUM('preparation','active','closed') NOT NULL DEFAULT 'preparation',
  config_version INT UNSIGNED NOT NULL DEFAULT 1,
  activated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_campaign_state_dates(state, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_config_versions (
  campaign_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  state ENUM('draft','active','superseded') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  activated_at DATETIME NULL,
  snapshot_sha256 CHAR(64) NULL,
  change_note VARCHAR(500) NULL,
  PRIMARY KEY(campaign_id,version),
  CONSTRAINT fk_config_campaign FOREIGN KEY(campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
  CONSTRAINT fk_config_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  config_version INT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  prompt TEXT NOT NULL,
  help_text TEXT NULL,
  response_type ENUM('text','integer','decimal','single_choice','date') NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 1,
  position INT NOT NULL,
  options_json JSON NULL,
  validation_json JSON NULL,
  is_builtin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_question_code(campaign_id,config_version,code),
  UNIQUE KEY uq_question_position(campaign_id,config_version,position),
  CONSTRAINT fk_question_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS score_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  config_version INT UNSIGNED NOT NULL,
  scale ENUM('FINDRISC','MEDAS') NOT NULL,
  rule_code VARCHAR(64) NOT NULL,
  title VARCHAR(200) NOT NULL,
  rule_json JSON NOT NULL,
  position INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rule(campaign_id,config_version,scale,rule_code),
  CONSTRAINT fk_rule_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tsi_categories (
  code VARCHAR(16) NOT NULL PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NOT NULL,
  config_version INT UNSIGNED NOT NULL,
  pharmacy_id BIGINT UNSIGNED NOT NULL,
  patient_code VARCHAR(64) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  updated_by BIGINT UNSIGNED NOT NULL,
  revision_number INT UNSIGNED NOT NULL DEFAULT 1,
  record_status ENUM('valid','invalidated') NOT NULL DEFAULT 'valid',
  invalidated_at DATETIME NULL,
  invalidated_by BIGINT UNSIGNED NULL,
  invalidation_reason VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_patient_per_pharmacy_campaign(campaign_id,pharmacy_id,patient_code),
  INDEX idx_record_pharmacy(campaign_id,pharmacy_id,created_at),
  INDEX idx_record_config(campaign_id,config_version),
  CONSTRAINT fk_record_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT,
  CONSTRAINT fk_record_pharmacy FOREIGN KEY(pharmacy_id) REFERENCES pharmacies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_record_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_record_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_record_invalidator FOREIGN KEY(invalidated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_answers (
  record_id BIGINT UNSIGNED NOT NULL,
  question_code VARCHAR(64) NOT NULL,
  answer_value TEXT NOT NULL,
  PRIMARY KEY(record_id,question_code),
  CONSTRAINT fk_answer_record FOREIGN KEY(record_id) REFERENCES patient_records(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  record_id BIGINT UNSIGNED NOT NULL,
  revision_number INT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  old_patient_code VARCHAR(64) NOT NULL,
  old_answers_json JSON NOT NULL,
  old_calculations_json JSON NOT NULL,
  UNIQUE KEY uq_record_revision(record_id,revision_number),
  CONSTRAINT fk_revision_record FOREIGN KEY(record_id) REFERENCES patient_records(id) ON DELETE RESTRICT,
  CONSTRAINT fk_revision_user FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  record_id BIGINT UNSIGNED NOT NULL,
  scale ENUM('FINDRISC','MEDAS') NOT NULL,
  outcome_status ENUM('calculated','not_applicable') NOT NULL,
  total_score SMALLINT UNSIGNED NULL,
  interpretation VARCHAR(120) NULL,
  rule_version INT UNSIGNED NOT NULL,
  item_scores_json JSON NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_calc_record_version(record_id,scale,rule_version,calculated_at),
  INDEX idx_calc_current(record_id,is_current,scale),
  CONSTRAINT fk_calc_record FOREIGN KEY(record_id) REFERENCES patient_records(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  record_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  incident_type VARCHAR(80) NOT NULL,
  description VARCHAR(1000) NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  resolved_by BIGINT UNSIGNED NULL,
  INDEX idx_incident_record_status(record_id,status),
  CONSTRAINT fk_incident_record FOREIGN KEY(record_id) REFERENCES patient_records(id) ON DELETE RESTRICT,
  CONSTRAINT fk_incident_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_incident_resolver FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username_key CHAR(64) NOT NULL,
  ip_key CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_login_username_time(username_key,attempted_at),
  INDEX idx_login_ip_time(ip_key,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id VARCHAR(120) NULL,
  result ENUM('success','failure') NOT NULL,
  metadata_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_time(occurred_at), INDEX idx_audit_entity(entity_type,entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(40) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
