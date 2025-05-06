<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get user ID from session
$userId = $_SESSION['user_id'];

// Initialize variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Connect to database
$conn = getDBConnection();

// Handle mark as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['id'])) {
    $notificationId = (int)$_GET['id'];
    
    $markReadQuery = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $markReadStmt = $conn->prepare($markReadQuery);
    $markReadStmt->bind_param("ii", $notificationId, $userId);
    $markReadStmt->execute();
    
    // Redirect to remove the action from URL
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read action
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $markAllReadQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $markAllReadStmt = $conn->prepare($markAllReadQuery);
    $markAllReadStmt->bind_param("i", $userId);
    $markAllReadStmt->execute();
    
    // Redirect to remove the action from URL
    header("Location: notifications.php");
    exit;
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $notificationId = (int)$_GET['id'];
    
    $deleteQuery = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $notificationId, $userId);
    $deleteStmt->execute();
    
    // Redirect to remove the action from URL
    header("Location: notifications.php");
    exit;
}

// Handle delete all read action
if (isset($_GET['action']) && $_GET['action'] === 'delete_read') {
    $deleteReadQuery = "DELETE FROM notifications WHERE user_id = ? AND is_read = 1";
    $deleteReadStmt = $conn->prepare($deleteReadQuery);
    $deleteReadStmt->bind_param("i", $userId);
    $deleteReadStmt->execute();
    
    // Redirect to remove the action from URL
    header("Location: notifications.php");
    exit;
}

// Get total notifications count for pagination
$countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$totalNotifications = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalNotifications / $perPage);

// Get unread notifications count
$unreadCountQuery = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
$unreadCountStmt = $conn->prepare($unreadCountQuery);
$unreadCountStmt->bind_param("i", $userId);
$unreadCountStmt->execute();
$unreadCount = $unreadCountStmt->get_result()->fetch_assoc()['unread'];

// Get notifications with pagination
$notificationsQuery = "
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
";
$notificationsStmt = $conn->prepare($notificationsQuery);
$notificationsStmt->bind_param("iii", $userId, $perPage, $offset);
$notificationsStmt->execute();
$notificationsResult = $notificationsStmt->get_result();

// Close connection
$conn->close();

// Set page title
$pageTitle = "Notifications";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Notifications</h1>
            <p class="text-gray-600">Stay updated with important information</p>
        </div>
        
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <?php if ($unreadCount > 0): ?>
                <a href="?action=mark_all_read" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-check-double mr-2"></i> Mark All as Read
                </a>
            <?php endif; ?>
            
            <a href="?action=delete_read" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to delete all read notifications?');">
                <i class="fas fa-trash mr-2"></i> Delete Read Notifications
            </a>
        </div>
    </div>
    
    <?php if ($notificationsResult->num_rows === 0): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-bell-slash text-6xl"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">No notifications</h3>
            <p class="text-gray-600">You don't have any notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <ul class="divide-y divide-gray-200">
                <?php while ($notification = $notificationsResult->fetch_assoc()): ?>
                    <li class="<?php echo $notification['is_read'] ? 'bg-white' : 'bg-blue-50'; ?> hover:bg-gray-50 transition duration-150">
                        <div class="px-6 py-4 flex items-start">
                            <div class="flex-shrink-0 pt-1">
                                <?php
                                // Determine icon based on notification type
                                $iconClass = 'fas fa-bell';
                                $iconColor = 'text-blue-500';
                                
                                if (!empty($notification['related_to'])) {
                                    switch ($notification['related_to']) {
                                        case 'case':
                                            $iconClass = 'fas fa-briefcase';
                                            $iconColor = 'text-indigo-500';
                                            break;
                                        case 'appointment':
                                            $iconClass = 'fas fa-calendar-alt';
                                            $iconColor = 'text-green-500';
                                            break;
                                        case 'document':
                                            $iconClass = 'fas fa-file-alt';
                                            $iconColor = 'text-yellow-500';
                                            break;
                                        case 'invoice':
                                            $iconClass = 'fas fa-file-invoice-dollar';
                                            $iconColor = 'text-red-500';
                                            break;
                                        case 'message':
                                            $iconClass = 'fas fa-envelope';
                                            $iconColor = 'text-purple-500';
                                            break;
                                    }
                                }
                                ?>
                                <div class="w-10 h-10 rounded-full bg-<?php echo substr($iconColor, 5); ?>-100 flex items-center justify-center">
                                    <i class="<?php echo $iconClass; ?> <?php echo $iconColor; ?>"></i>
                                </div>
                            </div>
                            
                            <div class="ml-4 flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-base font-medium text-gray-900">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    New
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <p class="mt-1 text-xs text-gray-500"><?php echo formatDateTimeRelative($notification['created_at']); ?></p>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <?php if (!$notification['is_read']): ?>
                                            <a href="?action=mark_read&id=<?php echo $notification['notification_id']; ?>" class="text-blue-600 hover:text-blue-800" title="Mark as Read">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($notification['related_to']) && !empty($notification['related_id'])): ?>
                                            <?php
                                            // Determine link based on notification type
                                            $link = '#';
                                            switch ($notification['related_to']) {
                                                case 'case':
                                                    $link = "cases/view.php?id=" . $notification['related_id'];
                                                    break;
                                                case 'appointment':
                                                    $link = "appointments/view.php?id=" . $notification['related_id'];
                                                    break;
                                                case 'document':
                                                    $link = "documents/view.php?id=" . $notification['related_id'];
                                                    break;
                                                case 'invoice':
                                                    $link = "invoices/view.php?id=" . $notification['related_id'];
                                                    break;
                                                case 'message':
                                                    $link = "messages/view.php?id=" . $notification['related_id'];
                                                    break;
                                            }
                                            ?>
                                            <a href="<?php echo $link; ?>" class="text-indigo-600 hover:text-indigo-800" title="View Details">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?action=delete&id=<?php echo $notification['notification_id']; ?>" class="text-red-600 hover:text-red-800" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-between items-center">
                <p class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalNotifications); ?> of <?php echo $totalNotifications; ?> notifications
                </p>
                
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>