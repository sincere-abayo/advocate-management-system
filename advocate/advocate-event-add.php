<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
include_once '../classes/Client.php';
include_once '../classes/Case.php';
include_once '../classes/Event.php';
include_once '../classes/Advocate.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$client_obj = new Client($db);
$case_obj = new LegalCase($db);
$event_obj = new Event($db);
$advocate_obj = new Advocate($db);

// Get advocate ID from user ID or create one if it doesn't exist
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    // Create a new advocate profile for this user
    $advocate_obj->license_number = "DEFAULT-LICENSE";
    $advocate_obj->specialization = "General Practice";
    $advocate_obj->experience_years = 0;
    $advocate_obj->education = "Not specified";
    $advocate_obj->bio = "";
    $advocate_obj->hourly_rate = 0;
    
    $advocate_id = $advocate_obj->create();
    if(!$advocate_id) {
        die("Error: Could not create advocate profile for this user.");
    }
    
    // Read the newly created advocate profile
    $advocate_obj->readByUserId();
}

// Get cases for this advocate
$cases = $case_obj->readAll();

// Get clients
$clients = $client_obj->read();

// Process form submission
$add_success = $add_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set event properties
    $event_obj->title = $_POST['title'];
    $event_obj->description = $_POST['description'];
    $event_obj->event_date = $_POST['event_date'];
    $event_obj->event_time = $_POST['event_time'];
    $event_obj->end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $event_obj->location = $_POST['location'];
    $event_obj->event_type = $_POST['event_type'];
    $event_obj->advocate_id = $advocate_obj->id; // Use advocate_id from advocates table
    
    // Set optional properties
    $event_obj->case_id = !empty($_POST['case_id']) ? $_POST['case_id'] : null;
    $event_obj->client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    
    // Create the event
    if($event_obj->create()) {
        $add_success = "Event created successfully.";
    } else {
        $add_error = "Unable to create event.";
    }
}

// Set page title
$page_title = "Add Event - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Add Event</h1>
        <a href="advocate-calendar.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Calendar
        </a>
    </div>
    
    <?php if(!empty($add_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $add_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($add_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $add_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Event Add Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Event Information</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" id="title" name="title" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="event_date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="event_date" name="event_date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="event_type" class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                        <select id="event_type" name="event_type" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="Hearing">Hearing</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Deadline">Deadline</option>
                            <option value="Reminder">Reminder</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="event_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" id="event_time" name="event_time" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time (optional)</label>
                        <input type="time" id="end_time" name="end_time" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" id="location" name="location" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case (optional)</label>
                        <select id="case_id" name="case_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="">Select a case</option>
                            <?php 
                            if($cases && $cases->rowCount() > 0) {
                                while($case = $cases->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . $case['id'] . '">' . htmlspecialchars($case['case_number'] . ' - ' . $case['title']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Related Client (optional)</label>
                        <select id="client_id" name="client_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="">Select a client</option>
                            <?php 
                            if($clients && $clients->rowCount() > 0) {
                                while($client = $clients->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . $client['id'] . '">' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Create Event
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
