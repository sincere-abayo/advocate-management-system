-- Create database
CREATE DATABASE advocate_management_system;
USE advocate_management_system;

-- Users table (for all user types)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('admin', 'advocate', 'client') NOT NULL,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'
);

-- Advocate profiles (extends users)
CREATE TABLE advocate_profiles (
    advocate_id INT PRIMARY KEY,
    user_id INT UNIQUE,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    specialization VARCHAR(100),
    experience_years INT,
    education TEXT,
    bio TEXT,
    hourly_rate DECIMAL(10, 2),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Client profiles (extends users)
CREATE TABLE client_profiles (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    occupation VARCHAR(100),
    date_of_birth DATE,
    reference_source VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Cases table
CREATE TABLE cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    case_type VARCHAR(100) NOT NULL,
    court VARCHAR(100),
    filing_date DATE,
    hearing_date DATE,
    status ENUM('pending', 'active', 'closed', 'won', 'lost', 'settled') NOT NULL DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    client_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES client_profiles(client_id)
);

-- Case assignments (many-to-many relationship between advocates and cases)
CREATE TABLE case_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    advocate_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role VARCHAR(50) DEFAULT 'primary',
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id) ON DELETE CASCADE,
    UNIQUE KEY unique_case_advocate (case_id, advocate_id)
);

-- Case updates/activities
CREATE TABLE case_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type ENUM('update', 'document', 'hearing', 'note', 'status_change') NOT NULL,
    description TEXT NOT NULL,
    activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Documents table
CREATE TABLE documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    document_type VARCHAR(100),
    description TEXT,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
);

-- Appointments table
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    advocate_id INT NOT NULL,
    client_id INT NOT NULL,
    case_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id),
    FOREIGN KEY (client_id) REFERENCES client_profiles(client_id),
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    related_to VARCHAR(50),
    related_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Billing table
CREATE TABLE billings (
    billing_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT,
    client_id INT NOT NULL,
    advocate_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    billing_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES client_profiles(client_id),
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id)
);

-- System settings
CREATE TABLE settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE contact_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    company VARCHAR(100),
    message TEXT NOT NULL,
    request_type VARCHAR(50) NOT NULL,
    status ENUM('new', 'in_progress', 'completed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    subscription_date DATETIME NOT NULL,
    unsubscription_date DATETIME NULL,
    source VARCHAR(50) DEFAULT 'website'
);
ALTER TABLE users ADD COLUMN verification_token VARCHAR(64);

-- Insert default admin user
INSERT INTO users (username, password, email, full_name, user_type) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'System Administrator', 'admin');

ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'active';
ALTER TABLE advocate_profiles MODIFY advocate_id INT AUTO_INCREMENT;
ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(64) NULL,
ADD COLUMN reset_token_expiry DATETIME NULL;

-- Add fields to track case financials
ALTER TABLE cases ADD COLUMN total_income DECIMAL(10, 2) DEFAULT 0;
ALTER TABLE cases ADD COLUMN total_expenses DECIMAL(10, 2) DEFAULT 0;
ALTER TABLE cases ADD COLUMN profit DECIMAL(10, 2) DEFAULT 0;

-- Create table for case expenses
CREATE TABLE case_expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    advocate_id INT NOT NULL,
    expense_date DATE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    receipt_file VARCHAR(255),
    expense_category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id)
);

-- Create table for case income
CREATE TABLE case_income (
    income_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    advocate_id INT NOT NULL,
    income_date DATE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    income_category VARCHAR(100),
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id)
);

-- Create table for advocate activities/time tracking
CREATE TABLE advocate_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    advocate_id INT NOT NULL,
    case_id INT,
    activity_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    hours_spent DECIMAL(5, 2) NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    billable BOOLEAN DEFAULT TRUE,
    billing_rate DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id),
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE SET NULL
);

-- Create table for non-case income
CREATE TABLE advocate_other_income (
    income_id INT AUTO_INCREMENT PRIMARY KEY,
    advocate_id INT NOT NULL,
    income_date DATE NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    income_category VARCHAR(100),
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (advocate_id) REFERENCES advocate_profiles(advocate_id)
);
-- Case hearings table
CREATE TABLE case_hearings (
    hearing_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    hearing_date DATE NOT NULL,
    hearing_time TIME NOT NULL,
    hearing_type VARCHAR(100) NOT NULL,
    court_room VARCHAR(100),
    judge VARCHAR(100),
    description TEXT,
    outcome TEXT,
    next_steps TEXT,
    status ENUM('scheduled', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);
-- Create table for billing items
CREATE TABLE billing_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    rate DECIMAL(10, 2) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (billing_id) REFERENCES billings(billing_id) ON DELETE CASCADE
);
-- Create table for payments
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (billing_id) REFERENCES billings(billing_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);
-- Create table for conversations
CREATE TABLE conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    initiator_id INT NOT NULL,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (initiator_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create table for messages
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Add fields to advocate_profiles for financial tracking
ALTER TABLE advocate_profiles ADD COLUMN total_income_ytd DECIMAL(12, 2) DEFAULT 0;
ALTER TABLE advocate_profiles ADD COLUMN total_expenses_ytd DECIMAL(12, 2) DEFAULT 0;
ALTER TABLE advocate_profiles ADD COLUMN profit_ytd DECIMAL(12, 2) DEFAULT 0;

