<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has advocate role
if($_SESSION['role'] != 'advocate') {
    header("Location: ../login.php");
    exit();
}

// Check if document ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-documents.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Document.php';
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$document_obj = new Document($db);
$case_obj = new LegalCase($db);

// Set document ID
$document_obj->id = $_GET['id'];

// Read document details
if(!$document_obj->readOne()) {
    header("Location: advocate-documents.php?error=1&msg=Document+not+found");
    exit();
}

// Store file path and case ID before deletion
$file_path = '../' . $document_obj->file_path;
$case_id = $document_obj->case_id;
$document_title = $document_obj->title;

// Delete the document
if($document_obj->delete()) {
    // Delete the physical file
    if(file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Add to case history if associated with a case
    if(!empty($case_id)) {
        $case_obj->id = $case_id;
        $case_obj->addToHistory($case_id, "Document deleted", "Document '{$document_title}' was deleted");
    }
    
    // Redirect with success message
    header("Location: advocate-documents.php?success=1&msg=Document+deleted+successfully");
    exit();
} else {
    // Redirect with error message
    header("Location: advocate-documents.php?error=1&msg=Failed+to+delete+document");
    exit();
}
?>