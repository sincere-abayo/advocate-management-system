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

// Process form submission
$update_success = $update_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set task properties
    $task_obj->title = $_POST['title'];
    $task_obj->description = $_POST['description'];
    $task_obj->due_date = $_POST['due_date'];
    $task_obj->priority = $_POST['priority'];
    $task_obj->status = $_POST['status'];
    $task_obj->assigned_to = $_POST['assigned_to'];
    
    // Update task
    if($task_obj->update()) {
        // Add to case history if associated with a case
        if($task_obj->case_id) {
            $case_obj->addToHistory($task_obj->case_id, "Task updated", "Task '{$_POST['title']}' was updated");
        }
        
        $update_success = "Task updated successfully.";
    } else {
        $update_error = "Failed to update task.";
    }
}

// Get users for assignment dropdown
$users = $user_obj->read();

// Set page title
$page_title = "Edit Task - " . $task_obj->title;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Edit Task</h1>
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
            
            <a href="advocate-task-view.php?id=<?php echo $task_obj->id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-eye mr-2"></i>View Task
            </a>
        </div>
    </div>
    
    <?php if(!empty($update_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $update_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($update_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $update_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Task Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Task Details</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $task_obj->id); ?>" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Task Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($task_obj->title); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo htmlspecialchars($task_obj->due_date); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><?php echo htmlspecialchars($task_obj->description); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="priority" name="priority" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="Low" <?php echo ($task_obj->priority == 'Low') ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo ($task_obj->priority == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo ($task_obj->priority == 'High') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="Pending" <?php echo ($task_obj->status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo ($task_obj->status == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo ($task_obj->status == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                        <select id="assigned_to" name="assigned_to" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <?php 
                            if($users && $users->rowCount() > 0) {
                                while($user = $users->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($user['id'] == $task_obj->assigned_to) ? 'selected' : '';
                                    echo '<option value="' . $user['id'] . '" ' . $selected . '>' . 
                                         htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . 
                                         ' (' . ucfirst($user['role']) . ')' .
                                         '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <?php if($task_obj->case_id): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Related Case</label>
                    <div class="bg-gray-100 p-2.5 rounded-lg">
                        <a href="advocate-case-view.php?id=<?php echo $task_obj->case_id; ?>" class="text-blue-600 hover:text-blue-900">
                            <?php echo htmlspecialchars($case_obj->case_number . ' - ' . $case_obj->title); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Update Task
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

