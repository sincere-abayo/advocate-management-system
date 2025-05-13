<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['recipient_id']) || !isset($_POST['subject']) || !isset($_POST['message'])) {
    $_SESSION['flash_message'] = "Invalid request";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Get form data
$recipientId = (int)$_POST['recipient_id'];
$subject = trim($_POST['subject']);
$messageContent = trim($_POST['message']);

// Validate form data
$errors = [];

if (empty($recipientId)) {
    $errors[] = "Recipient is required";
}

if (empty($subject)) {
    $errors[] = "Subject is required";
}

if (empty($messageContent)) {
    $errors[] = "Message content is required";
}

// If there are errors, redirect back with error message
if (!empty($errors)) {
    $_SESSION['flash_message'] = implode("<br>", $errors);
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Connect to database
$conn = getDBConnection();

// Check if recipient exists and is an advocate
$recipientQuery = "
    SELECT u.user_id, u.full_name, u.user_type
    FROM users u
    WHERE u.user_id = ? AND u.user_type = 'advocate'
";
$recipientStmt = $conn->prepare($recipientQuery);
$recipientStmt->bind_param("i", $recipientId);
$recipientStmt->execute();
$recipientResult = $recipientStmt->get_result();

if ($recipientResult->num_rows === 0) {
    $_SESSION['flash_message'] = "Invalid recipient";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$recipient = $recipientResult->fetch_assoc();

// Check if a conversation already exists between these users
$existingConversationQuery = "
    SELECT conversation_id
    FROM conversations
    WHERE (initiator_id = ? AND recipient_id = ?) OR (initiator_id = ? AND recipient_id = ?)
";
$existingConversationStmt = $conn->prepare($existingConversationQuery);
$existingConversationStmt->bind_param("iiii", $userId, $recipientId, $recipientId, $userId);
$existingConversationStmt->execute();
$existingConversationResult = $existingConversationStmt->get_result();

// Begin transaction
$conn->begin_transaction();

try {
    $conversationId = null;
    
    if ($existingConversationResult->num_rows > 0) {
        // Use existing conversation
        $existingConversation = $existingConversationResult->fetch_assoc();
        $conversationId = $existingConversation['conversation_id'];
        
        // Update conversation's updated_at timestamp and subject
        $updateConversationQuery = "
            UPDATE conversations
            SET updated_at = NOW(), subject = ?
            WHERE conversation_id = ?
        ";
        $updateConversationStmt = $conn->prepare($updateConversationQuery);
        $updateConversationStmt->bind_param("si", $subject, $conversationId);
        $updateConversationStmt->execute();
    } else {
        // Create new conversation
        $createConversationQuery = "
            INSERT INTO conversations (initiator_id, recipient_id, subject, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ";
        $createConversationStmt = $conn->prepare($createConversationQuery);
        $createConversationStmt->bind_param("iis", $userId, $recipientId, $subject);
        $createConversationStmt->execute();
        
        $conversationId = $conn->insert_id;
    }
    
    // Create new message
    $createMessageQuery = "
        INSERT INTO messages (conversation_id, sender_id, content, created_at, is_read)
        VALUES (?, ?, ?, NOW(), 0)
    ";
    $createMessageStmt = $conn->prepare($createMessageQuery);
    $createMessageStmt->bind_param("iis", $conversationId, $userId, $messageContent);
    $createMessageStmt->execute();
    
    // Create notification for recipient
    $notificationTitle = "New Message";
    $notificationMessage = "You have received a new message from " . $_SESSION['full_name'];
    
    createNotification(
        $recipientId,
        $notificationTitle,
        $notificationMessage,
        'message',
        $conversationId
    );
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Message sent successfully";
    $_SESSION['flash_type'] = "success";
    
    // Redirect to conversation view
    header("Location: view.php?id=$conversationId");
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['flash_message'] = "Error sending message: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Close connection
$conn->close();
?>
