<?php
// Include required files
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an advocate
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'advocate') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get advocate data
$advocateData = getAdvocateData($_SESSION['user_id']);
$advocateId = $advocateData['advocate_id'];

// Check if client_id is provided
if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Client ID is required']);
    exit;
}

$clientId = (int)$_GET['client_id'];

// Get database connection
$conn = getDBConnection();

try {
    // Get cases for the selected client that are assigned to this advocate
    $stmt = $conn->prepare("
        SELECT c.case_id, c.title, c.case_number
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        WHERE c.client_id = ? AND ca.advocate_id = ?
        ORDER BY c.created_at DESC
    ");
    
    $stmt->bind_param("ii", $clientId, $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cases = [];
    while ($case = $result->fetch_assoc()) {
        $cases[] = $case;
    }
    
    // Return cases as JSON
    header('Content-Type: application/json');
    echo json_encode($cases);
    
} catch (Exception $e) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

// Close database connection
$conn->close();