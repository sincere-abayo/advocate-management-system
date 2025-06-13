<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if conversation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid conversation ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$conversationId = (int)$_GET['id'];

// Connect to database
$conn = getDBConnection();

/// Check if conversation exists and user is a participant
$conversationQuery = "
    SELECT 
        c.*,
        CASE 
            WHEN c.initiator_id = ? THEN c.recipient_id
            ELSE c.initiator_id
        END as other_user_id,
        CASE 
            WHEN c.initiator_id = ? THEN ru.full_name
            ELSE iu.full_name
        END as other_user_name,
        CASE 
            WHEN c.initiator_id = ? THEN ru.profile_image
            ELSE iu.profile_image
        END as other_user_image,
        CASE 
            WHEN c.initiator_id = ? THEN ru.user_type
            ELSE iu.user_type
        END as other_user_type
    FROM conversations c
    JOIN users iu ON c.initiator_id = iu.user_id
    JOIN users ru ON c.recipient_id = ru.user_id
    WHERE c.conversation_id = ? AND (c.initiator_id = ? OR c.recipient_id = ?)
";

$conversationStmt = $conn->prepare($conversationQuery);
$conversationStmt->bind_param("iiiiiii", $userId, $userId, $userId, $userId, $conversationId, $userId, $userId);
$conversationStmt->execute();
$conversationResult = $conversationStmt->get_result();

// Check if conversation exists and user is a participant
if ($conversationResult->num_rows === 0) {
    $_SESSION['flash_message'] = "Conversation not found or you don't have permission to view it";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$conversation = $conversationResult->fetch_assoc();
$otherUserId = $conversation['other_user_id'];
$otherUserName = $conversation['other_user_name'];
$otherUserType = $conversation['other_user_type'];
$otherUserImage = $conversation['other_user_image'] ?: '/assets/img/default-profile.png';

// Mark all messages from the other user as read
$markReadQuery = "
    UPDATE messages
    SET is_read = 1
    WHERE conversation_id = ? AND sender_id = ? AND is_read = 0
";
$markReadStmt = $conn->prepare($markReadQuery);
$markReadStmt->bind_param("ii", $conversationId, $otherUserId);
$markReadStmt->execute();

// Get messages for this conversation
$messagesQuery = "
    SELECT 
        m.*,
        u.full_name as sender_name,
        u.profile_image as sender_image,
        u.user_type as sender_type
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
";
$messagesStmt = $conn->prepare($messagesQuery);
$messagesStmt->bind_param("i", $conversationId);
$messagesStmt->execute();
$messagesResult = $messagesStmt->get_result();

// Process form submission for new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty($_POST['message'])) {
    $messageContent = trim($_POST['message']);
    
    // Insert new message
    $insertQuery = "
        INSERT INTO messages (conversation_id, sender_id, content, is_read)
        VALUES (?, ?, ?, 0)
    ";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("iis", $conversationId, $userId, $messageContent);
    
    if ($insertStmt->execute()) {
        // Update conversation's updated_at timestamp
        $updateQuery = "
            UPDATE conversations
            SET updated_at = NOW()
            WHERE conversation_id = ?
        ";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $conversationId);
        $updateStmt->execute();
        
        // Create notification for the other user
        $notificationTitle = "New Message";
        $notificationMessage = "You have received a new message from " . $_SESSION['full_name'];
        
        createNotification(
            $otherUserId,
            $notificationTitle,
            $notificationMessage,
            'message',
            $conversationId
        );
        
        // Redirect to avoid form resubmission
        header("Location: view.php?id=$conversationId");
        exit;
    }
}



// Set page title
$pageTitle = "Conversation with " . $otherUserName;
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i> Back to Messages
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Conversation Header -->
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-gray-300 overflow-hidden mr-3">
                    <?php if ($otherUserImage): ?>
                        <img src="../../uploads/profiles/<?php echo $otherUserImage; ?>" alt="<?php echo htmlspecialchars($otherUserName); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-blue-500 text-white">
                            <?php echo strtoupper(substr($otherUserName, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($otherUserName); ?></h2>
                    <p class="text-sm text-gray-600"><?php echo ucfirst($otherUserType); ?></p>
                </div>
            </div>
            <div>
            <?php 
    $advocateId = $otherUserType === 'advocate' ? getAdvocateIdFromUserId($otherUserId) : '';
    echo "<!-- Debug: otherUserType=$otherUserType, otherUserId=$otherUserId, advocateId=$advocateId -->";
?>
<a href="../appointments/request.php?advocate_id=<?php echo $advocateId; ?>" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-1.5 px-3 rounded-lg inline-flex items-center">
    <i class="fas fa-calendar-plus mr-1"></i> Schedule Appointment
</a>



            </div>
        </div>
        
        <!-- Messages Container -->
        <div id="messages-container" class="p-4 overflow-y-auto" style="height: 500px;">
            <?php if ($messagesResult->num_rows === 0): ?>
                <div class="text-center text-gray-500 my-8">
                    <p>No messages yet</p>
                    <p class="text-sm">Start the conversation by sending a message below</p>
                </div>
            <?php else: ?>
                <?php 
                $currentDate = null;
                while ($message = $messagesResult->fetch_assoc()): 
                    $messageDate = date('Y-m-d', strtotime($message['created_at']));
                    $showDateSeparator = $currentDate !== $messageDate;
                    $currentDate = $messageDate;
                    
                    $isOwnMessage = $message['sender_id'] == $userId;
                ?>
          <?php if ($showDateSeparator): ?>
    <div class="flex justify-center my-4">
        <div class="bg-gray-200 text-gray-600 text-xs px-3 py-1 rounded-full">
            <?php echo formatDateTimeRelative($messageDate . ' 00:00:00'); ?>
        </div>
    </div>
<?php endif; ?>

                    
                    <div class="flex <?php echo $isOwnMessage ? 'justify-end' : 'justify-start'; ?> mb-4">
                        <?php if (!$isOwnMessage): ?>
                            <div class="w-8 h-8 rounded-full bg-gray-300 overflow-hidden mr-2 flex-shrink-0">
                                <?php if ($message['sender_image']): ?>
                                    <img src="../../uploads/profiles/<?php echo $message['sender_image']; ?>" alt="<?php echo htmlspecialchars($message['sender_name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-blue-500 text-white">
                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="max-w-xs sm:max-w-md md:max-w-lg">
                            <div class="<?php echo $isOwnMessage ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?> rounded-lg px-4 py-2 break-words">
                                <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1 <?php echo $isOwnMessage ? 'text-right' : ''; ?>">
                                <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                <?php if ($isOwnMessage && $message['is_read']): ?>
                                    <span class="ml-1 text-blue-600"><i class="fas fa-check-double"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <!-- Message Input -->
        <div class="border-t border-gray-200 p-4">
            <form method="POST" action="" id="message-form">
                <div class="flex">
                    <textarea id="message" name="message" rows="2" class="form-textarea flex-grow rounded-l-lg resize-none" placeholder="Type your message here..." required></textarea>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded-r-lg">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php
// Close connection
$conn->close();
// Include footer
include '../includes/footer.php';
?>
