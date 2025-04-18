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

// Check if task ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-tasks.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Task.php';
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$task_obj = new Task($db);
$case_obj = new LegalCase($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set task ID
$task_obj->id = $_GET['id'];

// Read task details
if(!$task_obj->readOne()) {
    header("Location: advocate-tasks.php");
    exit();
}

// Check if the task belongs to the logged-in advocate
if($task_obj->advocate_id != $advocate_obj->id && $task_obj->assigned_to != $_SESSION['user_id']) {
    header("Location: advocate-tasks.php");
    exit();
}

// Check if task is already completed
if($task_obj->status == 'Completed') {
    // Redirect back to the task view page
    header("Location: advocate-task-view.php?id=" . $task_obj->id);
    exit();
}

// Mark task as completed
$task_obj->status = 'Completed';
$task_obj->completed_at = date('Y-m-d H:i:s');

// Update task
if($task_obj->markAsComplete()) {
    // Add to case history if associated with a case
    if($task_obj->case_id) {
        $case_obj->id = $task_obj->case_id;
        $case_obj->readOne();
        $case_obj->addToHistory($task_obj->case_id, "Task completed", "Task '{$task_obj->title}' was marked as completed");
    }
    
    // Set success message
    $_SESSION['success_message'] = "Task marked as completed successfully.";
} else {
    // Set error message
    $_SESSION['error_message'] = "Failed to mark task as completed.";
}


// Redirect back to the appropriate page
if($task_obj->case_id) {
    header("Location: advocate-case-view.php?id=" . $task_obj->case_id);
} else {
    header("Location: advocate-task-view.php?id=" . $task_obj->id);
}
exit();
?>
