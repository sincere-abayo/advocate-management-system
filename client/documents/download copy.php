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

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid document ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$documentId = $_GET['id'];

// Connect to database
$conn = getDBConnection();

// Get document details
$query = "
    SELECT 
        d.*,
        c.client_id
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    WHERE d.document_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();

// Check if document exists
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Document not found";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$document = $result->fetch_assoc();

// Verify the document belongs to the client
if ($document['client_id'] !== $clientId) {
    $_SESSION['flash_message'] = "You don't have permission to download this document";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Close database connection
$conn->close();

// Get file path
$filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $document['file_path'];

// Check if file exists
if (!file_exists($filePath)) {
    $_SESSION['flash_message'] = "File not found on server";
    $_SESSION['flash_type'] = "error";
    header("Location: view.php?id=" . $documentId);
    exit;
}

// Log the download activity
try {
    $conn = getDBConnection();
    
    // Add case activity
    $activityDesc = "Document downloaded: " . $document['title'];
    addCaseActivity($document['case_id'], $_SESSION['user_id'], 'document', $activityDesc);
    
    $conn->close();
} catch (Exception $e) {
    // Continue with download even if logging fails
}

// Get file information
$fileName = basename($document['file_path']);
$fileSize = filesize($filePath);
$fileType = mime_content_type($filePath);

// Extract original file name if available, otherwise use the document title
$originalFileName = $document['title'];
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$downloadFileName = sanitizeFileName($originalFileName) . '.' . $fileExtension;

// Set appropriate headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
header('Content-Length: ' . $fileSize);
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Clear output buffer
ob_clean();
flush();

// Output file
readfile($filePath);
exit;

/**
 * Sanitize a filename to make it safe for saving
 * 
 * @param string $name The original filename
 * @return string The sanitized filename
 */
function sanitizeFileName($name) {
    // Remove any character that isn't a letter, number, space, dash, or underscore
    $name = preg_replace('/[^\w\s\-\.]/', '', $name);
    
    // Replace spaces with underscores
    $name = str_replace(' ', '_', $name);
    
    // Limit length
    if (strlen($name) > 100) {
        $name = substr($name, 0, 100);
    }
    
    return $name;
}
?>