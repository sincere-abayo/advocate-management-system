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
include_once '../classes/Task.php';
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$task_obj = new Task($db);
$case_obj = new LegalCase($db);

// Get tasks assigned to this advocate
$task_obj->assigned_to = $_SESSION['user_id'];

// Filter by status if provided
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if(!empty($status_filter)) {
    $tasks = $task_obj->getTasksByStatus($status_filter);
} else {
    $tasks = $task_obj->readAllByUser();
}

// Set page title
$page_title = "My Tasks - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">My Tasks</h1>
        <div class="flex space-x-2">
            <a href="advocate-task-add.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-plus mr-2"></i>Add Task
            </a>
        </div>
    </div>
    
    <!-- Include the message block -->
    <?php include_once '../templates/message-block.php'; ?>
    
    <!-- Task Filters -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="advocate-tasks.php" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo empty($status_filter) ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'; ?>">
                All Tasks
            </a>
            <a href="advocate-tasks.php?status=Pending" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $status_filter == 'Pending' ? 'bg-yellow-500 text-white' : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'; ?>">
                Pending
            </a>
            <a href="advocate-tasks.php?status=In Progress" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $status_filter == 'In Progress' ? 'bg-blue-500 text-white' : 'bg-blue-100 text-blue-800 hover:bg-blue-200'; ?>">
                In Progress
            </a>
            <a href="advocate-tasks.php?status=Completed" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $status_filter == 'Completed' ? 'bg-green-500 text-white' : 'bg-green-100 text-green-800 hover:bg-green-200'; ?>">
                Completed
            </a>
        </div>
    </div>
    
    <!-- Tasks Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php 
                if(!empty($status_filter)) {
                    echo $status_filter . " Tasks";
                } else {
                    echo "All Tasks";
                }
                ?>
            </h2>
        </div>
        <div class="p-4">
            <?php if($tasks && $tasks->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related Case</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($task = $tasks->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['title'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                            if(isset($task['due_date'])) {
                                                $due_date = strtotime($task['due_date']);
                                                $today = strtotime(date('Y-m-d'));
                                                
                                                echo date('M d, Y', $due_date);
                                                
                                                // Show overdue indicator
                                                if($due_date < $today && $task['status'] != 'Completed') {
                                                    echo ' <span class="text-red-600 font-medium">(Overdue)</span>';
                                                }
                                            }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            $status = $task['status'] ?? 'Pending';
                                            switch($status) {
                                                case 'Completed':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'In Progress':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                default:
                                                    echo 'bg-yellow-100 text-yellow-800';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            $priority = $task['priority'] ?? 'Medium';
                                            switch(strtolower($priority)) {
                                                case 'high':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                case 'medium':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                default:
                                                    echo 'bg-blue-100 text-blue-800';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($priority); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if(!empty($task['case_id']) && !empty($task['case_number'])): ?>
                                            <a href="advocate-case-view.php?id=<?php echo $task['case_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($task['case_number'] ?? ''); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="advocate-task-view.php?id=<?php echo $task['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="advocate-task-edit.php?id=<?php echo $task['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if($task['status'] != 'Completed'): ?>
                                            <a href="advocate-task-complete.php?id=<?php echo $task['id']; ?>" class="text-green-600 hover:text-green-900 mr-3" title="Mark as Completed">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500 text-lg">
                        <?php 
                        if(!empty($status_filter)) {
                            echo "No {$status_filter} tasks found.";
                        } else {
                            echo "No tasks found.";
                        }
                        ?>
                    </p>
                    <a href="advocate-task-add.php" class="mt-2 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>Add Your First Task
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>