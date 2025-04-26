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

// Check if message ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-messages.php");
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
$message_obj->id = $_GET['id'];

// Read message details
if(!$message_obj->readOne()) {
    header("Location: advocate-messages.php?error=1&msg=Message+not+found");
    exit();
}

// Check if the message belongs to the logged-in user
if($message_obj->recipient_id != $_SESSION['user_id'] && $message_obj->sender_id != $_SESSION['user_id']) {
    header("Location: advocate-messages.php?error=1&msg=Unauthorized+access");
    exit();
}

// Determine if user is sender or recipient
$is_sender = ($message_obj->sender_id == $_SESSION['user_id']);

// Mark message as deleted by sender or recipient
if($is_sender) {
    $message_obj->is_deleted_by_sender = 1;
} else {
    $message_obj->is_deleted_by_recipient = 1;
}

// Update message
if($message_obj->updateDeleteStatus()) {
    // Check if both sender and recipient have deleted the message
    if($message_obj->is_deleted_by_sender == 1 && $message_obj->is_deleted_by_recipient == 1) {
        // Permanently delete the message
        $message_obj->delete();
        
        // Delete attachment if exists
        if(!empty($message_obj->attachment)) {
            $attachment_path = '../' . $message_obj->attachment;
            if(file_exists($attachment_path)) {
                unlink($attachment_path);
            }
        }
    }
    
    // Redirect with success message
    header("Location: advocate-messages.php?success=1&msg=Message+deleted+successfully");
    exit();
} else {
    // Redirect with error message
    header("Location: advocate-messages.php?error=1&msg=Failed+to+delete+message");
    exit();
}
?>