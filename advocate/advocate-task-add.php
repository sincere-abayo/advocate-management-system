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

// Check if case ID is provided
if(!isset($_GET['case_id']) || empty($_GET['case_id'])) {
    header("Location: advocate-cases.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Case.php';
include_once '../classes/Task.php';
include_once '../classes/User.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$case_obj = new LegalCase($db);
$task_obj = new Task($db);
$user_obj = new User($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set case ID
$case_obj->id = $_GET['case_id'];

// Read case details
if(!$case_obj->readOne()) {
    header("Location: advocate-cases.php");
    exit();
}

// Check if the case belongs to the logged-in advocate
if($case_obj->advocate_id != $advocate_obj->id) {
    header("Location: advocate-cases.php");
    exit();
}

// Process form submission
$task_success = $task_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set task properties
    $task_obj->title = $_POST['title'];
    $task_obj->description = $_POST['description'];
    $task_obj->due_date = $_POST['due_date'];
    $task_obj->priority = $_POST['priority'];
    $task_obj->status = 'Pending';
    $task_obj->case_id = $case_obj->id;
    $task_obj->client_id = $case_obj->client_id;
    $task_obj->advocate_id = $advocate_obj->id;
    $task_obj->assigned_to = $_POST['assigned_to'];
    $task_obj->assigned_by = $_SESSION['user_id'];
    
    // Create task
    if($task_obj->create()) {
        // Add to case history
        $case_obj->addToHistory($case_obj->id, "Task created", "Task '{$_POST['title']}' was created");
        
        $task_success = "Task created successfully.";
    } else {
        $task_error = "Failed to create task.";
    }
}

// Get users for assignment dropdown
$users = $user_obj->read();

// Set page title
$page_title = "Add Task - " . $case_obj->case_number;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Add Task</h1>
        <a href="advocate-case-view.php?id=<?php echo $case_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Case
        </a>
    </div>
    
    <!-- Case Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap">
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Case Number</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->case_number); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Title</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->title); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Client</p>
                    <p class="text-base text-gray-900">
                        <?php echo isset($case_obj->client_name) ? htmlspecialchars($case_obj->client_name) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if(!empty($task_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $task_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($task_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $task_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Task Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Task Details</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?case_id=" . $case_obj->id); ?>" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Task Title</label>
                        <input type="text" id="title" name="title" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" id="due_date" name="due_date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="priority" name="priority" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                        <select id="assigned_to" name="assigned_to" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="<?php echo $_SESSION['user_id']; ?>">Myself</option>
                            <?php 
                            if($users && $users->rowCount() > 0) {
                                while($user = $users->fetch(PDO::FETCH_ASSOC)) {
                                    // Skip the current user as they're already in the "Myself" option
                                    if($user['id'] == $_SESSION['user_id']) continue;
                                    
                                    echo '<option value="' . $user['id'] . '">' . 
                                         htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . 
                                         ' (' . ucfirst($user['role']) . ')' .
                                         '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Create Task
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
