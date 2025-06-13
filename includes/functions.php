<?php

// Clean and sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate a random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Format date for display
function formatDate($date, $format = 'd M, Y') {
    return date($format, strtotime($date));
}


// Format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}
// Get user by ID
function getUserById($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get user role details
function getUserRoleDetails($userId, $userType) {
    $conn = getDBConnection();
    
    if ($userType == 'advocate') {
        $stmt = $conn->prepare("SELECT * FROM advocate_profiles WHERE user_id = ?");
    } else if ($userType == 'client') {
        $stmt = $conn->prepare("SELECT * FROM client_profiles WHERE user_id = ?");
    } else {
        return null;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Create notification
function createNotification($userId, $title, $message, $relatedTo = null, $relatedId = null) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, related_to, related_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $userId, $title, $message, $relatedTo, $relatedId);
    return $stmt->execute();
}

function getAdvocateData($userId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT u.*, ap.* 
        FROM users u
        JOIN advocate_profiles ap ON u.user_id = ap.user_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
   
    return $data;
}

// Get unread notifications count
function getUnreadNotificationsCount($userId) {
    // Create a new connection directly
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Close the connection
    $conn->close();
    
    return $data['count'];
}
// Check if user has permission
function hasPermission($requiredType) {
    if (!isset($_SESSION['user_type'])) {
        return false;
    }
    
    if ($_SESSION['user_type'] == 'admin') {
        return true;
    }
    
    return $_SESSION['user_type'] == $requiredType;
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

// Display flash message
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $baseClasses = 'p-4 mb-4 rounded-lg';
        $typeClasses = '';
        
        switch ($type) {
            case 'success':
                $typeClasses = 'bg-green-100 text-green-800 border-green-300';
                break;
            case 'error':
                $typeClasses = 'bg-red-100 text-red-800 border-red-300';
                break;
            case 'warning':
                $typeClasses = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                break;
            default: // info
                $typeClasses = 'bg-blue-100 text-blue-800 border-blue-300';
                break;
        }
        
        echo '<div class="' . $baseClasses . ' ' . $typeClasses . '">' . $message . '</div>';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}


// Get case details by ID
function getCaseById($caseId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM cases WHERE case_id = ?");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get all cases for a client
function getClientCases($clientId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM cases WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cases = [];
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
    
    return $cases;
}

// Get cases assigned to an advocate
function getAdvocateCases($advocateId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT c.* 
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        WHERE ca.advocate_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cases = [];
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
    
    return $cases;
}

// Add case activity
function addCaseActivity($caseId, $userId, $activityType, $description) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO case_activities 
        (case_id, user_id, activity_type, description) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $caseId, $userId, $activityType, $description);
    return $stmt->execute();
}

// Get case activities
function getCaseActivities($caseId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT ca.*, u.full_name, u.user_type
        FROM case_activities ca
        JOIN users u ON ca.user_id = u.user_id
        WHERE ca.case_id = ?
        ORDER BY ca.activity_date DESC
    ");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    return $activities;
}

// Get documents for a case
function getCaseDocuments($caseId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT d.*, u.full_name as uploaded_by_name
        FROM documents d
        JOIN users u ON d.uploaded_by = u.user_id
        WHERE d.case_id = ?
        ORDER BY d.upload_date DESC
    ");
    $stmt->bind_param("i", $caseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    return $documents;
}

// Get appointments for a user
function getUserAppointments($userId, $userType) {
    $conn = getDBConnection();
    
    if ($userType == 'advocate') {
        $roleId = getUserRoleDetails($userId, 'advocate')['advocate_id'];
        $stmt = $conn->prepare("
            SELECT a.*, c.full_name as client_name, cs.title as case_title
            FROM appointments a
            JOIN client_profiles cp ON a.client_id = cp.client_id
            JOIN users c ON cp.user_id = c.user_id
            LEFT JOIN cases cs ON a.case_id = cs.case_id
            WHERE a.advocate_id = ?
            ORDER BY a.appointment_date, a.start_time
        ");
        $stmt->bind_param("i", $roleId);
    } else if ($userType == 'client') {
        $roleId = getUserRoleDetails($userId, 'client')['client_id'];
        $stmt = $conn->prepare("
            SELECT a.*, adv.full_name as advocate_name, cs.title as case_title
            FROM appointments a
            JOIN advocate_profiles ap ON a.advocate_id = ap.advocate_id
            JOIN users adv ON ap.user_id = adv.user_id
            LEFT JOIN cases cs ON a.case_id = cs.case_id
            WHERE a.client_id = ?
            ORDER BY a.appointment_date, a.start_time
        ");
        $stmt->bind_param("i", $roleId);
    } else {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    return $appointments;
}

// Format currency
function formatCurrency($amount) {
    return 'RWF' . number_format($amount, 2);
}

// Check if string contains a search term
function containsSearchTerm($haystack, $needle) {
    return stripos($haystack, $needle) !== false;
}

// Generate pagination links
function generatePagination($currentPage, $totalPages, $urlPattern) {
    $links = '';
    
    if ($currentPage > 1) {
        $links .= '<a href="' . sprintf($urlPattern, $currentPage - 1) . '" class="pagination-link">&laquo; Previous</a>';
    }
    
    for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
        if ($i == $currentPage) {
            $links .= '<span class="pagination-current">' . $i . '</span>';
        } else {
            $links .= '<a href="' . sprintf($urlPattern, $i) . '" class="pagination-link">' . $i . '</a>';
        }
    }
    
    if ($currentPage < $totalPages) {
        $links .= '<a href="' . sprintf($urlPattern, $currentPage + 1) . '" class="pagination-link">Next &raquo;</a>';
    }
    
    return '<div class="pagination">' . $links . '</div>';
}

// Get user notifications
function getUserNotifications($userId, $limit = 10) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

// Mark notification as read
function markNotificationAsRead($notificationId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt->bind_param("i", $notificationId);
    return $stmt->execute();
}

// Get system setting
function getSetting($settingName, $default = null) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = ?");
    $stmt->bind_param("s", $settingName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return $default;
}
function getClientData($userId) {
    // Create a new connection directly
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("
        SELECT u.*, cp.* 
        FROM users u
        JOIN client_profiles cp ON u.user_id = cp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Close the connection
    $conn->close();
   
    return $data;
}
/**
 * Get advocate_id from user_id
 * 
 * @param int $userId The user ID
 * @return int|null The advocate ID or null if not found
 */
function getAdvocateIdFromUserId($userId) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT advocate_id FROM advocate_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['advocate_id'];
    }
    
    return null;
}

// Add this near the top of admin/cases/view.php, after the includes and before using the function

// Check if formatDateTime function exists, if not define it
if (!function_exists('formatDateTime')) {
    function formatDateTime($dateTime) {
        if (empty($dateTime)) return 'N/A';
        
        $timestamp = strtotime($dateTime);
        return date('M j, Y g:i A', $timestamp);
    }
}

// Update system setting
function updateSetting($settingName, $settingValue) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_name, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->bind_param("sss", $settingName, $settingValue, $settingValue);
    return $stmt->execute();
}
// Format date and time as a relative time string (e.g., "2 hours ago", "Yesterday", etc.)
function formatDateTimeRelative($dateTime) {
    $timestamp = strtotime($dateTime);
    $now = time();
    $diff = $now - $timestamp;
    
    // If less than 1 minute
    if ($diff < 60) {
        return 'Just now';
    }
    
    // If less than 1 hour
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
    }
    
    // If less than 24 hours
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    }
    
    // If less than 48 hours
    if ($diff < 172800) {
        return 'Yesterday';
    }
    
    // If less than 7 days
    if ($diff < 604800) {
        $days = floor($diff / 86400);
      /**
 * Get advocate_id from user_id
 * 
 * @param int $userId The user ID
 * @return int|null The advocate ID or null if not found
 */

  return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    }
    
    // If less than 30 days
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
    }
    
    // If less than 365 days
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
    }
    
    // More than a year
    $years = floor($diff / 31536000);
    return $years . ' ' . ($years == 1 ? 'year' : 'years') . ' ago';
}
