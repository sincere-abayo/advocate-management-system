<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get user ID
$userId = $_SESSION['user_id'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect to messages index if not a POST request
    header("Location: index.php");
    exit;
}

// Validate form data
$errors = [];

// Get and validate recipient ID
if (empty($_POST['recipient_id'])) {
    $errors['recipient_id'] = 'Recipient is required';
} else {
    $recipientId = (int)$_POST['recipient_id'];
}

// Get and validate subject
if (empty($_POST['subject'])) {
    $errors['subject'] = 'Subject is required';
} else {
    $subject = trim($_POST['subject']);
    if (strlen($subject) > 255) {
        $errors['subject'] = 'Subject must be less than 255 characters';
    }
}

// Get and validate message content
if (empty($_POST['message'])) {
    $errors['message'] = 'Message content is required';
} else {
    $messageContent = trim($_POST['message']);
}

// If there are errors, redirect back with error messages
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: index.php");
    exit;
}

// Get database connection
$conn = getDBConnection();

// Begin transaction
$conn->begin_transaction();

try {
    // Check if a conversation already exists between these users
    $checkConversationQuery = "
        SELECT conversation_id 
        FROM conversations 
        WHERE (initiator_id = ? AND recipient_id = ?) 
           OR (initiator_id = ? AND recipient_id = ?)
        LIMIT 1
    ";
    
    $checkStmt = $conn->prepare($checkConversationQuery);
    $checkStmt->bind_param("iiii", $userId, $recipientId, $recipientId, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Conversation exists, use existing conversation ID
        $conversation = $result->fetch_assoc();
        $conversationId = $conversation['conversation_id'];
        
        // Update the conversation's updated_at timestamp
        $updateQuery = "UPDATE conversations SET updated_at = NOW() WHERE conversation_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $conversationId);
        $updateStmt->execute();
    } else {
        // Create a new conversation
        $createConversationQuery = "
            INSERT INTO conversations (initiator_id, recipient_id, subject, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ";
        
        $createStmt = $conn->prepare($createConversationQuery);
        $createStmt->bind_param("iis", $userId, $recipientId, $subject);
        $createStmt->execute();
        
        $conversationId = $conn->insert_id;
    }
    
    // Add the message to the conversation
    $addMessageQuery = "
        INSERT INTO messages (conversation_id, sender_id, content, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ";
    
    $messageStmt = $conn->prepare($addMessageQuery);
    $messageStmt->bind_param("iis", $conversationId, $userId, $messageContent);
    $messageStmt->execute();
    
    // Create a notification for the recipient
    $senderName = $_SESSION['full_name'];
    $notificationTitle = "New Message from $senderName";
    $notificationMessage = "You have received a new message: " . substr($messageContent, 0, 100) . (strlen($messageContent) > 100 ? '...' : '');
    
    $notificationQuery = "
        INSERT INTO notifications (user_id, title, message, is_read, created_at, related_to, related_id)
        VALUES (?, ?, ?, 0, NOW(), 'message', ?)
    ";
    
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("issi", $recipientId, $notificationTitle, $notificationMessage, $conversationId);
    $notificationStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Message sent successfully";
    $_SESSION['flash_type'] = "success";
    
    // Redirect to the conversation view
    header("Location: view.php?id=$conversationId");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message
    $_SESSION['flash_message'] = "Error sending message: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    
    // Redirect back to messages index
    header("Location: index.php");
    exit;
}