-- Service Gateway - MySQL 8 reference schema
-- Canonical schema is defined by Laravel migrations in database/migrations.

CREATE TABLE migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO migrations (migration, batch) VALUES
  ('0001_01_01_000000_create_users_table', 1),
  ('0001_01_01_000001_create_cache_and_jobs_tables', 1),
  ('2026_09_04_000100_create_services_table', 1),
  ('2026_09_04_000200_create_service_fields_table', 1),
  ('2026_09_04_000300_create_service_user_table', 1),
  ('2026_09_04_000400_create_service_requests_table', 1),
  ('2026_09_04_000500_create_service_request_attempts_table', 1),
  ('2026_09_04_000600_create_audit_logs_table', 1),
  ('2026_09_05_000700_create_system_settings_table', 2);

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  phone VARCHAR(32) NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(16) NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at TIMESTAMP NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX users_role_index (role), INDEX users_is_active_index (is_active), INDEX users_last_login_at_index (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  email VARCHAR(255) PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
  id VARCHAR(255) PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX sessions_user_id_index (user_id),
  INDEX sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(128) NOT NULL UNIQUE,
  value TEXT NULL COMMENT 'Laravel encrypted string cast',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  endpoint_url VARCHAR(2048) NOT NULL,
  http_method VARCHAR(10) NOT NULL DEFAULT 'POST',
  payload_mode VARCHAR(16) NOT NULL DEFAULT 'json',
  response_format VARCHAR(16) NOT NULL DEFAULT 'json',
  headers TEXT NULL COMMENT 'Laravel encrypted array cast',
  timeout_seconds TINYINT UNSIGNED NOT NULL DEFAULT 15,
  connect_timeout_seconds TINYINT UNSIGNED NOT NULL DEFAULT 5,
  retry_times TINYINT UNSIGNED NOT NULL DEFAULT 1,
  retry_delay_ms INT UNSIGNED NOT NULL DEFAULT 200,
  rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
  allow_resubmit TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  INDEX services_active_sort_idx (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_fields (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id BIGINT UNSIGNED NOT NULL,
  direction VARCHAR(16) NOT NULL,
  `key` VARCHAR(128) NOT NULL,
  label VARCHAR(255) NOT NULL,
  type VARCHAR(24) NOT NULL DEFAULT 'text',
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  validation_rules JSON NULL,
  default_value TEXT NULL,
  json_path VARCHAR(512) NULL,
  options JSON NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT service_fields_service_fk FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY service_fields_service_direction_key_unique (service_id,direction,`key`),
  INDEX service_fields_lookup_idx (service_id,direction,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_user (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  rate_limit_per_minute INT UNSIGNED NULL,
  assigned_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT service_user_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT service_user_service_fk FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE,
  UNIQUE KEY service_user_unique (user_id,service_id), INDEX service_user_service_active_idx (service_id,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  execution_token CHAR(36) NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NOT NULL,
  input_payload LONGTEXT NOT NULL COMMENT 'Laravel encrypted array cast',
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_requested_at TIMESTAMP NULL,
  last_responded_at TIMESTAMP NULL,
  last_http_status SMALLINT UNSIGNED NULL,
  last_duration_ms INT UNSIGNED NULL,
  last_error VARCHAR(1000) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT service_requests_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT service_requests_service_fk FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE RESTRICT,
  INDEX service_requests_user_created_idx (user_id,created_at), INDEX service_requests_service_created_idx (service_id,created_at),
  INDEX service_requests_status_created_idx (status,created_at), INDEX service_requests_status_updated_idx (status,updated_at),
  INDEX service_requests_user_service_created_idx (user_id,service_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_request_id BIGINT UNSIGNED NOT NULL,
  sequence INT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'running',
  endpoint_url VARCHAR(2048) NOT NULL,
  http_method VARCHAR(10) NOT NULL,
  request_payload LONGTEXT NULL COMMENT 'Laravel encrypted array cast',
  response_payload LONGTEXT NULL COMMENT 'Laravel encrypted array cast',
  mapped_response LONGTEXT NULL COMMENT 'Stable encrypted result snapshot',
  response_raw LONGTEXT NULL COMMENT 'Laravel encrypted string cast',
  http_status SMALLINT UNSIGNED NULL,
  duration_ms INT UNSIGNED NULL,
  error_code VARCHAR(128) NULL,
  error_message TEXT NULL,
  started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT request_attempt_request_fk FOREIGN KEY(service_request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
  UNIQUE KEY service_request_attempt_sequence_unique (service_request_id,sequence),
  INDEX service_request_attempt_lookup_idx (service_request_id,created_at), INDEX service_request_attempt_status_idx(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event VARCHAR(128) NOT NULL,
  auditable_type VARCHAR(255) NULL,
  auditable_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT audit_logs_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX audit_logs_event_idx(event), INDEX audit_logs_subject_idx(auditable_type,auditable_id), INDEX audit_logs_user_created_idx(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache (
  `key` VARCHAR(255) PRIMARY KEY,
  value MEDIUMTEXT NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_locks (
  `key` VARCHAR(255) PRIMARY KEY,
  owner VARCHAR(255) NOT NULL,
  expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  INDEX jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
  id VARCHAR(255) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  total_jobs INT NOT NULL,
  pending_jobs INT NOT NULL,
  failed_jobs INT NOT NULL,
  failed_job_ids LONGTEXT NOT NULL,
  options MEDIUMTEXT NULL,
  cancelled_at INT NULL,
  created_at INT NOT NULL,
  finished_at INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
