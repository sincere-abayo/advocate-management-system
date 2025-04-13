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
include_once '../classes/Advocate.php';
include_once '../classes/Client.php';
include_once '../classes/Case.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$client_obj = new Client($db);
$case_obj = new LegalCase($db);
$event_obj = new Event($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set event ID
$event_obj->id = $_GET['id'];

// Read event details
if(!$event_obj->readOne()) {
    header("Location: advocate-calendar.php");
    exit();
}

// Check if the event belongs to the logged-in advocate
if($event_obj->advocate_id != $advocate_obj->id) {
    header("Location: advocate-calendar.php");
    exit();
}

// Get cases for this advocate
$case_obj->advocate_id = $advocate_obj->id;
$cases = $case_obj->readByAdvocate();

// Get clients for this advocate
$clients = $case_obj->getClientsByAdvocate($advocate_obj->id);

// Process form submission
$update_success = $update_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set event properties
    $event_obj->title = $_POST['title'];
    $event_obj->description = $_POST['description'];
    $event_obj->event_date = $_POST['event_date'];
    $event_obj->event_time = $_POST['event_time'];
    $event_obj->end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $event_obj->location = $_POST['location'];
    $event_obj->event_type = $_POST['event_type'];
    
    // Set optional properties
    $event_obj->case_id = !empty($_POST['case_id']) ? $_POST['case_id'] : null;
    $event_obj->client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
    
    // Update the event
    if($event_obj->update()) {
        $update_success = "Event updated successfully.";
        
        // Refresh event data
        $event_obj->readOne();
    } else {
        $update_error = "Unable to update event.";
    }
}

// Set page title
$page_title = "Edit Event - " . $event_obj->title;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Edit Event</h1>
        <a href="advocate-event-view.php?id=<?php echo $event_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Event
        </a>
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
    
    <!-- Event Edit Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Event Information</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $event_obj->id); ?>" method="post">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($event_obj->title); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="event_date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_obj->event_date); ?>" class="bg
