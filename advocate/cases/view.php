<?php
// Set page title
$pageTitle = "Case Details";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage($path_url.'advocate/cases/index.php', 'Invalid case ID', 'error');
}

$caseId = (int)$_GET['id'];

// Create database connection
$conn = getDBConnection();

// Check if advocate has access to this case
$accessStmt = $conn->prepare("
    SELECT COUNT(*) as has_access
    FROM case_assignments
    WHERE case_id = ? AND advocate_id = ?
");
$accessStmt->bind_param("ii", $caseId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();
$hasAccess = $accessResult->fetch_assoc()['has_access'] > 0;

if (!$hasAccess) {
    $conn->close();
    redirectWithMessage($path_url.'advocate/cases/index.php', 'You do not have access to this case', 'error');
}

// Get case details
$caseStmt = $conn->prepare("
    SELECT c.*, cp.client_id, u.full_name as client_name, u.email as client_email, u.phone as client_phone
    FROM cases c
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE c.case_id = ?
");
$caseStmt->bind_param("i", $caseId);
$caseStmt->execute();
$caseResult = $caseStmt->get_result();

if ($caseResult->num_rows === 0) {
    $conn->close();
    redirectWithMessage($path_url.'advocate/cases/index.php', 'Case not found', 'error');
}

$case = $caseResult->fetch_assoc();

// Get case activities
$activitiesStmt = $conn->prepare("
    SELECT ca.*, u.full_name
    FROM case_activities ca
    JOIN users u ON ca.user_id = u.user_id
    WHERE ca.case_id = ?
    ORDER BY ca.activity_date DESC
    LIMIT 5
");
$activitiesStmt->bind_param("i", $caseId);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();

// Get case documents
$documentsStmt = $conn->prepare("
    SELECT d.*, u.full_name as uploaded_by_name
    FROM documents d
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE d.case_id = ?
    ORDER BY d.upload_date DESC
");
$documentsStmt->bind_param("i", $caseId);
$documentsStmt->execute();
$documentsResult = $documentsStmt->get_result();

// Get case hearings
$hearingsStmt = $conn->prepare("
    SELECT *
    FROM case_hearings
    WHERE case_id = ?
    ORDER BY hearing_date ASC
");
$hearingsStmt->bind_param("i", $caseId);
$hearingsStmt->execute();
$hearingsResult = $hearingsStmt->get_result();

// Get assigned advocates
$advocatesStmt = $conn->prepare("
    SELECT ca.*, u.full_name, ap.license_number, ap.specialization
    FROM case_assignments ca
    JOIN advocate_profiles ap ON ca.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE ca.case_id = ?
");
$advocatesStmt->bind_param("i", $caseId);
$advocatesStmt->execute();
$advocatesResult = $advocatesStmt->get_result();

// Process status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $newStatus = sanitizeInput($_POST['status']);
    $updateStmt = $conn->prepare("UPDATE cases SET status = ? WHERE case_id = ?");
    $updateStmt->bind_param("si", $newStatus, $caseId);
    
    if ($updateStmt->execute()) {
        // Add activity log
        $activityDesc = "Case status updated from '{$case['status']}' to '{$newStatus}'";
        $activityStmt = $conn->prepare("
            INSERT INTO case_activities (case_id, user_id, activity_type, description)
            VALUES (?, ?, 'status_change', ?)
        ");
        $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $activityDesc);
        $activityStmt->execute();
        
        // Update case variable
        $case['status'] = $newStatus;
        
        $_SESSION['flash_message'] = "Case status updated successfully";
        $_SESSION['flash_type'] = "success";
        
        // Redirect to refresh the page
        header("Location: ".$path_url."advocate/cases/view.php?id=$caseId");
        exit;
    }
}

// Process add note
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_note') {
    $noteContent = sanitizeInput($_POST['note_content']);
    
    if (!empty($noteContent)) {
        $activityStmt = $conn->prepare("
            INSERT INTO case_activities (case_id, user_id, activity_type, description)
            VALUES (?, ?, 'note', ?)
        ");
        $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $noteContent);
        
        if ($activityStmt->execute()) {
            $_SESSION['flash_message'] = "Note added successfully";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to refresh the page
            header("Location: ".$path_url."advocate/cases/view.php?id=$caseId");
            exit;
        }
    }
}

// Close connection
$conn->close();

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'closed':
            return 'bg-gray-100 text-gray-800';
        case 'won':
            return 'bg-green-100 text-green-800';
        case 'lost':
            return 'bg-red-100 text-red-800';
        case 'settled':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to get priority badge class
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'high':
            return 'bg-red-100 text-red-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 flex items-center">
                <span class="mr-3"><?php echo htmlspecialchars($case['title']); ?></span>
                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo getStatusBadgeClass($case['status']); ?>">
                    <?php echo ucfirst(htmlspecialchars($case['status'])); ?>
                </span>
            </h1>
            <p class="text-gray-600 mt-1">Case Number: <span class="font-medium"><?php echo htmlspecialchars($case['case_number']); ?></span></p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="<?php echo $path_url; ?>advocate/cases/edit.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit Case
            </a>
            <a href="<?php echo $path_url; ?>advocate/cases/index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cases
            </a>
        </div>
    </div>
</div>

<!-- Case Details Tabs -->
<div x-data="{ activeTab: 'details' }" class="bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Tab Navigation -->
    <div class="bg-gray-50 border-b border-gray-200">
        <nav class="flex overflow-x-auto">
            <button @click="activeTab = 'details'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'details', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'details' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                <i class="fas fa-info-circle mr-2"></i> Details
            </button>
            <button @click="activeTab = 'activities'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'activities', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'activities' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                <i class="fas fa-history mr-2"></i> Activities
            </button>
            <button @click="activeTab = 'documents'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'documents', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'documents' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                <i class="fas fa-file-alt mr-2"></i> Documents
            </button>
            <button @click="activeTab = 'hearings'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'hearings', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'hearings' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none">
                <i class="fas fa-gavel mr-2"></i> Hearings
            </button>
            <button @click="activeTab = 'team'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'team', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'team' }" class="py-4 px-6 border-b-2 font-medium text-sm focus:outline-none hidden">
                <i class="fas fa-users mr-2"></i> Team
            </button>
        </nav>
    </div>
    
    <!-- Tab Content -->
    <div class="p-6">
        <!-- Details Tab -->
        <div x-show="activeTab === 'details'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Case Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Case Information</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Case Type:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($case['case_type']); ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Court:</span>
                            <span class="font-medium"><?php echo !empty($case['court']) ? htmlspecialchars($case['court']) : 'Not specified'; ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Filing Date:</span>
                            <span class="font-medium"><?php echo !empty($case['filing_date']) ? formatDate($case['filing_date']) : 'Not specified'; ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Next Hearing:</span>
                            <span class="font-medium"><?php echo !empty($case['hearing_date']) ? formatDate($case['hearing_date']) : 'Not scheduled'; ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Priority:</span>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo getPriorityBadgeClass($case['priority']); ?>">
                                <?php echo ucfirst(htmlspecialchars($case['priority'])); ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Created:</span>
                            <span class="font-medium"><?php echo formatDate($case['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Status Update Form -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Update Status</h4>
                        <form method="POST" action="" class="flex space-x-2">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="form-select flex-grow">
                                <option value="pending" <?php echo $case['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $case['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo $case['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="won" <?php echo $case['status'] == 'won' ? 'selected' : ''; ?>>Won</option>
                                <option value="lost" <?php echo $case['status'] == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                <option value="settled" <?php echo $case['status'] == 'settled' ? 'selected' : ''; ?>>Settled</option>
                            </select>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                                Update
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Client Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Client Information</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xl mr-3">
                                <?php echo strtoupper(substr($case['client_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="font-medium"><?php echo htmlspecialchars($case['client_name']); ?></div>
                                <div class="text-sm text-gray-600">Client</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-gray-400 w-5 mr-2"></i>
                            <span><?php echo htmlspecialchars($case['client_email']); ?></span>
                        </div>
                        
                        <?php if (!empty($case['client_phone'])): ?>
                            <div class="flex items-center">
                                <i class="fas fa-phone text-gray-400 w-5 mr-2"></i>
                                <span><?php echo htmlspecialchars($case['client_phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="../clients/view.php?id=<?php echo $case['client_id']; ?>" class="text-blue-600 hover:underline inline-flex items-center">
                                <i class="fas fa-user mr-1"></i> View Client Profile
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Quick Actions</h4>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?php echo $path_url ?>advocate/appointments/create.php?client_id=<?php echo $case['client_id']; ?>&case_id=<?php echo $caseId; ?>" class="bg-green-100 hover:bg-green-200 text-green-800 text-sm font-medium py-2 px-3 rounded inline-flex items-center">
                                <i class="fas fa-calendar-plus mr-1"></i> Schedule Appointment
                            </a>
                            <a href="<?php echo $path_url ?>advocate/documents/upload.php?case_id=<?php echo $caseId; ?>" class="bg-blue-100 hover:bg-blue-200 text-blue-800 text-sm font-medium py-2 px-3 rounded inline-flex items-center">
                                <i class="fas fa-file-upload mr-1"></i> Upload Document
                            </a>
                            <a href="<?php echo $path_url ?>advocate/messages/compose.php?client_id=<?php echo $case['client_id']; ?>&case_id=<?php echo $caseId; ?>" class="bg-purple-100 hover:bg-purple-200 text-purple-800 text-sm font-medium py-2 px-3 rounded inline-flex items-center">
                                <i class="fas fa-envelope mr-1"></i> Send Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Case Description -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Case Description</h3>
                <div class="prose max-w-none">
                    <?php if (!empty($case['description'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                    <?php else: ?>
                        <p class="text-gray-500 italic">No description provided</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Note Form -->
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add Note</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note_content" rows="3" class="form-textarea w-full mb-3" placeholder="Add a note about this case..."></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fas fa-plus mr-2"></i> Add Note
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Recent Activities -->
            <div class="bg-gray-50 rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Activities</h3>
                    <button @click="activeTab = 'activities'" class="text-blue-600 hover:underline text-sm">
                        View All
                    </button>
                </div>
                
                <?php if ($activitiesResult->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($activity = $activitiesResult->fetch_assoc()): ?>
                            <div class="flex">
                                <div class="flex-shrink-0 mr-3">
                                    <?php
                                    $iconClass = 'fas fa-info-circle text-blue-500';
                                    switch ($activity['activity_type']) {
                                        case 'update':
                                            $iconClass = 'fas fa-edit text-blue-500';
                                            break;
                                        case 'document':
                                            $iconClass = 'fas fa-file-alt text-green-500';
                                            break;
                                        case 'hearing':
                                            $iconClass = 'fas fa-gavel text-purple-500';
                                            break;
                                        case 'note':
                                            $iconClass = 'fas fa-sticky-note text-yellow-500';
                                            break;
                                        case 'status_change':
                                            $iconClass = 'fas fa-exchange-alt text-red-500';
                                            break;
                                    }
                                    ?>
                                    <div class="bg-gray-100 rounded-full p-2">
                                        <i class="<?php echo $iconClass; ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        <?php echo htmlspecialchars($activity['full_name']); ?>
                                        <span class="text-gray-500 font-normal">
                                            <?php echo $activity['activity_type'] == 'note' ? 'added a note' : 'made an update'; ?>
                                        </span>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php echo date('M d, Y h:i A', strtotime($activity['activity_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2"><i class="fas fa-history text-3xl"></i></div>
                        <p class="text-gray-500">No activities recorded yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Activities Tab -->
        <div x-show="activeTab === 'activities'" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Case Activities</h3>
                <a href="add-activity.php?case_id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
    <i class="fas fa-plus mr-2"></i> Add Activity
</a>

            </div>
            
            <?php
            // Reset the activities result pointer
            $activitiesResult->data_seek(0);
            ?>
            
            <?php if ($activitiesResult->num_rows > 0): ?>
                <div class="space-y-6">
                    <?php while ($activity = $activitiesResult->fetch_assoc()): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0 mr-3">
                                    <?php
                                    $iconClass = 'fas fa-info-circle text-blue-500';
                                    switch ($activity['activity_type']) {
                                        case 'update':
                                            $iconClass = 'fas fa-edit text-blue-500';
                                            break;
                                        case 'document':
                                            $iconClass = 'fas fa-file-alt text-green-500';
                                            break;
                                        case 'hearing':
                                            $iconClass = 'fas fa-gavel text-purple-500';
                                            break;
                                        case 'note':
                                            $iconClass = 'fas fa-sticky-note text-yellow-500';
                                            break;
                                        case 'status_change':
                                            $iconClass = 'fas fa-exchange-alt text-red-500';
                                            break;
                                    }
                                    ?>
                                    <div class="bg-white rounded-full p-2">
                                        <i class="<?php echo $iconClass; ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex justify-between items-start">
                                        <p class="text-sm font-medium">
                                            <?php echo htmlspecialchars($activity['full_name']); ?>
                                            <span class="text-gray-500 font-normal">
                                                <?php 
                                                switch ($activity['activity_type']) {
                                                    case 'update':
                                                        echo 'updated the case';
                                                        break;
                                                    case 'document':
                                                        echo 'added a document';
                                                        break;
                                                    case 'hearing':
                                                        echo 'added a hearing';
                                                        break;
                                                    case 'note':
                                                        echo 'added a note';
                                                        break;
                                                    case 'status_change':
                                                        echo 'changed the status';
                                                        break;
                                                    default:
                                                        echo 'made an update';
                                                }
                                                ?>
                                            </span>
                                        </p>
                                        <span class="text-xs text-gray-400">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['activity_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="mt-2 text-sm text-gray-600">
                                        <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <div class="text-gray-400 mb-3"><i class="fas fa-history text-5xl"></i></div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No activities recorded</h3>
                    <p class="text-gray-500 mb-6">Start tracking case progress by adding activities</p>
                    <a href="add-activity.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add First Activity
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Documents Tab -->
        <div x-show="activeTab === 'documents'" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Case Documents</h3>
                <a href="../documents/upload.php?case_id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-file-upload mr-2"></i> Upload Document
                </a>
            </div>
            
            <?php if ($documentsResult->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Document
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Uploaded By
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($document = $documentsResult->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php
                                            $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                            $iconClass = 'fas fa-file text-gray-500';
                                            
                                            switch (strtolower($fileExtension)) {
                                                case 'pdf':
                                                    $iconClass = 'fas fa-file-pdf text-red-500';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $iconClass = 'fas fa-file-word text-blue-500';
                                                    break;
                                                case 'xls':
                                                case 'xlsx':
                                                    $iconClass = 'fas fa-file-excel text-green-500';
                                                    break;
                                                case 'jpg':
                                                case 'jpeg':
                                                case 'png':
                                                    $iconClass = 'fas fa-file-image text-purple-500';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $iconClass; ?> mr-3 text-lg"></i>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($document['title']); ?>
                                                </div>
                                                <?php if (!empty($document['description'])): ?>
                                                    <div class="text-xs text-gray-500 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($document['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($document['document_type']) ? htmlspecialchars($document['document_type']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($document['uploaded_by_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($document['upload_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a href="../../uploads/documents/<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900" title="View Document">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../../uploads/documents/<?php echo $document['file_path']; ?>" download class="text-green-600 hover:text-green-900" title="Download Document">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($document['uploaded_by'] == $_SESSION['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $document['document_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit Document">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $document['document_id']; ?>" class="text-red-600 hover:text-red-900" title="Delete Document" onclick="return confirm('Are you sure you want to delete this document?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <div class="text-gray-400 mb-3"><i class="fas fa-file-alt text-5xl"></i></div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No documents uploaded</h3>
                    <p class="text-gray-500 mb-6">Upload case-related documents for better organization</p>
                    <a href="../documents/upload.php?case_id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-file-upload mr-2"></i> Upload First Document
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Hearings Tab -->
        <div x-show="activeTab === 'hearings'" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Case Hearings</h3>
                <a href="add-hearing.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add Hearing
                </a>
            </div>
            
            <?php if ($hearingsResult->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date & Time
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Court
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Judge
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Purpose
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($hearing = $hearingsResult->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo formatDate($hearing['hearing_date']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo formatTime($hearing['hearing_time']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($hearing['court_room'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($hearing['judge']) ? htmlspecialchars($hearing['judge']) : 'Not specified'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($hearing['description'] ?? ''); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($hearing['status']) {
                                            case 'scheduled':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'completed':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'adjourned':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($hearing['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a href="<?php echo $path_url ?>advocate/cases/hearing-details.php?id=<?php echo $hearing['hearing_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Hearing">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?php echo $path_url ?>advocate/cases/edit-hearing.php?id=<?php echo $hearing['hearing_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit Hearing">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo $path_url ?>advocate/cases/delete-hearing.php?id=<?php echo $hearing['hearing_id']; ?>" class="text-red-600 hover:text-red-900" title="Delete Hearing" onclick="return confirm('Are you sure you want to delete this hearing?');">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <div class="text-gray-400 mb-3"><i class="fas fa-gavel text-5xl"></i></div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No hearings scheduled</h3>
                    <p class="text-gray-500 mb-6">Add hearing details to keep track of court appearances</p>
                    <a href="<?php echo $path_url ?>advocate/cases/add-hearing.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> Schedule First Hearing
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Team Tab -->
        <div x-show="activeTab === 'team'" style="display: none;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Case Team</h3>
                <a href="<?php echo $path_url ?>advocate/cases/assign-advocate.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-user-plus mr-2"></i> Assign Advocate
                </a>
            </div>
            
            <?php if ($advocatesResult->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php while ($advocate = $advocatesResult->fetch_assoc()): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3">
                                    <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xl">
                                        <?php echo strtoupper(substr($advocate['full_name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($advocate['full_name']); ?></h4>
                                            <p class="text-xs text-gray-500">
                                                <?php echo ucfirst(htmlspecialchars($advocate['role'])); ?> Advocate
                                                <?php if (!empty($advocate['specialization'])): ?>
                                                    • <?php echo htmlspecialchars($advocate['specialization']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($advocate['license_number'])): ?>
                                                <p class="text-xs text-gray-500 mt-1">License: <?php echo htmlspecialchars($advocate['license_number']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Assigned: <?php echo date('M d, Y', strtotime($advocate['assigned_date'])); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 flex space-x-2">
                                        <a href="<?php echo $path_url ?>advocate/profile.php?id=<?php echo $advocate['advocate_id']; ?>" class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-1 px-2 rounded">
                                            View Profile
                                        </a>
                                        <?php if ($advocate['advocate_id'] != $advocateId): ?>
                                            <a href="<?php echo $path_url ?>advocate/messages/compose.php?advocate_id=<?php echo $advocate['advocate_id']; ?>&case_id=<?php echo $caseId; ?>" class="text-xs bg-green-100 hover:bg-green-200 text-green-800 font-medium py-1 px-2 rounded">
                                                Message
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($advocate['role'] != 'primary' || $advocate['advocate_id'] != $advocateId): ?>
                                            <a href="<?php echo $path_url ?>advocate/cases/remove-advocate.php?case_id=<?php echo $caseId; ?>&advocate_id=<?php echo $advocate['advocate_id']; ?>" class="text-xs bg-red-100 hover:bg-red-200 text-red-800 font-medium py-1 px-2 rounded" onclick="return confirm('Are you sure you want to remove this advocate from the case?');">
                                                Remove
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8 bg-gray-50 rounded-lg">
                    <div class="text-gray-400 mb-3"><i class="fas fa-users text-5xl"></i></div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No advocates assigned</h3>
                    <p class="text-gray-500 mb-6">Assign additional advocates to collaborate on this case</p>
                    <a href="<?php echo $path_url ?>advocate/cases/assign-advocate.php?id=<?php echo $caseId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-user-plus mr-2"></i> Assign Advocate
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the tab from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    // Set active tab based on URL parameter
    if (tab) {
        const tabElement = document.querySelector(`[x-data]`);
        if (tabElement && tabElement.__x) {
            tabElement.__x.$data.activeTab = tab;
        }
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
