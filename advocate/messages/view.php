<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get user ID
$userId = $_SESSION['user_id'];

// Check if conversation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid conversation ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$conversationId = (int) $_GET['id'];

// Get database connection
$conn = getDBConnection();

// Check if the user is part of this conversation
$checkAccessQuery = "
    SELECT c.*, 
        CASE 
            WHEN c.initiator_id = ? THEN c.recipient_id
            ELSE c.initiator_id
        END as other_user_id,
        CASE 
            WHEN c.initiator_id = ? THEN ru.full_name
            ELSE iu.full_name
        END as other_user_name,
        CASE 
            WHEN c.initiator_id = ? THEN ru.user_type
            ELSE iu.user_type
        END as other_user_type,
        CASE 
            WHEN c.initiator_id = ? THEN ru.profile_image
            ELSE iu.profile_image
        END as other_user_image
    FROM conversations c
    JOIN users iu ON c.initiator_id = iu.user_id
    JOIN users ru ON c.recipient_id = ru.user_id
    WHERE c.conversation_id = ? AND (c.initiator_id = ? OR c.recipient_id = ?)
    LIMIT 1
";

$checkStmt = $conn->prepare($checkAccessQuery);
$checkStmt->bind_param("iiiiiii", $userId, $userId, $userId, $userId, $conversationId, $userId, $userId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "You don't have access to this conversation";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$conversation = $result->fetch_assoc();
$otherUserId = $conversation['other_user_id'];
$otherUserName = $conversation['other_user_name'];
$otherUserType = $conversation['other_user_type'];
$otherUserImage = $conversation['other_user_image'] ?: '/assets/img/default-profile.png';

// Get messages in this conversation
$messagesQuery = "
    SELECT m.*, u.full_name as sender_name, u.user_type as sender_type
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
";

$messagesStmt = $conn->prepare($messagesQuery);
$messagesStmt->bind_param("i", $conversationId);
$messagesStmt->execute();
$messagesResult = $messagesStmt->get_result();

// Mark unread messages as read
$markReadQuery = "
    UPDATE messages
    SET is_read = 1
    WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
";

$markReadStmt = $conn->prepare($markReadQuery);
$markReadStmt->bind_param("ii", $conversationId, $userId);
$markReadStmt->execute();

// Process new message form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $messageContent = trim($_POST['message']);

    if (!empty($messageContent)) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Add the message to the conversation
            $addMessageQuery = "
                INSERT INTO messages (conversation_id, sender_id, content, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ";

            $messageStmt = $conn->prepare($addMessageQuery);
            $messageStmt->bind_param("iis", $conversationId, $userId, $messageContent);
            $messageStmt->execute();

            // Update the conversation's updated_at timestamp
            $updateQuery = "UPDATE conversations SET updated_at = NOW() WHERE conversation_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $conversationId);
            $updateStmt->execute();

            // Create a notification for the recipient
            $senderName = $_SESSION['full_name'];
            $notificationTitle = "New Message from $senderName";
            $notificationMessage = "You have received a new message: " . substr($messageContent, 0, 100) . (strlen($messageContent) > 100 ? '...' : '');

            $notificationQuery = "
                INSERT INTO notifications (user_id, title, message, is_read, created_at, related_to, related_id)
                VALUES (?, ?, ?, 0, NOW(), 'message', ?)
            ";

            $notificationStmt = $conn->prepare($notificationQuery);
            $notificationStmt->bind_param("issi", $otherUserId, $notificationTitle, $notificationMessage, $conversationId);
            $notificationStmt->execute();

            // Commit transaction
            $conn->commit();

            // Redirect to refresh the page (prevents form resubmission)
            header("Location: view.php?id=$conversationId");
            exit;

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();

            $_SESSION['flash_message'] = "Error sending message: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
}

// Set page title
$pageTitle = "Conversation with " . $otherUserName;

// Include header
include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-2">
                    <i class="fas fa-arrow-left"></i>
                </a>
                Conversation with <?php echo htmlspecialchars($otherUserName); ?>
            </h1>
            <p class="text-gray-600">Subject: <?php echo htmlspecialchars($conversation['subject']); ?></p>
        </div>
    </div>

    <!-- Messages Container -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center">
                <?php if ($otherUserType == 'advocate'): ?>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-500">
                    <i class="fas fa-user-tie"></i>
                </div>
                <?php elseif ($otherUserType == 'client'): ?>
                <div class="w-10 h-10 rounded-full bg-gray-300 overflow-hidden">
                    <?php if ($otherUserImage): ?>
                    <img src="../../<?php echo $otherUserImage; ?>"
                        alt="<?php echo htmlspecialchars($otherUserName); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-blue-500 text-white">
                        <?php echo strtoupper(substr($otherUserName, 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-500">
                    <i class="fas fa-user-shield"></i>
                </div>
                <?php endif; ?>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($otherUserName); ?></h3>
                    <p class="text-xs text-gray-500"><?php echo ucfirst($otherUserType); ?></p>
                </div>
            </div>
        </div>

        <div class="p-4 h-96 overflow-y-auto" id="messages-container">
            <?php if ($messagesResult->num_rows === 0): ?>
            <div class="text-center py-8 text-gray-500">
                <p>No messages yet. Start the conversation!</p>
            </div>
            <?php else: ?>
            <?php
                $prevDate = null;
                while ($message = $messagesResult->fetch_assoc()):
                    $messageDate = date('Y-m-d', strtotime($message['created_at']));
                    $showDateDivider = $prevDate !== $messageDate;
                    $prevDate = $messageDate;
                    $isSentByMe = $message['sender_id'] == $userId;
                    ?>
            <?php if ($showDateDivider): ?>
            <div class="flex justify-center my-4">
                <div class="px-4 py-1 bg-gray-100 rounded-full text-xs text-gray-500">
                    <?php echo formatDate($messageDate); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-4 <?php echo $isSentByMe ? 'text-right' : ''; ?>">
                <div
                    class="inline-block max-w-3/4 px-4 py-2 rounded-lg <?php echo $isSentByMe ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                    <p class="text-sm"><?php echo nl2br(htmlspecialchars($message['content'])); ?></p>
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                    <?php if ($isSentByMe): ?>
                    <?php if ($message['is_read']): ?>
                    <span class="ml-1 text-blue-500"><i class="fas fa-check-double"></i></span>
                    <?php else: ?>
                    <span class="ml-1 text-gray-400"><i class="fas fa-check"></i></span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Message Input Form -->
        <div class="p-4 border-t border-gray-200">
            <form method="POST" action="" id="messageForm">
                <div class="flex">
                    <textarea name="message" id="message" rows="2" class="form-textarea flex-grow mr-2 resize-none"
                        placeholder="Type your message..." required></textarea>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg self-end">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to bottom of messages container
    const messagesContainer = document.getElementById('messages-container');
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Focus on message input
    document.getElementById('message').focus();

    // Submit form on Ctrl+Enter
    document.getElementById('message').addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            document.getElementById('messageForm').submit();
        }
    });
});
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include_once '../includes/footer.php';
?>