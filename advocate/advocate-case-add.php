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
include_once '../classes/Advocate.php';
include_once '../classes/Client.php';
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$client_obj = new Client($db);
$case_obj = new LegalCase($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}
// Get a mix of all clients but highlight ones the advocate has worked with
$worked_with_clients = $case_obj->getClientsByAdvocate($advocate_obj->id);
$all_clients = $client_obj->read();

// Create an array of client IDs the advocate has worked with for easy lookup
$worked_with_client_ids = [];
if($worked_with_clients && $worked_with_clients->rowCount() > 0) {
    while($client = $worked_with_clients->fetch(PDO::FETCH_ASSOC)) {
        $worked_with_client_ids[$client['client_id']] = true;
    }
}

// Process form submission
$add_success = $add_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set case properties
    $case_obj->title = $_POST['title'];
    $case_obj->description = $_POST['description'];
    $case_obj->client_id = $_POST['client_id'];
    $case_obj->advocate_id = $advocate_obj->id;
    $case_obj->case_type = $_POST['case_type'];
    $case_obj->court_name = $_POST['court_name'];
    $case_obj->filing_date = $_POST['filing_date'];
    $case_obj->hearing_date = !empty($_POST['hearing_date']) ? $_POST['hearing_date'] : null;
    $case_obj->status = $_POST['status'];
    $case_obj->priority = $_POST['priority'];
    
    // Create the case
    $case_id = $case_obj->create();
    if($case_id !== false && $case_id > 0) {
        $add_success = "Case created successfully.";
        
        // Redirect to the new case view
        header("Location: advocate-case-view.php?id=" . $case_id . "&success=1");
        exit();
    } else {
        $add_error = "Unable to create case.";
    }
}

// Set page title
$page_title = "Add New Case - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Create New Case</h1>
        <a href="advocate-cases.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Cases
        </a>
    </div>
    
    <?php if(!empty($add_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $add_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Case Creation Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Case Title</label>
                    <input type="text" id="title" name="title" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="mb-4">
    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
    <select id="client_id" name="client_id" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
        <option value="">Select a client</option>
        <?php 
        if($all_clients && $all_clients->rowCount() > 0) {
            // Group clients: first show clients the advocate has worked with
            if(!empty($worked_with_client_ids)) {
                echo '<optgroup label="Previous Clients">';
                $all_clients->execute(); // Reset the cursor to start
                while($client = $all_clients->fetch(PDO::FETCH_ASSOC)) {
                    if(isset($worked_with_client_ids[$client['id']])) {
                        echo '<option value="' . $client['id'] . '">' . 
                             htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . 
                             '</option>';
                    }
                }
                echo '</optgroup>';
                
                echo '<optgroup label="Other Clients">';
                $all_clients->execute(); // Reset the cursor to start again
                while($client = $all_clients->fetch(PDO::FETCH_ASSOC)) {
                    if(!isset($worked_with_client_ids[$client['id']])) {
                        echo '<option value="' . $client['id'] . '">' . 
                             htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . 
                             '</option>';
                    }
                }
                echo '</optgroup>';
            } else {
                // If advocate hasn't worked with any clients yet, just show all
                while($client = $all_clients->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . $client['id'] . '">' . 
                         htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) . 
                         '</option>';
                }
            }
        } else {
            echo '<option value="">No clients available</option>';
        }
        ?>
    </select>
</div>

                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
                        <select id="case_type" name="case_type" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="">Select case type</option>
                            <option value="Civil">Civil</option>
                            <option value="Criminal">Criminal</option>
                            <option value="Family">Family</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Property">Property</option>
                            <option value="Tax">Tax</option>
                            <option value="Intellectual Property">Intellectual Property</option>
                            <option value="Labor">Labor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="court_name" class="block text-sm font-medium text-gray-700 mb-1">Court Name</label>
                        <input type="text" id="court_name" name="court_name" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="filing_date" class="block text-sm font-medium text-gray-700 mb-1">Filing Date</label>
                        <input type="date" id="filing_date" name="filing_date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="hearing_date" class="block text-sm font-medium text-gray-700 mb-1">Next Hearing Date (if known)</label>
                        <input type="date" id="hearing_date" name="hearing_date" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="closed">Closed</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="priority" name="priority" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Case Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Create Case
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
