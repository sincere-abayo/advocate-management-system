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
include_once '../classes/Client.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$message_obj = new Message($db);
$user_obj = new User($db);
$client_obj = new Client($db);

// Get all clients for recipient dropdown
$clients = $client_obj->read();

// Get all staff for recipient dropdown
// $staff = $user_obj->readStaff();

// Process form submission
$send_success = $send_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set message properties
    $message_obj->sender_id = $_SESSION['user_id'];
    $message_obj->recipient_id = $_POST['recipient_id'];
    $message_obj->subject = $_POST['subject'];
    $message_obj->message = $_POST['message'];
    $message_obj->is_read = 0;
    $message_obj->is_starred = 0;
    $message_obj->is_deleted_by_sender = 0;
    $message_obj->is_deleted_by_recipient = 0;
    
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
                $message_obj->attachment = 'uploads/attachments/' . $new_filename;
                $message_obj->attachment_name = $filename;
            } else {
                $send_error = "Failed to upload attachment.";
            }
        } else {
            $send_error = "Invalid file type. Allowed types: " . implode(', ', $allowed);
        }
    }
    
    if(empty($send_error)) {
        // Create message
        if($message_obj->create()) {
            $send_success = "Message sent successfully.";
            
            // Clear form data on success
            $_POST = array();
        } else {
            $send_error = "Failed to send message.";
        }
    }
}

// Set page title
$page_title = "Compose Message - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Compose Message</h1>
        <a href="advocate-messages.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Messages
        </a>
    </div>
    
    <?php if(!empty($send_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $send_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($send_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $send_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Compose Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">New Message</h2>
        </div>
        <div class="p-6">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="recipient_id" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <select id="recipient_id" name="recipient_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        <option value="">Select Recipient</option>
                        <?php if($clients && $clients->rowCount() > 0): ?>
                            <optgroup label="Clients">
                                <?php while($client = $clients->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $client['user_id']; ?>" <?php echo (isset($_POST['recipient_id']) && $_POST['recipient_id'] == $client['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </optgroup>
                        <?php endif; ?>
                        
                        <?php if($staff && $staff->rowCount() > 0): ?>
                            <optgroup label="Staff">
                                <?php while($staff_member = $staff->fetch(PDO::FETCH_ASSOC)): ?>
                                    <?php if($staff_member['id'] != $_SESSION['user_id']): ?>
                                        <option value="<?php echo $staff_member['id']; ?>" <?php echo (isset($_POST['recipient_id']) && $_POST['recipient_id'] == $staff_member['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name'] . ' (' . ucfirst($staff_member['role']) . ')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="mb-4">
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea id="message" name="message" rows="10" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>
                
                <div class="mb-6">
                    <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1">Attachment (Optional)</label>
                    <input type="file" id="attachment" name="attachment" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                    <p class="mt-1 text-xs text-gray-500">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        <i class="fas fa-paper-plane mr-2"></i>Send Message
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