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

// Check if form is submitted
if($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['case_id']) || empty($_POST['case_id'])) {
    header("Location: advocate-cases.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Case.php';
include_once '../classes/CaseHistory.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$case_obj = new LegalCase($db);
$history_obj = new CaseHistory($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set case ID
$case_obj->id = $_POST['case_id'];

// Read case details
if(!$case_obj->readOne()) {
    header("Location: advocate-cases.php");
    exit();
}

// Check if the case belongs to the logged-in advocate
if($case_obj->advocate_id != $advocate_obj->id) {
    header("Location: advocate-cases.php");
    exit();
}

// Set history properties
$history_obj->case_id = $case_obj->id;
$history_obj->action_type = $_POST['action_type'];
$history_obj->description = $_POST['description'];
$history_obj->performed_by = $_SESSION['user_id'];

// Add history entry
if($history_obj->create()) {
    // Redirect back to case history with success message
    header("Location: advocate-case-history.php?id=" . $case_obj->id . "&success=1");
    exit();
} else {
    // Redirect back to case history with error message
    header("Location: advocate-case-history.php?id=" . $case_obj->id . "&error=1");
    exit();
}
?>
