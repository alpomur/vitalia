-- Vitalia Database Schema for MySQL
-- PHP + MySQL implementation for shared hosting
-- Version: 2.0

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ==================== KULLANICI TABLOLARI ====================

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

-- ==================== CHAT TABLOLARI ====================

-- Chat messages table
CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id VARCHAR(50) NOT NULL UNIQUE,
  user_id VARCHAR(50) NOT NULL,
  role ENUM('user','assistant','system') NOT NULL,
  message_type ENUM('routine','faq','motivation','personal_complex','out_of_scope') NOT NULL DEFAULT 'personal_complex',
  message_text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chat_user_time (user_id, created_at),
  INDEX idx_message_id (message_id),
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== LOG TABLOLARI ====================

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

-- ==================== CACHE TABLOLARI ====================

-- FAQ table (pre-defined answers)
CREATE TABLE IF NOT EXISTS qa_faq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic ENUM('nutrition','exercise','water','weight','motivation','outside_food','general') NOT NULL DEFAULT 'general',
  tag VARCHAR(80) NOT NULL,
  question_example VARCHAR(500) NULL,
  answer_text TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_faq_tag (tag),
  INDEX idx_topic (topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Q&A cache table (dynamic caching)
CREATE TABLE IF NOT EXISTS qa_cache (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic ENUM('nutrition','exercise','water','weight','motivation','outside_food','general') NOT NULL DEFAULT 'general',
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

-- ==================== ADMIN TABLOLARI ====================

-- Admin settings table
CREATE TABLE IF NOT EXISTS admin_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_value MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- ==================== GÜVENLİK TABLOLARI ====================

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
  identifier_type ENUM('ip','user_id') NOT NULL DEFAULT 'ip',
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  window_start DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_identifier_window (identifier, window_start),
  INDEX idx_identifier (identifier, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== SPOR PLAN TABLOLARI ====================

-- Workout plans table
CREATE TABLE IF NOT EXISTS workout_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  level ENUM('beginner','intermediate','advanced') NOT NULL,
  location ENUM('home','gym') NOT NULL,
  days_per_week TINYINT UNSIGNED NOT NULL DEFAULT 3,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_level_location (level, location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout plan days
CREATE TABLE IF NOT EXISTS workout_plan_days (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id VARCHAR(50) NOT NULL,
  day_number TINYINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  INDEX idx_plan_day (plan_id, day_number),
  CONSTRAINT fk_plan_day FOREIGN KEY (plan_id) REFERENCES workout_plans(plan_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Workout exercises
CREATE TABLE IF NOT EXISTS workout_exercises (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_day_id BIGINT UNSIGNED NOT NULL,
  exercise_name VARCHAR(255) NOT NULL,
  sets TINYINT UNSIGNED NOT NULL DEFAULT 3,
  reps VARCHAR(50) NOT NULL,
  rest_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  notes TEXT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_exercise_day FOREIGN KEY (plan_day_id) REFERENCES workout_plan_days(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== DIŞARIDA YEMEK REHBERİ ====================

-- Outside food categories
CREATE TABLE IF NOT EXISTS outside_food_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_key VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(50) NOT NULL DEFAULT 'fa-utensils',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outside food recommendations
CREATE TABLE IF NOT EXISTS outside_food_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id BIGINT UNSIGNED NOT NULL,
  item_type ENUM('best','avoid','tip') NOT NULL,
  content TEXT NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_food_category FOREIGN KEY (category_id) REFERENCES outside_food_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== MOTİVASYON ŞABLONLARI ====================

-- Motivation templates
CREATE TABLE IF NOT EXISTS motivation_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trigger_type ENUM('water_goal','workout_done','streak','weight_loss','check_in') NOT NULL,
  language VARCHAR(5) NOT NULL DEFAULT 'tr',
  message_text TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_trigger_lang (trigger_type, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== DEFAULT VERILER ====================

-- Admin settings defaults
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

-- Sample FAQ entries
INSERT INTO qa_faq (topic, tag, question_example, answer_text, is_active) VALUES
('water', 'günlük su', 'Günde ne kadar su içmeliyim?', 'Günlük su ihtiyacın kilona göre değişir. Genel kural: kilo (kg) x 30 ml. Örneğin 70 kg için yaklaşık 2.1 litre (8-9 bardak). Egzersiz günlerinde 500ml daha ekle! 💧', 1),
('exercise', 'başlangıç spor', 'Spora yeni başlıyorum ne yapmalıyım?', 'Harika bir karar! 🎉 Başlangıç için haftada 3 gün, 20-30 dakika yeterli. Önce yürüyüş ve basit vücut ağırlığı egzersizleriyle başla. Spor Planları sayfasından sana uygun programı seçebilirsin!', 1),
('nutrition', 'protein', 'Protein ne kadar almalıyım?', 'Aktif bireyler için günlük 1.6-2.2 g/kg protein önerilir. Kaynak olarak: tavuk, balık, yumurta, süt ürünleri, baklagiller. Her öğünde avuç içi kadar protein hedefle! 💪', 1),
('weight', 'kilo verme', 'Sağlıklı kilo verme hızı nedir?', 'Haftada 0.5-1 kg sağlıklı kabul edilir. Daha hızlısı kas kaybına yol açabilir. Sabırlı ol, tutarlı kal. Küçük adımlar büyük sonuçlar doğurur! 🎯', 1),
('motivation', 'motivasyon', 'Motivasyonum düşük ne yapmalıyım?', 'Bu tamamen normal! Küçük hedefler koy, her başarıyı kutla. Bugün sadece bir bardak su daha iç veya 10 dakika yürü. Mükemmel olmak değil, devam etmek önemli! Sen yapabilirsin! 💪✨', 1)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Sample outside food categories
INSERT INTO outside_food_categories (category_key, name, icon, sort_order) VALUES
('fast_food', 'Fast Food', 'fa-burger', 1),
('pizza', 'Pizza', 'fa-pizza-slice', 2),
('kebap', 'Kebapçı', 'fa-utensils', 3),
('asian', 'Uzak Doğu', 'fa-bowl-rice', 4),
('cafe', 'Kafe / Salata', 'fa-leaf', 5),
('breakfast', 'Kahvaltıcı', 'fa-egg', 6)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Sample motivation templates
INSERT INTO motivation_templates (trigger_type, language, message_text) VALUES
('water_goal', 'tr', 'Bugünkü su hedefine ulaştın! 🎉💧 Vücudun sana teşekkür ediyor!'),
('water_goal', 'en', 'You reached your water goal today! 🎉💧 Your body thanks you!'),
('workout_done', 'tr', 'Antrenmanı tamamladın! 💪 Her egzersiz seni hedefine yaklaştırıyor!'),
('workout_done', 'en', 'Workout complete! 💪 Every exercise brings you closer to your goal!'),
('streak', 'tr', 'Üst üste 7 gün! 🔥 Tutarlılık başarının anahtarı!'),
('streak', 'en', '7 days in a row! 🔥 Consistency is the key to success!'),
('check_in', 'tr', 'Bugün de buradasın! 🌟 Küçük adımlar büyük değişimler yaratır!'),
('check_in', 'en', 'You showed up today! 🌟 Small steps create big changes!')
ON DUPLICATE KEY UPDATE message_text = VALUES(message_text);

-- Admin user
INSERT INTO users (user_id, email, name, role, auth_provider, status, preferred_language)
VALUES ('admin_001', 'admin@vitalia.com', 'Admin', 'admin', 'google', 'active', 'tr')
ON DUPLICATE KEY UPDATE name = 'Admin';
