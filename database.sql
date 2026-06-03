-- ═══════════════════════════════════════════════════════════════════════════════
-- FA PRINT DATABASE SCHEMA
-- Production-ready MySQL schema with all required tables and relationships
-- ═══════════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS faprint;
USE faprint;

-- ───────────────────────────────────────────────────────────────────────────────
-- USERS TABLE (Students, Vendors, Admins)
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'vendor', 'admin') NOT NULL DEFAULT 'student',
    regno VARCHAR(50) DEFAULT NULL COMMENT 'Registration number for students',
    otp VARCHAR(10) DEFAULT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_verified (verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- VENDORS TABLE (Printing shops)
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    shop_name VARCHAR(100) NOT NULL,
    price_bw DECIMAL(10, 2) DEFAULT 100.00 COMMENT 'Price per page for B&W printing',
    price_color DECIMAL(10, 2) DEFAULT 500.00 COMMENT 'Price per page for color printing',
    status ENUM('pending', 'approved', 'suspended') DEFAULT 'pending',
    latitude DECIMAL(10, 8) DEFAULT NULL COMMENT 'GPS latitude coordinate',
    longitude DECIMAL(11, 8) DEFAULT NULL COMMENT 'GPS longitude coordinate',
    location VARCHAR(255) DEFAULT NULL COMMENT 'Human-readable location name',
    photos TEXT DEFAULT NULL COMMENT 'JSON array of photo URLs',
    queue_status ENUM('active', 'busy', 'closed') DEFAULT 'active',
    queue_count INT DEFAULT 0 COMMENT 'Current number of orders in queue',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_location (latitude, longitude),
    INDEX idx_queue_status (queue_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- ORDERS TABLE
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    student_id INT NOT NULL,
    vendor_id INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    payment_method VARCHAR(50) DEFAULT NULL COMMENT 'e.g., mpesa, card, cash',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- DOCUMENTS TABLE (Files linked to orders)
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL COMMENT 'Original filename',
    stored_name VARCHAR(255) NOT NULL COMMENT 'Sanitized stored filename',
    file_path VARCHAR(255) NOT NULL COMMENT 'Path relative to uploads directory',
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL COMMENT 'File size in bytes',
    copies INT DEFAULT 1 COMMENT 'Number of copies to print',
    color_mode ENUM('bw', 'color') DEFAULT 'bw',
    binding BOOLEAN DEFAULT FALSE COMMENT 'Whether binding is required',
    notes TEXT DEFAULT NULL COMMENT 'Special printing instructions',
    status ENUM('pending', 'downloaded', 'printed', 'completed') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- SUPPORT TICKETS TABLE
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    response TEXT DEFAULT NULL COMMENT 'Admin response to ticket',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- PAYMENT TRANSACTIONS TABLE
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL COMMENT 'mpesa, card, cash, etc.',
    transaction_id VARCHAR(100) DEFAULT NULL COMMENT 'External transaction ID',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    response_data JSON DEFAULT NULL COMMENT 'Payment gateway response',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- AUDIT LOG TABLE
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL COMMENT 'e.g., order, vendor, user',
    entity_id INT NOT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────────────────────────
-- NOTIFICATIONS TABLE
-- ───────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'e.g., order_status, payment, support',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50) DEFAULT NULL,
    related_entity_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP DEFAULT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- SAMPLE DATA (Optional - for testing)
-- ═══════════════════════════════════════════════════════════════════════════════

-- Insert admin user (password: admin123)
INSERT IGNORE INTO users (name, email, phone, password, role, verified)
VALUES ('Admin User', 'admin@faprint.local', '255700000000', 
        '$2y$10$YourHashedPasswordHere', 'admin', 1);

-- Insert sample student (password: student123)
INSERT IGNORE INTO users (name, email, phone, password, role, regno, verified)
VALUES ('John Student', 'student@faprint.local', '255700000001',
        '$2y$10$YourHashedPasswordHere', 'student', 'STU001', 1);

-- Insert sample vendor (password: vendor123)
INSERT IGNORE INTO users (name, email, phone, password, role, verified)
VALUES ('Jane Vendor', 'vendor@faprint.local', '255700000002',
        '$2y$10$YourHashedPasswordHere', 'vendor', NULL, 1);

-- Insert vendor details for sample vendor
INSERT IGNORE INTO vendors (user_id, shop_name, price_bw, price_color, status, latitude, longitude, location)
VALUES (3, 'Quick Print Shop', 150, 250, 'approved', -6.7924, 37.6662, 'CUoM Campus');

-- ═══════════════════════════════════════════════════════════════════════════════
-- END OF SCHEMA
-- ═══════════════════════════════════════════════════════════════════════════════
