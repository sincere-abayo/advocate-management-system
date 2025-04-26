<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
include_once '../classes/Case.php';
include_once '../classes/Client.php';
include_once '../classes/Event.php';
include_once '../classes/Task.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$case_obj = new LegalCase($db);
$event_obj = new Event($db);
$task_obj = new Task($db);

// Get cases - no advocate filtering needed as this is a single advocate system
$advocate_cases = $case_obj->readAll();

// Get upcoming events - no advocate filtering needed
$upcoming_events = $event_obj->getUpcomingEvents(7);

// Get today's events - no advocate filtering needed
$today_events = $event_obj->getTodayEvents();

// Get tasks assigned to this user
$task_obj->assigned_to = $_SESSION['user_id'];
$pending_tasks = $task_obj->getTasksByStatus('Pending');

// Set page title
$page_title = "Advocate Dashboard - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Advocate Dashboard</h1>
    
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>!</h2>
        <p class="text-gray-600">Here's an overview of your cases, events, and tasks.</p>
    </div>
    
    <!-- My Cases -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">My Cases</h2>
        </div>
        <div class="p-4">
            <?php if($advocate_cases && $advocate_cases->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($case = $advocate_cases->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['case_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['client_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($case['status'] == 'Active'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php elseif($case['status'] == 'Pending'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="advocate-case-view.php?id=<?php echo $case['id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No cases found.</p>
            <?php endif; ?>
            
            <div class="mt-4 text-right">
                <a href="advocate-cases.php" class="text-indigo-600 hover:text-indigo-900 font-medium">View all cases →</a>
            </div>
        </div>
    </div>
    
    <!-- Today's Events -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Today's Events</h2>
            </div>
            <div class="p-4">
                <?php if($today_events && $today_events->rowCount() > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php while($event = $today_events->fetch(PDO::FETCH_ASSOC)): ?>
                            <li class="py-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <span class="text-xs font-medium text-white bg-primary px-2 py-1 rounded">
                                            <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['title']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php if(!empty($event['case_number'])): ?>
                                                Case: <?php echo htmlspecialchars($event['case_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <?php if(!empty($event['location'])): ?>
                                            <p class="text-xs text-gray-500">
                                                <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($event['location']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No events scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Tasks -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Pending Tasks</h2>
            </div>
            <div class="p-4">
                <?php if($pending_tasks && $pending_tasks->rowCount() > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php while($task = $pending_tasks->fetch(PDO::FETCH_ASSOC)): ?>
                            <li class="py-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <span class="text-gray-500"><i class="far fa-circle"></i></span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['title']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                            <?php if(!empty($task['case_number'])): ?>
                                                | Case: <?php echo htmlspecialchars($task['case_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No pending tasks</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 text-right">
                    <a href="tasks.php" class="text-indigo-600 hover:text-indigo-900 font-medium">View all tasks →</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="events-add.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-red-600 text-3xl mb-2">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h3 class="text-gray-800 font-medium">New Event</h3>
            </a>
            <a href="tasks-add.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-green-600 text-3xl mb-2">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="text-gray-800 font-medium">New Task</h3>
            </a>
            <a href="documents.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-blue-600 text-3xl mb-2">
                    <i class="fas fa-file-upload"></i>
                </div>
                <h3 class="text-gray-800 font-medium">Upload Document</h3>
            </a>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
