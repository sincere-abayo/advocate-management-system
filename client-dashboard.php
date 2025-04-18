<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has client role
if($_SESSION['role'] != 'client') {
    header("Location: login.php");
    exit();
}

// Include database and required classes
include_once 'config/database.php';
include_once 'classes/Case.php';
include_once 'classes/Client.php';
include_once 'classes/Event.php';
include_once 'classes/Task.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$case_obj = new LegalCase($db);
$client_obj = new Client($db);
$event_obj = new Event($db);

// Get client ID
$client_obj->user_id = $_SESSION['user_id'];
if($client_obj->readByUserId()) {
    $client_id = $client_obj->id;
    
    // Set client ID for case object
    $case_obj->client_id = $client_id;
    
    // Get cases for this client
    $client_cases = $case_obj->readByClient();
    
    // Get upcoming events for this client
    $event_obj->client_id = $client_id;
    $upcoming_events = $event_obj->getUpcomingEventsByClient(7);
    
    // Get today's events for this client
    $today_events = $event_obj->getTodayEventsByClient();
}

// Set page title
$page_title = "Client Dashboard - Legal Case Management System";

// Include header
include_once 'templates/client-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-semibold text-gray-800 mb-6">Client Dashboard</h1>
    
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Welcome, <?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>!</h2>
        <p class="text-gray-600">Here's an overview of your cases and upcoming events.</p>
    </div>
    
    <!-- My Cases -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">My Cases</h2>
        </div>
        <div class="p-4">
            <?php if($client_cases && $client_cases->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advocate</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($case = $client_cases->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['case_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['advocate_name']); ?></td>
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
                                        <a href="client-case-view.php?id=<?php echo $case['id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">You don't have any cases yet.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Upcoming Events -->
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
        
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Upcoming Events</h2>
            </div>
            <div class="p-4">
                <?php if($upcoming_events && $upcoming_events->rowCount() > 0): ?>
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
                        <p class="text-gray-500">No upcoming events</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Contact Information -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Need Help?</h2>
        <p class="text-gray-600 mb-4">If you have any questions or need assistance with your case, please don't hesitate to contact us.</p>
        <div class="flex items-center mb-3">
            <i class="fas fa-phone-alt text-primary mr-3"></i>
            <span class="text-gray-700">+1 (555) 123-4567</span>
        </div>
        <div class="flex items-center">
            <i class="fas fa-envelope text-primary mr-3"></i>
            <span class="text-gray-700">support@legalcasemanagement.com</span>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="client-profile.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-blue-600 text-3xl mb-2">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h3 class="text-gray-800 font-medium">Update Profile</h3>
            </a>
            <a href="client-documents.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-green-600 text-3xl mb-2">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 class="text-gray-800 font-medium">View Documents</h3>
            </a>
            <a href="client-messages.php" class="bg-white p-4 rounded-lg shadow text-center hover:bg-gray-50 transition duration-150">
                <div class="text-purple-600 text-3xl mb-2">
                    <i class="fas fa-comment-alt"></i>
                </div>
                <h3 class="text-gray-800 font-medium">Messages</h3>
            </a>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'templates/footer.php';
?>
