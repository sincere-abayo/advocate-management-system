<?php
// Set page title
$pageTitle = "Hearing Details";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if hearing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'Invalid hearing ID', 'error');
    exit;
}

$hearingId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get hearing details and verify advocate has access
$stmt = $conn->prepare("
    SELECT h.*, c.case_id, c.case_number, c.title as case_title, c.status as case_status,
           u.full_name as created_by_name
    FROM case_hearings h
    JOIN cases c ON h.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN users u ON h.created_by = u.user_id
    WHERE h.hearing_id = ? AND ca.advocate_id = ?
");
$stmt->bind_param("ii", $hearingId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirectWithMessage('index.php', 'You do not have access to this hearing', 'error');
    exit;
}

$hearing = $result->fetch_assoc();
$stmt->close();

// Get related documents
$docsStmt = $conn->prepare("
    SELECT d.document_id, d.title, d.document_type, d.upload_date, u.full_name as uploaded_by
    FROM documents d
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE d.case_id = ? AND (d.document_type LIKE '%hearing%' OR d.title LIKE ?)
    ORDER BY d.upload_date DESC
    LIMIT 5
");
$searchTerm = '%' . date('Y-m-d', strtotime($hearing['hearing_date'])) . '%';
$docsStmt->bind_param("is", $hearing['case_id'], $searchTerm);
$docsStmt->execute();
$docsResult = $docsStmt->get_result();
$relatedDocuments = [];
while ($doc = $docsResult->fetch_assoc()) {
    $relatedDocuments[] = $doc;
}
$docsStmt->close();

// Get related activities
$activitiesStmt = $conn->prepare("
    SELECT ca.*, u.full_name
    FROM case_activities ca
    JOIN users u ON ca.user_id = u.user_id
    WHERE ca.case_id = ? AND (ca.activity_type = 'hearing' OR ca.description LIKE ?)
    ORDER BY ca.activity_date DESC
    LIMIT 5
");
$activitiesStmt->bind_param("is", $hearing['case_id'], $searchTerm);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();
$relatedActivities = [];
while ($activity = $activitiesResult->fetch_assoc()) {
    $relatedActivities[] = $activity;
}
$activitiesStmt->close();

// Close database connection
$conn->close();

// Format status for display
$statusClasses = [
    'scheduled' => 'bg-blue-100 text-blue-800',
    'completed' => 'bg-green-100 text-green-800',
    'cancelled' => 'bg-red-100 text-red-800',
    'postponed' => 'bg-yellow-100 text-yellow-800'
];
$statusClass = $statusClasses[$hearing['status']] ?? 'bg-gray-100 text-gray-800';
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hearing Details</h1>
            <p class="text-gray-600">
                <a href="view.php?id=<?php echo $hearing['case_id']; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($hearing['case_number']); ?> - <?php echo htmlspecialchars($hearing['case_title']); ?>
                </a>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-3">
            <a href="view.php?id=<?php echo $hearing['case_id']; ?>" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Case
            </a>
            <a href="edit-hearing.php?id=<?php echo $hearingId; ?>" class="btn-primary">
                <i class="fas fa-edit mr-2"></i> Edit Hearing
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Hearing Information -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?php echo htmlspecialchars($hearing['hearing_type']); ?> Hearing
                    </h2>
                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                        <?php echo ucfirst(htmlspecialchars($hearing['status'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Date & Time</h3>
                        <p class="mt-1 text-base text-gray-900">
                            <?php echo formatDate($hearing['hearing_date'], 'l, F j, Y'); ?><br>
                            <?php echo formatTime($hearing['hearing_time']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Location</h3>
                        <p class="mt-1 text-base text-gray-900">
                            <?php if (!empty($hearing['court_room'])): ?>
                                <?php echo htmlspecialchars($hearing['court_room']); ?>
                            <?php else: ?>
                                <span class="text-gray-400">Not specified</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Judge</h3>
                        <p class="mt-1 text-base text-gray-900">
                            <?php if (!empty($hearing['judge'])): ?>
                                <?php echo htmlspecialchars($hearing['judge']); ?>
                            <?php else: ?>
                                <span class="text-gray-400">Not specified</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Created By</h3>
                        <p class="mt-1 text-base text-gray-900">
                            <?php echo htmlspecialchars($hearing['created_by_name']); ?><br>
                            <span class="text-sm text-gray-500">
                                <?php echo formatDate($hearing['created_at'], 'M j, Y g:i A'); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-500">Description</h3>
                    <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                        <?php if (!empty($hearing['description'])): ?>
                            <p class="text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($hearing['description']); ?></p>
                        <?php else: ?>
                            <p class="text-gray-400">No description provided</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($hearing['status'] === 'completed'): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-500">Outcome</h3>
                    <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                        <?php if (!empty($hearing['outcome'])): ?>
                            <p class="text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($hearing['outcome']); ?></p>
                        <?php else: ?>
                            <p class="text-gray-400">No outcome recorded</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Next Steps</h3>
                    <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                        <?php if (!empty($hearing['next_steps'])): ?>
                            <p class="text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($hearing['next_steps']); ?></p>
                        <?php else: ?>
                            <p class="text-gray-400">No next steps recorded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div>
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Quick Actions</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 gap-4">
                    <a href="../documents/upload.php?case_id=<?php echo $hearing['case_id']; ?>&hearing_id=<?php echo $hearingId; ?>" class="btn-action bg-blue-50 hover:bg-blue-100">
                        <i class="fas fa-file-upload text-blue-500"></i>
                        <span>Upload Document</span>
                    </a>
                    
                    <a href="add-activity.php?case_id=<?php echo $hearing['case_id']; ?>&hearing_id=<?php echo $hearingId; ?>" class="btn-action bg-green-50 hover:bg-green-100">
                        <i class="fas fa-plus-circle text-green-500"></i>
                        <span>Add Activity</span>
                    </a>
                    
                    <?php if ($hearing['status'] === 'scheduled'): ?>
                    <a href="update-hearing-status.php?id=<?php echo $hearingId; ?>&status=completed" class="btn-action bg-purple-50 hover:bg-purple-100">
                        <i class="fas fa-check-circle text-purple-500"></i>
                        <span>Mark as Completed</span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="print-hearing.php?id=<?php echo $hearingId; ?>" target="_blank" class="hidden btn-action bg-gray-50 hover:bg-gray-100">
                        <i class="fas fa-print text-gray-500"></i>
                        <span>Print Details</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Related Documents -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Related Documents</h2>
                    <a href="../documents/index.php?case_id=<?php echo $hearing['case_id']; ?>" class="text-sm text-blue-600 hover:underline">View All</a>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (empty($relatedDocuments)): ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No related documents found</p>
                        <a href="../documents/upload.php?case_id=<?php echo $hearing['case_id']; ?>&hearing_id=<?php echo $hearingId; ?>" class="mt-2 inline-block text-blue-600 hover:underline">
                            <i class="fas fa-plus-circle mr-1"></i> Upload Document
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($relatedDocuments as $doc): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3">
                                    <i class="fas fa-file-alt text-blue-500"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="../documents/view.php?id=<?php echo $doc['document_id']; ?>" class="text-sm font-medium text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($doc['title']); ?>
                                    </a>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($doc['document_type']); ?> • 
                                        <?php echo formatDate($doc['upload_date'], 'M j, Y'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
              <!-- Related Activities -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">Related Activities</h2>
                    <a href="activities.php?case_id=<?php echo $hearing['case_id']; ?>" class="text-sm text-blue-600 hover:underline">View All</a>
                </div>
            </div>
            
            <div class="p-6">
                <?php if (empty($relatedActivities)): ?>
                    <div class="text-center py-4">
                        <p class="text-gray-500">No related activities found</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($relatedActivities as $activity): ?>
                            <div class="flex items-start">
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
                                    <i class="<?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($activity['full_name']); ?> • 
                                        <?php echo formatDate($activity['activity_date'], 'M j, Y g:i A'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-secondary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-action {
    @apply flex items-center space-x-2 p-3 rounded-lg transition duration-150;
}

.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>