<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has appropriate permissions
requireLogin();
requireUserType('advocate');

// Get user ID from session
$userId = $_SESSION['user_id'];

// Initialize variables
$notifications = [];
$unreadCount = 0;

// Connect to database
$conn = getDBConnection();

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notificationId = (int)$_GET['mark_read'];
    
    $markReadStmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE notification_id = ? AND user_id = ?
    ");
    $markReadStmt->bind_param("ii", $notificationId, $userId);
    $markReadStmt->execute();
    
    // Redirect to remove the query parameter
    header("Location: notifications.php");
    exit;
}

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read'])) {
    $markAllReadStmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ?
    ");
    $markAllReadStmt->bind_param("i", $userId);
    $markAllReadStmt->execute();
    
    // Redirect to remove the query parameter
    header("Location: notifications.php");
    exit;
}

// Delete notification if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notificationId = (int)$_GET['delete'];
    
    $deleteStmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE notification_id = ? AND user_id = ?
    ");
    $deleteStmt->bind_param("ii", $notificationId, $userId);
    $deleteStmt->execute();
    
    // Redirect to remove the query parameter
    header("Location: notifications.php");
    exit;
}

// Get notifications for the user
$notificationsStmt = $conn->prepare("
    SELECT * 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
    LIMIT 50
");
$notificationsStmt->bind_param("i", $userId);
$notificationsStmt->execute();
$notificationsResult = $notificationsStmt->get_result();

// Fetch notifications
while ($notification = $notificationsResult->fetch_assoc()) {
    $notifications[] = $notification;
    if (!$notification['is_read']) {
        $unreadCount++;
    }
}

// Set page title
$pageTitle = "Notifications";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
        
        <?php if (count($notifications) > 0): ?>
            <div class="flex space-x-2">
                <?php if ($unreadCount > 0): ?>
                    <a href="?mark_all_read=1" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-check-double mr-2"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-bell-slash text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No notifications</h3>
            <p class="text-gray-500">You don't have any notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <ul class="divide-y divide-gray-200">
                <?php foreach ($notifications as $notification): ?>
                    <li class="<?php echo $notification['is_read'] ? 'bg-white' : 'bg-blue-50'; ?> hover:bg-gray-50">
                        <div class="px-6 py-5 flex items-start">
                            <div class="flex-shrink-0">
                                <?php
                                // Determine icon based on notification type
                                $iconClass = 'fas fa-bell text-blue-500';
                                
                                if (!empty($notification['related_to'])) {
                                    switch ($notification['related_to']) {
                                        case 'case':
                                            $iconClass = 'fas fa-briefcase text-blue-500';
                                            break;
                                        case 'appointment':
                                            $iconClass = 'fas fa-calendar-alt text-green-500';
                                            break;
                                        case 'document':
                                            $iconClass = 'fas fa-file-alt text-yellow-500';
                                            break;
                                        case 'invoice':
                                            $iconClass = 'fas fa-file-invoice-dollar text-purple-500';
                                            break;
                                        case 'message':
                                            $iconClass = 'fas fa-envelope text-red-500';
                                            break;
                                    }
                                }
                                ?>
                                <span class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="<?php echo $iconClass; ?>"></i>
                                </span>
                            </div>
                            <div class="ml-4 flex-1">
                                <div class="flex justify-between">
                                    <h3 class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <?php echo formatDateTimeRelative($notification['created_at']); ?>
                                    </p>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <div class="mt-2 flex space-x-4">
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="?mark_read=<?php echo $notification['notification_id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-check mr-1"></i> Mark as Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($notification['related_to']) && !empty($notification['related_id'])): ?>
                                        <?php
                                        // Determine link based on notification type
                                        $link = '#';
                                        switch ($notification['related_to']) {
                                            case 'case':
                                                $link = "cases/view.php?id={$notification['related_id']}";
                                                break;
                                            case 'appointment':
                                                $link = "appointments/view.php?id={$notification['related_id']}";
                                                break;
                                            case 'document':
                                                $link = "documents/view.php?id={$notification['related_id']}";
                                                break;
                                            case 'invoice':
                                                $link = "finance/invoices/view.php?id={$notification['related_id']}";
                                                break;
                                            case 'message':
                                                $link = "messages/view.php?id={$notification['related_id']}";
                                                break;
                                        }
                                        ?>
                                        <a href="<?php echo $link; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-external-link-alt mr-1"></i> View Details
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?delete=<?php echo $notification['notification_id']; ?>" class="text-sm text-red-600 hover:text-red-800" onclick="return confirm('Are you sure you want to delete this notification?');">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
// Close connection
$conn->close();

include 'includes/footer.php';
?>