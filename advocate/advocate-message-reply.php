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

// Get original sender details
$user_obj->id = $message_obj->sender_id;
$user_obj->readOne();
$original_sender = $user_obj->first_name . ' ' . $user_obj->last_name;

// Process form submission
$send_success = $send_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create new message object for reply
    $reply_obj = new Message($db);
    
    // Set reply properties
    $reply_obj->sender_id = $_SESSION['user_id'];
    $reply_obj->recipient_id = $message_obj->sender_id; // Reply to original sender
    $reply_obj->subject = !empty($_POST['subject']) ? $_POST['subject'] : 'Re: ' . $message_obj->subject;
    $reply_obj->message = $_POST['message'];
    $reply_obj->is_read = 0;
    $reply_obj->is_starred = 0;
    $reply_obj->is_deleted_by_sender = 0;
    $reply_obj->is_deleted_by_recipient = 0;
    $reply_obj->parent_id = $message_obj->id; // Link to original message
    
    // Handle attachment if provided
    if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt');
        $filename = $_FILES['attachment']['name'];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Validate file extension
        if(in_array(strtolower($file_ext), $allowed)) {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/attachments/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = uniqid() . '_' . $filename;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if(move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $reply_obj->attachment = 'uploads/attachments/' . $new_filename;
                $reply_obj->attachment_name = $filename;
            } else {
                $send_error = "Failed to upload attachment.";
            }
        } else {
            $send_error = "Invalid file type. Allowed types: " . implode(', ', $allowed);
        }
    }
    
    if(empty($send_error)) {
        // Create reply message
        if($reply_obj->create()) {
            $send_success = "Reply sent successfully.";
            
            // Redirect to messages page after successful reply
            header("Location: advocate-messages.php?success=1&msg=Reply+sent+successfully");
            exit();
        } else {
            $send_error = "Failed to send reply.";
        }
    }
}

// Format original message for quoting in reply
$quoted_message = "\n\n\n----- Original Message from " . $original_sender . " on " . date('M d, Y h:i A', strtotime($message_obj->created_at)) . " -----\n" . strip_tags($message_obj->message);

// Set page title
$page_title = "Reply to Message - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Reply to Message</h1>
        <a href="advocate-message-view.php?id=<?php echo $message_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Message
        </a>
    </div>
    
    <?php if(!empty($send_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $send_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Original Message Summary -->
    <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
        <h2 class="text-sm font-medium text-gray-700 mb-2">Original Message</h2>
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-semibold text-gray-900">From: <?php echo htmlspecialchars($original_sender); ?></p>
                <p class="text-sm text-gray-700">Subject: <?php echo htmlspecialchars($message_obj->subject); ?></p>
            </div>
            <div class="text-sm text-gray-500">
                <?php echo date('M d, Y h:i A', strtotime($message_obj->created_at)); ?>
            </div>
        </div>
    </div>
    
    <!-- Reply Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Compose Reply</h2>
        </div>
        <div class="p-6">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $message_obj->id); ?>" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="recipient" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="text" id="recipient" value="<?php echo htmlspecialchars($original_sender); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" readonly>
                </div>
                
                <div class="mb-4">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="subject" name="subject" value="Re: <?php echo htmlspecialchars($message_obj->subject); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="mb-4">
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea id="message" name="message" rows="10" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required><?php echo htmlspecialchars($quoted_message); ?></textarea>
                </div>
                
                <div class="mb-6">
                    <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                    <input type="file" id="attachment" name="attachment" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                    <p class="mt-1 text-xs text-gray-500">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
