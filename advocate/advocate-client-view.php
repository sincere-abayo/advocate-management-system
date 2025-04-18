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

// Check if client ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: advocate-clients.php");
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

// Set client ID
$client_obj->id = $_GET['id'];

// Read client details
if(!$client_obj->readOne()) {
    header("Location: advocate-clients.php");
    exit();
}

// Check if the client is associated with this advocate
$case_obj->advocate_id = $advocate_obj->id;
$case_obj->client_id = $client_obj->id;
if(!$case_obj->checkClientAdvocateAssociation()) {
    header("Location: advocate-clients.php");
    exit();
}

// Get client cases
$client_cases = $case_obj->readByClientAndAdvocate();

// Set page title
$page_title = "Client Details - " . $client_obj->first_name . " " . $client_obj->last_name;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Client Details</h1>
        <a href="advocate-clients.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Clients
        </a>
    </div>
    
    <!-- Client Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Client Information</h2>
        </div>
        <div class="p-4">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 h-24 w-24">
                    <?php if(!empty($client_obj->profile_image)): ?>
                        <img class="h-24 w-24 rounded-full" src="../uploads/profile/<?php echo $client_obj->profile_image; ?>" alt="Profile image">
                    <?php else: ?>
                        <div class="h-24 w-24 rounded-full bg-gray-300 flex items-center justify-center">
                            <span class="text-gray-600 text-2xl font-medium"><?php echo substr($client_obj->first_name, 0, 1) . substr($client_obj->last_name, 0, 1); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ml-6">
                    <h3 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($client_obj->first_name . ' ' . $client_obj->last_name); ?></h3>
                    <p class="text-sm text-gray-500">Client since: <?php echo date('F Y', strtotime($client_obj->created_at)); ?></p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Email</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->email); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Phone</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->phone); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Address</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->address); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Occupation</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->occupation); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Company</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->company); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Reference Source</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($client_obj->reference_source); ?></p>
                </div>
            </div>
            
            <?php if(!empty($client_obj->notes)): ?>
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-500">Notes</p>
                <p class="text-base text-gray-900"><?php echo nl2br(htmlspecialchars($client_obj->notes)); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Client Cases -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Client Cases</h2>
        </div>
        <div class="p-4">
            <?php if($client_cases && $client_cases->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Hearing</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($case = $client_cases->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['case_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['case_type']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($case['status'] == 'Active'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php elseif($case['status'] == 'Pending'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($case['hearing_date']) ? date('M d, Y', strtotime($case['hearing_date'])) : 'Not scheduled'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="advocate-case-view.php?id=<?php echo $case['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="advocate-case-update.php?id=<?php echo $case['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No cases found for this client.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
