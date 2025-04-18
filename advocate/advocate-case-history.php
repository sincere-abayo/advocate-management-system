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
include_once '../classes/Advocate.php';
include_once '../classes/Case.php';
include_once '../classes/CaseHistory.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$case_obj = new LegalCase($db);
$history_obj = new CaseHistory($db);

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

// Get case history
$history_obj->case_id = $case_obj->id;
$history = $history_obj->readByCaseId();

// Set page title
$page_title = "Case History - " . $case_obj->case_number;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Case History</h1>
        <div class="flex space-x-2">
            <a href="advocate-case-view.php?id=<?php echo $case_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Case
            </a>
        </div>
    </div>
    
    <!-- Case Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap">
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Case Number</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->case_number); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Title</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->title); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    <p class="text-base">
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                        <?php 
                            switch(strtolower($case_obj->status)) {
                                case 'active':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'pending':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'closed':
                                    echo 'bg-gray-100 text-gray-800';
                                    break;
                                case 'won':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                case 'lost':
                                    echo 'bg-red-100 text-red-800';
                                    default:
                                    echo 'bg-gray-100 text-gray-800';
                                    break;
                            }
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($case_obj->status)); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Case History Timeline -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Timeline</h2>
        </div>
        <div class="p-4">
            <?php if($history && $history->rowCount() > 0): ?>
                <div class="relative">
                    <!-- Timeline line -->
                    <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                    
                    <div class="space-y-6">
                        <?php while($item = $history->fetch(PDO::FETCH_ASSOC)): ?>
                            <div class="relative pl-10">
                                <!-- Timeline dot -->
                                <div class="absolute left-0 top-1.5 w-10 flex items-center justify-center">
                                    <div class="h-5 w-5 rounded-full border-4 border-white bg-blue-500 shadow"></div>
                                </div>
                                
                                <!-- Timeline content -->
                                <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($item['action_type']); ?></h3>
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('M d, Y h:i A', strtotime($item['action_date'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <p class="text-xs text-gray-500 mt-2">
                                        By: <?php echo htmlspecialchars($item['user_name']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 mb-2">
                        <i class="fas fa-history text-4xl"></i>
                    </div>
                    <p class="text-gray-500">No history records found for this case.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add History Entry (for manual entries) -->
    <div class="mt-6 bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Add History Entry</h2>
        </div>
        <div class="p-4">
            <form action="advocate-case-history-add.php" method="post">
                <input type="hidden" name="case_id" value="<?php echo $case_obj->id; ?>">
                
                <div class="mb-4">
                    <label for="action_type" class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                    <select id="action_type" name="action_type" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        <option value="">Select action type</option>
                        <option value="Note">Note</option>
                        <option value="Phone Call">Phone Call</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Document Filed">Document Filed</option>
                        <option value="Court Appearance">Court Appearance</option>
                        <option value="Client Communication">Client Communication</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Add Entry
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

