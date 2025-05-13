<?php
// Include required files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an advocate
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'advocate') {
    header("Location: ../../auth/login.php");
    exit;
}

// Check if hearing ID and status are provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['status']) || empty($_GET['status'])) {
    redirectWithMessage('..index.php', 'Invalid request parameters', 'error');
    exit;
}

$hearingId = (int)$_GET['id'];
$status = sanitizeInput($_GET['status']);

// Validate status
$validStatuses = ['scheduled', 'completed', 'cancelled', 'postponed'];
if (!in_array($status, $validStatuses)) {
    redirectWithMessage('..index.php', 'Invalid status value', 'error');
    exit;
}

// Get advocate ID
$advocateId = null;
if (isset($_SESSION['advocate_id'])) {
    $advocateId = $_SESSION['advocate_id'];
} else {
    // Get advocate ID from database if not in session
    $conn = getDBConnection();
    $userStmt = $conn->prepare("
        SELECT ap.advocate_id 
        FROM advocate_profiles ap 
        WHERE ap.user_id = ?
    ");
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows > 0) {
        $advocateData = $userResult->fetch_assoc();
        $advocateId = $advocateData['advocate_id'];
    } else {
        redirectWithMessage('..index.php', 'Advocate profile not found', 'error');
        exit;
    }
    
    $userStmt->close();
}

// Get database connection
$conn = getDBConnection();

// Verify advocate has access to this hearing
$accessStmt = $conn->prepare("
    SELECT h.*, c.case_id, c.title as case_title
    FROM case_hearings h
    JOIN cases c ON h.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE h.hearing_id = ? AND ca.advocate_id = ?
");
$accessStmt->bind_param("ii", $hearingId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();

if ($accessResult->num_rows === 0) {
    $accessStmt->close();
    $conn->close();
    redirectWithMessage('..index.php', 'You do not have access to this hearing', 'error');
    exit;
}

$hearing = $accessResult->fetch_assoc();
$accessStmt->close();

// Begin transaction
$conn->begin_transaction();

try {
    // Update hearing status
    $updateStmt = $conn->prepare("
        UPDATE case_hearings 
        SET status = ?, updated_at = NOW()
        WHERE hearing_id = ?
    ");
    $updateStmt->bind_param("si", $status, $hearingId);
    $updateResult = $updateStmt->execute();
    $updateStmt->close();
    
    if (!$updateResult) {
        throw new Exception("Failed to update hearing status");
    }
    
    // Add case activity
    $activityDesc = "Hearing status updated to " . ucfirst($status) . ": " . 
                    $hearing['hearing_type'] . " on " . 
                    formatDate($hearing['hearing_date']) . " at " . 
                    formatTime($hearing['hearing_time']);
    
    $activityStmt = $conn->prepare("
        INSERT INTO case_activities (case_id, user_id, activity_type, description)
        VALUES (?, ?, 'hearing', ?)
    ");
    $activityStmt->bind_param("iis", $hearing['case_id'], $_SESSION['user_id'], $activityDesc);
    $activityResult = $activityStmt->execute();
    $activityStmt->close();
    
    if (!$activityResult) {
        throw new Exception("Failed to log hearing status update activity");
    }
    
    // If status is completed, prompt for outcome if not already set
    $needOutcome = ($status === 'completed' && empty($hearing['outcome']));
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    if ($needOutcome) {
        // Redirect to edit hearing page to add outcome
        redirectWithMessage(
            '..edit-hearing.php?id=' . $hearingId . '&prompt_outcome=1', 
            'Hearing marked as completed. Please add the outcome details.', 
            'success'
        );
    } else {
        // Redirect back to hearing details
        redirectWithMessage(
            '..hearing-details.php?id=' . $hearingId, 
            'Hearing status updated successfully to ' . ucfirst($status), 
            'success'
        );
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Hearing status update error: " . $e->getMessage());
    
    // Redirect with error message
    redirectWithMessage(
        '..hearing-details.php?id=' . $hearingId, 
        'Failed to update hearing status: ' . $e->getMessage(), 
        'error'
    );
}

// Close database connection
$conn->close();
?>