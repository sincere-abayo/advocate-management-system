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
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize case object
$case_obj = new LegalCase($db);

// Get all cases - no advocate filtering needed for single advocate system
$advocate_cases = $case_obj->readAll();

// Set page title
$page_title = "My Cases - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">My Cases</h1>
        <div class="flex space-x-2">
            <a href="advocate-case-add.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-plus mr-2"></i>Add Case
            </a>
        </div>
    </div>
    
    <!-- Cases Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">All Cases</h2>
        </div>
        
        <div class="overflow-x-auto">
            <?php if($advocate_cases && $advocate_cases->rowCount() > 0): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case Number</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Hearing</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($case = $advocate_cases->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['case_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['title']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['client_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($case['case_type']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
    $status_class = '';
    $status_text = ucfirst($case['status']);
    
    switch(strtolower($case['status'])) {
        case 'active':
            $status_class = 'bg-green-100 text-green-800';
            break;
        case 'pending':
            $status_class = 'bg-yellow-100 text-yellow-800';
            break;
        case 'closed':
            $status_class = 'bg-gray-100 text-gray-800';
            break;
        case 'won':
            $status_class = 'bg-blue-100 text-blue-800';
            break;
        case 'lost':
            $status_class = 'bg-red-100 text-red-800';
            break;
        default:
            $status_class = 'bg-gray-100 text-gray-800';
            break;
    }
?>
<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
    <?php echo $status_text; ?>
</span>

                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($case['hearing_date']) ? date('M d, Y', strtotime($case['hearing_date'])) : 'Not scheduled'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="advocate-case-view.php?id=<?php echo $case['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <!-- <a href="advocate-case-update.php?id=<?php //echo $case['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a> -->
                                    <a href="advocate-document-upload.php?case_id=<?php echo $case['id']; ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500 text-lg">No cases found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
