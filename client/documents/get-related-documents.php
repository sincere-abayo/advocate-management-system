<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get client ID from session
$clientId = $_SESSION['client_id'];

// Initialize response array
$response = [];

// Check if case ID is provided
if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    // Return empty array if no case ID
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$caseId = (int)$_GET['case_id'];
$currentDocumentId = isset($_GET['current_id']) ? (int)$_GET['current_id'] : 0;

// Connect to database
$conn = getDBConnection();

// Verify the case belongs to the client
$caseCheckStmt = $conn->prepare("SELECT case_id FROM cases WHERE case_id = ? AND client_id = ?");
$caseCheckStmt->bind_param("ii", $caseId, $clientId);
$caseCheckStmt->execute();
if ($caseCheckStmt->get_result()->num_rows === 0) {
    // Return empty array if case doesn't belong to client
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get related documents (excluding current document)
$query = "
    SELECT 
        document_id,
        title,
        document_type,
        file_path,
        upload_date
    FROM documents
    WHERE case_id = ? AND document_id != ?
    ORDER BY upload_date DESC
    LIMIT 6
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $caseId, $currentDocumentId);
$stmt->execute();
$result = $stmt->get_result();

// Fetch documents
while ($document = $result->fetch_assoc()) {
    $response[] = $document;
}

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>