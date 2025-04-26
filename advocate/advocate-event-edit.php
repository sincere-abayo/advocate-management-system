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
include_once '../classes/Client.php';
include_once '../classes/Case.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$client_obj = new Client($db);
$case_obj = new LegalCase($db);
$event_obj = new Event($db);

// Set event ID
$event_obj->id = $_GET['id'];

// Read event details
if(!$event_obj->readOne()) {
    header("Location: advocate-calendar.php");
    exit();
}

// Get cases
$cases = $case_obj->readAll();

// Get clients
$clients = $client_obj->read();

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
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($event_obj->title ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="event_date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="event_date" name="event_date" value="<?php echo htmlspecialchars($event_obj->event_date ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="event_type" class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                        <select id="event_type" name="event_type" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="Hearing" <?php echo ($event_obj->event_type == 'Hearing') ? 'selected' : ''; ?>>Hearing</option>
                            <option value="Meeting" <?php echo ($event_obj->event_type == 'Meeting') ? 'selected' : ''; ?>>Meeting</option>
                            <option value="Deadline" <?php echo ($event_obj->event_type == 'Deadline') ? 'selected' : ''; ?>>Deadline</option>
                            <option value="Reminder" <?php echo ($event_obj->event_type == 'Reminder') ? 'selected' : ''; ?>>Reminder</option>
                            <option value="Other" <?php echo ($event_obj->event_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="event_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" id="event_time" name="event_time" value="<?php echo htmlspecialchars($event_obj->event_time ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time (optional)</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($event_obj->end_time ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($event_obj->location ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case (optional)</label>
                        <select id="case_id" name="case_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="">Select a case</option>
                            <?php 
                            if($cases && $cases->rowCount() > 0) {
                                while($case = $cases->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($event_obj->case_id == $case['id']) ? 'selected' : '';
                                    echo '<option value="' . $case['id'] . '" ' . $selected . '>' . htmlspecialchars($case['case_number'] . ' - ' . $case['title']) . '</option>';
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
                                    $selected = ($event_obj->client_id == $client['id']) ? 'selected' : '';
                                    echo '<option value="' . $client['id'] . '" ' . $selected . '>' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><?php echo htmlspecialchars($event_obj->description ?? ''); ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Update Event
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
