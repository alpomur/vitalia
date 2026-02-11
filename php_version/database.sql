-- Vitalia Database Schema for MySQL
-- Version: 1.0.0
-- PHP + MySQL implementation for shared hosting

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(190) NULL,
  name VARCHAR(255) NULL,
  picture VARCHAR(500) NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  auth_provider ENUM('guest','google','apple') NOT NULL DEFAULT 'guest',
  status ENUM('active','banned') NOT NULL DEFAULT 'active',
  preferred_language VARCHAR(5) NOT NULL DEFAULT 'tr',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  last_active_at DATETIME NULL,
  UNIQUE KEY uniq_email (email),
  INDEX idx_user_id (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profiles table
CREATE TABLE IF NOT EXISTS profiles (
  user_id VARCHAR(50) PRIMARY KEY,
  age SMALLINT UNSIGNED NULL,
  height_cm SMALLINT UNSIGNED NULL,
  weight_kg DECIMAL(5,2) NULL,
  goal ENUM('lose','maintain','gain','healthy') NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  session_token VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session_token (session_token),
  INDEX idx_user_id (user_id),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages table
CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id VARCHAR(50) NOT NULL UNIQUE,
  user_id VARCHAR(50) NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message_type ENUM('routine','faq','motivation','personal_complex','out_of_scope') NOT NULL,
  message_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chat_user_time (user_id, created_at),
  INDEX idx_message_id (message_id),
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily logs table
CREATE TABLE IF NOT EXISTS daily_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id VARCHAR(50) NOT NULL UNIQUE,
  user_id VARCHAR(50) NOT NULL,
  log_date DATE NOT NULL,
  mood ENUM('good','ok','bad') NULL,
  steps_level ENUM('low','medium','high') NULL,
  water_ml INT UNSIGNED NOT NULL DEFAULT 0,
  workout_done TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_date (user_id, log_date),
  INDEX idx_log_date (log_date),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weight logs table
CREATE TABLE IF NOT EXISTS weight_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id VARCHAR(50) NOT NULL UNIQUE,
  user_id VARCHAR(50) NOT NULL,
  log_date DATE NOT NULL,
  weight_kg DECIMAL(5,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_weight_date (user_id, log_date),
  INDEX idx_weight_date (log_date),
  CONSTRAINT fk_weight_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FAQ table (pre-defined answers)
CREATE TABLE IF NOT EXISTS qa_faq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic ENUM('nutrition','exercise','water','weight','motivation','outside_food','general') NOT NULL,
  tag VARCHAR(80) NOT NULL,
  question_example VARCHAR(500) NULL,
  answer_text TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_faq_tag (tag),
  INDEX idx_topic (topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Q&A cache table (dynamic caching)
CREATE TABLE IF NOT EXISTS qa_cache (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic ENUM('nutrition','exercise','water','weight','motivation','outside_food','general') NOT NULL,
  question_fingerprint CHAR(64) NOT NULL,
  normalized_question VARCHAR(500) NOT NULL,
  answer_text TEXT NOT NULL,
  hit_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cache_fp (question_fingerprint),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OpenAI usage tracking
CREATE TABLE IF NOT EXISTS openai_usage_daily (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  usage_date DATE NOT NULL,
  calls_count INT UNSIGNED NOT NULL DEFAULT 0,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_usage_user_date (user_id, usage_date),
  INDEX idx_usage_date (usage_date),
  CONSTRAINT fk_usage_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin settings table
CREATE TABLE IF NOT EXISTS admin_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security events table
CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('rate_limit','captcha','abuse','ban','login_fail') NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_id VARCHAR(50) NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sec_time (created_at),
  INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limit tracking
CREATE TABLE IF NOT EXISTS rate_limit_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(100) NOT NULL,
  identifier_type ENUM('ip','user_id') NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_identifier (identifier, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin settings
INSERT INTO admin_settings (setting_key, setting_value) VALUES
('openai_model', '"gpt-4o-mini"'),
('openai_max_tokens', '500'),
('openai_temperature', '0.7'),
('water_ml_per_kg', '30'),
('water_exercise_bonus_ml', '500'),
('glass_ml', '250'),
('steps_goal_lose', '{"min": 8000, "max": 11000}'),
('steps_goal_maintain', '{"min": 6000, "max": 8500}'),
('steps_goal_gain', '{"min": 5000, "max": 7000}'),
('weight_checkin_days', '7'),
('daily_message_limit_guest', '10'),
('daily_message_limit_user', '50'),
('daily_token_limit_guest', '2000'),
('daily_token_limit_user', '10000'),
('rate_limit_per_minute', '10'),
('cache_ttl_days', '180'),
('max_suggestions', '6'),
('anon_message_threshold', '8')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Create admin user (change email after setup)
INSERT INTO users (user_id, email, name, role, auth_provider, status, preferred_language)
VALUES ('admin_001', 'admin@yoursite.com', 'Admin', 'admin', 'google', 'active', 'tr')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
