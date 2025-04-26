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
include_once '../classes/Document.php';
include_once '../classes/Event.php';
include_once '../classes/Task.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$case_obj = new LegalCase($db);
$document_obj = new Document($db);  
$event_obj = new Event($db);
$task_obj = new Task($db);

// Set case ID
$case_obj->id = $_GET['id'];

// Read case details
if(!$case_obj->readOne()) {
    header("Location: advocate-cases.php");
    exit();
}

// Get case documents
$document_obj->case_id = $case_obj->id;
$case_documents = $document_obj->readByCaseId();

// Get case events
$event_obj->case_id = $case_obj->id;
$case_events = $event_obj->readByCaseId();

// Get case tasks
$task_obj->case_id = $case_obj->id;
$case_tasks = $task_obj->readByCaseId();

// Set page title
$page_title = "Case Details - " . $case_obj->case_number;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Case Details</h1>
        <div class="flex space-x-2">
            <a href="advocate-cases.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Cases
            </a>
            <a href="advocate-case-update.php?id=<?php echo $case_obj->id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-edit mr-2"></i>Edit Case
            </a>
        </div>
    </div>
    
    <!-- Case Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Case Number</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->case_number); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Status</p>
                    <p class="text-base text-gray-900">
    <?php 
        $status_class = '';
        $status_text = ucfirst($case_obj->status);
        
        switch(strtolower($case_obj->status)) {
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
</p>

                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Title</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->title); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Case Type</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->case_type); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Court Name</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->court_name); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Priority</p>
                    <p class="text-base text-gray-900">
                        <?php if($case_obj->priority == 'high'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">High</span>
                        <?php elseif($case_obj->priority == 'medium'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Medium</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Low</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Filing Date</p>
                    <p class="text-base text-gray-900"><?php echo !empty($case_obj->filing_date) ? date('M d, Y', strtotime($case_obj->filing_date)) : 'Not specified'; ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Next Hearing Date</p>
                    <p class="text-base text-gray-900"><?php echo !empty($case_obj->hearing_date) ? date('M d, Y', strtotime($case_obj->hearing_date)) : 'Not scheduled'; ?></p>
                </div>
            </div>
            
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-500">Description</p>
                <p class="text-base text-gray-900"><?php echo nl2br(htmlspecialchars($case_obj->description)); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Client Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Client Information</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Client Name</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->client_name); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Email</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->client_email); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Phone</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->client_phone); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Case Documents -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Case Documents</h2>
            <a href="advocate-document-upload.php?case_id=<?php echo $case_obj->id; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-upload mr-2"></i>Upload Document
            </a>
        </div>
        <div class="p-4">
            <?php if($case_documents && $case_documents->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded On</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
    <?php while($document = $case_documents->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($document['title']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($document['file_type']) ? htmlspecialchars($document['file_type']) : 'Unknown'; ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo isset($document['upload_date']) ? date('M d, Y', strtotime($document['upload_date'])) : date('M d, Y', time()); ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../<?php echo $document['file_path']; ?>" download class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-download"></i>
                                        </a>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody>

                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No documents uploaded for this case yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Case Events -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Case Events</h2>
            <a href="advocate-event-add.php?case_id=<?php echo $case_obj->id; ?>" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-calendar-plus mr-2"></i>Add Event
            </a>
        </div>
        <div class="p-4">
            <?php if($case_events && $case_events->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($event = $case_events->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('h:i A', strtotime($event['event_time'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($event['location']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="advocate-event-view.php?id=<?php echo $event['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="advocate-event-edit.php?id=<?php echo $event['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
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
                    <p class="text-gray-500">No events scheduled for this case yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Case Tasks -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Case Tasks</h2>
            <a href="advocate-task-add.php?case_id=<?php echo $case_obj->id; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                <i class="fas fa-tasks mr-2"></i>Add Task
            </a>
        </div>
        <div class="p-4">
            <?php if($case_tasks && $case_tasks->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($task = $case_tasks->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($task['status'] == 'Completed'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                                        <?php elseif($task['status'] == 'In Progress'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">In Progress</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($task['assigned_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="advocate-task-view.php?id=<?php echo $task['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="advocate-task-edit.php?id=<?php echo $task['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if($task['status'] != 'Completed'): ?>
                                            <a href="advocate-task-complete.php?id=<?php echo $task['id']; ?>" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No tasks created for this case yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
