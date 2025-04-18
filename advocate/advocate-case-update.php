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
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-cases.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Case.php';
include_once '../classes/Advocate.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$case_obj = new LegalCase($db);
$advocate_obj = new Advocate($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set case ID
$case_obj->id = $_GET['id'];

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
$update_success = $update_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set case properties
    $case_obj->title = $_POST['title'];
    $case_obj->description = $_POST['description'];
    $case_obj->case_type = $_POST['case_type'];
    $case_obj->court_name = $_POST['court_name'];
    $case_obj->filing_date = $_POST['filing_date'];
    $case_obj->hearing_date = $_POST['hearing_date'];
    $case_obj->status = $_POST['status'];
    $case_obj->priority = $_POST['priority'];
    
    // Update the case
    if($case_obj->update()) {
        $update_success = "Case updated successfully.";
        
        // Refresh case data
        $case_obj->readOne();
    } else {
        $update_error = "Unable to update case.";
    }
}

// Set page title
$page_title = "Update Case - " . $case_obj->case_number;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Update Case</h1>
        <a href="advocate-case-view.php?id=<?php echo $case_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Case
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
    
    <!-- Case Update Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $case_obj->id); ?>" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="case_number" class="block text-sm font-medium text-gray-700 mb-1">Case Number</label>
                        <input type="text" id="case_number" name="case_number" value="<?php echo htmlspecialchars($case_obj->case_number); ?>" class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" readonly>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="pending" <?php echo ($case_obj->status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo ($case_obj->status == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo ($case_obj->status == 'closed') ? 'selected' : ''; ?>>Closed</option>
                            <option value="won" <?php echo ($case_obj->status == 'won') ? 'selected' : ''; ?>>Won</option>
                            <option value="lost" <?php echo ($case_obj->status == 'lost') ? 'selected' : ''; ?>>Lost</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($case_obj->title); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
                        <input type="text" id="case_type" name="case_type" value="<?php echo htmlspecialchars($case_obj->case_type); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    </div>
                    <div>
                        <label for="court_name" class="block text-sm font-medium text-gray-700 mb-1">Court Name</label>
                        <input type="text" id="court_name" name="court_name" value="<?php echo htmlspecialchars($case_obj->court_name); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="filing_date" class="block text-sm font-medium text-gray-700 mb-1">Filing Date</label>
                        <input type="date" id="filing_date" name="filing_date" value="<?php echo htmlspecialchars($case_obj->filing_date); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="hearing_date" class="block text-sm font-medium text-gray-700 mb-1">Next Hearing Date</label>
                        <input type="date" id="hearing_date" name="hearing_date" value="<?php echo htmlspecialchars($case_obj->hearing_date); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select id="priority" name="priority" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            <option value="low" <?php echo ($case_obj->priority == 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo ($case_obj->priority == 'medium') ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo ($case_obj->priority == 'high') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><?php echo htmlspecialchars($case_obj->description); ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Update Case
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
