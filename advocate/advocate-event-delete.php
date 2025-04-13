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
include_once '../classes/Advocate.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$event_obj = new Event($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set event ID
$event_obj->id = $_GET['id'];

// Read event details
if(!$event_obj->readOne()) {
    header("Location: advocate-calendar.php");
    exit();
}

// Check if the event belongs to the logged-in advocate
if($event_obj->advocate_id != $advocate_obj->id) {
    header("Location: advocate-calendar.php");
    exit();
}

// Delete the event
if($event_obj->delete()) {
    // Redirect to calendar with success message
    header("Location: advocate-calendar.php?success=1");
    exit();
} else {
    // Redirect to event view with error message
    header("Location: advocate-event-view.php?id=" . $event_obj->id . "&error=1");
    exit();
}
?>
