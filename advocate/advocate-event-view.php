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

// Check if event ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-calendar.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$event_obj = new Event($db);

// Set event ID
$event_obj->id = $_GET['id'];

// Read event details
if(!$event_obj->readOne()) {
    header("Location: advocate-calendar.php");
    exit();
}

// Set page title
$page_title = "Event Details - " . $event_obj->title;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Event Details</h1>
        <div class="flex space-x-2">
            <a href="advocate-event-edit.php?id=<?php echo $event_obj->id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-edit mr-2"></i>Edit
            </a>
            <a href="advocate-events.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to events
            </a>
        </div>
    </div>
    
    <!-- Event Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Event Information</h2>
        </div>
        <div class="p-4">
            <div class="mb-4">
                <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($event_obj->title); ?></h3>
                <div class="flex items-center mt-2">
                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                    <?php 
                        switch($event_obj->event_type) {
                            case 'Hearing':
                                echo 'bg-red-100 text-red-800';
                                break;
                            case 'Meeting':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'Deadline':
                                echo 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'Reminder':
                                echo 'bg-purple-100 text-purple-800';
                                break;
                            default:
                                echo 'bg-blue-100 text-blue-800';
                        }
                        ?>">
                        <?php echo $event_obj->event_type !== null ? htmlspecialchars($event_obj->event_type) : ''; ?>
                    </span>

                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Date</p>
                    <p class="text-base text-gray-900"><?php echo date('F d, Y', strtotime($event_obj->event_date)); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Time</p>
                    <p class="text-base text-gray-900">
                        <?php 
                            echo date('h:i A', strtotime($event_obj->event_time));
                            if(!empty($event_obj->end_time)) {
                                echo ' - ' . date('h:i A', strtotime($event_obj->end_time));
                            }
                        ?>
                    </p>
                </div>
                 
                <?php if(!empty($event_obj->location)): ?>
                <div>
                    <p class="text-sm font-medium text-gray-500">Location</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($event_obj->location); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($event_obj->case_number)): ?>
                <div>
                    <p class="text-sm font-medium text-gray-500">Related Case</p>
                    <p class="text-base text-gray-900">
                        <a href="advocate-case-view.php?id=<?php echo $event_obj->case_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <?php echo htmlspecialchars($event_obj->case_number . ' - ' . $event_obj->case_title); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($event_obj->client_name)): ?>
                <div>
                    <p class="text-sm font-medium text-gray-500">Related Client</p>
                    <p class="text-base text-gray-900">
                        <a href="advocate-client-view.php?id=<?php echo $event_obj->client_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <?php echo htmlspecialchars($event_obj->client_name); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if(!empty($event_obj->description)): ?>
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-500">Description</p>
                <p class="text-base text-gray-900"><?php echo nl2br(htmlspecialchars($event_obj->description)); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="confirmDelete(<?php echo $event_obj->id; ?>)" class="bg-red-500 hover:bg-red-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Event
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if(confirm('Are you sure you want to delete this event?')) {
        window.location.href = 'advocate-event-delete.php?id=' + id;
    }
}
</script>

<?php
// Include footer
include_once '../templates/footer.php';
?>
