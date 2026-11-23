-- Additive extension of pickleball_booking.sql. Executed by tools/setup-database.php.
CREATE TABLE IF NOT EXISTS court_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    court_id INT NOT NULL, day_of_week TINYINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
    opens_at TIME NOT NULL, closes_at TIME NOT NULL, is_open BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (court_id,day_of_week), FOREIGN KEY (court_id) REFERENCES courts(court_id), CHECK (closes_at <> opens_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS court_maintenance (
    maintenance_id INT AUTO_INCREMENT PRIMARY KEY, court_id INT NOT NULL,
    starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, reason VARCHAR(500) NOT NULL,
    created_by INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (court_id) REFERENCES courts(court_id), FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX (court_id,starts_at,ends_at), CHECK (ends_at > starts_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS system_settings (setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(160) NOT NULL, message TEXT NOT NULL,
    entity_type VARCHAR(40) NOT NULL, entity_id INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (
    activity_id INT AUTO_INCREMENT PRIMARY KEY, actor_id INT NULL, entity_type VARCHAR(40) NOT NULL, entity_id INT NOT NULL,
    action VARCHAR(100) NOT NULL, details TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(actor_id) REFERENCES users(user_id), INDEX(entity_type,entity_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id BIGINT AUTO_INCREMENT PRIMARY KEY, identity_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(identity_hash,attempted_at)
) ENGINE=InnoDB;
