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

// Check if document ID is provided
if (!isset($_POST['document_id']) || empty($_POST['document_id'])) {
    redirectWithMessage('index.php', 'Invalid document ID', 'error');
    exit;
}

$documentId = (int)$_POST['document_id'];
$advocateId = $_SESSION['advocate_id'] ?? null;

// If advocate ID is not in session, get it from the database
if (!$advocateId) {
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
        redirectWithMessage('index.php', 'Advocate profile not found', 'error');
        exit;
    }
    
    $userStmt->close();
}

// Get database connection
$conn = getDBConnection();

// Get document details and verify advocate has access
$stmt = $conn->prepare("
    SELECT d.*, c.case_id, c.title as case_title
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE d.document_id = ? AND ca.advocate_id = ?
");
$stmt->bind_param("ii", $documentId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirectWithMessage('index.php', 'You do not have access to this document', 'error');
    exit;
}

$document = $result->fetch_assoc();
$stmt->close();

// Begin transaction
$conn->begin_transaction();

try {
    // Delete document record from database
    $deleteStmt = $conn->prepare("DELETE FROM documents WHERE document_id = ?");
    $deleteStmt->bind_param("i", $documentId);
    $deleteResult = $deleteStmt->execute();
    $deleteStmt->close();
    
    if (!$deleteResult) {
        throw new Exception("Failed to delete document record from database");
    }
    
    // Add case activity
    $activityDesc = "Document deleted: " . $document['title'];
    $activityStmt = $conn->prepare("
        INSERT INTO case_activities (case_id, user_id, activity_type, description)
        VALUES (?, ?, 'document', ?)
    ");
    $activityStmt->bind_param("iis", $document['case_id'], $_SESSION['user_id'], $activityDesc);
    $activityResult = $activityStmt->execute();
    $activityStmt->close();
    
    if (!$activityResult) {
        throw new Exception("Failed to log document deletion activity");
    }
    
    // Delete the physical file
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documents/' . $document['file_path'];
    
    // Only attempt to delete if file exists
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            // Log error but don't fail the transaction
            error_log("Failed to delete document file: $filePath");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    redirectWithMessage('index.php', 'Document deleted successfully', 'success');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Document deletion error: " . $e->getMessage());
    
    // Redirect with error message
    redirectWithMessage('index.php', 'Failed to delete document: ' . $e->getMessage(), 'error');
}

// Close database connection
$conn->close();
?>