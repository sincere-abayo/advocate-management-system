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
include_once '../classes/User.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$message_obj = new Message($db);
$user_obj = new User($db);

// Set message ID
$message_obj->id = $_GET['id'];

// Read message details
if(!$message_obj->readOne()) {
    header("Location: advocate-messages.php");
    exit();
}

// Check if the message belongs to the logged-in user
if($message_obj->recipient_id != $_SESSION['user_id'] && $message_obj->sender_id != $_SESSION['user_id']) {
    header("Location: advocate-messages.php");
    exit();
}

// Mark message as read if user is recipient
if($message_obj->recipient_id == $_SESSION['user_id'] && $message_obj->is_read == 0) {
    $message_obj->markAsRead();
}

// Get sender details
$user_obj->id = $message_obj->sender_id;
$user_obj->readOne();

// Set page title
$page_title = "View Message - " . $message_obj->subject;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">

        <h1 class="text-3xl font-semibold text-gray-800">View Message</h1>
        <a href="advocate-messages.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Messages
        </a>
    </div>
    
    <!-- Include the message block -->
    <?php include_once '../templates/message-block.php'; ?>
    
    <!-- Message View -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($message_obj->subject); ?></h2>
            <div class="flex space-x-2">
                <a href="advocate-message-reply.php?id=<?php echo $message_obj->id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-reply mr-2"></i>Reply
                </a>
                <a href="#" onclick="confirmDelete(<?php echo $message_obj->id; ?>); return false;" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-trash-alt mr-2"></i>Delete
                </a>
            </div>
        </div>
        
        <div class="p-6">
            <div class="flex items-start mb-6">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-200">
                        <?php if(!empty($user_obj->profile_image)): ?>
                            <img src="../uploads/profile/<?php echo $user_obj->profile_image; ?>" alt="Sender" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-500">
                                <i class="fas fa-user-circle text-2xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">
                                <?php echo htmlspecialchars($user_obj->first_name . ' ' . $user_obj->last_name); ?>
                            </h3>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($user_obj->email); ?>
                            </p>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo date('M d, Y h:i A', strtotime($message_obj->created_at)); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-6">
                <div class="prose max-w-none">
                    <?php echo $message_obj->message; ?>
                </div>
                
                <?php if(!empty($message_obj->attachment)): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Attachment</h4>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0 text-gray-400">
                            <i class="fas fa-paperclip text-xl"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($message_obj->attachment_name); ?></p>
                        </div>
                        <div>
                            <a href="../<?php echo $message_obj->attachment; ?>" download class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <i class="fas fa-download mr-1"></i>Download
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
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
