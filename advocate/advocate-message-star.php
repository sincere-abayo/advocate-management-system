<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if user has advocate role
if($_SESSION['role'] != 'advocate') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if message ID and star status are provided
if(!isset($_POST['id']) || !isset($_POST['star'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Message.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize message object
$message_obj = new Message($db);

// Set message ID
$message_obj->id = $_POST['id'];

// Read message details
if(!$message_obj->readOne()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit();
}

// Check if the message belongs to the logged-in user
if($message_obj->recipient_id != $_SESSION['user_id'] && $message_obj->sender_id != $_SESSION['user_id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set star status
$message_obj->is_starred = ($_POST['star'] == '1') ? 1 : 0;

// Update message
if($message_obj->updateStarStatus()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message updated successfully']);
    exit();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update message']);
    exit();
}
?>