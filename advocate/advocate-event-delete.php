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

// Check if event ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-calendar.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$event_obj = new Event($db);

// Set event ID
$event_obj->id = $_GET['id'];

// Read event details
if(!$event_obj->readOne()) {
    header("Location: advocate-calendar.php");
    exit();
}

// Delete the event
if($event_obj->delete()) {
    // Redirect to calendar with success message
    header("Location: advocate-events.php?success=1&msg=Event+deleted+successfully");
    exit();
} else {
    // Redirect to event view with error message
    header("Location: advocate-events.php?id=" . $event_obj->id . "&error=1&msg=Failed+to+delete+event");
    exit();
}
?>
