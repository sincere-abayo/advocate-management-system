<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'functions.php';

// Register a new user
function registerUser($username, $password, $email, $fullName, $userType, $additionalData = []) {
    $conn = getDBConnection();
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, full_name, user_type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssss", $username, $hashedPassword, $email, $fullName, $userType);
        $stmt->execute();
        
        $userId = $conn->insert_id;
        
        // Insert role-specific data
        if ($userType == 'advocate' && isset($additionalData['license_number'])) {
            $stmt = $conn->prepare("
                INSERT INTO advocate_profiles 
                (advocate_id, user_id, license_number, specialization, experience_years) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $advocateId = $userId; // Using user_id as advocate_id for simplicity
            $specialization = $additionalData['specialization'] ?? '';
            $experienceYears = $additionalData['experience_years'] ?? 0;
            $stmt->bind_param("iissi", $advocateId, $userId, $additionalData['license_number'], $specialization, $experienceYears);
            $stmt->execute();
        } elseif ($userType == 'client') {
            $stmt = $conn->prepare("
                INSERT INTO client_profiles 
                (user_id, occupation, date_of_birth) 
                VALUES (?, ?, ?)
            ");
            $occupation = $additionalData['occupation'] ?? '';
            $dob = $additionalData['date_of_birth'] ?? null;
            $stmt->bind_param("iss", $userId, $occupation, $dob);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

// Authenticate user
function loginUser($username, $password) {
    $conn = getDBConnection();
    
    // Get user by username or email
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Check if user is active
    if ($user['status'] != 'active') {
        return ['success' => false, 'message' => 'Your account is not active. Please contact administrator.'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
    
    // Get role-specific ID
    if ($user['user_type'] == 'advocate') {
        $roleDetails = getUserRoleDetails($user['user_id'], 'advocate');
        $_SESSION['advocate_id'] = $roleDetails['advocate_id'];
    } elseif ($user['user_type'] == 'client') {
        $roleDetails = getUserRoleDetails($user['user_id'], 'client');
        $_SESSION['client_id'] = $roleDetails['client_id'];
    }
    
    return ['success' => true, 'user' => $user];
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Logout user
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: ../auth/login.php");
    exit;
}

// Require login for protected pages
function requireLogin() {
    if (!isLoggedIn()) {
        redirectWithMessage('../auth/login.php', 'Please login to access this page', 'warning');
    }
}

// Require specific user type
function requireUserType($requiredType) {
    requireLogin();
    
    if ($_SESSION['user_type'] != $requiredType && $_SESSION['user_type'] != 'admin') {
        redirectWithMessage('../index.php', 'You do not have permission to access this page', 'error');
    }
}


// Reset password
function resetPassword($userId, $newPassword) {
    $conn = getDBConnection();
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    return $stmt->execute();
}

// Generate password reset token
function generatePasswordResetToken($email) {
    $conn = getDBConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return ['success' => false, 'message' => 'Email not found'];
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store token in database
    $stmt = $conn->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
    ");
    $stmt->bind_param("issss", $userId, $token, $expires, $token, $expires);
    $stmt->execute();
    
    return [
        'success' => true, 
        'token' => $token, 
        'user_id' => $userId,
        'expires' => $expires
    ];
}

// Verify password reset token
function verifyPasswordResetToken($token) {
    $conn = getDBConnection();
    
    // Get token from database
    $stmt = $conn->prepare("
        SELECT pr.*, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.user_id
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return ['success' => false, 'message' => 'Invalid or expired token'];
    }
    
    $reset = $result->fetch_assoc();
    
    return ['success' => true, 'user_id' => $reset['user_id'], 'email' => $reset['email']];
}

// Update user profile
function updateUserProfile($userId, $data) {
    $conn = getDBConnection();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update users table
        $query = "UPDATE users SET ";
        $params = [];
        $types = "";
        
        if (isset($data['full_name'])) {
            $query .= "full_name = ?, ";
            $params[] = $data['full_name'];
            $types .= "s";
        }
        
        if (isset($data['email'])) {
            $query .= "email = ?, ";
            $params[] = $data['email'];
            $types .= "s";
        }
        
        if (isset($data['phone'])) {
            $query .= "phone = ?, ";
            $params[] = $data['phone'];
            $types .= "s";
        }
        
        if (isset($data['address'])) {
            $query .= "address = ?, ";
            $params[] = $data['address'];
            $types .= "s";
        }
        
        if (isset($data['profile_image'])) {
            $query .= "profile_image = ?, ";
            $params[] = $data['profile_image'];
            $types .= "s";
        }
        
        // Remove trailing comma and space
        $query = rtrim($query, ", ");
        
        $query .= " WHERE user_id = ?";
        $params[] = $userId;
        $types .= "i";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        // Get user type
        $user = getUserById($userId);
        $userType = $user['user_type'];
        
        // Update role-specific tables
        if ($userType == 'advocate' && isset($data['advocate'])) {
            $advocateData = $data['advocate'];
            $query = "UPDATE advocate_profiles SET ";
            $params = [];
            $types = "";
            
            if (isset($advocateData['specialization'])) {
                $query .= "specialization = ?, ";
                $params[] = $advocateData['specialization'];
                $types .= "s";
            }
            
            if (isset($advocateData['experience_years'])) {
                $query .= "experience_years = ?, ";
                $params[] = $advocateData['experience_years'];
                $types .= "i";
            }
            
            if (isset($advocateData['education'])) {
                $query .= "education = ?, ";
                $params[] = $advocateData['education'];
                $types .= "s";
            }
            
            if (isset($advocateData['bio'])) {
                $query .= "bio = ?, ";
                $params[] = $advocateData['bio'];
                $types .= "s";
            }
            
            if (isset($advocateData['hourly_rate'])) {
                $query .= "hourly_rate = ?, ";
                $params[] = $advocateData['hourly_rate'];
                $types .= "d";
            }
            
            // Remove trailing comma and space
            $query = rtrim($query, ", ");
            
            $query .= " WHERE user_id = ?";
            $params[] = $userId;
            $types .= "i";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        } elseif ($userType == 'client' && isset($data['client'])) {
            $clientData = $data['client'];
            $query = "UPDATE client_profiles SET ";
            $params = [];
            $types = "";
            
            if (isset($clientData['occupation'])) {
                $query .= "occupation = ?, ";
                $params[] = $clientData['occupation'];
                $types .= "s";
            }
            
            if (isset($clientData['date_of_birth'])) {
                $query .= "date_of_birth = ?, ";
                $params[] = $clientData['date_of_birth'];
                $types .= "s";
            }
            
            // Remove trailing comma and space
            $query = rtrim($query, ", ");
            
            $query .= " WHERE user_id = ?";
            $params[] = $userId;
            $types .= "i";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
    }
}

// Change user password
function changePassword($userId, $currentPassword, $newPassword) {
    $conn = getDBConnection();
    
    // Get current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Update password
    return resetPassword($userId, $newPassword);
}

// Get user permissions
function getUserPermissions($userId) {
    $conn = getDBConnection();
    
    // Get user type
    $user = getUserById($userId);
    
    if (!$user) {
        return [];
    }
    
    $userType = $user['user_type'];
    
    // Define permissions based on user type
    $permissions = [];
    
    if ($userType == 'admin') {
        // Admin has all permissions
        $permissions = [
            'manage_users' => true,
            'manage_cases' => true,
            'manage_appointments' => true,
            'manage_documents' => true,
            'manage_billing' => true,
            'view_reports' => true,
            'manage_settings' => true
        ];
    } elseif ($userType == 'advocate') {
        // Advocate permissions
        $permissions = [
            'manage_cases' => true,
            'manage_appointments' => true,
            'manage_documents' => true,
            'manage_billing' => true,
            'view_reports' => true,
            'manage_settings' => false,
            'manage_users' => false
        ];
    } elseif ($userType == 'client') {
        // Client permissions
        $permissions = [
            'view_cases' => true,
            'view_appointments' => true,
            'view_documents' => true,
            'view_billing' => true,
            'manage_cases' => false,
            'manage_appointments' => true,
            'manage_documents' => false,
            'manage_billing' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'manage_users' => false
        ];
    }
    
    return $permissions;
}
?>
