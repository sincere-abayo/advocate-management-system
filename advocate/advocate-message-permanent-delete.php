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
    header("Location: advocate-messages-trash.php");
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
    header("Location: advocate-messages-trash.php?error=1&msg=Message+not+found");
    exit();
}

// Check if the message belongs to the logged-in user
if($message_obj->recipient_id != $_SESSION['user_id'] && $message_obj->sender_id != $_SESSION['user_id']) {
    header("Location: advocate-messages-trash.php?error=1&msg=Unauthorized+access");
    exit();
}

// Check if the message is in trash
$is_sender = ($message_obj->sender_id == $_SESSION['user_id']);
if(($is_sender && $message_obj->is_deleted_by_sender != 1) || 
   (!$is_sender && $message_obj->is_deleted_by_recipient != 1)) {
    header("Location: advocate-messages-trash.php?error=1&msg=Message+is+not+in+trash");
    exit();
}

// Permanently delete the message if both sender and recipient have deleted it
// or if the other party doesn't exist anymore
if(($message_obj->is_deleted_by_sender == 1 && $message_obj->is_deleted_by_recipient == 1) ||
   ($is_sender && $message_obj->recipient_id == 0) ||
   (!$is_sender && $message_obj->sender_id == 0)) {
    // Permanently delete the message
    if($message_obj->delete()) {
        header("Location: advocate-messages-trash.php?success=1&msg=Message+permanently+deleted");
        exit();
    } else {
        header("Location: advocate-messages-trash.php?error=1&msg=Failed+to+delete+message");
        exit();
    }
} else {
    // If only one party has deleted it, just mark it as deleted for that party
    // This is already done when the message was moved to trash, so just redirect back
    header("Location: advocate-messages-trash.php?info=1&msg=Message+remains+in+trash+for+you+but+is+still+available+to+the+other+party");
    exit();
}
?>
