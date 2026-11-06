-- Migración desde esquema del primer corte (v0.1.0).
-- Hacer copia de seguridad primero. Ejecutar una sola vez con un usuario administrador de MariaDB.
-- Los DDL de MariaDB pueden hacer commit implícito: si falla, revisar estado antes de reintentar.

CREATE TABLE campaign_config_versions (
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

INSERT INTO campaign_config_versions(campaign_id,version,state,activated_at,change_note)
SELECT id,config_version,IF(activated_at IS NOT NULL,'active','draft'),activated_at,'Migración desde v0.1.0'
FROM campaigns;

ALTER TABLE questions
  ADD COLUMN config_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER campaign_id,
  DROP INDEX uq_question_code,
  DROP INDEX uq_question_position,
  ADD UNIQUE KEY uq_question_code(campaign_id,config_version,code),
  ADD UNIQUE KEY uq_question_position(campaign_id,config_version,position),
  ADD CONSTRAINT fk_question_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT;

UPDATE questions
SET options_json=JSON_OBJECT(),
    validation_json=JSON_OBJECT('required',TRUE,'options_source','tsi_categories')
WHERE code='tsi';

ALTER TABLE score_rules
  ADD COLUMN config_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER campaign_id,
  DROP INDEX uq_rule,
  ADD UNIQUE KEY uq_rule(campaign_id,config_version,scale,rule_code),
  ADD CONSTRAINT fk_rule_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT;

ALTER TABLE patient_records
  ADD COLUMN record_status ENUM('valid','invalidated') NOT NULL DEFAULT 'valid',
  ADD COLUMN invalidated_at DATETIME NULL,
  ADD COLUMN invalidated_by BIGINT UNSIGNED NULL,
  ADD COLUMN invalidation_reason VARCHAR(1000) NULL,
  ADD INDEX idx_record_config(campaign_id,config_version),
  ADD CONSTRAINT fk_record_config FOREIGN KEY(campaign_id,config_version) REFERENCES campaign_config_versions(campaign_id,version) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_record_invalidator FOREIGN KEY(invalidated_by) REFERENCES users(id) ON DELETE RESTRICT;

CREATE TABLE record_incidents (
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

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username_key CHAR(64) NOT NULL,
  ip_key CHAR(64) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  succeeded TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_login_username_time(username_key,attempted_at),
  INDEX idx_login_ip_time(ip_key,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations(version) VALUES('002_configuration_history_security');
