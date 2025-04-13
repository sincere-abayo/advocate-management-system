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

// Check if task ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-tasks.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Task.php';
include_once '../classes/Case.php';
include_once '../classes/User.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$task_obj = new Task($db);
$case_obj = new LegalCase($db);
$user_obj = new User($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set task ID
$task_obj->id = $_GET['id'];

// Read task details
if(!$task_obj->readOne()) {
    header("Location: advocate-tasks.php");
    exit();
}

// Check if the task belongs to the logged-in advocate
if($task_obj->advocate_id != $advocate_obj->id && $task_obj->assigned_to != $_SESSION['user_id']) {
    header("Location: advocate-tasks.php");
    exit();
}

// Get case details if task is associated with a case
if($task_obj->case_id) {
    $case_obj->id = $task_obj->case_id;
    $case_obj->readOne();
}

// Get assigned user details
if($task_obj->assigned_to) {
    $user_obj->id = $task_obj->assigned_to;
    $user_obj->readOne();
}

// Set page title
$page_title = "Task Details - " . $task_obj->title;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Task Details</h1>
        <div class="flex space-x-2">
            <?php if($task_obj->case_id): ?>
                <a href="advocate-case-view.php?id=<?php echo $task_obj->case_id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Case
                </a>
            <?php else: ?>
                <a href="advocate-tasks.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tasks
                </a>
            <?php endif; ?>
            
            <a href="advocate-task-edit.php?id=<?php echo $task_obj->id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-edit mr-2"></i>Edit Task
            </a>
            
            <?php if($task_obj->status != 'Completed'): ?>
                <a href="advocate-task-complete.php?id=<?php echo $task_obj->id; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-check mr-2"></i>Mark Complete 
                    <?php echo $task_obj->status ?>
                </a>
            <?php endif; ?>

        </div>
        <?php if(isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; ?></p>
        </div>
        <?php unset($_SESSION['success_message']); // Clear the message after displaying ?>
    <?php endif; ?>
    
    <!-- Display error message if set -->
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; ?></p>
        </div>
        <?php unset($_SESSION['error_message']); // Clear the message after displaying ?>
    <?php endif; ?>
    </div>
    
    <!-- Task Details -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Task Information</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo htmlspecialchars($task_obj->title); ?></h3>
                    
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Description</p>
                        <p class="text-base text-gray-900"><?php echo nl2br(htmlspecialchars($task_obj->description)); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <p class="text-base">
                            <?php 
                                $status_class = '';
                                switch($task_obj->status) {
                                    case 'Pending':
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'In Progress':
                                        $status_class = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'Completed':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    default:
                                        $status_class = 'bg-gray-100 text-gray-800';
                                }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($task_obj->status); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Priority</p>
                        <p class="text-base">
                            <?php 
                                $priority_class = '';
                                switch($task_obj->priority) {
                                    case 'Low':
                                        $priority_class = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'Medium':
                                        $priority_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'High':
                                        $priority_class = 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        $priority_class = 'bg-gray-100 text-gray-800';
                                }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $priority_class; ?>">
                                <?php echo htmlspecialchars($task_obj->priority); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div>
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Due Date</p>
                        <p class="text-base text-gray-900"><?php echo date('F d, Y', strtotime($task_obj->due_date)); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Assigned To</p>
                        <p class="text-base text-gray-900"><?php echo isset($user_obj->first_name) ? htmlspecialchars($user_obj->first_name . ' ' . $user_obj->last_name) : 'N/A'; ?></p>
                    </div>
                    
                    <?php if($task_obj->case_id): ?>
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Related Case</p>
                        <p class="text-base text-gray-900">
                            <a href="advocate-case-view.php?id=<?php echo $task_obj->case_id; ?>" class="text-blue-600 hover:text-blue-900">
                                <?php echo htmlspecialchars($case_obj->case_number . ' - ' . $case_obj->title); ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($task_obj->status == 'Completed' && $task_obj->completed_at): ?>
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Completed On</p>
                        <p class="text-base text-gray-900"><?php echo date('F d, Y \a\t h:i A', strtotime($task_obj->completed_at)); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-500">Created On</p>
                        <p class="text-base text-gray-900"><?php echo date('F d, Y', strtotime($task_obj->created_at)); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
