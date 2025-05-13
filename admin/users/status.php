<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Check if user ID and action are provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['action']) || empty($_GET['action'])) {
    redirectWithMessage('index.php', 'User ID and action are required', 'error');
    exit;
}

$userId = (int)$_GET['id'];
$action = sanitizeInput($_GET['action']);

// Validate action
$validActions = ['suspend', 'activate', 'approve'];
if (!in_array($action, $validActions)) {
    redirectWithMessage('index.php', 'Invalid action', 'error');
    exit;
}

// Get database connection
$conn = getDBConnection();

// Check if user exists and get current status
$userQuery = "SELECT user_id, username, full_name, email, user_type, status FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage('index.php', 'User not found', 'error');
    exit;
}

$user = $result->fetch_assoc();

// Prevent admin from changing their own status
if ($userId === $_SESSION['user_id']) {
    redirectWithMessage('view.php?id=' . $userId, 'You cannot change your own status', 'error');
    exit;
}

// Determine new status based on action
$newStatus = '';
$actionMessage = '';

switch ($action) {
    case 'suspend':
        if ($user['status'] !== 'active') {
            redirectWithMessage('view.php?id=' . $userId, 'Only active users can be suspended', 'error');
            exit;
        }
        $newStatus = 'suspended';
        $actionMessage = 'suspended';
        break;
        
    case 'activate':
        if ($user['status'] !== 'suspended' && $user['status'] !== 'inactive') {
            redirectWithMessage('view.php?id=' . $userId, 'Only suspended or inactive users can be activated', 'error');
            exit;
        }
        $newStatus = 'active';
        $actionMessage = 'activated';
        break;
        
    case 'approve':
        if ($user['status'] !== 'pending') {
            redirectWithMessage('view.php?id=' . $userId, 'Only pending users can be approved', 'error');
            exit;
        }
        $newStatus = 'active';
        $actionMessage = 'approved';
        break;
}

// Update user status
$updateQuery = "UPDATE users SET status = ? WHERE user_id = ?";
$updateStmt = $conn->prepare($updateQuery);
$updateStmt->bind_param("si", $newStatus, $userId);

if ($updateStmt->execute()) {
    // If approving an advocate, we might need additional processing
    if ($action === 'approve' && $user['user_type'] === 'advocate') {
        // Create notification for the advocate
        $notificationTitle = "Account Approved";
        $notificationMessage = "Your advocate account has been approved. You can now log in and start using the system.";
        
        $notifyQuery = "INSERT INTO notifications (user_id, title, message, related_to, related_id) VALUES (?, ?, ?, 'account', ?)";
        $notifyStmt = $conn->prepare($notifyQuery);
        $notifyStmt->bind_param("issi", $userId, $notificationTitle, $notificationMessage, $userId);
        $notifyStmt->execute();

    }
    
 // Find any case to associate the activity with
$caseQuery = "SELECT case_id FROM cases LIMIT 1";
$caseResult = $conn->query($caseQuery);
if ($caseResult->num_rows > 0) {
    $caseId = $caseResult->fetch_assoc()['case_id'];
    
    // Log the status change in the system activity log
    $logMessage = "User status changed to " . $newStatus;
    $activityQuery = "INSERT INTO case_activities (case_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'status_change', ?)";
    $activityStmt = $conn->prepare($activityQuery);
    $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $logMessage);
    $activityStmt->execute();
}

    
    redirectWithMessage('view.php?id=' . $userId, 'User has been ' . $actionMessage . ' successfully', 'success');
} else {
    redirectWithMessage('view.php?id=' . $userId, 'Failed to update user status: ' . $conn->error, 'error');
}
?>
