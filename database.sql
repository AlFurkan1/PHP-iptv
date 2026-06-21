CREATE DATABASE IF NOT EXISTS iptv_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iptv_system;

-- Admins
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Channels
CREATE TABLE IF NOT EXISTS channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    stream_type ENUM('M3U8','MP4','RTMP','Dash','YouTube','Restream','TS','Live') NOT NULL DEFAULT 'M3U8',
    url TEXT NOT NULL,
    stream_key VARCHAR(255) DEFAULT NULL,
    ingest_url VARCHAR(255) DEFAULT NULL,
    yt_format VARCHAR(50) DEFAULT NULL,
    direct_play TINYINT(1) NOT NULL DEFAULT 0,
    logo VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    total_views INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Live sessions (track online viewers)
CREATE TABLE IF NOT EXISTS live_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    channel_id INT DEFAULT NULL,
    current_page VARCHAR(255) DEFAULT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Traffic stats (for bandwidth chart)
CREATE TABLE IF NOT EXISTS traffic_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recorded_at DATETIME NOT NULL,
    bytes_in BIGINT UNSIGNED NOT NULL,
    bytes_out BIGINT UNSIGNED NOT NULL,
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB;

-- Default admin (password: admin123)
INSERT IGNORE INTO admins (username, password, role) VALUES
('admin', '$2y$10$CFPLkffrY34uRiARFpFMC.9bKWBCMZcHh1WYH03UVEpD6hl5cRBW2', 'superadmin');
