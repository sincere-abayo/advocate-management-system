<?php
// Start session
session_start();
// Add at the top of index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database and required classes
include_once 'config/database.php';
include_once 'classes/Case.php';
include_once 'classes/Client.php';
include_once 'classes/Advocate.php';
include_once 'classes/Event.php';
include_once 'classes/Task.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$case_obj = new LegalCase($db);
$client_obj = new Client($db);
$advocate_obj = new Advocate($db);
$event_obj = new Event($db);
$task_obj = new Task($db);

// Get counts
$total_cases = $case_obj->count();
$total_clients = $client_obj->count();
$total_advocates = $advocate_obj->count();

// Get cases by status
$active_cases = $case_obj->countByStatus('Active');
$pending_cases = $case_obj->countByStatus('Pending');
$closed_cases = $case_obj->countByStatus('Closed');

// Get upcoming events
$upcoming_events = $event_obj->getUpcomingEvents(7);

// Get today's events
$today_events = $event_obj->getTodayEvents();

// Get recent tasks
$recent_tasks = $task_obj->getRecentTasks(5);

// Set page title
$page_title = "Dashboard - Legal Case Management System";

// Include header
include_once 'templates/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 card-hover">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-gray-600 text-sm font-medium">Total Cases</h2>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $total_cases; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-xs font-medium text-green-600 bg-green-100 px-2 py-1 rounded-full">Active: <?php echo $active_cases; ?></span>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">Pending: <?php echo $pending_cases; ?></span>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-gray-600 bg-gray-100 px-2 py-1 rounded-full">Closed: <?php echo $closed_cases; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 card-hover">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-gray-600 text-sm font-medium">Total Clients</h2>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $total_clients; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <a href="clients.php" class="text-sm text-purple-600 hover:text-purple-800 font-medium">View all clients →</a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 card-hover">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-gray-600 text-sm font-medium">Total Advocates</h2>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $total_advocates; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <a href="advocates.php" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View all advocates →</a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6 card-hover">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h2 class="text-gray-600 text-sm font-medium">Today's Events</h2>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo $today_events->rowCount(); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <a href="calendar.php" class="text-sm text-red-600 hover:text-red-800 font-medium">View calendar →</a>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Today's Events -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Today's Events</h2>
            </div>
            <div class="p-4">
                <?php if($today_events->rowCount() > 0): ?>
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
        
        <!-- Upcoming Events -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Upcoming Events</h2>
            </div>
            <div class="p-4">
                <?php if($upcoming_events->rowCount() > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php while($event = $upcoming_events->fetch(PDO::FETCH_ASSOC)): ?>
                            <li class="py-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <span class="text-xs font-medium text-white bg-indigo-500 px-2 py-1 rounded">
                                            <?php echo date('M d', strtotime($event['event_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['title']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                            <?php if(!empty($event['case_number'])): ?>
                                                | Case: <?php echo htmlspecialchars($event['case_number']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No upcoming events</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Tasks -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Recent Tasks</h2>
            </div>
            <div class="p-4">
                <?php if($recent_tasks->rowCount() > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php while($task = $recent_tasks->fetch(PDO::FETCH_ASSOC)): ?>
                            <li class="py-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <?php if($task['status'] == 'Completed'): ?>
                                            <span class="text-green-500"><i class="fas fa-check-circle"></i></span>
                                        <?php elseif($task['status'] == 'In Progress'): ?>
                                            <span class="text-blue-500"><i class="fas fa-spinner"></i></span>
                                        <?php else: ?>
                                            <span class="text-gray-500"><i class="far fa-circle"></i></span>
                                        <?php endif; ?>
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
                        <p class="text-gray-500">No recent tasks</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="cases-add.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-primary text-3xl mb-2">
                    <i class="fas fa-folder-plus"></i>
                </div>
                <h3 class="text-gray-800 font-medium">New Case</h3>
            </a>
            <a href="clients-add.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-purple-600 text-3xl mb-2">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="text-gray-800 font-medium">New Client</h3>
            </a>
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
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'templates/footer.php';
?>
