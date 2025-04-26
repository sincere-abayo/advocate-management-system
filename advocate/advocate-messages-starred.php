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

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Message.php';
include_once '../classes/User.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$message_obj = new Message($db);
$user_obj = new User($db);

// Set user ID
$user_id = $_SESSION['user_id'];

// Handle search
$search_term = '';
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $_GET['search'];
    $messages = $message_obj->search($search_term, $user_id);
} else {
    // Get starred messages for the current user
    $messages = $message_obj->getStarredMessagesByUser($user_id);
}

// Get unread message count for inbox display
$unread_count = $message_obj->getUnreadCount($user_id);

// Set page title
$page_title = "Starred Messages - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Starred Messages</h1>
        <div class="flex space-x-2">
            <form class="flex items-center">
                <div class="relative mr-2">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5" placeholder="Search messages...">
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Search
                </button>
            </form>
            <a href="advocate-message-compose.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-pen mr-2"></i>Compose
            </a>
        </div>
    </div>
    
    <!-- Include the message block -->
    <?php include_once '../templates/message-block.php'; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Message Folders -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Folders</h2>
            </div>
            <div class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="advocate-messages.php" class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-gray-100 text-gray-700">
                            <span class="flex items-center">
                                <i class="fas fa-inbox mr-3"></i>
                                Inbox
                            </span>
                            <?php if($unread_count > 0): ?>
                                <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="advocate-messages-sent.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-100 text-gray-700">
                            <i class="fas fa-paper-plane mr-3"></i>
                            Sent
                        </a>
                    </li>
                    <li>
                        <a href="advocate-messages-starred.php" class="flex items-center px-3 py-2 rounded-lg bg-blue-50 text-blue-700 font-medium">
                            <i class="fas fa-star mr-3"></i>
                            Starred
                        </a>
                    </li>
                    <li>
                        <a href="advocate-messages-trash.php" class="flex items-center px-3 py-2 rounded-lg hover:bg-gray-100 text-gray-700">
                            <i class="fas fa-trash-alt mr-3"></i>
                            Trash
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Message List -->
        <div class="md:col-span-3 bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">
                    <?php 
                    if(!empty($search_term)) {
                        echo "Search Results for \"" . htmlspecialchars($search_term) . "\"";
                    } else {
                        echo "Starred Messages";
                    }
                    ?>
                </h2>
                <div class="flex items-center text-sm text-gray-500">
                    <?php if($messages && $messages->rowCount() > 0): ?>
                        <span><?php echo $messages->rowCount(); ?> message(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="divide-y divide-gray-200">
                <?php if($messages && $messages->rowCount() > 0): ?>
                    <?php while($message = $messages->fetch(PDO::FETCH_ASSOC)): ?>
                        <?php 
                        $is_read = $message['is_read'] == 1;
                        $is_sender = $message['sender_id'] == $user_id;
                        $display_name = $is_sender ? $message['recipient_name'] : $message['sender_name'];
                        $profile_image = $is_sender ? $message['recipient_profile_image'] : $message['sender_profile_image'];
                        ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors duration-150 <?php echo !$is_read && !$is_sender ? 'bg-blue-50' : ''; ?>">
                            <a href="advocate-message-view.php?id=<?php echo $message['id']; ?>" class="block">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                                            <?php if(!empty($profile_image)): ?>
                                                <img src="../uploads/profile/<?php echo $profile_image; ?>" alt="User" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-500">
                                                    <i class="fas fa-user-circle text-xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-semibold text-gray-900">
                                                <?php echo $is_sender ? 'To: ' : 'From: '; ?><?php echo htmlspecialchars($display_name); ?>
                                            </h3>
                                            <p class="text-xs text-gray-500">
                                                <?php echo date('M d, Y h:i A', strtotime($message['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-yellow-400 mr-2">
                                            <i class="fas fa-star"></i>
                                        </span>
                                        <?php if(!$is_read && !$is_sender): ?>
                                            <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-full">
                                                New
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h4 class="text-sm font-medium text-gray-900 mb-1">
                                    <?php echo htmlspecialchars($message['subject']); ?>
                                </h4>
                                <p class="text-sm text-gray-600 truncate">
                                    <?php echo htmlspecialchars(substr(strip_tags($message['message']), 0, 150)); ?>
                                    <?php echo strlen(strip_tags($message['message'])) > 150 ? '...' : ''; ?>
                                </p>
                            </a>
                            <div class="flex justify-end mt-2 space-x-2">
                                <a href="#" onclick="toggleStar(<?php echo $message['id']; ?>, false); return false;" class="text-yellow-500 hover:text-yellow-600 text-sm">
                                    <i class="fas fa-star mr-1"></i>Unstar
                                </a>
                                <a href="#" onclick="confirmDelete(<?php echo $message['id']; ?>); return false;" class="text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-8 text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-star text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No starred messages found</h3>
                        <p class="text-gray-500">
                            <?php 
                            if(!empty($search_term)) {
                                echo "No messages matching \"" . htmlspecialchars($search_term) . "\".";
                            } else {
                                echo "You haven't starred any messages yet.";
                            }
                            ?>
                        </p>
                        <a href="advocate-messages.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-inbox mr-2"></i>Go to Inbox
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleStar(messageId, star) {
    // Send AJAX request to star/unstar message
    fetch('advocate-message-star.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + messageId + '&star=' + (star ? '1' : '0')
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Reload the page to reflect changes
            window.location.reload();
        } else {
            alert('Failed to update message: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function confirmDelete(id) {
    if(confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
        window.location.href = 'advocate-message-delete.php?id=' + id;
    }
}
</script>

<?php
// Include footer
include_once '../templates/footer.php';
?>