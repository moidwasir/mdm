-- MDM System Database Schema
-- Run this file to create the database and tables

CREATE DATABASE IF NOT EXISTS mdm_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mdm_system;

-- ============================================================
-- ADMINS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'viewer') NOT NULL DEFAULT 'admin',
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- POLICIES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    allowed_apps JSON DEFAULT NULL,
    kiosk_mode TINYINT(1) NOT NULL DEFAULT 1,
    kiosk_app VARCHAR(255) DEFAULT 'com.mdm.chat',
    disable_play_store TINYINT(1) NOT NULL DEFAULT 1,
    disable_camera TINYINT(1) NOT NULL DEFAULT 0,
    disable_bluetooth TINYINT(1) NOT NULL DEFAULT 0,
    disable_wifi_config TINYINT(1) NOT NULL DEFAULT 0,
    disable_usb TINYINT(1) NOT NULL DEFAULT 0,
    disable_screen_capture TINYINT(1) NOT NULL DEFAULT 0,
    disable_factory_reset TINYINT(1) NOT NULL DEFAULT 1,
    password_policy ENUM('none', 'pin', 'password', 'complex') NOT NULL DEFAULT 'pin',
    min_password_length INT NOT NULL DEFAULT 4,
    auto_lock_timeout INT NOT NULL DEFAULT 300 COMMENT 'seconds',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DEVICES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    imei VARCHAR(20) NOT NULL UNIQUE,
    imei2 VARCHAR(20) DEFAULT NULL,
    serial_number VARCHAR(50) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    manufacturer VARCHAR(100) DEFAULT NULL,
    os_version VARCHAR(20) DEFAULT NULL,
    mdm_agent_version VARCHAR(20) DEFAULT NULL,
    chat_app_version VARCHAR(20) DEFAULT NULL,
    device_name VARCHAR(100) DEFAULT NULL,
    enrollment_status ENUM('pending', 'enrolled', 'unenrolled', 'blocked') NOT NULL DEFAULT 'pending',
    policy_id INT DEFAULT NULL,
    assigned_user_id INT DEFAULT NULL,
    last_heartbeat TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    battery_level INT DEFAULT NULL,
    storage_free_mb INT DEFAULT NULL,
    storage_total_mb INT DEFAULT NULL,
    wifi_ssid VARCHAR(100) DEFAULT NULL,
    network_type VARCHAR(20) DEFAULT NULL,
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    is_kiosk_active TINYINT(1) NOT NULL DEFAULT 0,
    is_locked TINYINT(1) NOT NULL DEFAULT 0,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    enrolled_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE SET NULL,
    INDEX idx_enrollment_status (enrollment_status),
    INDEX idx_is_online (is_online),
    INDEX idx_last_heartbeat (last_heartbeat)
) ENGINE=InnoDB;

-- ============================================================
-- USERS TABLE (Chat Users)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    pin_hash VARCHAR(255) DEFAULT NULL COMMENT 'optional PIN for chat login',
    device_id INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Add foreign key back to devices for assigned_user_id
ALTER TABLE devices ADD CONSTRAINT fk_device_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- ============================================================
-- CONVERSATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('direct', 'group') NOT NULL DEFAULT 'direct',
    name VARCHAR(100) DEFAULT NULL COMMENT 'for group chats',
    avatar VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CONVERSATION MEMBERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS conversation_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    is_muted TINYINT(1) NOT NULL DEFAULT 0,
    last_read_message_id INT DEFAULT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (conversation_id, user_id)
) ENGINE=InnoDB;

-- ============================================================
-- MESSAGES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT DEFAULT NULL,
    message_type ENUM('text', 'image', 'file', 'voice', 'system') NOT NULL DEFAULT 'text',
    media_url VARCHAR(500) DEFAULT NULL,
    media_size INT DEFAULT NULL COMMENT 'bytes',
    reply_to_id INT DEFAULT NULL,
    status ENUM('sent', 'delivered', 'read') NOT NULL DEFAULT 'sent',
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL,
    INDEX idx_conversation_created (conversation_id, created_at),
    INDEX idx_sender (sender_id)
) ENGINE=InnoDB;

-- ============================================================
-- ENROLLMENT TOKENS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS enrollment_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    qr_data TEXT DEFAULT NULL,
    policy_id INT DEFAULT NULL,
    device_id INT DEFAULT NULL COMMENT 'pre-assigned device if any',
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_by_imei VARCHAR(20) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE SET NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- DEVICE LOGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS device_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    event_type ENUM('enrollment', 'heartbeat', 'policy_applied', 'app_installed', 'app_removed', 'command_received', 'command_executed', 'violation', 'error', 'location_update') NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_event (device_id, event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- DEVICE COMMANDS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS device_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    command_type ENUM('lock', 'unlock', 'wipe', 'restart', 'update_policy', 'install_app', 'uninstall_app', 'ring', 'message') NOT NULL,
    payload JSON DEFAULT NULL,
    status ENUM('pending', 'sent', 'executed', 'failed') NOT NULL DEFAULT 'pending',
    issued_by INT DEFAULT NULL,
    executed_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_device_pending (device_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- APP VERSIONS TABLE (for OTA updates)
-- ============================================================
CREATE TABLE IF NOT EXISTS app_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_name VARCHAR(100) NOT NULL,
    package_name VARCHAR(255) NOT NULL,
    version_code INT NOT NULL,
    version_name VARCHAR(20) NOT NULL,
    apk_path VARCHAR(500) NOT NULL,
    apk_size INT NOT NULL COMMENT 'bytes',
    changelog TEXT DEFAULT NULL,
    is_latest TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_package_latest (package_name, is_latest)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin (password: admin123)
INSERT INTO admins (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@mdm.local', '$2y$10$0WuHrLX4FQISgi33WS6PPuKADqJwXX2g4ntBmWG8D/gXWHz2XllBW', 'System Administrator', 'super_admin');

-- Default policy
INSERT INTO policies (name, description, kiosk_mode, kiosk_app, disable_play_store, disable_factory_reset, password_policy, is_default) VALUES
('Default Lockdown', 'Standard MDM lockdown policy - Chat app only, all other apps disabled', 1, 'com.mdm.chat', 1, 1, 'pin', 1);

-- Relaxed policy (for testing)
INSERT INTO policies (name, description, kiosk_mode, kiosk_app, disable_play_store, disable_factory_reset, password_policy) VALUES
('Testing Mode', 'Relaxed policy for testing - allows more device access', 0, 'com.mdm.chat', 0, 0, 'none');
