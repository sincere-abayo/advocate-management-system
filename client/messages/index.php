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

// Get client ID from session
$clientId = $_SESSION['client_id'];
$userId = $_SESSION['user_id'];

// Connect to database
$conn = getDBConnection();

// Get conversations
$conversationsQuery = "
    SELECT 
        c.conversation_id,
        c.created_at,
        c.updated_at,
        c.subject,
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
        (
            SELECT COUNT(*) 
            FROM messages m 
            WHERE m.conversation_id = c.conversation_id 
            AND m.is_read = 0 
            AND m.sender_id != ?
        ) as unread_count,
        (
            SELECT m.content 
            FROM messages m 
            WHERE m.conversation_id = c.conversation_id 
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT m.created_at 
            FROM messages m 
            WHERE m.conversation_id = c.conversation_id 
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_message_time
    FROM conversations c
    JOIN users iu ON c.initiator_id = iu.user_id
    JOIN users ru ON c.recipient_id = ru.user_id
    WHERE c.initiator_id = ? OR c.recipient_id = ?
    ORDER BY last_message_time DESC
";

$conversationsStmt = $conn->prepare($conversationsQuery);
$conversationsStmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
$conversationsStmt->execute();
$conversationsResult = $conversationsStmt->get_result();

// Get advocates the client is working with
$advocatesQuery = "
    SELECT DISTINCT 
        ap.advocate_id,
        u.user_id,
        u.full_name,
        u.profile_image
    FROM case_assignments ca
    JOIN cases c ON ca.case_id = c.case_id
    JOIN advocate_profiles ap ON ca.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE c.client_id = ?
    ORDER BY u.full_name
";

$advocatesStmt = $conn->prepare($advocatesQuery);
$advocatesStmt->bind_param("i", $clientId);
$advocatesStmt->execute();
$advocatesResult = $advocatesStmt->get_result();

$advocates = [];
while ($advocate = $advocatesResult->fetch_assoc()) {
    $advocates[] = $advocate;
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "Messages";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-600">Communicate with your advocates</p>
        </div>
        <div class="mt-4 md:mt-0">
            <button id="new-message-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> New Message
            </button>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-3">
            <!-- Conversations List -->
            <div class="md:col-span-1 border-r border-gray-200">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <div class="relative">
                        <input type="text" id="search-conversations" class="form-input w-full pl-10" placeholder="Search messages...">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="conversations-list overflow-y-auto" style="max-height: 600px;">
                    <?php if ($conversationsResult->num_rows === 0): ?>
                        <div class="p-6 text-center text-gray-500">
                            <div class="text-gray-400 mb-2">
                                <i class="fas fa-comments text-4xl"></i>
                            </div>
                            <p class="mb-2">No conversations yet</p>
                            <p class="text-sm">Start a new conversation with your advocate</p>
                        </div>
                    <?php else: ?>
                        <?php while ($conversation = $conversationsResult->fetch_assoc()): ?>
                            <a href="view.php?id=<?php echo $conversation['conversation_id']; ?>" class="block border-b border-gray-200 hover:bg-gray-50 transition duration-150 <?php echo $conversation['unread_count'] > 0 ? 'bg-blue-50' : ''; ?>">
                                <div class="p-4">
                                    <div class="flex justify-between items-start mb-1">
                                        <h3 class="font-medium text-gray-900 <?php echo $conversation['unread_count'] > 0 ? 'font-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($conversation['other_user_name']); ?>
                                            <span class="text-xs text-gray-500 font-normal ml-1">(<?php echo ucfirst($conversation['other_user_type']); ?>)</span>
                                        </h3>
                                        <div class="flex items-center">
                                            <?php if ($conversation['unread_count'] > 0): ?>
                                                <span class="bg-blue-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                                                    <?php echo $conversation['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="ml-2 text-xs text-gray-500"><?php echo formatDateTimeRelative($conversation['last_message_time']); ?></span>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?php echo htmlspecialchars($conversation['subject']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 truncate mt-1">
                                        <?php echo htmlspecialchars($conversation['last_message']); ?>
                                    </p>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Welcome Screen / Message Preview -->
            <div class="md:col-span-2 bg-gray-50 flex items-center justify-center" style="min-height: 600px;">
                <div class="text-center p-6">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-paper-plane text-6xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Welcome to Messages</h2>
                    <p class="text-gray-600 mb-6">Select a conversation or start a new one</p>
                    <button id="mobile-new-message-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> New Message
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div id="new-message-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="flex justify-between items-center border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-800">New Message</h3>
            <button id="close-modal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form action="create.php" method="POST" class="px-6 py-4">
            <div class="mb-4">
                <label for="recipient" class="block text-sm font-medium text-gray-700 mb-1">Recipient *</label>
                <select id="recipient" name="recipient_id" class="form-select w-full" required>
                    <option value="">Select an advocate</option>
                    <?php foreach ($advocates as $advocate): ?>
                        <option value="<?php echo $advocate['user_id']; ?>">
                            <?php echo htmlspecialchars($advocate['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($advocates)): ?>
                    <p class="text-sm text-red-500 mt-1">You don't have any advocates assigned to your cases yet.</p>
                <?php endif; ?>
            </div>
            
            <div class="mb-4">
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                <input type="text" id="subject" name="subject" class="form-input w-full" placeholder="Enter message subject" required>
            </div>
            
            <div class="mb-4">
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                <textarea id="message" name="message" rows="5" class="form-textarea w-full" placeholder="Type your message here..." required></textarea>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancel-message" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // New message modal functionality
    const newMessageBtn = document.getElementById('new-message-btn');
    const mobileNewMessageBtn = document.getElementById('mobile-new-message-btn');
    const newMessageModal = document.getElementById('new-message-modal');
    const closeModal = document.getElementById('close-modal');
    const cancelMessage = document.getElementById('cancel-message');
    
    function openModal() {
        newMessageModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }
    
    function closeModalFunc() {
        newMessageModal.classList.add('hidden');
        document.body.style.overflow = ''; // Re-enable scrolling
    }
    
    newMessageBtn.addEventListener('click', openModal);
    mobileNewMessageBtn.addEventListener('click', openModal);
    closeModal.addEventListener('click', closeModalFunc);
    cancelMessage.addEventListener('click', closeModalFunc);
    
    // Close modal when clicking outside
    newMessageModal.addEventListener('click', function(e) {
        if (e.target === newMessageModal) {
            closeModalFunc();
        }
    });
    
    // Search functionality
    const searchInput = document.getElementById('search-conversations');
    const conversationItems = document.querySelectorAll('.conversations-list a');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        conversationItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>