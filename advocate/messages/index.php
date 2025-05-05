<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
requireLogin();

// Get user ID and type
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// Set page title
$pageTitle = "Messages";

// Include header
include_once '../includes/header.php';

// Get database connection
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

// Get users for new message
$usersQuery = "
    SELECT u.user_id, u.full_name, u.user_type
    FROM users u
    WHERE u.user_id != ? AND u.status = 'active'
    ORDER BY u.full_name
";

$usersStmt = $conn->prepare($usersQuery);
$usersStmt->bind_param("i", $userId);
$usersStmt->execute();
$usersResult = $usersStmt->get_result();

$users = [];
while ($user = $usersResult->fetch_assoc()) {
    $users[] = $user;
}

// Close database connection
$conn->close();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-600">Communicate with clients and team members</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" 
                    onclick="document.getElementById('newMessageModal').classList.remove('hidden')">
                <i class="fas fa-plus mr-2"></i> New Message
            </button>
        </div>
    </div>
    
    <!-- Messages List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($conversationsResult->num_rows > 0): ?>
            <ul class="divide-y divide-gray-200">
                <?php while ($conversation = $conversationsResult->fetch_assoc()): ?>
                    <li class="hover:bg-gray-50 <?php echo $conversation['unread_count'] > 0 ? 'bg-blue-50' : ''; ?>">
                        <a href="view.php?id=<?php echo $conversation['conversation_id']; ?>" class="block p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <?php if ($conversation['other_user_type'] == 'advocate'): ?>
                                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-500">
                                                <i class="fas fa-user-tie text-xl"></i>
                                            </div>
                                        <?php elseif ($conversation['other_user_type'] == 'client'): ?>
                                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-500">
                                                <i class="fas fa-user text-xl"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-500">
                                                <i class="fas fa-user-shield text-xl"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="flex items-center">
                                            <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($conversation['other_user_name']); ?></h3>
                                            <span class="ml-2 text-xs text-gray-500"><?php echo formatDateTimeRelative($conversation['last_message_time']); ?></span>
                                        </div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($conversation['subject']); ?></p>
                                        <p class="mt-1 text-sm text-gray-600 truncate"><?php echo htmlspecialchars($conversation['last_message']); ?></p>
                                    </div>
                                </div>
                                <?php if ($conversation['unread_count'] > 0): ?>
                                    <div class="ml-2 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-blue-600 rounded-full">
                                            <?php echo $conversation['unread_count']; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-comments text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No messages yet</h3>
                <p class="text-gray-500 mb-6">Start a conversation with a client or team member</p>
                <button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        onclick="document.getElementById('newMessageModal').classList.remove('hidden')">
                    <i class="fas fa-plus mr-2"></i> New Message
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Message Modal -->
<div id="newMessageModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">New Message</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="document.getElementById('newMessageModal').classList.add('hidden')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form action="create.php" method="POST" class="px-6 py-4">
            <div class="mb-4">
                <label for="recipient" class="block text-sm font-medium text-gray-700 mb-1">Recipient</label>
                <select id="recipient" name="recipient_id" class="form-select w-full" required>
                    <option value="">Select a recipient</option>
                    <?php if ($userType == 'advocate'): ?>
                        <optgroup label="Clients">
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['user_type'] == 'client'): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Advocates">
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['user_type'] == 'advocate'): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php elseif ($userType == 'client'): ?>
                        <optgroup label="Advocates">
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['user_type'] == 'advocate'): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($userType == 'admin' || $userType == 'advocate'): ?>
                        <optgroup label="Administrators">
                            <?php foreach ($users as $user): ?>
                                <?php if ($user['user_type'] == 'admin'): ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <input type="text" id="subject" name="subject" class="form-input w-full" required>
            </div>
            
            <div class="mb-4">
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea id="message" name="message" rows="5" class="form-textarea w-full" required></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        onclick="document.getElementById('newMessageModal').classList.add('hidden')">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Close modal when clicking outside
document.getElementById('newMessageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.add('hidden');
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>